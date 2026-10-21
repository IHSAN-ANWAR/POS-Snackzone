<?php
require 'config.php';
setCORS();
$conn = getDB();

$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL");

$phone   = trim($_GET['phone']   ?? '');
$user_id = intval($_GET['user_id'] ?? 0);

if (!$phone && !$user_id) {
    echo json_encode(['success' => false, 'message' => 'Phone or user_id required.']);
    exit;
}

// Security: validate phone format strictly — digits, optional leading +, 7-15 chars
if ($phone && !preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number format.']);
    exit;
}

// Security: user_id must be positive integer
if ($user_id < 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user_id.']);
    exit;
}

// Security: require at least one verified identity signal
// user_id must match a real verified user — prevents enumeration by random user_id
if ($user_id && !$phone) {
    $verifyStmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND email_verified = 1");
    $verifyStmt->bind_param('i', $user_id);
    $verifyStmt->execute();
    $verifiedUser = $verifyStmt->get_result()->fetch_assoc();
    $verifyStmt->close();
    if (!$verifiedUser) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }
}

// Ensure columns exist (handles both 'note' and 'notes' naming, and prep_time)
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS prep_time VARCHAR(50) DEFAULT ''");
// Support both possible column names: add 'notes' if it doesn't exist
$colCheck = $conn->query("SHOW COLUMNS FROM orders LIKE 'notes'");
if ($colCheck->num_rows === 0) {
    // Check if 'note' column exists instead
    $noteCheck = $conn->query("SHOW COLUMNS FROM orders LIKE 'note'");
    if ($noteCheck->num_rows > 0) {
        // Rename note -> notes for consistency
        $conn->query("ALTER TABLE orders CHANGE COLUMN note notes TEXT DEFAULT ''");
    } else {
        $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS notes TEXT DEFAULT ''");
    }
}

// Fetch by user_id if provided, otherwise by phone
// For user_id: also match by phone to catch orders saved before login
if ($user_id) {
    // Get user's phone number first
    $uStmt = $conn->prepare("SELECT phone FROM users WHERE id = ?");
    $uStmt->bind_param('i', $user_id);
    $uStmt->execute();
    $uRow = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();
    $userPhone = $uRow ? $uRow['phone'] : '';

    if ($userPhone) {
        $stmt = $conn->prepare(
            "SELECT id, name, phone, address, items, total, notes, status, prep_time, created_at
             FROM orders WHERE user_id = ? OR phone = ?
             ORDER BY created_at DESC"
        );
        $stmt->bind_param('is', $user_id, $userPhone);
    } else {
        $stmt = $conn->prepare(
            "SELECT id, name, phone, address, items, total, notes, status, prep_time, created_at
             FROM orders WHERE user_id = ? ORDER BY created_at DESC"
        );
        $stmt->bind_param('i', $user_id);
    }
} else {
    $stmt = $conn->prepare(
        "SELECT id, name, phone, address, items, total, notes, status, prep_time, created_at
         FROM orders WHERE phone = ? ORDER BY created_at DESC"
    );
    $stmt->bind_param('s', $phone);
}

$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $row['items']  = json_decode($row['items'], true) ?? [];
    $row['total']  = (float) $row['total'];
    $row['id']     = (int)   $row['id'];
    // Normalize blank status to pending
    if (empty($row['status'])) $row['status'] = 'pending';
    $orders[]      = $row;
}
$stmt->close();

// Stats
$total      = count($orders);
$pending    = count(array_filter($orders, fn($o) => $o['status'] === 'pending'));
$confirmed  = count(array_filter($orders, fn($o) => $o['status'] === 'confirmed'));
$dispatched = count(array_filter($orders, fn($o) => $o['status'] === 'dispatched'));
$cancelled  = count(array_filter($orders, fn($o) => $o['status'] === 'cancelled'));
$totalSpent = array_sum(array_column($orders, 'total'));
$name       = $total > 0 ? $orders[0]['name'] : '';

echo json_encode([
    'success' => true,
    'name'    => $name,
    'phone'   => $phone,
    'stats'   => compact('total', 'pending', 'confirmed', 'dispatched', 'cancelled', 'totalSpent'),
    'orders'  => $orders,
]);

$conn->close();
