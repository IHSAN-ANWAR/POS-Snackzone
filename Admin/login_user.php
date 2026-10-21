<?php
require 'config.php';
require 'mailer.php'; // PHPMailer enabled
setCORS();
$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Valid email required.']);
    exit;
}

// Check if user exists and is verified
$stmt = $conn->prepare("SELECT id, name, phone, email_verified FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'No account found with this email. Please register first.']);
    exit;
}

if (!$user['email_verified']) {
    echo json_encode(['success' => false, 'message' => 'Email not verified. Please complete registration first.']);
    exit;
}

// Generate 6-digit OTP
$otp     = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', time() + 600); // 10 minutes

$upd = $conn->prepare("UPDATE users SET email_otp=?, otp_expires=? WHERE id=?");
$upd->bind_param('ssi', $otp, $expires, $user['id']);
$upd->execute();
$upd->close();

// Send OTP email
$sent = sendOtpEmail($email, $user['name'], $otp);

if (!$sent) {
    echo json_encode([
        'success' => false,
        'message' => 'Could not send OTP email. Check SMTP settings.'
    ]);
    $conn->close();
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Login code sent to your email.',
    'name'    => $user['name'],
]);

$conn->close();
