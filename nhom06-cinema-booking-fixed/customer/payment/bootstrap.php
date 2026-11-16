<?php
/**
 * Kết nối và bộ hàm chung của phần thanh toán / vé điện tử demo.
 * Tích hợp đăng nhập thật: $_SESSION['customer_user']['id'].
 */
declare(strict_types=1);

date_default_timezone_set('Asia/Ho_Chi_Minh');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");

function cbp_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cbp_id($value): ?int
{
    if (!is_string($value) && !is_int($value)) {
        return null;
    }
    $value = (string) $value;
    if (!preg_match('/\A[1-9][0-9]{0,9}\z/', $value) || (int) $value > 2147483647) {
        return null;
    }
    return (int) $value;
}

/** Giá DECIMAL được đổi sang phần trăm đồng để cộng chính xác, không dùng float. */
function cbp_cents($value): ?int
{
    if (!is_string($value) && !is_int($value)) {
        return null;
    }
    if (!preg_match('/\A([0-9]{1,10})(?:\.([0-9]{1,2}))?\z/', (string) $value, $parts)) {
        return null;
    }
    return ((int) $parts[1] * 100) + (int) str_pad($parts[2] ?? '', 2, '0');
}

function cbp_money(?int $cents): string
{
    if ($cents === null) {
        return 'Chưa xác định';
    }
    $text = number_format(intdiv($cents, 100), 0, ',', '.');
    if ($cents % 100 !== 0) {
        $text .= ',' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
    return $text . ' ₫';
}

function cbp_datetime($value): ?DateTimeImmutable
{
    if (!is_string($value)) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
    return $date && $date->format('Y-m-d H:i:s') === $value ? $date : null;
}

function cbp_date_label($value, string $format = 'H:i · d/m/Y'): string
{
    $date = cbp_datetime($value);
    return $date ? $date->format($format) : 'Chưa cập nhật';
}

function cbp_demo_allowed(): bool
{
    global $db_name;
    return isset($db_name) && in_array($db_name, ['cinema_booking_db', 'cinema_booking_test'], true)
        && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true);
}

function cbp_csrf_token(): string
{
    if (empty($_SESSION['cbp_csrf'])) {
        $_SESSION['cbp_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['cbp_csrf'];
}

function cbp_valid_csrf($value): bool
{
    return is_string($value) && isset($_SESSION['cbp_csrf'])
        && hash_equals($_SESSION['cbp_csrf'], $value);
}

function cbp_notice(int $status, string $title, string $message, bool $demoLink = false): void
{
    http_response_code($status);
    ?>
    <!doctype html>
    <html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= cbp_e($title) ?> · CINEBOOK</title>
        <link rel="stylesheet" href="checkout.css">
    </head>
    <body>
        <main class="message-page panel">
            <a class="brand" href="checkout.php">CINE<span>BOOK</span></a>
            <h1><?= cbp_e($title) ?></h1>
            <p class="muted"><?= cbp_e($message) ?></p>
            <?php if ($demoLink && cbp_demo_allowed() && is_file(__DIR__ . '/dev-login.php')): ?>
                <a class="button" href="dev-login.php">Mở tài khoản mẫu trên localhost</a>
            <?php elseif ($status >= 400 && $status !== 401): ?>
                <a class="button" href="checkout.php">Về đơn hàng của tôi</a>
            <?php endif; ?>
        </main>
    </body>
    </html>
    <?php
    exit;
}

function cbp_query(string $sql, string $types = '', array $values = []): array
{
    global $conn;
    $statement = $conn->prepare($sql);
    if ($types !== '') {
        $statement->bind_param($types, ...$values);
    }
    $statement->execute();
    $rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $statement->close();
    return $rows;
}

/** Không dùng admin_user để xác thực khách hàng. Luôn kiểm tra lại vai trò và khóa tài khoản. */
function cbp_customer(): ?array
{
    $real = $_SESSION['customer_user'] ?? null;
    $id = is_array($real) ? cbp_id($real['id'] ?? null) : null;
    if ($real === null && cbp_demo_allowed()) {
        $id = cbp_id($_SESSION['kiet_demo_customer_id'] ?? null);
    }
    if ($id === null) {
        return null;
    }
    $rows = cbp_query(
        "SELECT id, fullname, email, phone FROM users WHERE id = ? AND role = 'customer' AND status = 'active' LIMIT 1",
        'i', [$id]
    );
    return $rows[0] ?? null;
}

function cbp_order(int $id, int $customerId): ?array
{
    $rows = cbp_query(
        'SELECT b.id, b.booking_code, b.total_amount, b.payment_status, b.booking_status, b.created_at,
                st.id AS showtime_id, st.room_id, st.start_time, st.end_time, st.status AS showtime_status,
                m.title AS movie_title, m.duration, r.name AS room_name,
                c.name AS cinema_name, c.address AS cinema_address, c.city AS cinema_city
         FROM bookings b
         JOIN showtimes st ON st.id = b.showtime_id
         JOIN movies m ON m.id = st.movie_id
         JOIN rooms r ON r.id = st.room_id
         JOIN cinemas c ON c.id = r.cinema_id
         WHERE b.id = ? AND b.user_id = ? LIMIT 1', 'ii', [$id, $customerId]
    );
    return $rows[0] ?? null;
}

function cbp_seats(int $bookingId): array
{
    // Chỉ gọi sau khi cbp_order() đã xác nhận chủ đơn hàng.
    return cbp_query(
        'SELECT bd.id, bd.seat_id, bd.price, s.id AS matched_seat_id, s.seat_number, s.seat_type, s.room_id
         FROM booking_details bd LEFT JOIN seats s ON s.id = bd.seat_id
         WHERE bd.booking_id = ? ORDER BY s.seat_number, bd.id', 'i', [$bookingId]
    );
}

function cbp_order_issues(array $order, array $seats): array
{
    $total = cbp_cents($order['total_amount']);
    if (!$seats || $total === null || $total <= 0) {
        return ['Đơn hàng chưa có đủ thông tin ghế hoặc số tiền hợp lệ.'];
    }
    $sum = 0;
    $seen = [];
    foreach ($seats as $seat) {
        $price = cbp_cents($seat['price']);
        if ($price === null || $price <= 0 || empty($seat['matched_seat_id'])
            || (int) $seat['room_id'] !== (int) $order['room_id']
            || isset($seen[$seat['seat_id']])) {
            return ['Thông tin ghế trong đơn chưa hợp lệ. Vui lòng liên hệ hỗ trợ.'];
        }
        $seen[$seat['seat_id']] = true;
        $sum += $price;
    }
    return $sum === $total ? [] : ['Tổng giá ghế chưa khớp số tiền của đơn. Vui lòng liên hệ hỗ trợ.'];
}

function cbp_order_state(array $order, array $issues): array
{
    if ($order['booking_status'] === 'cancelled') {
        return ['tone' => 'neutral', 'title' => 'Đơn hàng đã hủy', 'message' => 'Đơn hàng này không thể tiếp tục thanh toán.'];
    }
    if ($order['payment_status'] === 'paid') {
        return ['tone' => 'success', 'title' => 'Đã thanh toán', 'message' => 'Đơn hàng đã được ghi nhận thanh toán. Không cần thanh toán lại.'];
    }
    if ($issues) {
        return ['tone' => 'danger', 'title' => 'Cần kiểm tra thông tin đơn', 'message' => $issues[0]];
    }
    $start = cbp_datetime($order['start_time']);
    $end = cbp_datetime($order['end_time']);
    if (!$start || !$end || $end <= $start || $order['showtime_status'] !== 'active') {
        return ['tone' => 'danger', 'title' => 'Suất chiếu không khả dụng', 'message' => 'Suất chiếu hiện không nhận thanh toán. Vui lòng liên hệ rạp để được hỗ trợ.'];
    }
    if ($start <= new DateTimeImmutable('now')) {
        return ['tone' => 'warning', 'title' => 'Suất chiếu đã bắt đầu', 'message' => 'Bạn vẫn có thể xem thông tin đơn hàng, nhưng không thể thanh toán cho suất chiếu đã bắt đầu.'];
    }
    if ($order['payment_status'] === 'failed') {
        return ['tone' => 'danger', 'title' => 'Thanh toán chưa thành công', 'message' => 'Đơn hàng chưa được ghi nhận thanh toán thành công.'];
    }
    if ($order['payment_status'] !== 'pending'
        || !in_array($order['booking_status'], ['pending', 'confirmed'], true)) {
        return ['tone' => 'danger', 'title' => 'Cần kiểm tra trạng thái đơn', 'message' => 'Vui lòng liên hệ hỗ trợ trước khi tiếp tục.'];
    }
    return ['tone' => 'info', 'title' => 'Đang chờ thanh toán', 'message' => 'Kiểm tra phim, suất chiếu, ghế và số tiền trước khi thanh toán.'];
}

try {
    $cbpConfigPath = dirname(__DIR__, 2) . '/config/database.php';
    if (!is_file($cbpConfigPath)) {
        cbp_notice(503, 'Chưa tìm thấy cấu hình', 'Hãy đặt thư mục customer cùng cấp với thư mục config của dự án.');
    }
    require_once $cbpConfigPath;
    if (!isset($conn) || !($conn instanceof mysqli)) {
        cbp_notice(503, 'Chưa kết nối được cơ sở dữ liệu', 'Vui lòng kiểm tra cấu hình kết nối của dự án.');
    }
} catch (Throwable $error) {
    error_log('[CINEBOOK checkout] ' . $error->getMessage());
    cbp_notice(503, 'Chưa kết nối được cơ sở dữ liệu', 'Vui lòng kiểm tra MySQL Server và cấu hình kết nối của dự án.');
}

require_once __DIR__ . '/domain.php';
require_once __DIR__ . '/ui.php';
