<?php
/**
 * Quản lý Đơn Đặt Vé (Orders & Bookings)
 * File: admin/orders.php
 */
$page_title = 'Quản Lý Đơn Đặt Vé';
require_once __DIR__ . '/header.php';

// Xử lý các thao tác POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // Cập nhật trạng thái thanh toán và đặt vé
    if ($action === 'update_order_status') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $paymentStatus = $_POST['payment_status'] ?? 'pending';
        $bookingStatus = $_POST['booking_status'] ?? 'pending';

        if ($bookingId > 0 && in_array($paymentStatus, ['pending', 'paid', 'failed']) && in_array($bookingStatus, ['confirmed', 'pending', 'cancelled'])) {
            $stmt = $conn->prepare("UPDATE bookings SET payment_status = ?, booking_status = ? WHERE id = ?");
            $stmt->bind_param("ssi", $paymentStatus, $bookingStatus, $bookingId);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash_success'] = 'Cập nhật trạng thái đơn hàng thành công!';
        }
        header("Location: orders.php");
        exit;
    }
}

// Xử lý Xóa đơn hàng qua GET
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $delId);
    if ($stmt->execute()) {
        $_SESSION['flash_success'] = 'Xóa đơn đặt vé thành công!';
    } else {
        $_SESSION['flash_error'] = 'Lỗi khi xóa đơn đặt vé: ' . $conn->error;
    }
    $stmt->close();
    header("Location: orders.php");
    exit;
}

// Lọc và Tìm kiếm
$search = trim($_GET['search'] ?? '');
$filter_payment = $_GET['payment_status'] ?? 'all';
$filter_booking = $_GET['booking_status'] ?? 'all';

// Thống kê tổng số lượng đơn và doanh thu
$statTotalOrders = $conn->query("SELECT COUNT(*) AS cnt FROM bookings")->fetch_assoc()['cnt'];
$statPaidOrders = $conn->query("SELECT COUNT(*) AS cnt FROM bookings WHERE payment_status = 'paid'")->fetch_assoc()['cnt'];
$statPendingOrders = $conn->query("SELECT COUNT(*) AS cnt FROM bookings WHERE payment_status = 'pending'")->fetch_assoc()['cnt'];
$statRevenue = $conn->query("SELECT COALESCE(SUM(total_amount), 0) AS rev FROM bookings WHERE payment_status = 'paid'")->fetch_assoc()['rev'];

// Xây dựng câu truy vấn danh sách đơn hàng
$sql = "
    SELECT b.*, 
           u.fullname, u.email, u.phone,
           m.title AS movie_title, m.poster,
           s.start_time,
           r.name AS room_name,
           c.name AS cinema_name,
           (SELECT COUNT(*) FROM booking_details bd WHERE bd.booking_id = b.id) AS total_tickets,
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
    WHERE 1=1
";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (b.booking_code LIKE ? OR u.fullname LIKE ? OR u.phone LIKE ?)";
    $like = "%" . $search . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

if ($filter_payment !== 'all' && in_array($filter_payment, ['paid', 'pending', 'failed'])) {
    $sql .= " AND b.payment_status = ?";
    $params[] = $filter_payment;
    $types .= "s";
}

if ($filter_booking !== 'all' && in_array($filter_booking, ['confirmed', 'pending', 'cancelled'])) {
    $sql .= " AND b.booking_status = ?";
    $params[] = $filter_booking;
    $types .= "s";
}

$sql .= " ORDER BY b.id DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders = $stmt->get_result();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h4 class="fw-bold mb-1">Quản lý Đơn Đặt Vé</h4>
        <p class="text-muted mb-0 small">Theo dõi tình trạng đơn đặt vé, ghế đã chọn, thanh toán và xử lý đơn</p>
    </div>
</div>

<!-- 4 THẺ THỐNG KÊ NHANH -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card p-3 h-100">
            <div class="text-muted small">Tổng số đơn hàng</div>
            <div class="fw-bold fs-4 text-dark mt-1"><?= number_format($statTotalOrders) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card p-3 h-100">
            <div class="text-muted small">Đơn đã thanh toán</div>
            <div class="fw-bold fs-4 text-success mt-1"><?= number_format($statPaidOrders) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card p-3 h-100">
            <div class="text-muted small">Đơn chờ xử lý</div>
            <div class="fw-bold fs-4 text-warning mt-1"><?= number_format($statPendingOrders) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card p-3 h-100">
            <div class="text-muted small">Doanh thu thu được</div>
            <div class="fw-bold fs-4 text-primary mt-1"><?= format_currency($statRevenue) ?></div>
        </div>
    </div>
</div>

<!-- BỘ LỌC VÀ TÌM KIẾM -->
<div class="card p-3 mb-4">
    <form method="GET" class="row g-2 align-items-center">
        <!-- Tìm kiếm -->
        <div class="col-12 col-md-4">
            <div class="input-group">
                <span class="input-group-text bg-white text-muted border-end-0"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Mã đơn, tên khách, số ĐT..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>

        <!-- Trạng thái thanh toán -->
        <div class="col-12 col-md-3">
            <select name="payment_status" class="form-select">
                <option value="all" <?= $filter_payment === 'all' ? 'selected' : '' ?>>-- Thanh toán: Tất cả --</option>
                <option value="paid" <?= $filter_payment === 'paid' ? 'selected' : '' ?>>Đã thanh toán (Paid)</option>
                <option value="pending" <?= $filter_payment === 'pending' ? 'selected' : '' ?>>Chờ thanh toán (Pending)</option>
                <option value="failed" <?= $filter_payment === 'failed' ? 'selected' : '' ?>>Thất bại (Failed)</option>
            </select>
        </div>

        <!-- Trạng thái đặt vé -->
        <div class="col-12 col-md-3">
            <select name="booking_status" class="form-select">
                <option value="all" <?= $filter_booking === 'all' ? 'selected' : '' ?>>-- Đơn vé: Tất cả --</option>
                <option value="confirmed" <?= $filter_booking === 'confirmed' ? 'selected' : '' ?>>Đã xác nhận</option>
                <option value="pending" <?= $filter_booking === 'pending' ? 'selected' : '' ?>>Chờ xử lý</option>
                <option value="cancelled" <?= $filter_booking === 'cancelled' ? 'selected' : '' ?>>Đã hủy</option>
            </select>
        </div>

        <!-- Nút lọc -->
        <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-secondary w-100">Lọc đơn</button>
            <?php if (!empty($search) || $filter_payment !== 'all' || $filter_booking !== 'all'): ?>
                <a href="orders.php" class="btn btn-outline-secondary" title="Hủy bộ lọc"><i class="fa-solid fa-rotate-left"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- DANH SÁCH ĐƠN HÀNG -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-custom align-middle">
            <thead>
                <tr>
                    <th style="width: 10%;">Mã Đơn</th>
                    <th style="width: 12%;">Khách Hàng</th>
                    <th style="width: 22%;">Phim & Suất Chiếu</th>
                    <th style="width: 9%;">Ghế Đã Đặt</th>
                    <th style="width: 10%;">Tổng Tiền</th>
                    <th style="width: 11%;">Thanh Toán</th>
                    <th style="width: 9%;">Trạng Thái</th>
                    <th style="width: 9%;">Thời Gian</th>
                    <th class="text-end" style="width: 8%;">Hành Động</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($orders && $orders->num_rows > 0): ?>
                    <?php while ($ord = $orders->fetch_assoc()): ?>
                        <tr>
                            <td class="text-nowrap">
                                <span class="fw-bold font-monospace text-primary" style="font-size: 0.78rem; letter-spacing: 0.02em;">
                                    <?= htmlspecialchars($ord['booking_code']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-1 lh-sm">
                                    <span class="fw-semibold text-dark text-nowrap" style="font-size: 0.82rem;"><?= htmlspecialchars($ord['fullname']) ?></span>
                                    <span class="text-muted small d-inline-flex align-items-center text-nowrap" style="font-size: 0.75rem;">
                                        <i class="fa-solid fa-phone me-1 opacity-75" style="font-size: 0.68rem;"></i><?= htmlspecialchars($ord['phone'] ?: 'N/A') ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-1 lh-sm" style="max-width: 175px;">
                                    <div class="fw-bold text-dark text-truncate" title="<?= htmlspecialchars($ord['movie_title']) ?>">
                                        <?= htmlspecialchars($ord['movie_title']) ?>
                                    </div>
                                    <div class="text-muted small text-truncate" title="<?= htmlspecialchars($ord['cinema_name'] . ' • ' . $ord['room_name']) ?>" style="font-size: 0.75rem;">
                                        <?= htmlspecialchars($ord['cinema_name']) ?> &bull; <?= htmlspecialchars($ord['room_name']) ?>
                                    </div>
                                    <div class="text-primary small fw-medium d-inline-flex align-items-center text-nowrap" style="font-size: 0.75rem;">
                                        <i class="fa-regular fa-clock me-1"></i><?= format_datetime($ord['start_time']) ?>
                                    </div>
                                </div>
                            </td>
                            <td class="text-nowrap">
                                <span class="badge bg-light text-dark border d-inline-flex align-items-center px-2 py-1">
                                    <i class="fa-solid fa-chair text-secondary me-1"></i><?= htmlspecialchars($ord['seat_list'] ?: 'Chưa có ghế') ?>
                                </span>
                                <div class="text-muted small mt-1" style="font-size: 0.72rem;">(<?= (int)$ord['total_tickets'] ?> vé)</div>
                            </td>
                            <td class="text-nowrap">
                                <span class="fw-bold text-success" style="font-size: 0.88rem;">
                                    <?= format_currency($ord['total_amount']) ?>
                                </span>
                            </td>
                            <td class="text-nowrap">
                                <?php if ($ord['payment_status'] === 'paid'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="fa-solid fa-check me-1"></i>Đã thanh toán
                                    </span>
                                <?php elseif ($ord['payment_status'] === 'pending'): ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                        <i class="fa-solid fa-hourglass-half me-1"></i>Chờ xử lý
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                        <i class="fa-solid fa-xmark me-1"></i>Thất bại
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <?php if ($ord['booking_status'] === 'confirmed'): ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Đã xác nhận</span>
                                <?php elseif ($ord['booking_status'] === 'pending'): ?>
                                    <span class="badge bg-secondary-subtle text-secondary">Chờ duyệt</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">Đã hủy</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <div class="d-flex flex-column gap-1 text-muted lh-1" style="font-size: 0.78rem;">
                                    <span class="fw-semibold text-dark"><?= date('d/m/Y', strtotime($ord['created_at'])) ?></span>
                                    <span><i class="fa-regular fa-clock me-1 opacity-75"></i><?= date('H:i', strtotime($ord['created_at'])) ?></span>
                                </div>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="btn-group btn-group-sm">
                                    <!-- Xem chi tiết modal -->
                                    <button class="btn btn-outline-primary" style="padding: 3px 6px; font-size: 0.72rem;" 
                                            onclick="viewOrderDetail(<?= $ord['id'] ?>)" 
                                            title="Xem chi tiết đơn & ghế">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                    <!-- Đổi trạng thái modal -->
                                    <button class="btn btn-outline-secondary" style="padding: 3px 6px; font-size: 0.72rem;" 
                                            onclick="openUpdateStatusModal(<?= $ord['id'] ?>, '<?= $ord['payment_status'] ?>', '<?= $ord['booking_status'] ?>', '<?= $ord['booking_code'] ?>')" 
                                            title="Cập nhật trạng thái">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <!-- Xóa đơn -->
                                    <a href="orders.php?delete_id=<?= $ord['id'] ?>" 
                                       class="btn btn-outline-danger" style="padding: 3px 6px; font-size: 0.72rem;" 
                                       data-confirm="Bạn có chắc chắn muốn xóa đơn đặt vé '<?= $ord['booking_code'] ?>'?"
                                       title="Xóa đơn">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-receipt fs-1 mb-2 d-block opacity-25"></i>
                            Không tìm thấy đơn đặt vé nào phù hợp.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL XEM CHI TIẾT ĐƠN HÀNG -->
<div class="modal fade" id="orderDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-ticket text-primary me-2"></i>Chi Tiết Đơn Đặt Vé: <span id="modalOrderCode" class="text-primary font-monospace"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4" id="orderModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>

            <div class="modal-footer bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                    <i class="fa-solid fa-print me-1"></i> In Vé / Hóa Đơn
                </button>
                <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CẬP NHẬT TRẠNG THÁI ĐƠN -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <input type="hidden" name="action" value="update_order_status">
                <input type="hidden" name="booking_id" id="status_booking_id" value="0">

                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">
                        Cập Nhật Trạng Thái Đơn: <span id="status_booking_code" class="text-primary font-monospace"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Trạng thái thanh toán</label>
                        <select name="payment_status" id="select_payment_status" class="form-select">
                            <option value="paid">Đã thanh toán (Paid)</option>
                            <option value="pending">Chờ thanh toán (Pending)</option>
                            <option value="failed">Thất bại (Failed)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Trạng thái đặt vé</label>
                        <select name="booking_status" id="select_booking_status" class="form-select">
                            <option value="confirmed">Đã xác nhận (Confirmed)</option>
                            <option value="pending">Chờ xử lý (Pending)</option>
                            <option value="cancelled">Hủy đơn (Cancelled)</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">Cập Nhật</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewOrderDetail(orderId) {
    const modal = new bootstrap.Modal(document.getElementById('orderDetailModal'));
    modal.show();

    const body = document.getElementById('orderModalBody');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';

    fetch('ajax_get_order_detail.php?order_id=' + orderId)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                body.innerHTML = '<div class="text-danger py-4">' + data.message + '</div>';
                return;
            }

            const ord = data.order;
            document.getElementById('modalOrderCode').innerText = ord.booking_code;

            let seatsTableHtml = '';
            ord.seats.forEach(s => {
                seatsTableHtml += `
                    <tr>
                        <td class="fw-bold">${s.seat_number}</td>
                        <td><span class="badge bg-light text-dark border">${s.seat_type}</span></td>
                        <td class="text-end fw-semibold text-success">${s.price_formatted}</td>
                    </tr>
                `;
            });

            body.innerHTML = `
                <div class="row g-3 mb-4">
                    <!-- Thông tin khách -->
                    <div class="col-12 col-md-6">
                        <div class="p-3 bg-light rounded-3 h-100">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-2">
                                <i class="fa-solid fa-user text-primary me-2"></i>Thông Tin Khách Hàng
                            </h6>
                            <div class="mb-1"><strong>Họ tên:</strong> ${ord.customer.fullname}</div>
                            <div class="mb-1"><strong>Email:</strong> ${ord.customer.email}</div>
                            <div><strong>Điện thoại:</strong> ${ord.customer.phone}</div>
                        </div>
                    </div>

                    <!-- Thông tin suất chiếu -->
                    <div class="col-12 col-md-6">
                        <div class="p-3 bg-light rounded-3 h-100">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-2">
                                <i class="fa-solid fa-clapperboard text-primary me-2"></i>Thông Tin Suất Chiếu
                            </h6>
                            <div class="mb-1"><strong>Phim:</strong> ${ord.movie.title} (${ord.movie.duration})</div>
                            <div class="mb-1"><strong>Rạp:</strong> ${ord.showtime.cinema_name} - ${ord.showtime.room_name}</div>
                            <div class="mb-1"><strong>Suất chiếu:</strong> <span class="text-primary fw-bold">${ord.showtime.start_time}</span></div>
                            <div><strong>Thời gian đặt:</strong> ${ord.created_at}</div>
                        </div>
                    </div>
                </div>

                <!-- Bảng danh sách ghế đã chọn -->
                <div class="card mb-3">
                    <div class="card-header bg-white py-2 px-3 fw-bold small text-uppercase">
                        Danh sách ghế đã đặt (${ord.seats.length} ghế)
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Số Ghế</th>
                                    <th>Hạng Ghế</th>
                                    <th class="text-end">Đơn Giá</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${seatsTableHtml}
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="2" class="text-end fw-bold">Tổng thanh toán:</th>
                                    <th class="text-end fw-bold text-success fs-6">${ord.total_amount_formatted}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Trạng thái -->
                <div class="d-flex justify-content-between align-items-center p-3 border rounded-3 bg-white">
                    <div>
                        <span class="text-muted small me-2">Thanh toán:</span>
                        <strong class="${ord.payment_status === 'paid' ? 'text-success' : (ord.payment_status === 'pending' ? 'text-warning' : 'text-danger')}">
                            ${ord.payment_status.toUpperCase()}
                        </strong>
                    </div>
                    <div>
                        <span class="text-muted small me-2">Trạng thái vé:</span>
                        <strong class="${ord.booking_status === 'confirmed' ? 'text-primary' : (ord.booking_status === 'pending' ? 'text-secondary' : 'text-danger')}">
                            ${ord.booking_status.toUpperCase()}
                        </strong>
                    </div>
                </div>
            `;
        })
        .catch(err => {
            body.innerHTML = '<div class="text-danger py-4">Lỗi kết nối: ' + err.message + '</div>';
        });
}

function openUpdateStatusModal(bookingId, paymentStatus, bookingStatus, bookingCode) {
    document.getElementById('status_booking_id').value = bookingId;
    document.getElementById('status_booking_code').innerText = bookingCode;
    document.getElementById('select_payment_status').value = paymentStatus;
    document.getElementById('select_booking_status').value = bookingStatus;
    new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
