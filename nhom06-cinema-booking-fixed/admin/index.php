<?php
/**
 * Dashboard Thống kê Quản trị
 * File: admin/index.php
 */
$page_title = 'Bảng Điều Khiển';
require_once __DIR__ . '/header.php';

// 1. Thống kê tổng doanh thu (Chỉ tính các đơn đã thanh toán)
$resRevenue = $conn->query("SELECT COALESCE(SUM(total_amount), 0) AS total_revenue FROM bookings WHERE payment_status = 'paid'");
$totalRevenue = $resRevenue ? $resRevenue->fetch_assoc()['total_revenue'] : 0;

// 2. Tổng số vé bán ra (Từ booking_details của đơn đã thanh toán)
$resTickets = $conn->query("SELECT COUNT(*) AS total_tickets FROM booking_details bd JOIN bookings b ON bd.booking_id = b.id WHERE b.payment_status = 'paid'");
$totalTickets = $resTickets ? $resTickets->fetch_assoc()['total_tickets'] : 0;

// 3. Tổng số phim đang chiếu
$resMovies = $conn->query("SELECT COUNT(*) AS total_showing FROM movies WHERE status = 'showing'");
$totalMoviesShowing = $resMovies ? $resMovies->fetch_assoc()['total_showing'] : 0;

// 4. Tổng số khách hàng
$resUsers = $conn->query("SELECT COUNT(*) AS total_customers FROM users WHERE role = 'customer'");
$totalCustomers = $resUsers ? $resUsers->fetch_assoc()['total_customers'] : 0;

// 5. Thống kê doanh thu 7 ngày gần nhất cho biểu đồ
$days = [];
$revenues = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $displayDate = date('d/m', strtotime("-$i days"));
    $days[] = $displayDate;

    $stmtDay = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) AS daily_rev FROM bookings WHERE payment_status = 'paid' AND DATE(created_at) = ?");
    $stmtDay->bind_param("s", $date);
    $stmtDay->execute();
    $rev = $stmtDay->get_result()->fetch_assoc()['daily_rev'];
    $revenues[] = (float)$rev;
    $stmtDay->close();
}

// 6. Thống kê tỷ lệ trạng thái đơn hàng
$orderStatusStats = ['confirmed' => 0, 'pending' => 0, 'cancelled' => 0];
$resStatus = $conn->query("SELECT booking_status, COUNT(*) as cnt FROM bookings GROUP BY booking_status");
if ($resStatus) {
    while ($row = $resStatus->fetch_assoc()) {
        $orderStatusStats[$row['booking_status']] = (int)$row['cnt'];
    }
}

// 7. Lấy 5 đơn đặt vé mới nhất
$recentBookingsQuery = "
    SELECT b.*, u.fullname, u.phone, 
           m.title AS movie_title,
           s.start_time,
           r.name AS room_name,
           c.name AS cinema_name,
           (SELECT GROUP_CONCAT(se.seat_number SEPARATOR ', ') 
            FROM booking_details bd 
            JOIN seats se ON bd.seat_id = se.id 
            WHERE bd.booking_id = b.id) AS seat_list
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN showtimes s ON b.showtime_id = s.id
    JOIN movies m ON s.movie_id = m.id
    JOIN rooms r ON s.room_id = r.id
    JOIN cinemas c ON r.cinema_id = c.id
    ORDER BY b.created_at DESC
    LIMIT 5
";
$recentBookings = $conn->query($recentBookingsQuery);
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h4 class="fw-bold mb-1">Tổng quan hoạt động</h4>
        <p class="text-muted mb-0 small">Dữ liệu thống kê doanh số, lượng vé bán ra và các đơn đặt vé mới nhất</p>
    </div>
    <div class="d-flex gap-2">
        <a href="showtimes.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="fa-solid fa-calendar-plus me-1"></i> Thêm lịch chiếu
        </a>
        <a href="movies.php" class="btn btn-primary btn-sm rounded-pill px-3">
            <i class="fa-solid fa-film me-1"></i> Quản lý phim
        </a>
    </div>
</div>

<!-- 4 THẺ THỐNG KÊ (STAT CARDS) -->
<div class="row g-3 mb-4">
    <!-- Doanh thu -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card revenue h-100">
            <div>
                <div class="stat-title">Tổng doanh thu</div>
                <div class="stat-value text-success"><?= format_currency($totalRevenue) ?></div>
                <div class="text-muted small mt-2">
                    <i class="fa-solid fa-circle-check text-success me-1"></i>Đơn đã thanh toán
                </div>
            </div>
            <div class="stat-icon">
                <i class="fa-solid fa-wallet"></i>
            </div>
        </div>
    </div>

    <!-- Số vé bán ra -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card tickets h-100">
            <div>
                <div class="stat-title">Tổng số vé đã bán</div>
                <div class="stat-value text-indigo" style="color: #6366f1;"><?= number_format($totalTickets) ?> <span class="fs-6 fw-normal text-muted">vé</span></div>
                <div class="text-muted small mt-2">
                    <i class="fa-solid fa-ticket text-primary me-1"></i>Ghế đã giữ thành công
                </div>
            </div>
            <div class="stat-icon">
                <i class="fa-solid fa-ticket-simple"></i>
            </div>
        </div>
    </div>

    <!-- Phim đang chiếu -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card movies h-100">
            <div>
                <div class="stat-title">Phim đang chiếu</div>
                <div class="stat-value text-warning"><?= number_format($totalMoviesShowing) ?> <span class="fs-6 fw-normal text-muted">bộ phim</span></div>
                <div class="text-muted small mt-2">
                    <i class="fa-solid fa-play text-warning me-1"></i>Đang mở bán vé
                </div>
            </div>
            <div class="stat-icon">
                <i class="fa-solid fa-video"></i>
            </div>
        </div>
    </div>

    <!-- Khách hàng -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card users h-100">
            <div>
                <div class="stat-title">Khách hàng thành viên</div>
                <div class="stat-value text-danger"><?= number_format($totalCustomers) ?> <span class="fs-6 fw-normal text-muted">tài khoản</span></div>
                <div class="text-muted small mt-2">
                    <i class="fa-solid fa-user-check text-danger me-1"></i>Đã đăng ký tài khoản
                </div>
            </div>
            <div class="stat-icon">
                <i class="fa-solid fa-user-group"></i>
            </div>
        </div>
    </div>
</div>

<!-- KHU VỰC BIỂU ĐỒ (CHARTS) -->
<div class="row g-3 mb-4">
    <!-- Biểu đồ doanh thu 7 ngày -->
    <div class="col-12 col-lg-8">
        <div class="card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0 text-slate-800">
                    <i class="fa-solid fa-chart-line text-primary me-2"></i>Doanh thu 7 ngày gần nhất
                </h6>
                <span class="badge bg-primary-subtle text-primary px-3 py-1 rounded-pill">VND</span>
            </div>
            <div style="position: relative; height: 260px;">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Biểu đồ trạng thái đơn hàng -->
    <div class="col-12 col-lg-4">
        <div class="card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0 text-slate-800">
                    <i class="fa-solid fa-chart-pie text-warning me-2"></i>Tình trạng đơn đặt vé
                </h6>
                <a href="orders.php" class="text-decoration-none small text-primary">Xem tất cả &rarr;</a>
            </div>
            <div style="position: relative; height: 210px;" class="d-flex align-items-center justify-content-center">
                <canvas id="orderStatusChart"></canvas>
            </div>
            <div class="d-flex justify-content-around text-center mt-3 pt-2 border-top small">
                <div>
                    <span class="d-inline-block rounded-circle bg-success" style="width: 8px; height: 8px;"></span>
                    <span class="text-muted d-block">Đã duyệt</span>
                    <strong><?= $orderStatusStats['confirmed'] ?></strong>
                </div>
                <div>
                    <span class="d-inline-block rounded-circle bg-warning" style="width: 8px; height: 8px;"></span>
                    <span class="text-muted d-block">Chờ xử lý</span>
                    <strong><?= $orderStatusStats['pending'] ?></strong>
                </div>
                <div>
                    <span class="d-inline-block rounded-circle bg-danger" style="width: 8px; height: 8px;"></span>
                    <span class="text-muted d-block">Đã hủy</span>
                    <strong><?= $orderStatusStats['cancelled'] ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 5 ĐƠN ĐẶT VÉ MỚI NHẤT -->
<div class="card">
    <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center">
        <div>
            <h6 class="fw-bold mb-0">
                <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Đơn đặt vé mới nhất
            </h6>
        </div>
        <a href="orders.php" class="btn btn-outline-primary btn-sm rounded-pill px-3">
            Xem toàn bộ đơn <i class="fa-solid fa-chevron-right ms-1"></i>
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-custom align-middle">
            <thead>
                <tr>
                    <th>Mã đơn</th>
                    <th>Khách hàng</th>
                    <th>Phim & Suất chiếu</th>
                    <th>Ghế đặt</th>
                    <th>Tổng tiền</th>
                    <th>Thanh toán</th>
                    <th>Trạng thái</th>
                    <th>Thời gian</th>
                    <th class="text-end">Chi tiết</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recentBookings && $recentBookings->num_rows > 0): ?>
                    <?php while ($b = $recentBookings->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <span class="fw-bold text-primary font-monospace"><?= htmlspecialchars($b['booking_code']) ?></span>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($b['fullname']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($b['phone'] ?: 'Chưa cập nhật') ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars($b['movie_title']) ?>">
                                    <?= htmlspecialchars($b['movie_title']) ?>
                                </div>
                                <div class="text-muted small">
                                    <i class="fa-regular fa-clock me-1"></i><?= format_datetime($b['start_time']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= htmlspecialchars($b['seat_list'] ?: 'Không có') ?>
                                </span>
                            </td>
                            <td class="fw-bold text-success">
                                <?= format_currency($b['total_amount']) ?>
                            </td>
                            <td>
                                <?php if ($b['payment_status'] === 'paid'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                        <i class="fa-solid fa-check me-1"></i>Đã thanh toán
                                    </span>
                                <?php elseif ($b['payment_status'] === 'pending'): ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                                        <i class="fa-solid fa-hourglass-half me-1"></i>Chờ xử lý
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                        <i class="fa-solid fa-xmark me-1"></i>Thất bại
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($b['booking_status'] === 'confirmed'): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">Đã xác nhận</span>
                                <?php elseif ($b['booking_status'] === 'pending'): ?>
                                    <span class="badge bg-secondary-subtle text-secondary px-2 py-1">Chờ duyệt</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border px-2 py-1">Đã hủy</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small">
                                <?= format_datetime($b['created_at']) ?>
                            </td>
                            <td class="text-end">
                                <a href="orders.php?search=<?= urlencode($b['booking_code']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2" title="Xem chi tiết đơn">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-ticket-simple fs-3 mb-2 d-block opacity-50"></i>
                            Chưa có đơn đặt vé nào trong hệ thống.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // 1. Biểu đồ Doanh thu (Chart.js)
    const ctxRevenue = document.getElementById('revenueChart').getContext('2d');
    const revenueGradient = ctxRevenue.createLinearGradient(0, 0, 0, 260);
    revenueGradient.addColorStop(0, 'rgba(99, 102, 241, 0.4)');
    revenueGradient.addColorStop(1, 'rgba(99, 102, 241, 0.0)');

    new Chart(ctxRevenue, {
        type: 'line',
        data: {
            labels: <?= json_encode($days) ?>,
            datasets: [{
                label: 'Doanh thu (₫)',
                data: <?= json_encode($revenues) ?>,
                borderColor: '#4f46e5',
                backgroundColor: revenueGradient,
                fill: true,
                tension: 0.35,
                borderWidth: 3,
                pointBackgroundColor: '#4f46e5',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Doanh thu: ' + new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(context.raw);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: {
                        callback: function(value) {
                            if (value >= 1000000) return (value / 1000000) + 'M';
                            if (value >= 1000) return (value / 1000) + 'K';
                            return value;
                        }
                    }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });

    // 2. Biểu đồ Trạng thái đơn hàng
    const ctxStatus = document.getElementById('orderStatusChart').getContext('2d');
    new Chart(ctxStatus, {
        type: 'doughnut',
        data: {
            labels: ['Đã duyệt', 'Chờ xử lý', 'Đã hủy'],
            datasets: [{
                data: [
                    <?= $orderStatusStats['confirmed'] ?>,
                    <?= $orderStatusStats['pending'] ?>,
                    <?= $orderStatusStats['cancelled'] ?>
                ],
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            cutout: '72%'
        }
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

