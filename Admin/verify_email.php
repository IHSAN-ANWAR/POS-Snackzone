<?php
require 'config.php';
setCORS();
$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$otp   = trim($_POST['otp']   ?? '');

if (!$email || !$otp) {
    echo json_encode(['success' => false, 'message' => 'Email and OTP are required.']);
    exit;
}

if (!preg_match('/^\d{6}$/', $otp)) {
    echo json_encode(['success' => false, 'message' => 'OTP must be 6 digits.']);
    exit;
}

// Find user with matching OTP
$stmt = $conn->prepare(
    "SELECT id, name, phone, email, email_verified, email_otp, otp_expires
     FROM users WHERE email = ? LIMIT 1"
);
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Email not found. Please register first.']);
    exit;
}

if ($user['email_verified']) {
    echo json_encode(['success' => true, 'already_verified' => true,
        'user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'phone' => $user['phone']]]);
    exit;
}

// Check OTP match
if ($user['email_otp'] !== $otp) {
    echo json_encode(['success' => false, 'message' => 'Incorrect OTP. Please try again.']);
    exit;
}

// Check expiry
if (strtotime($user['otp_expires']) < time()) {
    echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
    exit;
}

// Mark verified, clear OTP
$upd = $conn->prepare(
    "UPDATE users SET email_verified=1, email_otp='', otp_expires=NULL WHERE id=?"
);
$upd->bind_param('i', $user['id']);
$upd->execute();
$upd->close();
$conn->close();

echo json_encode([
    'success' => true,
    'message' => 'Email verified successfully!',
    'user'    => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'phone' => $user['phone']],
]);
