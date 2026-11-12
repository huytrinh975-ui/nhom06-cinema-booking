<?php
$page_title = 'Thống Kê Doanh Thu';
require_once __DIR__ . '/auth.php';

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-29 days'));
$to = $_GET['to'] ?? date('Y-m-d');
$cinemaId = max(0, (int) ($_GET['cinema_id'] ?? 0));
$cinemas = $conn->query('SELECT id, name FROM cinemas ORDER BY name')->fetch_all(MYSQLI_ASSOC);
$validRange = admin_valid_date($from) && admin_valid_date($to) && $from <= $to;
$daily = [];
$totalOrders = $totalTickets = $totalRevenue = 0;

if ($validRange) {
    $stmt = $conn->prepare("SELECT DATE(b.created_at) AS sale_date, COUNT(*) AS paid_orders,
        SUM((SELECT COUNT(*) FROM booking_details bd WHERE bd.booking_id = b.id)) AS tickets,
        SUM(b.total_amount) AS revenue
        FROM bookings b
        JOIN showtimes s ON s.id = b.showtime_id
        JOIN rooms r ON r.id = s.room_id
        WHERE b.payment_status = 'paid' AND b.booking_status <> 'cancelled'
          AND b.created_at >= ? AND b.created_at < DATE_ADD(?, INTERVAL 1 DAY)
          AND (? = 0 OR r.cinema_id = ?)
        GROUP BY DATE(b.created_at)
        ORDER BY sale_date DESC");
    $stmt->bind_param('ssii', $from, $to, $cinemaId, $cinemaId);
    $stmt->execute();
    $daily = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $totalOrders = array_sum(array_column($daily, 'paid_orders'));
    $totalTickets = array_sum(array_column($daily, 'tickets'));
    $totalRevenue = array_sum(array_column($daily, 'revenue'));
}

require_once __DIR__ . '/header.php';
?>
<div class="mb-4">
    <h4 class="fw-bold mb-1">Thống kê doanh thu</h4>
    <p class="small text-muted mb-0">Tính theo ngày đặt vé của các đơn đã thanh toán và còn hiệu lực.</p>
</div>
<form method="get" class="card p-3 mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-6 col-lg-3">
            <label for="report-from" class="form-label">Từ ngày</label>
            <input id="report-from" name="from" type="date" class="form-control"
                   value="<?= htmlspecialchars(is_string($from) ? $from : '') ?>" required>
        </div>
        <div class="col-6 col-lg-3">
            <label for="report-to" class="form-label">Đến ngày</label>
            <input id="report-to" name="to" type="date" class="form-control"
                   value="<?= htmlspecialchars(is_string($to) ? $to : '') ?>" required>
        </div>
        <div class="col-12 col-lg-4">
            <label for="report-cinema" class="form-label">Rạp</label>
            <select id="report-cinema" name="cinema_id" class="form-select">
                <option value="0">Tất cả rạp</option>
                <?php foreach ($cinemas as $cinema): ?>
                    <option value="<?= (int) $cinema['id'] ?>" <?= $cinemaId === (int) $cinema['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cinema['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-lg-2"><button class="btn btn-primary w-100" type="submit">Xem thống kê</button></div>
    </div>
</form>

<?php if (!$validRange): ?>
    <div class="alert alert-danger" role="alert">Chọn khoảng ngày hợp lệ. Ngày bắt đầu phải trước hoặc bằng ngày kết thúc.</div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-4">
            <div class="stat-card revenue h-100">
                <div><div class="stat-title">Doanh thu</div><div class="stat-value text-success"><?= format_currency($totalRevenue) ?></div></div>
                <div class="stat-icon"><i class="fa-solid fa-wallet"></i></div>
            </div>
        </div>
        <div class="col-6 col-sm-4">
            <div class="stat-card tickets h-100">
                <div><div class="stat-title">Vé đã bán</div><div class="stat-value"><?= number_format($totalTickets) ?></div></div>
                <div class="stat-icon"><i class="fa-solid fa-ticket"></i></div>
            </div>
        </div>
        <div class="col-6 col-sm-4">
            <div class="stat-card h-100">
                <div><div class="stat-title">Đơn đã thanh toán</div><div class="stat-value"><?= number_format($totalOrders) ?></div></div>
                <div class="stat-icon"><i class="fa-solid fa-receipt"></i></div>
            </div>
        </div>
    </div>
    <div class="card table-responsive">
        <table class="table table-custom align-middle mb-0">
            <thead><tr><th>Ngày đặt</th><th class="text-end">Số đơn</th><th class="text-end">Số vé</th><th class="text-end">Doanh thu</th></tr></thead>
            <tbody>
            <?php foreach ($daily as $day): ?>
                <tr>
                    <td><?= format_date($day['sale_date']) ?></td>
                    <td class="text-end"><?= number_format($day['paid_orders']) ?></td>
                    <td class="text-end"><?= number_format($day['tickets']) ?></td>
                    <td class="text-end fw-semibold"><?= format_currency($day['revenue']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$daily): ?>
                <tr><td colspan="4" class="text-center text-muted py-4">Chưa có đơn đã thanh toán trong khoảng thời gian đã chọn.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/footer.php'; ?>
