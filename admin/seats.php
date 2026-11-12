<?php
$page_title = 'Quản Lý Sơ Đồ Ghế';
require_once __DIR__ . '/auth.php';

$roomId = (int) ($_GET['room_id'] ?? 0);
$stmt = $conn->prepare('SELECT r.name, c.name AS cinema_name, r.cinema_id
                        FROM rooms r JOIN cinemas c ON c.id = r.cinema_id WHERE r.id = ?');
$stmt->bind_param('i', $roomId);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
if (!$room) {
    $_SESSION['flash_error'] = 'Không tìm thấy phòng chiếu.';
    admin_redirect('cinemas.php');
}

$seatTypes = ['standard' => 'Ghế thường', 'vip' => 'Ghế VIP', 'couple' => 'Ghế đôi'];
$defaultPrices = ['standard' => 1.00, 'vip' => 1.25, 'couple' => 1.80];
$page = 'seats.php?room_id=' . $roomId;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action !== 'save' && ($action !== 'delete' || $id <= 0)) {
        admin_redirect($page);
    }

    $number = strtoupper(trim($_POST['seat_number'] ?? ''));
    $type = $_POST['seat_type'] ?? '';
    $multiplier = trim($_POST['price_multiplier'] ?? '');
    if ($action === 'save' && (!preg_match('/^[A-Z]{1,2}[1-9]\d{0,2}(?:-[A-Z]{1,2}[1-9]\d{0,2})?$/D', $number)
        || strlen($number) > 10 || !is_string($type) || !isset($seatTypes[$type])
        || ($multiplier !== '' && (!preg_match('/^\d(\.\d{1,2})?$/D', $multiplier) || (float) $multiplier <= 0)))) {
        $_SESSION['flash_error'] = 'Kiểm tra mã ghế, loại ghế và hệ số giá từ 0,01 đến 9,99.';
        admin_redirect($page . ($id > 0 ? '&edit=' . $id : ''));
    }
    if ($action === 'save' && $multiplier === '') {
        $multiplier = $defaultPrices[$type];
    }

    $conn->begin_transaction();
    try {
        $lockRoom = $conn->prepare('SELECT id FROM rooms WHERE id = ? FOR UPDATE');
        $lockRoom->bind_param('i', $roomId);
        $lockRoom->execute();
        if (!$lockRoom->get_result()->fetch_assoc()) {
            throw new DomainException('Phòng chiếu đã bị xóa.');
        }

        if ($id > 0) {
            $lockSeat = $conn->prepare('SELECT id FROM seats WHERE id = ? AND room_id = ? FOR UPDATE');
            $lockSeat->bind_param('ii', $id, $roomId);
            $lockSeat->execute();
            if (!$lockSeat->get_result()->fetch_assoc()) {
                throw new DomainException('Không tìm thấy ghế trong phòng này.');
            }
            $booked = $conn->prepare('SELECT 1 FROM booking_details WHERE seat_id = ? LIMIT 1');
            $booked->bind_param('i', $id);
            $booked->execute();
            if ($booked->get_result()->fetch_row()) {
                throw new DomainException('Ghế đã có đơn đặt vé liên quan nên không thể sửa hoặc xóa.');
            }
        }

        if ($action === 'delete') {
            $stmt = $conn->prepare('DELETE FROM seats WHERE id = ? AND room_id = ?');
            $stmt->bind_param('ii', $id, $roomId);
        } elseif ($id > 0) {
            $stmt = $conn->prepare('UPDATE seats SET seat_number = ?, seat_type = ?, price_multiplier = ? WHERE id = ? AND room_id = ?');
            $stmt->bind_param('ssdii', $number, $type, $multiplier, $id, $roomId);
        } else {
            $stmt = $conn->prepare('INSERT INTO seats (room_id, seat_number, seat_type, price_multiplier) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('issd', $roomId, $number, $type, $multiplier);
        }
        $stmt->execute();

        $update = $conn->prepare('UPDATE rooms SET total_seats = (SELECT COUNT(*) FROM seats WHERE room_id = ?) WHERE id = ?');
        $update->bind_param('ii', $roomId, $roomId);
        $update->execute();
        $conn->commit();
        $_SESSION['flash_success'] = $action === 'delete' ? 'Đã xóa ghế khỏi sơ đồ.' : 'Đã lưu ghế vào sơ đồ.';
    } catch (DomainException $e) {
        $conn->rollback();
        $_SESSION['flash_error'] = $e->getMessage();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        if ((int) $e->getCode() !== 1062) {
            throw $e;
        }
        $_SESSION['flash_error'] = 'Mã ghế này đã có trong phòng.';
        admin_redirect($page . ($id > 0 ? '&edit=' . $id : ''));
    }
    admin_redirect($page);
}

$stmt = $conn->prepare('SELECT s.*, EXISTS(SELECT 1 FROM booking_details bd WHERE bd.seat_id = s.id) AS has_bookings
                        FROM seats s WHERE s.room_id = ?');
$stmt->bind_param('i', $roomId);
$stmt->execute();
$seats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
usort($seats, static fn($a, $b) => strnatcasecmp($a['seat_number'], $b['seat_number']));
$rows = [];
$editing = null;
$editId = (int) ($_GET['edit'] ?? 0);
foreach ($seats as $seat) {
    preg_match('/^[A-Z]+/i', $seat['seat_number'], $match);
    $rows[strtoupper($match[0] ?? substr($seat['seat_number'], 0, 1))][] = $seat;
    if ((int) $seat['id'] === $editId && !$seat['has_bookings']) {
        $editing = $seat;
    }
}
require_once __DIR__ . '/header.php';
?>
<div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Sơ đồ ghế</h4>
        <p class="text-muted mb-0"><?= htmlspecialchars($room['cinema_name']) ?> · <?= htmlspecialchars($room['name']) ?> · <?= count($seats) ?> vị trí</p>
    </div>
    <a class="btn btn-outline-secondary" href="cinemas.php?cinema_id=<?= (int) $room['cinema_id'] ?>">Về danh sách phòng</a>
</div>
<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="card p-3">
            <h5 class="h6 fw-bold mb-3"><?= $editing ? 'Sửa ghế' : 'Thêm ghế' ?></h5>
            <form method="post" action="<?= htmlspecialchars($page) ?>">
                <?php admin_csrf_field(); ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
                <label for="seat-number" class="form-label">Mã ghế</label>
                <input id="seat-number" name="seat_number" class="form-control text-uppercase" maxlength="10"
                       placeholder="A1" aria-describedby="seat-help" value="<?= htmlspecialchars($editing['seat_number'] ?? '') ?>" required>
                <div id="seat-help" class="form-text mb-3">Chữ đầu chỉ hàng ghế. Ví dụ A1 hoặc D1-D2.</div>
                <label for="seat-type" class="form-label">Loại ghế</label>
                <select id="seat-type" name="seat_type" class="form-select mb-3">
                    <?php foreach ($seatTypes as $type => $label): ?>
                        <option value="<?= $type ?>" <?= ($editing['seat_type'] ?? 'standard') === $type ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="seat-price" class="form-label">Hệ số giá</label>
                <input id="seat-price" name="price_multiplier" type="number" min="0.01" max="9.99" step="0.01"
                       aria-describedby="seat-price-help" class="form-control"
                       value="<?= htmlspecialchars($editing['price_multiplier'] ?? '') ?>">
                <div id="seat-price-help" class="form-text mb-3">Để trống để dùng hệ số theo loại ghế (1 / 1,25 / 1,80).</div>
                <button type="submit" class="btn btn-primary"><?= $editing ? 'Lưu thay đổi' : 'Thêm ghế' ?></button>
                <?php if ($editing): ?><a href="<?= htmlspecialchars($page) ?>" class="btn btn-light">Hủy</a><?php endif; ?>
            </form>
        </div>
    </div>
    <div class="col-12 col-lg-8">
        <div class="card p-3 mb-3">
            <div class="cinema-screen"></div>
            <div class="screen-text text-center mb-3">Màn hình</div>
            <div class="d-grid gap-2 overflow-auto">
                <?php foreach ($rows as $rowName => $row): ?>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted fw-semibold text-center flex-shrink-0" style="width: 2rem"><?= htmlspecialchars((string) $rowName) ?></span>
                        <?php foreach ($row as $seat): ?>
                            <?php
                            $seatClass = 'seat-item seat-' . $seat['seat_type'] . ' flex-shrink-0 px-1 w-auto';
                            $label = htmlspecialchars($seat['seat_number']);
                            ?>
                            <?php if ($seat['has_bookings']): ?>
                                <span class="<?= $seatClass ?> seat-booked" title="Ghế đã có đơn đặt vé"><?= $label ?></span>
                            <?php else: ?>
                                <a href="<?= htmlspecialchars($page) ?>&amp;edit=<?= (int) $seat['id'] ?>"
                                   class="<?= $seatClass ?> text-decoration-none" style="cursor: pointer; min-width: 2.25rem" aria-label="Sửa ghế <?= $label ?>"><?= $label ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$rows): ?><p class="text-center text-muted my-2">Phòng chưa có ghế.</p><?php endif; ?>
            </div>
        </div>
        <div class="card table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead><tr><th>Mã ghế</th><th>Loại ghế</th><th>Hệ số giá</th><th class="text-end">Thao tác</th></tr></thead>
                <tbody>
                <?php foreach ($seats as $seat): ?>
                    <tr>
                        <td><?= htmlspecialchars($seat['seat_number']) ?></td>
                        <td><?= $seatTypes[$seat['seat_type']] ?></td>
                        <td><?= htmlspecialchars($seat['price_multiplier']) ?></td>
                        <td class="text-end text-nowrap">
                            <?php if ($seat['has_bookings']): ?>
                                <span class="text-muted small">Đã có đơn đặt</span>
                            <?php else: ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($page) ?>&amp;edit=<?= (int) $seat['id'] ?>">Sửa</a>
                                <form method="post" action="<?= htmlspecialchars($page) ?>" class="d-inline">
                                    <?php admin_csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $seat['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Xóa ghế này khỏi sơ đồ?">Xóa</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$seats): ?><tr><td colspan="4" class="text-muted text-center py-4">Chưa có ghế nào.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
