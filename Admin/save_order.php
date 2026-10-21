<?php
require 'config.php';
setCORS();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$conn = getDB();

// Read and sanitize fields
$user_id = intval($_POST['user_id'] ?? 0) ?: null;
$name    = trim($_POST['name']    ?? '');
$phone   = trim($_POST['phone']   ?? '');
$address = trim($_POST['address'] ?? '');
$items   = trim($_POST['items']   ?? '');
$total   = floatval($_POST['total'] ?? 0);
$notes   = trim($_POST['notes']   ?? '');

// Validation
if (!$name)    { echo json_encode(['success' => false, 'field' => 'name',    'message' => 'Name is required.']);             exit; }
if (!$phone)   { echo json_encode(['success' => false, 'field' => 'phone',   'message' => 'Phone number is required.']);     exit; }

// Security: Validate phone number format
if (!preg_match('/^[0-9]{10,15}$/', $phone)) {
    echo json_encode(['success' => false, 'field' => 'phone', 'message' => 'Invalid phone number format.']);
    exit;
}

if (!$address) { echo json_encode(['success' => false, 'field' => 'address', 'message' => 'Delivery address is required.']); exit; }
if (!$items)   { echo json_encode(['success' => false, 'field' => 'items',   'message' => 'Please add items to your order.']); exit; }

// Validate items JSON
$decoded = json_decode($items, true);
if (!is_array($decoded) || count($decoded) === 0) {
    echo json_encode(['success' => false, 'field' => 'items', 'message' => 'Invalid items data.']);
    exit;
}
$itemsJson = json_encode($decoded);

// Ensure orders table has correct schema
$conn->query("ALTER TABLE orders MODIFY COLUMN status ENUM('pending','confirmed','dispatched','cancelled') NOT NULL DEFAULT 'pending'");
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS prep_time VARCHAR(50) DEFAULT ''");
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL");
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS token_number INT DEFAULT NULL");

// Generate token number - get max token for today and increment
$today = date('Y-m-d');
$tokenResult = $conn->query("SELECT MAX(token_number) as max_token FROM orders WHERE DATE(created_at) = '$today'");
$maxToken = 0;
if ($tokenResult && $row = $tokenResult->fetch_assoc()) {
    $maxToken = intval($row['max_token']);
}
$newToken = $maxToken + 1;

// Save with token
$stmt = $conn->prepare(
    "INSERT INTO orders (user_id, name, phone, address, items, total, notes, token_number)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param('issssdsi', $user_id, $name, $phone, $address, $itemsJson, $total, $notes, $newToken);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'order_id' => $conn->insert_id, 'token_number' => $newToken]);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not place order. Please try again.']);
}

$stmt->close();
$conn->close();
