<?php
require 'config.php';
setCORS();
$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$phone = trim($_POST['phone'] ?? '');
$code  = trim($_POST['code']  ?? '');

if (!$phone || !$code) {
    echo json_encode(['success' => false, 'message' => 'Phone and OTP code are required.']);
    exit;
}

// ── Find latest unused, unexpired OTP for this phone ─────
$stmt = $conn->prepare(
    "SELECT id FROM otp_codes
     WHERE phone = ? AND code = ? AND used = 0 AND expires_at > NOW()
     ORDER BY created_at DESC LIMIT 1"
);
$stmt->bind_param('ss', $phone, $code);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP. Please try again.']);
    exit;
}

// ── Mark OTP as used ──────────────────────────────────────
$conn->prepare("UPDATE otp_codes SET used = 1 WHERE id = ?")->execute() || null;
$upd = $conn->prepare("UPDATE otp_codes SET used = 1 WHERE id = ?");
$upd->bind_param('i', $row['id']);
$upd->execute();
$upd->close();

// ── Mark user phone as verified ───────────────────────────
$verify = $conn->prepare("UPDATE users SET phone_verified = 1 WHERE phone = ?");
$verify->bind_param('s', $phone);
$verify->execute();
$verify->close();

// ── Return user info ──────────────────────────────────────
$user = $conn->prepare("SELECT id, name, phone, email FROM users WHERE phone = ?");
$user->bind_param('s', $phone);
$user->execute();
$userData = $user->get_result()->fetch_assoc();
$user->close();

echo json_encode([
    'success' => true,
    'message' => 'Phone verified successfully.',
    'user'    => $userData,
]);

$conn->close();
