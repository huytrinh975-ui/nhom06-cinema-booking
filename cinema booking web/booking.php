<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Ho_Chi_Minh');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
$conn->set_charset('utf8mb4');

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money($value): string {
    return number_format((float)$value, 0, ',', '.') . ' ₫';
}

function dbRows(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException('Không thể chuẩn bị truy vấn.');
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $data;
}

function dbInsert(mysqli $conn, string $sql, string $types, array $params): int {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $id = $conn->insert_id;
    $stmt->close();
    return $id;
}

$movieId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$showtimeId = filter_input(INPUT_GET, 'showtime_id', FILTER_VALIDATE_INT) ?: 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $movieId = filter_input(INPUT_POST, 'movie_id', FILTER_VALIDATE_INT) ?: 0;
    $showtimeId = filter_input(INPUT_POST, 'showtime_id', FILTER_VALIDATE_INT) ?: 0;
}

$errors = [];
$movie = null;
$showtimes = [];
$showtime = null;
$seats = [];
$taken = [];
$customerId = (int)($_SESSION['customer_user']['id'] ?? 0);

if (empty($_SESSION['booking_csrf'])) {
    $_SESSION['booking_csrf'] = bin2hex(random_bytes(32));
}

try {
    $movie = dbRows(
        $conn,
        "SELECT id, title, duration
         FROM movies
         WHERE id = ? AND status = 'showing'",
        'i',
        [$movieId]
    )[0] ?? null;

    if (!$movie) {
        throw new RuntimeException('Không tìm thấy phim đang chiếu.');
    }

    $showtimes = dbRows(
        $conn,
        "SELECT s.id, s.room_id, s.start_time,
                s.end_time, s.base_price,
                r.name AS room_name
         FROM showtimes s
         JOIN rooms r ON r.id = s.room_id
         WHERE s.movie_id = ?
           AND s.status = 'active'
           AND s.start_time > NOW()
         ORDER BY s.start_time",
        'i',
        [$movieId]
    );

    if (!$showtimeId && $showtimes) {
        $showtimeId = (int)$showtimes[0]['id'];
    }

    foreach ($showtimes as $candidate) {
        if ((int)$candidate['id'] === $showtimeId) {
            $showtime = $candidate;
            break;
        }
    }

    if ($showtime) {
        $seats = dbRows(
            $conn,
            "SELECT id, seat_number, seat_type, price_multiplier
             FROM seats
             WHERE room_id = ?
             ORDER BY LEFT(seat_number, 1),
                      CAST(SUBSTRING(seat_number, 2) AS UNSIGNED)",
            'i',
            [(int)$showtime['room_id']]
        );

        $takenRows = dbRows(
            $conn,
            "SELECT DISTINCT bd.seat_id
             FROM booking_details bd
             JOIN bookings b ON b.id = bd.booking_id
             WHERE b.showtime_id = ?
               AND b.booking_status <> 'cancelled'
             UNION
             SELECT seat_id
             FROM cbp_seat_claims
             WHERE showtime_id = ?",
            'ii',
            [$showtimeId, $showtimeId]
        );

        $taken = array_map('intval', array_column($takenRows, 'seat_id'));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($customerId <= 0) {
            header('Location: ../login.php');
            exit;
        }

        if (!hash_equals(
            $_SESSION['booking_csrf'],
            (string)($_POST['csrf'] ?? '')
        )) {
            throw new RuntimeException('Phiên đặt vé hết hạn.');
        }

        if (!$showtime) {
            throw new RuntimeException('Suất chiếu không khả dụng.');
        }

        $raw = $_POST['seats'] ?? [];

        if (!is_array($raw) || count($raw) < 1 || count($raw) > 10) {
            throw new RuntimeException('Vui lòng chọn từ 1 đến 10 ghế.');
        }

        $selected = [];

        foreach ($raw as $item) {
            if (!is_string($item) || !ctype_digit($item) || (int)$item <= 0) {
                throw new RuntimeException('Danh sách ghế không hợp lệ.');
            }

            $selected[] = (int)$item;
        }

        if (count(array_unique($selected)) !== count($selected)) {
            throw new RuntimeException('Ghế bị trùng trong yêu cầu.');
        }

        $conn->begin_transaction();

        try {
            $locked = dbRows(
                $conn,
                "SELECT id, room_id, base_price
                 FROM showtimes
                 WHERE id = ?
                   AND movie_id = ?
                   AND status = 'active'
                   AND start_time > NOW()
                 FOR UPDATE",
                'ii',
                [$showtimeId, $movieId]
            )[0] ?? null;

            if (!$locked) {
                throw new RuntimeException('Suất chiếu không còn khả dụng.');
            }

            $placeholders = implode(',', array_fill(0, count($selected), '?'));

            $validSeats = dbRows(
                $conn,
                "SELECT id, price_multiplier
                 FROM seats
                 WHERE room_id = ?
                   AND id IN ($placeholders)",
                str_repeat('i', count($selected) + 1),
                array_merge([(int)$locked['room_id']], $selected)
            );

            if (count($validSeats) !== count($selected)) {
                throw new RuntimeException('Ghế không thuộc phòng chiếu.');
            }

            $occupied = dbRows(
                $conn,
                "SELECT DISTINCT bd.seat_id
                 FROM booking_details bd
                 JOIN bookings b ON b.id = bd.booking_id
                 WHERE b.showtime_id = ?
                   AND b.booking_status <> 'cancelled'
                   AND bd.seat_id IN ($placeholders)
                 UNION
                 SELECT seat_id
                 FROM cbp_seat_claims
                 WHERE showtime_id = ?
                   AND seat_id IN ($placeholders)",
                str_repeat('i', 2 * count($selected) + 2),
                array_merge(
                    [$showtimeId],
                    $selected,
                    [$showtimeId],
                    $selected
                )
            );

            if ($occupied) {
                throw new RuntimeException('Ghế vừa được người khác đặt. Vui lòng chọn lại.');
            }

            $active = dbRows(
                $conn,
                "SELECT id FROM users
                 WHERE id = ? AND role = 'customer'",
                'i',
                [$customerId]
            );

            if (!$active) {
                throw new RuntimeException('Tài khoản khách hàng không hợp lệ.');
            }

            $prices = [];
            $total = 0;

            foreach ($validSeats as $seat) {
                $price = (int)round(
                    (float)$locked['base_price'] *
                    (float)$seat['price_multiplier']
                );

                $prices[(int)$seat['id']] = $price;
                $total += $price;
            }

            $bookingCode = 'BK' . strtoupper(bin2hex(random_bytes(8)));

            $bookingId = dbInsert(
                $conn,
                "INSERT INTO bookings
                 (booking_code, user_id, showtime_id, total_amount,
                  payment_status, booking_status)
                 VALUES (?, ?, ?, ?, 'pending', 'pending')",
                'siid',
                [$bookingCode, $customerId, $showtimeId, $total]
            );

            foreach ($selected as $seatId) {
                dbInsert(
                    $conn,
                    "INSERT INTO booking_details
                     (booking_id, seat_id, price)
                     VALUES (?, ?, ?)",
                    'iid',
                    [$bookingId, $seatId, $prices[$seatId]]
                );
            }

            $conn->commit();

            header(
                'Location: ../customer/payment/checkout.php?booking_id=' . $bookingId,
                true,
                303
            );
            exit;

        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }

} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chọn ghế | CINEMATIX</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #0b1220;
    color: #e8eef7;
}

a {
    color: #93c5fd;
    text-decoration: none;
}

.container {
    max-width: 1100px;
    margin: auto;
    padding: 30px 20px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.logo {
    color: white;
    font-size: 26px;
    font-weight: bold;
}

.logo span {
    color: #ef4444;
}

.panel {
    background: #141f31;
    border: 1px solid #2a3a51;
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 20px;
}

.layout {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 20px;
}

select {
    width: 100%;
    padding: 12px;
    margin: 12px 0 20px;
    background: #0b1220;
    color: white;
    border: 1px solid #40516b;
    border-radius: 8px;
}

.screen {
    width: 75%;
    height: 10px;
    background: #dce8f7;
    border-radius: 50%;
    margin: 35px auto 12px;
    box-shadow: 0 10px 25px #a6caff40;
}

.screen-text {
    text-align: center;
    color: #aabbd0;
    font-size: 12px;
    margin-bottom: 30px;
}

.seats {
    display: grid;
    grid-template-columns: repeat(10, 1fr);
    gap: 8px;
}

.seat {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    background: #40516b;
    border-radius: 7px;
    font-size: 13px;
    font-weight: bold;
    cursor: pointer;
}

.seat.vip {
    background: #2563eb;
}

.seat.taken {
    background: #334155;
    color: #74859b;
    cursor: not-allowed;
    text-decoration: line-through;
}

.seat:has(input:checked) {
    background: #16a34a;
}

.seat input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.seat:focus-within {
    outline: 2px solid white;
    outline-offset: 2px;
}

.legend {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 25px;
    font-size: 13px;
}

.dot {
    display: inline-block;
    width: 13px;
    height: 13px;
    border-radius: 3px;
    margin-right: 5px;
    vertical-align: middle;
}

.muted {
    color: #9baec5;
}

.summary {
    display: flex;
    justify-content: space-between;
    margin: 18px 0;
}

.total {
    color: #fda4af;
    font-size: 24px;
    font-weight: bold;
}

.btn {
    display: block;
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 10px;
    background: #ef4444;
    color: white;
    font-size: 16px;
    font-weight: bold;
    text-align: center;
    cursor: pointer;
}

.btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.error {
    background: #55282e;
    border: 1px solid #ef4444;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 15px;
}

@media (max-width: 760px) {
    .layout {
        grid-template-columns: 1fr;
    }

    .seats {
        gap: 4px;
    }

    .seat {
        min-height: 32px;
        font-size: 11px;
    }
}
</style>
</head>

<body>
<div class="container">

    <header class="header">
        <a href="index.php" class="logo">CINEMA<span>TIX</span></a>
        <a href="index.php">← Trang chủ</a>
    </header>

    <h1>Chọn ghế & đặt vé</h1>

    <?php foreach ($errors as $error): ?>
        <div class="error"><?= h($error) ?></div>
    <?php endforeach; ?>

    <?php if ($movie): ?>

        <section class="panel">
            <h2><?= h($movie['title']) ?></h2>
            <p class="muted">
                Thời lượng: <?= (int)$movie['duration'] ?> phút
            </p>
        </section>

        <?php if (!$showtimes): ?>

            <div class="panel">
                Hiện chưa có suất chiếu tương lai cho phim này.
                Hãy tạo suất chiếu mới trong trang quản trị.
            </div>

        <?php else: ?>

            <div class="layout">

                <section class="panel">

                    <form method="GET">
                        <input type="hidden" name="id" value="<?= (int)$movieId ?>">

                        <label for="showtime">Chọn suất chiếu</label>

                        <select
                            id="showtime"
                            name="showtime_id"
                            onchange="this.form.submit()"
                        >
                            <?php foreach ($showtimes as $s): ?>
                                <option
                                    value="<?= (int)$s['id'] ?>"
                                    <?= (int)$s['id'] === $showtimeId ? 'selected' : '' ?>
                                >
                                    <?= h(date('d/m/Y H:i', strtotime($s['start_time']))) ?>
                                    —
                                    <?= h($s['room_name']) ?>
                                    —
                                    <?= money($s['base_price']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <?php if ($showtime): ?>

                        <div class="screen"></div>
                        <div class="screen-text">MÀN HÌNH CHIẾU</div>

                        <form method="POST" id="bookingForm">

                            <input type="hidden" name="movie_id" value="<?= (int)$movieId ?>">
                            <input type="hidden" name="showtime_id" value="<?= (int)$showtimeId ?>">
                            <input type="hidden" name="csrf" value="<?= h($_SESSION['booking_csrf']) ?>">

                            <div class="seats">

                                <?php foreach ($seats as $seat): ?>

                                    <?php
                                    $occupied = in_array(
                                        (int)$seat['id'],
                                        $taken,
                                        true
                                    );

                                    $price = (int)round(
                                        (float)$showtime['base_price'] *
                                        (float)$seat['price_multiplier']
                                    );
                                    ?>

                                    <label
                                        class="seat
                                            <?= $seat['seat_type'] === 'vip' ? 'vip' : '' ?>
                                            <?= $occupied ? 'taken' : '' ?>"
                                        title="<?= h($seat['seat_number']) ?> — <?= money($price) ?>"
                                    >
                                        <input
                                            type="checkbox"
                                            name="seats[]"
                                            value="<?= (int)$seat['id'] ?>"
                                            data-name="<?= h($seat['seat_number']) ?>"
                                            data-price="<?= $price ?>"
                                            <?= $occupied ? 'disabled' : '' ?>
                                        >

                                        <?= h($seat['seat_number']) ?>
                                    </label>

                                <?php endforeach; ?>

                            </div>

                        </form>

                        <div class="legend">
                            <span>
                                <i class="dot" style="background:#40516b"></i>
                                Ghế thường
                            </span>

                            <span>
                                <i class="dot" style="background:#2563eb"></i>
                                Ghế VIP
                            </span>

                            <span>
                                <i class="dot" style="background:#16a34a"></i>
                                Đang chọn
                            </span>

                            <span>
                                <i class="dot" style="background:#334155"></i>
                                Đã đặt
                            </span>
                        </div>

                    <?php endif; ?>

                </section>

                <aside class="panel">
                    <h2>Thông tin vé</h2>

                    <?php if ($showtime): ?>

                        <p class="muted">
                            <?= h($showtime['room_name']) ?>
                            <br>
                            <?= h(date('H:i d/m/Y', strtotime($showtime['start_time']))) ?>
                        </p>

                        <div class="summary">
                            <span>Ghế đã chọn</span>
                            <strong id="selectedSeats">Chưa chọn</strong>
                        </div>

                        <div class="summary">
                            <span>Số lượng</span>
                            <strong id="count">0</strong>
                        </div>

                        <hr style="border-color:#334155">

                        <div class="summary">
                            <span>Tổng tiền</span>
                            <span class="total" id="total">0 ₫</span>
                        </div>

                        <?php if ($customerId): ?>

                            <button
                                type="submit"
                                form="bookingForm"
                                class="btn"
                                id="submitBooking"
                                disabled
                            >
                                Xác nhận đặt vé
                            </button>

                        <?php else: ?>

                            <a href="../login.php" class="btn">
                                Đăng nhập để đặt vé
                            </a>

                        <?php endif; ?>

                        <p class="muted" style="font-size:12px">
                            Hệ thống sẽ kiểm tra lại ghế trước khi tạo đơn.
                            Thanh toán được thực hiện ở bước tiếp theo.
                        </p>

                    <?php endif; ?>
                </aside>

            </div>

        <?php endif; ?>

    <?php endif; ?>

</div>

<script>
const form = document.getElementById('bookingForm');

if (form) {
    const checkboxes = [
        ...form.querySelectorAll('input[name="seats[]"]')
    ];

    function updateBooking() {
        const checked = checkboxes.filter(item => item.checked);

        if (checked.length > 10) {
            checked[checked.length - 1].checked = false;
            alert('Bạn chỉ được chọn tối đa 10 ghế.');
            return updateBooking();
        }

        const names = checked.map(item => item.dataset.name);

        const total = checked.reduce(
            (sum, item) => sum + Number(item.dataset.price),
            0
        );

        document.getElementById('selectedSeats').textContent =
            names.join(', ') || 'Chưa chọn';

        document.getElementById('count').textContent =
            checked.length;

        document.getElementById('total').textContent =
            total.toLocaleString('vi-VN') + ' ₫';

        const button = document.getElementById('submitBooking');

        if (button) {
            button.disabled = checked.length === 0;
        }
    }

    checkboxes.forEach(item => {
        item.addEventListener('change', updateBooking);
    });

    updateBooking();
}
</script>

</body>
</html>
