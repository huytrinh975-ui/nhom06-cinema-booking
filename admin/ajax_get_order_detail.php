<?php
/**
 * AJAX API: Lấy chi tiết đơn đặt vé và danh sách ghế
 * File: admin/ajax_get_order_detail.php
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth.php';

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Mã đơn không hợp lệ!']);
    exit;
}

$query = "
    SELECT b.*, 
           u.fullname, u.email, u.phone,
           m.title AS movie_title, m.poster, m.duration,
           s.start_time, s.end_time, s.base_price,
           r.name AS room_name,
           c.name AS cinema_name, c.address AS cinema_address, c.city AS cinema_city
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN showtimes s ON b.showtime_id = s.id
    JOIN movies m ON s.movie_id = m.id
    JOIN rooms r ON s.room_id = r.id
    JOIN cinemas c ON r.cinema_id = c.id
    WHERE b.id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng!']);
    exit;
}

// Lấy danh sách ghế chi tiết của đơn
$detailsQuery = "
    SELECT bd.id, bd.price, s.seat_number, s.seat_type
    FROM booking_details bd
    JOIN seats s ON bd.seat_id = s.id
    WHERE bd.booking_id = ?
    ORDER BY s.seat_number ASC
";
$dStmt = $conn->prepare($detailsQuery);
$dStmt->bind_param("i", $orderId);
$dStmt->execute();
$dRes = $dStmt->get_result();

$seats = [];
while ($row = $dRes->fetch_assoc()) {
    $seats[] = [
        'seat_number' => $row['seat_number'],
        'seat_type' => strtoupper($row['seat_type']),
        'price' => (float)$row['price'],
        'price_formatted' => format_currency($row['price'])
    ];
}
$dStmt->close();

echo json_encode([
    'success' => true,
    'order' => [
        'id' => (int)$order['id'],
        'booking_code' => $order['booking_code'],
        'created_at' => format_datetime($order['created_at']),
        'total_amount' => (float)$order['total_amount'],
        'total_amount_formatted' => format_currency($order['total_amount']),
        'payment_status' => $order['payment_status'],
        'booking_status' => $order['booking_status'],
        'customer' => [
            'fullname' => $order['fullname'],
            'email' => $order['email'],
            'phone' => $order['phone'] ?: 'Chưa cập nhật'
        ],
        'movie' => [
            'title' => $order['movie_title'],
            'poster' => $order['poster'],
            'duration' => $order['duration'] . ' phút'
        ],
        'showtime' => [
            'cinema_name' => $order['cinema_name'],
            'cinema_address' => $order['cinema_address'] . ', ' . $order['cinema_city'],
            'room_name' => $order['room_name'],
            'start_time' => format_datetime($order['start_time']),
            'base_price' => format_currency($order['base_price'])
        ],
        'seats' => $seats
    ]
]);

