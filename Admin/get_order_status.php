<?php
require 'config.php';
setCORS();
$conn = getDB();

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit;
}

$stmt = $conn->prepare("SELECT id, status, prep_time, created_at FROM orders WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$order  = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

echo json_encode([
    'success'   => true,
    'id'        => (int) $order['id'],
    'status'    => $order['status'],
    'prep_time' => $order['prep_time'] ?? '',
]);
