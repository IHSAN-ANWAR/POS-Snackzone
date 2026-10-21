<?php
require 'config.php';
setCORS();
$conn = getDB();

// Ensure vendor tables exist
$conn->query("CREATE TABLE IF NOT EXISTS `vendors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `cnic` varchar(20) NOT NULL,
  `profile_image` varchar(255) DEFAULT '',
  `status` enum('pending','approved','rejected','suspended') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `cnic` (`cnic`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `vendor_stores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `store_name` varchar(200) NOT NULL,
  `store_description` text DEFAULT '',
  `store_address` text DEFAULT '',
  `store_logo` varchar(255) DEFAULT '',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `vendor_id` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get form data
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$cnic = trim($_POST['cnic'] ?? '');
$password = trim($_POST['password'] ?? '');
$store_name = trim($_POST['store_name'] ?? '');

// Validation
if (!$name || !$email || !$phone || !$cnic || !$password || !$store_name) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Validate CNIC (13 digits)
if (!preg_match('/^\d{13}$/', $cnic)) {
    echo json_encode(['success' => false, 'message' => 'CNIC must be 13 digits']);
    exit;
}

// Check if email already exists
$stmt = $conn->prepare("SELECT id FROM vendors WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already registered']);
    exit;
}
$stmt->close();

// Check if CNIC already exists
$stmt = $conn->prepare("SELECT id FROM vendors WHERE cnic = ?");
$stmt->bind_param('s', $cnic);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'CNIC already registered']);
    exit;
}
$stmt->close();

// Handle profile image upload
$profile_image = '';
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/uploads/vendors/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
    
    if (in_array($file_ext, $allowed_ext)) {
        $new_filename = 'vendor_' . uniqid() . '.' . $file_ext;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
            $profile_image = 'uploads/vendors/' . $new_filename;
        }
    }
}

// Hash password
$password_hash = password_hash($password, PASSWORD_BCRYPT);

// Start transaction
$conn->begin_transaction();

try {
    // Insert vendor
    $stmt = $conn->prepare("INSERT INTO vendors (name, email, phone, cnic, password, profile_image, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param('ssssss', $name, $email, $phone, $cnic, $password_hash, $profile_image);
    $stmt->execute();
    $vendor_id = $stmt->insert_id;
    $stmt->close();
    
    // Create store
    $stmt = $conn->prepare("INSERT INTO vendor_stores (vendor_id, store_name, is_active) VALUES (?, ?, 0)");
    $stmt->bind_param('is', $vendor_id, $store_name);
    $stmt->execute();
    $store_id = $stmt->insert_id;
    $stmt->close();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful! Your store is pending approval.',
        'vendor_id' => $vendor_id,
        'store_id' => $store_id
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
}

$conn->close();
