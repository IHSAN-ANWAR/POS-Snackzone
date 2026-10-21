<?php
require 'config.php';
$conn = getDB();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$conn->query("CREATE TABLE IF NOT EXISTS notices (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200) NOT NULL, body TEXT NOT NULL, image_path VARCHAR(255) DEFAULT '', is_active TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

$res  = $conn->query("SELECT id, title, body, image_path FROM notices WHERE is_active=1 ORDER BY created_at DESC LIMIT 1");
$notice = $res ? $res->fetch_assoc() : null;

if ($notice) {
    echo json_encode(['success' => true, 'notice' => $notice]);
} else {
    echo json_encode(['success' => false]);
}
$conn->close();
