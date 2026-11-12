<?php
/**
 * AJAX API: Lấy sơ đồ tình trạng ghế của một Suất chiếu cụ thể
 * File: admin/ajax_get_showtime_seats.php
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth.php';

$showtimeId = isset($_GET['showtime_id']) ? (int)$_GET['showtime_id'] : 0;

if ($showtimeId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Mã suất chiếu không hợp lệ!']);
    exit;
}

// Lấy thông tin chi tiết của suất chiếu
$stQuery = "
    SELECT s.*, 
           m.title AS movie_title, m.duration, m.poster,
           r.name AS room_name, r.id AS room_id,
           c.name AS cinema_name
    FROM showtimes s
    JOIN movies m ON s.movie_id = m.id
    JOIN rooms r ON s.room_id = r.id
    JOIN cinemas c ON r.cinema_id = c.id
    WHERE s.id = ?
";
$stmt = $conn->prepare($stQuery);
$stmt->bind_param("i", $showtimeId);
$stmt->execute();
$showtime = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$showtime) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy thông tin suất chiếu!']);
    exit;
}

// Lấy danh sách ID ghế đã được đặt cho suất chiếu này (không tính các đơn đã bị hủy)
$bookedSeats = [];
$bQuery = "
    SELECT bd.seat_id, bd.price, u.fullname, b.booking_code
    FROM booking_details bd
    JOIN bookings b ON bd.booking_id = b.id
    JOIN users u ON b.user_id = u.id
    WHERE b.showtime_id = ? AND b.booking_status != 'cancelled'
";
$bStmt = $conn->prepare($bQuery);
$bStmt->bind_param("i", $showtimeId);
$bStmt->execute();
$bRes = $bStmt->get_result();
while ($bRow = $bRes->fetch_assoc()) {
    $bookedSeats[$bRow['seat_id']] = [
        'customer' => $bRow['fullname'],
        'booking_code' => $bRow['booking_code'],
        'price' => (float)$bRow['price']
    ];
}
$bStmt->close();

// Lấy toàn bộ danh sách ghế của phòng
$seatsStmt = $conn->prepare("SELECT id, seat_number, seat_type, price_multiplier FROM seats WHERE room_id = ? ORDER BY id ASC");
$seatsStmt->bind_param("i", $showtime['room_id']);
$seatsStmt->execute();
$seatsRes = $seatsStmt->get_result();

$seatsList = [];
$bookedCount = 0;
$totalEstimatedRevenue = 0;

while ($s = $seatsRes->fetch_assoc()) {
    $seatId = (int)$s['id'];
    $isBooked = isset($bookedSeats[$seatId]);
    $price = round((float)$showtime['base_price'] * (float)$s['price_multiplier'], -3);

    if ($isBooked) {
        $bookedCount++;
        $totalEstimatedRevenue += $bookedSeats[$seatId]['price'];
    }

    $seatsList[] = [
        'id' => $seatId,
        'seat_number' => $s['seat_number'],
        'seat_type' => $s['seat_type'],
        'price_multiplier' => (float)$s['price_multiplier'],
        'price' => $price,
        'is_booked' => $isBooked,
        'booking_info' => $isBooked ? $bookedSeats[$seatId] : null
    ];
}
$seatsStmt->close();

echo json_encode([
    'success' => true,
    'showtime' => [
        'id' => (int)$showtime['id'],
        'movie_title' => $showtime['movie_title'],
        'cinema_name' => $showtime['cinema_name'],
        'room_name' => $showtime['room_name'],
        'start_time' => format_datetime($showtime['start_time']),
        'base_price' => (float)$showtime['base_price'],
        'base_price_formatted' => format_currency($showtime['base_price'])
    ],
    'total_seats' => count($seatsList),
    'booked_count' => $bookedCount,
    'available_count' => count($seatsList) - $bookedCount,
    'occupancy_rate' => count($seatsList) > 0 ? round(($bookedCount / count($seatsList)) * 100, 1) : 0,
    'total_revenue' => $totalEstimatedRevenue,
    'total_revenue_formatted' => format_currency($totalEstimatedRevenue),
    'seats' => $seatsList
]);

