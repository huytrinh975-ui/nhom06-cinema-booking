<?php
declare(strict_types=1);

class CBPUserError extends RuntimeException {}

function cbp_settings(): array
{
    static $settings;
    if ($settings === null) {
        $settings = require __DIR__ . '/settings.php';
        if (!is_int($settings['cancel_cutoff_minutes']) || $settings['cancel_cutoff_minutes'] < 0
            || !is_int($settings['refund_percent']) || $settings['refund_percent'] < 0 || $settings['refund_percent'] > 100) {
            throw new RuntimeException('Invalid module policy settings');
        }
    }
    return $settings;
}

function cbp_now(): string { return (new DateTimeImmutable())->format('Y-m-d H:i:s'); }
function cbp_decimal(int $cents): string { return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT); }
function cbp_code(string $prefix): string { return $prefix . date('ymd') . strtoupper(bin2hex(random_bytes(6))); }

function cbp_execute(string $sql, string $types = '', array $values = []): int
{
    global $conn;
    $stmt = $conn->prepare($sql);
    if ($types !== '') { $stmt->bind_param($types, ...$values); }
    $stmt->execute();
    $changed = $stmt->affected_rows;
    $stmt->close();
    return $changed;
}

function cbp_transaction(callable $work)
{
    global $conn;
    $conn->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    $conn->begin_transaction();
    try {
        $result = $work();
        $conn->commit();
        return $result;
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
}

function cbp_ready(): bool
{
    $row = cbp_query("SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN ('cbp_payments','cbp_tickets','cbp_cancellations','cbp_seat_claims')")[0];
    return (int) $row['n'] === 4;
}

function cbp_install(): void
{
    // DDL chỉ được gọi từ POST của setup-demo.php (localhost + DB test + CSRF).
    $sql = file_get_contents(__DIR__ . '/sql/001_payment_module.sql');
    if ($sql === false) { throw new RuntimeException('Migration file missing'); }
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    global $conn;
    foreach (explode(';', $sql) as $statement) {
        if (trim($statement) !== '') { $conn->query($statement); }
    }
    if (!cbp_ready()) { throw new RuntimeException('Incomplete migration'); }
}

function cbp_require_module(): void
{
    if (!cbp_ready()) {
        http_response_code(503);
        cbp_header('Chuẩn bị bản demo', null);
        echo '<section class="panel narrow"><h1>Cần cài đặt phần thanh toán</h1><p class="muted">Database cần thêm các bảng cho giao dịch, vé điện tử và yêu cầu hủy.</p>';
        if (cbp_demo_allowed() && is_file(__DIR__ . '/setup-demo.php')) {
            echo '<a class="button" href="setup-demo.php">Cài đặt bản demo</a>';
        } else {
            echo '<p>Nhờ người quản lý dự án nhập file SQL bổ sung theo HUONG_DAN.md.</p>';
        }
        echo '</section>';
        cbp_footer();
        exit;
    }
}

function cbp_require_customer(): array
{
    $customer = cbp_customer();
    if (!$customer) { cbp_notice(401, 'Vui lòng đăng nhập', 'Bạn cần đăng nhập bằng tài khoản khách hàng đang hoạt động.', true); }
    cbp_require_module();
    return $customer;
}

function cbp_request_id(string $key, array $source): int
{
    $id = cbp_id($source[$key] ?? null);
    if ($id === null) { throw new CBPUserError('Mã yêu cầu không hợp lệ.', 400); }
    return $id;
}

function cbp_text($value, int $max = 500): string
{
    if (!is_string($value)) { throw new CBPUserError('Thông tin gửi lên không hợp lệ.', 400); }
    $value = trim($value);
    $count = preg_match_all('/./us', $value);
    if ($count === false || $count > $max) { throw new CBPUserError('Nội dung quá dài hoặc không hợp lệ.', 400); }
    return $value;
}

function cbp_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { throw new CBPUserError('Thao tác này cần được gửi từ biểu mẫu.', 405); }
    if (!cbp_valid_csrf($_POST['csrf_token'] ?? null)) { throw new CBPUserError('Phiên thao tác đã hết hạn. Vui lòng tải lại trang rồi thử lại.', 403); }
}

function cbp_redirect(string $localUrl): void
{
    header('Location: ' . $localUrl, true, 303);
    exit;
}

function cbp_handle_error(Throwable $error): void
{
    if ($error instanceof CBPUserError) {
        cbp_notice($error->getCode() ?: 409, 'Chưa thể thực hiện thao tác', $error->getMessage());
    }
    error_log('[CINEBOOK demo] ' . $error->getMessage());
    cbp_notice(503, 'Tạm thời chưa xử lý được', 'Vui lòng thử lại. Nếu mới chép module, hãy kiểm tra bước cài đặt database bổ sung.');
}

function cbp_owned_order(int $id, int $customerId): array
{
    $order = cbp_order($id, $customerId);
    if (!$order) { throw new CBPUserError('Đơn hàng không tồn tại hoặc không thuộc tài khoản đang đăng nhập.', 404); }
    return $order;
}

/** Mọi thao tác tài chính của module khóa theo cùng thứ tự: suất chiếu -> đơn -> khách. */
function cbp_lock_order(int $id, int $customerId): array
{
    $initial = cbp_query('SELECT showtime_id FROM bookings WHERE id = ? AND user_id = ?', 'ii', [$id, $customerId])[0] ?? null;
    if (!$initial) { throw new CBPUserError('Không tìm thấy đơn hàng của bạn.', 404); }
    cbp_query('SELECT id FROM showtimes WHERE id = ? FOR UPDATE', 'i', [(int) $initial['showtime_id']]);
    $locked = cbp_query('SELECT id, showtime_id FROM bookings WHERE id = ? AND user_id = ? FOR UPDATE', 'ii', [$id, $customerId])[0] ?? null;
    if (!$locked || (int) $locked['showtime_id'] !== (int) $initial['showtime_id']) {
        throw new CBPUserError('Đơn hàng vừa thay đổi. Hãy tải lại trang.', 409);
    }
    $active = cbp_query("SELECT id FROM users WHERE id = ? AND role = 'customer' AND status = 'active' LOCK IN SHARE MODE", 'i', [$customerId]);
    if (!$active) { throw new CBPUserError('Tài khoản không còn được phép thực hiện thao tác.', 403); }
    return cbp_owned_order($id, $customerId);
}

function cbp_ticket_for_order(int $bookingId): ?array
{
    return cbp_query('SELECT * FROM cbp_tickets WHERE booking_id = ?', 'i', [$bookingId])[0] ?? null;
}

function cbp_cancellation(int $bookingId): ?array
{
    return cbp_query('SELECT * FROM cbp_cancellations WHERE booking_id = ?', 'i', [$bookingId])[0] ?? null;
}

function cbp_snapshot(array $ticket): array
{
    $value = json_decode($ticket['snapshot_json'], true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($value) || !isset($value['seats']) || !is_array($value['seats']) || !$value['seats']) {
        throw new CBPUserError('Dữ liệu vé chưa hợp lệ. Vui lòng liên hệ hỗ trợ.', 409);
    }
    foreach (['booking_code', 'movie', 'cinema', 'address', 'room', 'start', 'end', 'name', 'total_amount'] as $key) {
        if (!isset($value[$key]) || !is_string($value[$key])) { throw new CBPUserError('Thông tin vé không đầy đủ.', 409); }
    }
    foreach ($value['seats'] as $seat) {
        if (!is_array($seat) || !isset($seat['number'], $seat['type'], $seat['price']) || !is_string($seat['number']) || !is_string($seat['type']) || cbp_cents($seat['price']) === null) {
            throw new CBPUserError('Thông tin ghế trên vé không hợp lệ.', 409);
        }
    }
    return $value;
}

function cbp_payment_issues(array $order, array $seats): array
{
    if ($order['booking_status'] === 'cancelled' || cbp_cancellation((int) $order['id'])) { return ['Đơn đã hủy, không thể thanh toán lại.']; }
    if ($order['payment_status'] === 'paid') { return ['Đơn đã thanh toán, không cần trả tiền lại.']; }
    $issues = cbp_order_issues($order, $seats);
    if ($issues) { return $issues; }
    if (!in_array($order['payment_status'], ['pending', 'failed'], true)
        || !in_array($order['booking_status'], ['pending', 'confirmed'], true)) { return ['Trạng thái đơn chưa hợp lệ.']; }
    $start = cbp_datetime($order['start_time']);
    $end = cbp_datetime($order['end_time']);
    if (!$start || !$end || $end <= $start || $order['showtime_status'] !== 'active') { return ['Suất chiếu không khả dụng.']; }
    if ($start <= new DateTimeImmutable()) { return ['Suất chiếu đã bắt đầu, không thể tiếp tục thanh toán.']; }
    return [];
}

function cbp_payment_key(int $bookingId): string
{
    $key = $_SESSION['cbp_payment_keys'][$bookingId] ?? null;
    if (!is_string($key) || cbp_query('SELECT id FROM cbp_payments WHERE request_key = ?', 's', [$key])) {
        $key = bin2hex(random_bytes(32));
        $_SESSION['cbp_payment_keys'][$bookingId] = $key;
    }
    return $key;
}

function cbp_pay(int $bookingId, int $customerId, string $requestKey, string $outcome): int
{
    if (!preg_match('/\A[a-f0-9]{64}\z/', $requestKey) || !in_array($outcome, ['success', 'failed'], true)) {
        throw new CBPUserError('Kết quả hoặc mã giao dịch demo không hợp lệ.', 400);
    }
    return cbp_transaction(function () use ($bookingId, $customerId, $requestKey, $outcome): int {
        global $conn;
        $order = cbp_lock_order($bookingId, $customerId);
        $replay = cbp_query('SELECT id FROM cbp_payments WHERE booking_id = ? AND request_key = ?', 'is', [$bookingId, $requestKey])[0] ?? null;
        if ($replay) { return (int) $replay['id']; }
        if (!isset($_SESSION['cbp_payment_keys'][$bookingId]) || !hash_equals($_SESSION['cbp_payment_keys'][$bookingId], $requestKey)) {
            throw new CBPUserError('Biểu mẫu thanh toán đã cũ. Hãy mở lại trang thanh toán.', 403);
        }
        if ($order['payment_status'] === 'paid') {
            $success = cbp_query("SELECT id FROM cbp_payments WHERE booking_id = ? AND result = 'success' ORDER BY id DESC LIMIT 1", 'i', [$bookingId])[0] ?? null;
            if ($success) { return (int) $success['id']; }
        }
        $seats = cbp_seats($bookingId);
        $issues = cbp_payment_issues($order, $seats);
        if ($issues) { throw new CBPUserError($issues[0], 409); }
        // Đồng bộ với cách main đang coi đơn chưa hủy là đang giữ ghế.
        $conflict = cbp_query("SELECT other.id FROM booking_details mine
            JOIN booking_details occupied ON occupied.seat_id = mine.seat_id AND occupied.booking_id <> mine.booking_id
            JOIN bookings other ON other.id = occupied.booking_id
            WHERE mine.booking_id = ? AND other.showtime_id = ? AND other.booking_status <> 'cancelled' LIMIT 1", 'ii', [$bookingId, (int) $order['showtime_id']]);
        if ($conflict) { throw new CBPUserError('Một ghế trong đơn đang thuộc đơn đặt vé khác. Vui lòng chọn lại ghế.', 409); }
        cbp_execute("DELETE cl FROM cbp_seat_claims cl JOIN bookings b ON b.id = cl.booking_id
            WHERE cl.showtime_id = ? AND b.booking_status = 'cancelled'", 'i', [(int) $order['showtime_id']]);
        $claimConflict = cbp_query('SELECT booking_id FROM cbp_seat_claims WHERE showtime_id = ? AND booking_id <> ? AND seat_id IN (SELECT seat_id FROM booking_details WHERE booking_id = ?) LIMIT 1', 'iii', [(int) $order['showtime_id'], $bookingId, $bookingId]);
        if ($claimConflict) { throw new CBPUserError('Ghế đã được thanh toán trong một đơn khác.', 409); }

        cbp_execute('INSERT INTO cbp_payments (booking_id, attempt_code, request_key, amount, result, method, created_at) VALUES (?, ?, ?, ?, ?, \'DEMO\', ?)',
            'isssss', [$bookingId, cbp_code('PAY'), $requestKey, $order['total_amount'], $outcome, cbp_now()]);
        $attemptId = (int) $conn->insert_id;
        if ($outcome === 'failed') {
            cbp_execute("UPDATE bookings SET payment_status = 'failed' WHERE id = ?", 'i', [$bookingId]);
            return $attemptId;
        }
        foreach ($seats as $seat) {
            cbp_execute('INSERT INTO cbp_seat_claims (showtime_id, seat_id, booking_id, created_at) VALUES (?, ?, ?, ?)',
                'iiis', [(int) $order['showtime_id'], (int) $seat['seat_id'], $bookingId, cbp_now()]);
        }
        $customer = cbp_query('SELECT fullname, email, phone FROM users WHERE id = ?', 'i', [$customerId])[0];
        $snapshot = [
            'booking_code' => $order['booking_code'], 'movie' => $order['movie_title'], 'duration' => (int) $order['duration'],
            'cinema' => $order['cinema_name'], 'address' => $order['cinema_address'], 'room' => $order['room_name'],
            'start' => $order['start_time'], 'end' => $order['end_time'], 'name' => $customer['fullname'],
            'email' => $customer['email'], 'phone' => $customer['phone'], 'total_amount' => $order['total_amount'],
            'seats' => array_map(static function (array $seat): array {
                return ['number' => $seat['seat_number'], 'type' => $seat['seat_type'], 'price' => $seat['price']];
            }, $seats),
        ];
        cbp_execute('INSERT INTO cbp_tickets (booking_id, ticket_code, snapshot_json, issued_at) VALUES (?, ?, ?, ?)',
            'isss', [$bookingId, cbp_code('TKT'), json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), cbp_now()]);
        cbp_execute("UPDATE bookings SET payment_status = 'paid', booking_status = 'confirmed' WHERE id = ?", 'i', [$bookingId]);
        return $attemptId;
    });
}

function cbp_cancel_info(array $order, ?array $ticket, ?DateTimeImmutable $serverClock = null): array
{
    $policy = cbp_settings();
    $start = cbp_datetime($order['start_time']);
    if ($ticket) {
        $snapshot = cbp_snapshot($ticket);
        $original = cbp_datetime($snapshot['start']);
        if ($original && (!$start || $original < $start)) { $start = $original; }
    }
    $deadline = $start ? $start->modify('-' . $policy['cancel_cutoff_minutes'] . ' minutes') : null;
    // DATETIME của main có độ chính xác tới giây; không để microsecond làm sai mốc đúng 60 phút.
    $allowed = $deadline && ($serverClock ?? new DateTimeImmutable())->getTimestamp() <= $deadline->getTimestamp()
        && in_array($order['booking_status'], ['pending', 'confirmed'], true);
    if ($ticket && $ticket['status'] === 'cancelled') { $allowed = false; }
    $refundBase = cbp_cents($order['total_amount']);
    if ($ticket) { $refundBase = cbp_cents(cbp_snapshot($ticket)['total_amount']); }
    $refund = $order['payment_status'] === 'paid' && $refundBase !== null ? intdiv($refundBase * $policy['refund_percent'], 100) : 0;
    return [
        'allowed' => (bool) $allowed, 'deadline' => $deadline, 'refund' => $refund,
        'message' => $order['booking_status'] === 'cancelled' ? 'Đơn hàng đã được hủy.'
            : (!$deadline ? 'Không xác định được thời gian chiếu.' : (!$allowed ? 'Đơn không còn trong thời hạn hủy theo quy định.' : 'Đơn hàng đang trong thời hạn được hủy.')),
    ];
}

function cbp_cancel(int $bookingId, int $customerId, string $reason): int
{
    $reason = cbp_text($reason);
    if ($reason === '') { throw new CBPUserError('Vui lòng nhập lý do hủy vé.', 400); }
    return cbp_transaction(function () use ($bookingId, $customerId, $reason): int {
        global $conn;
        $order = cbp_lock_order($bookingId, $customerId);
        $existing = cbp_cancellation($bookingId);
        if ($existing) { return (int) $existing['id']; }
        $ticket = cbp_ticket_for_order($bookingId);
        $info = cbp_cancel_info($order, $ticket);
        if (!$info['allowed']) { throw new CBPUserError($info['message'], 409); }
        $refundStatus = $order['payment_status'] === 'paid' ? 'simulated_refunded' : 'not_applicable';
        cbp_execute('INSERT INTO cbp_cancellations (booking_id, user_id, cancellation_code, reason, refund_amount, refund_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            'iisssss', [$bookingId, $customerId, cbp_code('CAN'), $reason, cbp_decimal($info['refund']), $refundStatus, cbp_now()]);
        $cancellationId = (int) $conn->insert_id;
        cbp_execute("UPDATE bookings SET booking_status = 'cancelled' WHERE id = ?", 'i', [$bookingId]);
        cbp_execute("UPDATE cbp_tickets SET status = 'cancelled', cancelled_at = ? WHERE booking_id = ?", 'si', [cbp_now(), $bookingId]);
        cbp_execute('DELETE FROM cbp_seat_claims WHERE booking_id = ?', 'i', [$bookingId]);
        return $cancellationId;
    });
}

function cbp_owned_ticket(int $id, int $customerId): array
{
    $ticket = cbp_query('SELECT t.*, b.payment_status, b.booking_status, b.showtime_id, b.booking_code, st.status AS showtime_status
        FROM cbp_tickets t JOIN bookings b ON b.id = t.booking_id JOIN showtimes st ON st.id = b.showtime_id WHERE t.id = ? AND b.user_id = ?', 'ii', [$id, $customerId])[0] ?? null;
    if (!$ticket) { throw new CBPUserError('Vé không tồn tại hoặc không thuộc tài khoản của bạn.', 404); }
    return $ticket;
}

function cbp_ticket_state(array $ticket, array $snapshot): array
{
    if ($ticket['status'] === 'cancelled' || $ticket['booking_status'] === 'cancelled') { return ['label' => 'Vé đã hủy', 'tone' => 'neutral']; }
    if ($ticket['payment_status'] !== 'paid') { return ['label' => 'Vé không còn hiệu lực', 'tone' => 'danger']; }
    if (($ticket['showtime_status'] ?? '') === 'cancelled') { return ['label' => 'Suất chiếu đã bị hủy', 'tone' => 'danger']; }
    if ($ticket['booking_status'] !== 'confirmed') { return ['label' => 'Đơn cần được xác nhận', 'tone' => 'warning']; }
    $end = cbp_datetime($snapshot['end']);
    $start = cbp_datetime($snapshot['start']);
    if (!$end || !$start) { return ['label' => 'Cần kiểm tra thông tin vé', 'tone' => 'danger']; }
    if ($end <= new DateTimeImmutable()) { return ['label' => 'Suất chiếu đã kết thúc', 'tone' => 'neutral']; }
    if ($start <= new DateTimeImmutable()) { return ['label' => 'Suất chiếu đang diễn ra', 'tone' => 'info']; }
    return ['label' => 'Vé đang có hiệu lực', 'tone' => 'success'];
}

function cbp_create_demo(int $customerId, string $kind = 'normal'): int
{
    if (!cbp_demo_allowed()) { throw new CBPUserError('Chức năng tạo dữ liệu mẫu chỉ dùng trên localhost và database test.', 404); }
    return cbp_transaction(function () use ($customerId, $kind): int {
        global $conn;
        $movie = cbp_query("SELECT id, duration FROM movies WHERE status = 'showing' ORDER BY id LIMIT 1")[0] ?? null;
        $room = cbp_query('SELECT r.id FROM rooms r WHERE (SELECT COUNT(*) FROM seats s WHERE s.room_id = r.id) >= 2 ORDER BY r.id LIMIT 1 FOR UPDATE')[0] ?? null;
        if (!$movie || !$room || (int) $movie['duration'] < 1) { throw new CBPUserError('Cần có phim đang chiếu và phòng có ít nhất hai ghế trong dữ liệu main.', 409); }
        $active = cbp_query("SELECT id FROM users WHERE id = ? AND role = 'customer' AND status = 'active' LOCK IN SHARE MODE", 'i', [$customerId]);
        if (!$active) { throw new CBPUserError('Tài khoản mẫu không còn hoạt động.', 403); }
        $start = $kind === 'near' ? new DateTimeImmutable('+30 minutes') : (new DateTimeImmutable('+3 days'))->setTime(12, 0);
        $found = false;
        for ($i = 0; $i < 30; $i++) {
            $end = $start->modify('+' . (int) $movie['duration'] . ' minutes');
            $overlap = cbp_query("SELECT id FROM showtimes WHERE room_id = ? AND status <> 'cancelled' AND start_time < ? AND end_time > ? LIMIT 1", 'iss', [(int) $room['id'], $end->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s')]);
            if (!$overlap) { $found = true; break; }
            $start = $start->modify($kind === 'near' ? '+1 minute' : '+3 hours');
        }
        if (!$found || ($kind === 'near' && $start > new DateTimeImmutable('+59 minutes'))) { throw new CBPUserError('Chưa tìm được giờ chiếu trống cho kiểu demo này. Hãy chọn suất còn nhiều thời gian.', 409); }
        cbp_execute("INSERT INTO showtimes (movie_id, room_id, start_time, end_time, base_price, status) VALUES (?, ?, ?, ?, '100000.00', 'active')", 'iiss', [(int) $movie['id'], (int) $room['id'], $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        $showtimeId = (int) $conn->insert_id;
        $seats = cbp_query('SELECT id, price_multiplier FROM seats WHERE room_id = ? ORDER BY id LIMIT 2', 'i', [(int) $room['id']]);
        $prices = [];
        foreach ($seats as $seat) {
            $multiplier = cbp_cents($seat['price_multiplier']);
            if ($multiplier === null || $multiplier <= 0) { throw new CBPUserError('Hệ số giá ghế mẫu chưa hợp lệ.', 409); }
            $prices[] = intdiv(10000000 * $multiplier, 100);
        }
        cbp_execute("INSERT INTO bookings (booking_code, user_id, showtime_id, total_amount, payment_status, booking_status, created_at) VALUES (?, ?, ?, ?, 'pending', 'pending', ?)", 'siiss', [cbp_code('DM'), $customerId, $showtimeId, cbp_decimal(array_sum($prices)), cbp_now()]);
        $bookingId = (int) $conn->insert_id;
        foreach ($seats as $i => $seat) { cbp_execute('INSERT INTO booking_details (booking_id, seat_id, price) VALUES (?, ?, ?)', 'iis', [$bookingId, (int) $seat['id'], cbp_decimal($prices[$i])]); }
        return $bookingId;
    });
}
