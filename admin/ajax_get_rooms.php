<?php
/**
 * AJAX API: Lấy danh sách phòng chiếu theo Cụm rạp
 * File: admin/ajax_get_rooms.php
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth.php';

$cinemaId = isset($_GET['cinema_id']) ? (int)$_GET['cinema_id'] : 0;

if ($cinemaId <= 0) {
    echo json_encode(['success' => false, 'rooms' => []]);
    exit;
}

$stmt = $conn->prepare("SELECT id, name, total_seats FROM rooms WHERE cinema_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $cinemaId);
$stmt->execute();
$res = $stmt->get_result();

$rooms = [];
while ($row = $res->fetch_assoc()) {
    $rooms[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'total_seats' => (int)$row['total_seats']
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'rooms' => $rooms]);

