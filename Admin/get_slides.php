<?php
require 'config.php';
setCORS();

// Cache headers — 5 min browser cache
header('Cache-Control: public, max-age=300, stale-while-revalidate=60');

$conn = getDB();

// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS homepage_slides (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    label      VARCHAR(100) DEFAULT '',
    sort_order INT          DEFAULT 0,
    is_active  TINYINT(1)   DEFAULT 1,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$result = $conn->query("SELECT id, image_path, label FROM homepage_slides WHERE is_active=1 ORDER BY sort_order ASC, created_at ASC");
$slides = [];
while ($row = $result->fetch_assoc()) {
    $slides[] = $row;
}

echo json_encode(['success' => true, 'slides' => $slides]);
$conn->close();
