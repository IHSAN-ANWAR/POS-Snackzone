<?php
require 'config.php';
require 'mailer.php'; // PHPMailer enabled
setCORS();
$conn = getDB();

$conn->query("CREATE TABLE IF NOT EXISTS users (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    phone          VARCHAR(20)  NOT NULL,
    email          VARCHAR(150) NOT NULL UNIQUE,
    email_verified TINYINT(1)   DEFAULT 0,
    email_otp      VARCHAR(6)   DEFAULT '',
    otp_expires    DATETIME     DEFAULT NULL,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
)");

// Add OTP columns to existing installs
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS email_otp   VARCHAR(6) DEFAULT ''");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS otp_expires DATETIME   DEFAULT NULL");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$name  = trim($_POST['name']  ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');

// Validation
if (!$name) {
    echo json_encode(['success' => false, 'field' => 'name', 'message' => 'Name is required.']); exit;
}
if (!$phone) {
    echo json_encode(['success' => false, 'field' => 'phone', 'message' => 'Phone number is required.']); exit;
}
if (!preg_match('/^[0-9+\s\-]{7,15}$/', $phone)) {
    echo json_encode(['success' => false, 'field' => 'phone', 'message' => 'Enter a valid phone number.']); exit;
}
if (!$email) {
    echo json_encode(['success' => false, 'field' => 'email', 'message' => 'Email address is required.']); exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'field' => 'email', 'message' => 'Enter a valid email address.']); exit;
}

// Generate 6-digit OTP
$otp     = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', time() + 600); // 10 minutes

// Check existing user
$check = $conn->prepare("SELECT id, email_verified FROM users WHERE email = ?");
$check->bind_param('s', $email);
$check->execute();
$existing = $check->get_result()->fetch_assoc();
$check->close();

if ($existing) {
    if ($existing['email_verified']) {
        echo json_encode(['success' => false, 'field' => 'email', 'message' => 'This email is already registered.']);
        exit;
    }
    // Already registered but not verified — update OTP and resend
    $upd = $conn->prepare("UPDATE users SET name=?, phone=?, email_otp=?, otp_expires=? WHERE id=?");
    $upd->bind_param('ssssi', $name, $phone, $otp, $expires, $existing['id']);
    $upd->execute();
    $upd->close();
} else {
    // New user — insert
    $stmt = $conn->prepare(
        "INSERT INTO users (name, phone, email, email_otp, otp_expires) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('sssss', $name, $phone, $email, $otp, $expires);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Could not create account. Please try again.']);
        $stmt->close(); $conn->close(); exit;
    }
    $stmt->close();
}

// Send OTP email
error_log("Attempting to send OTP to: $email");
$sent = sendOtpEmail($email, $name, $otp);
error_log("OTP send result: " . ($sent ? "SUCCESS" : "FAILED"));

if (!$sent) {
    error_log("Failed to send OTP email to $email");
    echo json_encode([
        'success' => false,
        'message' => 'Could not send OTP email. Check SMTP settings.'
    ]);
    $conn->close();
    exit;
}

// Success — OTP sent via email
echo json_encode([
    'success'  => true,
    'needs_otp' => true,
    'message'  => 'Verification code sent to your email.',
]);
$conn->close();
exit;
