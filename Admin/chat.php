<?php
/**
 * chat.php — Live Chat API
 *
 * Actions:
 *   send_message   — user or receptionist sends a message
 *   get_messages   — poll new messages for a session (long-poll friendly)
 *   get_sessions   — receptionist: list all active chat sessions
 *   close_session  — receptionist: mark session closed
 *
 * Auth:
 *   user messages  → identified by order_id + guest_token (stored in browser)
 *   receptionist   → requires snackzone_receptionist or snackzone_admin session
 */

require_once __DIR__ . '/config.php';
setCORS();

session_start();

$conn   = getDB();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── Bootstrap tables ────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS chat_sessions (
        id          INT          AUTO_INCREMENT PRIMARY KEY,
        order_id    INT          NOT NULL,
        guest_token VARCHAR(64)  NOT NULL,
        customer_name VARCHAR(100) NOT NULL DEFAULT '',
        status      ENUM('open','closed') NOT NULL DEFAULT 'open',
        last_activity TIMESTAMP  DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_order (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("
    CREATE TABLE IF NOT EXISTS chat_messages (
        id          INT          AUTO_INCREMENT PRIMARY KEY,
        session_id  INT          NOT NULL,
        sender      ENUM('user','receptionist') NOT NULL,
        message     TEXT         NOT NULL,
        is_read     TINYINT(1)   NOT NULL DEFAULT 0,
        created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        KEY idx_session (session_id),
        KEY idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Helpers ──────────────────────────────────────────────
function isStaff(): bool {
    return isset($_SESSION['snackzone_receptionist']) || isset($_SESSION['snackzone_admin']);
}

function jsonOut(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function sanitize(string $s, int $max = 1000): string {
    return mb_substr(trim(strip_tags($s)), 0, $max);
}

/**
 * Simple IP-based rate limiter using DB.
 * Allows $maxRequests per $windowSeconds per IP per action.
 */
function rateLimit(mysqli $conn, string $action, int $maxRequests = 10, int $windowSeconds = 60): void {
    $conn->query("CREATE TABLE IF NOT EXISTS rate_limits (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        ip         VARCHAR(45) NOT NULL,
        action     VARCHAR(50) NOT NULL,
        hit_time   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_ip_action (ip, action, hit_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ip      = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    $cutoff  = date('Y-m-d H:i:s', time() - $windowSeconds);

    // Clean old entries for this IP
    $conn->query("DELETE FROM rate_limits WHERE hit_time < '$cutoff' AND ip='".mysqli_real_escape_string($conn,$ip)."'");

    // Count recent hits
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM rate_limits WHERE ip=? AND action=? AND hit_time >= ?");
    $stmt->bind_param('sss', $ip, $action, $cutoff);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    if ($count >= $maxRequests) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Too many requests. Please wait a moment.']);
        exit;
    }

    // Record this hit
    $stmt = $conn->prepare("INSERT INTO rate_limits (ip, action) VALUES (?, ?)");
    $stmt->bind_param('ss', $ip, $action);
    $stmt->execute();
    $stmt->close();
}

// ══════════════════════════════════════════════════════════
// ACTION: send_message
// ══════════════════════════════════════════════════════════
if ($action === 'send_message') {
    // Rate limit: max 20 messages per minute per IP
    rateLimit($conn, 'chat_send', 20, 60);

    $message     = sanitize($_POST['message'] ?? '');
    $order_id    = intval($_POST['order_id'] ?? 0);
    $guest_token = sanitize($_POST['guest_token'] ?? '', 64);

    if (!$message) jsonOut(['success' => false, 'message' => 'Message cannot be empty']);

    // ── Staff (receptionist/admin) sending reply ─────────
    if (isStaff()) {
        $session_id = intval($_POST['session_id'] ?? 0);
        if (!$session_id) jsonOut(['success' => false, 'message' => 'session_id required']);

        // Verify session exists
        $stmt = $conn->prepare("SELECT id FROM chat_sessions WHERE id = ? AND status = 'open'");
        $stmt->bind_param('i', $session_id);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            $stmt->close();
            jsonOut(['success' => false, 'message' => 'Session not found or closed']);
        }
        $stmt->close();

        $sender = 'receptionist';
        $stmt = $conn->prepare("INSERT INTO chat_messages (session_id, sender, message) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $session_id, $sender, $message);
        $stmt->execute();
        $msg_id = $conn->insert_id;
        $stmt->close();

        // Mark user messages in this session as read
        $conn->query("UPDATE chat_messages SET is_read=1 WHERE session_id=$session_id AND sender='user' AND is_read=0");

        jsonOut(['success' => true, 'id' => $msg_id, 'sender' => 'receptionist',
                 'message' => $message, 'created_at' => date('Y-m-d H:i:s')]);
    }

    // ── User (customer) sending message ──────────────────
    if (!$order_id || !$guest_token) {
        jsonOut(['success' => false, 'message' => 'order_id and guest_token required']);
    }

    // Verify the order exists and token matches
    $stmt = $conn->prepare("SELECT id, name FROM orders WHERE id = ?");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) jsonOut(['success' => false, 'message' => 'Order not found']);

    // Get or create chat session for this order
    $stmt = $conn->prepare("SELECT id, guest_token FROM chat_sessions WHERE order_id = ?");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($session) {
        // Verify token
        if (!hash_equals($session['guest_token'], $guest_token)) {
            jsonOut(['success' => false, 'message' => 'Invalid token'], 403);
        }
        $session_id = $session['id'];
        // Reopen if closed
        $conn->query("UPDATE chat_sessions SET status='open', last_activity=NOW() WHERE id=$session_id");
    } else {
        // Create new session
        $customer_name = $order['name'];
        $stmt = $conn->prepare(
            "INSERT INTO chat_sessions (order_id, guest_token, customer_name, status) VALUES (?, ?, ?, 'open')"
        );
        $stmt->bind_param('iss', $order_id, $guest_token, $customer_name);
        $stmt->execute();
        $session_id = $conn->insert_id;
        $stmt->close();
    }

    $sender = 'user';
    $stmt = $conn->prepare("INSERT INTO chat_messages (session_id, sender, message) VALUES (?, ?, ?)");
    $stmt->bind_param('iss', $session_id, $sender, $message);
    $stmt->execute();
    $msg_id = $conn->insert_id;
    $stmt->close();

    // Update last_activity
    $conn->query("UPDATE chat_sessions SET last_activity=NOW() WHERE id=$session_id");

    jsonOut(['success' => true, 'id' => $msg_id, 'session_id' => $session_id,
             'sender' => 'user', 'message' => $message, 'created_at' => date('Y-m-d H:i:s')]);
}

// ══════════════════════════════════════════════════════════
// ACTION: get_messages
// ══════════════════════════════════════════════════════════
elseif ($action === 'get_messages') {
    $after_id    = intval($_GET['after_id']    ?? $_POST['after_id']    ?? 0);
    $order_id    = intval($_GET['order_id']    ?? $_POST['order_id']    ?? 0);
    $guest_token = sanitize($_GET['guest_token'] ?? $_POST['guest_token'] ?? '', 64);
    $session_id  = intval($_GET['session_id']  ?? $_POST['session_id']  ?? 0);

    // Staff can poll any session by session_id
    if (isStaff() && $session_id) {
        // Mark user messages as read when staff polls
        $conn->query("UPDATE chat_messages SET is_read=1 WHERE session_id=$session_id AND sender='user' AND is_read=0");

        $stmt = $conn->prepare(
            "SELECT id, sender, message, is_read, created_at
             FROM chat_messages
             WHERE session_id = ? AND id > ?
             ORDER BY id ASC LIMIT 100"
        );
        $stmt->bind_param('ii', $session_id, $after_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Unread count (messages from user that staff hasn't read yet — only NEW ones)
        $unread = $conn->query("SELECT COUNT(*) as c FROM chat_messages WHERE session_id=$session_id AND sender='user' AND is_read=0")->fetch_assoc()['c'];
        jsonOut(['success' => true, 'messages' => $rows, 'unread' => (int)$unread]);
    }

    // User polls by order_id + token
    if (!$order_id || !$guest_token) jsonOut(['success' => false, 'message' => 'order_id and guest_token required']);

    $stmt = $conn->prepare("SELECT id FROM chat_sessions WHERE order_id = ? AND guest_token = ?");
    $stmt->bind_param('is', $order_id, $guest_token);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$session) jsonOut(['success' => true, 'messages' => []]);

    $session_id = $session['id'];

    // Mark receptionist messages as read when user polls
    $conn->query("UPDATE chat_messages SET is_read=1 WHERE session_id=$session_id AND sender='receptionist' AND is_read=0");

    $stmt = $conn->prepare(
        "SELECT id, sender, message, is_read, created_at
         FROM chat_messages
         WHERE session_id = ? AND id > ?
         ORDER BY id ASC LIMIT 100"
    );
    $stmt->bind_param('ii', $session_id, $after_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    jsonOut(['success' => true, 'messages' => $rows, 'session_id' => $session_id]);
}

// ══════════════════════════════════════════════════════════
// ACTION: get_sessions (receptionist/admin only)
// ══════════════════════════════════════════════════════════
elseif ($action === 'get_sessions') {
    if (!isStaff()) jsonOut(['success' => false, 'message' => 'Unauthorized'], 403);

    $stmt = $conn->prepare("
        SELECT
            cs.id,
            cs.order_id,
            cs.customer_name,
            cs.status,
            cs.last_activity,
            cs.created_at,
            o.total,
            o.status  AS order_status,
            (SELECT COUNT(*) FROM chat_messages cm
             WHERE cm.session_id = cs.id
               AND cm.sender = 'user'
               AND cm.is_read = 0) AS unread_count,
            (SELECT message FROM chat_messages cm2
             WHERE cm2.session_id = cs.id
             ORDER BY cm2.id DESC LIMIT 1) AS last_message
        FROM chat_sessions cs
        LEFT JOIN orders o ON o.id = cs.order_id
        WHERE cs.status = 'open'
        ORDER BY cs.last_activity DESC
        LIMIT 50
    ");
    $stmt->execute();
    $sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Total unread across all sessions
    $totalUnread = array_sum(array_column($sessions, 'unread_count'));

    jsonOut(['success' => true, 'sessions' => $sessions, 'total_unread' => (int)$totalUnread]);
}

// ══════════════════════════════════════════════════════════
// ACTION: close_session (receptionist/admin only)
// ══════════════════════════════════════════════════════════
elseif ($action === 'close_session') {
    if (!isStaff()) jsonOut(['success' => false, 'message' => 'Unauthorized'], 403);

    $session_id = intval($_POST['session_id'] ?? 0);
    if (!$session_id) jsonOut(['success' => false, 'message' => 'session_id required']);

    $stmt = $conn->prepare("UPDATE chat_sessions SET status='closed' WHERE id=?");
    $stmt->bind_param('i', $session_id);
    $ok = $stmt->execute();
    $stmt->close();

    jsonOut(['success' => $ok]);
}

// ══════════════════════════════════════════════════════════
// ACTION: get_unread_count (receptionist badge)
// ══════════════════════════════════════════════════════════
elseif ($action === 'get_unread_count') {
    if (!isStaff()) jsonOut(['success' => false, 'count' => 0], 403);

    $res = $conn->query("
        SELECT COUNT(*) as c FROM chat_messages cm
        JOIN chat_sessions cs ON cs.id = cm.session_id
        WHERE cm.sender = 'user' AND cm.is_read = 0 AND cs.status = 'open'
    ");
    $count = $res ? (int)$res->fetch_assoc()['c'] : 0;
    jsonOut(['success' => true, 'count' => $count]);
}

else {
    jsonOut(['success' => false, 'message' => 'Unknown action']);
}

$conn->close();
