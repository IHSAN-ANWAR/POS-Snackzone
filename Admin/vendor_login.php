<?php
session_start();
require 'config.php';
setCORS();
$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if (!$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'Email and password required']);
    exit;
}

// Get vendor
$stmt = $conn->prepare("SELECT id, name, email, phone, cnic, profile_image, password, status FROM vendors WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$vendor = $result->fetch_assoc();
$stmt->close();

if (!$vendor) {
    echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    exit;
}

// Verify password
if (!password_verify($password, $vendor['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    exit;
}

// Check status
if ($vendor['status'] === 'rejected') {
    echo json_encode(['success' => false, 'message' => 'Your account has been rejected']);
    exit;
}

if ($vendor['status'] === 'suspended') {
    echo json_encode(['success' => false, 'message' => 'Your account has been suspended']);
    exit;
}

// Get vendor store
$stmt = $conn->prepare("SELECT id, store_name, store_description, store_address, store_logo, is_active FROM vendor_stores WHERE vendor_id = ? LIMIT 1");
$stmt->bind_param('i', $vendor['id']);
$stmt->execute();
$result = $stmt->get_result();
$store = $result->fetch_assoc();
$stmt->close();

// Store session
$_SESSION['vendor_id'] = $vendor['id'];
$_SESSION['vendor_email'] = $vendor['email'];

unset($vendor['password']); // Don't send password to frontend

// Merge store data into vendor response
$vendorData = [
    'id' => $vendor['id'],
    'name' => $vendor['name'],
    'email' => $vendor['email'],
    'phone' => $vendor['phone'],
    'status' => $vendor['status'],
    'store_name' => $store['store_name'] ?? '',
    'is_active' => (bool)($store['is_active'] ?? false)
];

echo json_encode([
    'success' => true,
    'message' => 'Login successful',
    'vendor' => $vendorData
]);

$conn->close();
