<?php
/**
 * AJAX API: Lấy danh sách ghế của một phòng chiếu
 * File: admin/ajax_get_room_seats.php
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$roomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;

if ($roomId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Mã phòng không hợp lệ!']);
    exit;
}

$stmt = $conn->prepare("SELECT id, seat_number, seat_type, price_multiplier FROM seats WHERE room_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $roomId);
$stmt->execute();
$res = $stmt->get_result();

$seats = [];
while ($row = $res->fetch_assoc()) {
    $seats[] = [
        'id' => (int)$row['id'],
        'seat_number' => $row['seat_number'],
        'seat_type' => $row['seat_type'],
        'price_multiplier' => (float)$row['price_multiplier']
    ];
}
$stmt->close();

echo json_encode([
    'success' => true,
    'room_id' => $roomId,
    'total' => count($seats),
    'seats' => $seats
]);

