<?php
require 'config.php';
setCORS();
$conn = getDB();

$conn->query("CREATE TABLE IF NOT EXISTS event_bookings (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    phone      VARCHAR(20)  NOT NULL,
    email      VARCHAR(150) DEFAULT '',
    event_date DATE         NOT NULL,
    event_time VARCHAR(20)  NOT NULL,
    guests     INT          NOT NULL,
    menu_items TEXT         NOT NULL,
    notes      TEXT         DEFAULT '',
    status     VARCHAR(20)  DEFAULT 'pending',
    is_read    TINYINT(1)   DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
)");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$name       = trim($_POST['name']       ?? '');
$phone      = trim($_POST['phone']      ?? '');
$email      = trim($_POST['email']      ?? '');
$event_date = trim($_POST['event_date'] ?? '');
$event_time = trim($_POST['event_time'] ?? '');
$guests     = intval($_POST['guests']   ?? 0);
$menu_items = trim($_POST['menu_items'] ?? '');
$notes      = trim($_POST['notes']      ?? '');

// Length limits
$name       = mb_substr($name,       0, 100);
$phone      = mb_substr($phone,      0, 20);
$email      = mb_substr($email,      0, 150);
$event_time = mb_substr($event_time, 0, 20);
$menu_items = mb_substr($menu_items, 0, 1000);
$notes      = mb_substr($notes,      0, 1000);

// Honeypot — bots fill hidden field, humans don't
if (!empty($_POST['website'])) {
    echo json_encode(['success' => true, 'message' => 'Booking request submitted successfully']);
    exit;
}

if (!$name || !$phone || !$event_date || !$event_time || !$guests || !$menu_items) {
    echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
    exit;
}

// Validate phone
if (!preg_match('/^\+?[0-9\s\-]{7,20}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number.']);
    exit;
}

// Validate email if provided
if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

// Validate date format (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date) || !strtotime($event_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid event date.']);
    exit;
}

// Validate guests range
if ($guests < 1 || $guests > 10000) {
    echo json_encode(['success' => false, 'message' => 'Invalid number of guests.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO event_bookings (name, phone, email, event_date, event_time, guests, menu_items, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param('sssssiss', $name, $phone, $email, $event_date, $event_time, $guests, $menu_items, $notes);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Booking request submitted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not save booking, please try again']);
}

$stmt->close();
$conn->close();
