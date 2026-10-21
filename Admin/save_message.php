<?php
require 'config.php';
setCORS();
$conn = getDB();

$conn->query("CREATE TABLE IF NOT EXISTS contact_messages (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    phone      VARCHAR(20)  DEFAULT '',
    email      VARCHAR(150) DEFAULT '',
    subject    VARCHAR(100) NOT NULL,
    message    TEXT         NOT NULL,
    is_read    TINYINT(1)   DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
)");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$name    = trim($_POST['name']    ?? '');
$phone   = trim($_POST['phone']   ?? '');
$email   = trim($_POST['email']   ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

// Length limits
$name    = mb_substr($name,    0, 100);
$phone   = mb_substr($phone,   0, 20);
$email   = mb_substr($email,   0, 150);
$subject = mb_substr($subject, 0, 100);
$message = mb_substr($message, 0, 3000);

// Honeypot — bots fill hidden field, humans don't
if (!empty($_POST['website'])) {
    // Silent reject — looks like success to bots
    echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
    exit;
}

if (!$name || !$subject || !$message) {
    echo json_encode(['success' => false, 'message' => 'Name, subject and message are required']);
    exit;
}

// Validate email format if provided
if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

// Validate phone format if provided
if ($phone && !preg_match('/^\+?[0-9\s\-]{7,20}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO contact_messages (name, phone, email, subject, message) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param('sssss', $name, $phone, $email, $subject, $message);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not save message, please try again']);
}

$stmt->close();
$conn->close();
