<?php
/**
 * Quản lý Lịch & Suất Chiếu Phim
 * File: admin/showtimes.php
 */
$page_title = 'Quản Lý Lịch & Suất Chiếu';
require_once __DIR__ . '/auth.php';

// Xử lý các thao tác POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Thêm hoặc Sửa suất chiếu
    if ($action === 'save_showtime') {
        $id = (int)($_POST['id'] ?? 0);
        $movie_id = (int)($_POST['movie_id'] ?? 0);
        $room_id = (int)($_POST['room_id'] ?? 0);
        $start_time = trim($_POST['start_time'] ?? '');
        $end_time = trim($_POST['end_time'] ?? '');
        $base_price = (float)($_POST['base_price'] ?? 75000);
        $status = $_POST['status'] ?? 'active';

        // Tự động tính end_time nếu để trống dựa trên thời lượng phim + 15 phút dọn phòng
        if (empty($end_time) && $movie_id > 0 && !empty($start_time)) {
            $mStmt = $conn->prepare("SELECT duration FROM movies WHERE id = ?");
            $mStmt->bind_param("i", $movie_id);
            $mStmt->execute();
            $dur = (int)$mStmt->get_result()->fetch_assoc()['duration'];
            $mStmt->close();

            $startTimeTs = strtotime($start_time);
            $endTimeTs = $startTimeTs + (($dur + 15) * 60);
            $end_time = date('Y-m-d H:i:s', $endTimeTs);
        } else {
            $end_time = date('Y-m-d H:i:s', strtotime($end_time));
        }

        $start_time = date('Y-m-d H:i:s', strtotime($start_time));

        if ($movie_id <= 0 || $room_id <= 0 || empty($start_time) || $base_price <= 0) {
            $_SESSION['flash_error'] = 'Vui lòng chọn phim, phòng chiếu, thời gian bắt đầu và giá vé hợp lệ!';
        } else {
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE showtimes SET movie_id = ?, room_id = ?, start_time = ?, end_time = ?, base_price = ?, status = ? WHERE id = ?");
                $stmt->bind_param("iissdsi", $movie_id, $room_id, $start_time, $end_time, $base_price, $status, $id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['flash_success'] = 'Cập nhật suất chiếu thành công!';
            } else {
                $stmt = $conn->prepare("INSERT INTO showtimes (movie_id, room_id, start_time, end_time, base_price, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iissds", $movie_id, $room_id, $start_time, $end_time, $base_price, $status);
                $stmt->execute();
                $stmt->close();
                $_SESSION['flash_success'] = 'Thêm suất chiếu mới thành công!';
            }
        }
        admin_redirect('showtimes.php');
    }
}

// Xử lý Xóa suất chiếu
if (isset($_POST['delete_id'])) {
    $delId = (int)$_POST['delete_id'];
    $checkB = $conn->prepare("SELECT COUNT(*) as cnt FROM bookings WHERE showtime_id = ?");
    $checkB->bind_param("i", $delId);
    $checkB->execute();
    $bookingCount = $checkB->get_result()->fetch_assoc()['cnt'];
    $checkB->close();

    if ($bookingCount > 0) {
        $_SESSION['flash_error'] = "Không thể xóa suất chiếu này vì đã có {$bookingCount} đơn đặt vé. Vui lòng chuyển trạng thái sang 'Đã hủy'!";
    } else {
        $delStmt = $conn->prepare("DELETE FROM showtimes WHERE id = ?");
        $delStmt->bind_param("i", $delId);
        $delStmt->execute();
        $delStmt->close();
        $_SESSION['flash_success'] = 'Xóa suất chiếu thành công!';
    }
    admin_redirect('showtimes.php');
}

// Lọc và Tìm kiếm
$filter_movie = isset($_GET['movie_id']) ? (int)$_GET['movie_id'] : 0;
$filter_cinema = isset($_GET['cinema_id']) ? (int)$_GET['cinema_id'] : 0;
$filter_date = trim($_GET['date'] ?? '');
$filter_status = $_GET['status'] ?? 'all';

$sql = "
    SELECT s.*, 
           m.title AS movie_title, m.poster, m.duration,
           r.name AS room_name, r.total_seats,
           c.id AS cinema_id, c.name AS cinema_name, c.city,
           (SELECT COUNT(*) FROM booking_details bd 
            JOIN bookings b ON bd.booking_id = b.id 
            WHERE b.showtime_id = s.id AND b.booking_status != 'cancelled') AS booked_seats
    FROM showtimes s
    JOIN movies m ON s.movie_id = m.id
    JOIN rooms r ON s.room_id = r.id
    JOIN cinemas c ON r.cinema_id = c.id
    WHERE 1=1
";
$params = [];
$types = "";

if ($filter_movie > 0) {
    $sql .= " AND s.movie_id = ?";
    $params[] = $filter_movie;
    $types .= "i";
}

if ($filter_cinema > 0) {
    $sql .= " AND r.cinema_id = ?";
    $params[] = $filter_cinema;
    $types .= "i";
}

if (!empty($filter_date)) {
    $sql .= " AND DATE(s.start_time) = ?";
    $params[] = $filter_date;
    $types .= "s";
}

if ($filter_status !== 'all' && in_array($filter_status, ['active', 'cancelled', 'completed'])) {
    $sql .= " AND s.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

$sql .= " ORDER BY s.start_time DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$showtimes = $stmt->get_result();

// Danh sách phim đang hoạt động để đưa vào dropdown
$moviesList = $conn->query("SELECT id, title, duration FROM movies WHERE status != 'hidden' ORDER BY title ASC");

// Danh sách cụm rạp để đưa vào dropdown
$cinemasList = $conn->query("SELECT id, name, city FROM cinemas ORDER BY name ASC");
require_once __DIR__ . '/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h4 class="fw-bold mb-1">Quản lý Lịch & Suất Chiếu</h4>
        <p class="text-muted mb-0 small">Thiết lập lịch chiếu, giá vé và theo dõi sơ đồ ghế theo thời gian thực</p>
    </div>
    <button class="btn btn-primary rounded-pill px-3 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#showtimeModal" onclick="openAddShowtimeModal()">
        <i class="fa-solid fa-calendar-plus"></i> Thêm Suất Chiếu Mới
    </button>
</div>

<!-- KHU VỰC BỘ LỌC -->
<div class="card p-3 mb-4">
    <form method="GET" class="row g-2 align-items-center">
        <!-- Lọc theo phim -->
        <div class="col-12 col-md-3">
            <select name="movie_id" class="form-select">
                <option value="0">-- Tất cả phim --</option>
                <?php 
                $moviesList->data_seek(0);
                while ($m = $moviesList->fetch_assoc()):
                ?>
                    <option value="<?= $m['id'] ?>" <?= $filter_movie === (int)$m['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['title']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Lọc theo cụm rạp -->
        <div class="col-12 col-md-3">
            <select name="cinema_id" class="form-select">
                <option value="0">-- Tất cả cụm rạp --</option>
                <?php 
                $cinemasList->data_seek(0);
                while ($c = $cinemasList->fetch_assoc()):
                ?>
                    <option value="<?= $c['id'] ?>" <?= $filter_cinema === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['city']) ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Lọc theo ngày chiếu -->
        <div class="col-12 col-md-2">
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>" title="Lọc theo ngày chiếu">
        </div>

        <!-- Lọc theo trạng thái -->
        <div class="col-12 col-md-2">
            <select name="status" class="form-select">
                <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>-- Trạng thái --</option>
                <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Đang mở bán</option>
                <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Đã hoàn thành</option>
                <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Đã hủy</option>
            </select>
        </div>

        <!-- Nút áp dụng bộ lọc -->
        <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-secondary w-100">Lọc</button>
            <?php if ($filter_movie > 0 || $filter_cinema > 0 || !empty($filter_date) || $filter_status !== 'all'): ?>
                <a href="showtimes.php" class="btn btn-outline-secondary" title="Hủy bộ lọc"><i class="fa-solid fa-rotate-left"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- DANH SÁCH SUẤT CHIẾU -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-custom align-middle">
            <thead>
                <tr>
                    <th style="width: 70px;">Phim</th>
                    <th>Tên Phim & Cụm Rạp</th>
                    <th>Thời Gian Chiếu</th>
                    <th>Giá Vé Cơ Bản</th>
                    <th>Tình Trạng Ghế</th>
                    <th>Trạng Thái</th>
                    <th class="text-end" style="width: 170px;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($showtimes && $showtimes->num_rows > 0): ?>
                    <?php while ($st = $showtimes->fetch_assoc()): 
                        $totalS = (int)$st['total_seats'];
                        $bookedS = (int)$st['booked_seats'];
                        $occupancyRate = $totalS > 0 ? round(($bookedS / $totalS) * 100) : 0;
                    ?>
                        <tr>
                            <td>
                                <img src="<?= !empty($st['poster']) ? htmlspecialchars($st['poster']) : 'https://placehold.co/70x100?text=Movie' ?>" 
                                     alt="Poster" 
                                     class="rounded object-fit-cover shadow-sm" 
                                     style="width: 50px; height: 72px;"
                                     onerror="this.src='https://placehold.co/70x100?text=Movie'">
                            </td>
                            <td>
                                <div class="fw-bold text-slate-800 fs-6"><?= htmlspecialchars($st['movie_title']) ?></div>
                                <div class="text-muted small mt-1">
                                    <i class="fa-solid fa-building text-primary me-1"></i><?= htmlspecialchars($st['cinema_name']) ?> &bull; 
                                    <span class="text-primary fw-semibold"><?= htmlspecialchars($st['room_name']) ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold text-slate-800">
                                    <i class="fa-regular fa-calendar-check text-success me-1"></i><?= format_datetime($st['start_time']) ?>
                                </div>
                                <div class="text-muted small">
                                    Kết thúc: <?= date('H:i, d/m', strtotime($st['end_time'])) ?> (<?= $st['duration'] ?>p)
                                </div>
                            </td>
                            <td>
                                <span class="fw-bold text-success fs-6">
                                    <?= format_currency($st['base_price']) ?>
                                </span>
                            </td>
                            <td style="min-width: 160px;">
                                <div class="d-flex justify-content-between small fw-semibold mb-1">
                                    <span><?= $bookedS ?> / <?= $totalS ?> vé</span>
                                    <span><?= $occupancyRate ?>%</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar <?= $occupancyRate > 75 ? 'bg-danger' : ($occupancyRate > 40 ? 'bg-warning' : 'bg-primary') ?>" 
                                         role="progressbar" 
                                         style="width: <?= $occupancyRate ?>%"></div>
                                </div>
                            </td>
                            <td>
                                <?php if ($st['status'] === 'active'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Đang mở bán</span>
                                <?php elseif ($st['status'] === 'completed'): ?>
                                    <span class="badge bg-secondary-subtle text-secondary px-2 py-1">Đã hoàn thành</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">Đã hủy</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <!-- Nút xem sơ đồ ghế trực quan -->
                                    <button class="btn btn-primary" 
                                            onclick="openQuickSeatMap(<?= $st['id'] ?>)" 
                                            title="Xem sơ đồ tình trạng ghế">
                                        <i class="fa-solid fa-chair me-1"></i>Sơ đồ ghế
                                    </button>
                                    <!-- Sửa -->
                                    <button class="btn btn-outline-secondary" 
                                            onclick='openEditShowtimeModal(<?= json_encode($st, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                            title="Sửa thông tin">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <!-- Xóa -->
                                    <form method="POST" class="d-inline">
                                        <?php admin_csrf_field(); ?>
                                        <input type="hidden" name="delete_id" value="<?= $st['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                data-confirm="Bạn có chắc chắn muốn xóa suất chiếu này?"
                                                title="Xóa suất chiếu">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-calendar-xmark fs-1 mb-2 d-block opacity-25"></i>
                            Không có suất chiếu nào phù hợp với điều kiện tìm kiếm.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL THÊM / SỬA SUẤT CHIẾU -->
<div class="modal fade" id="showtimeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <?php admin_csrf_field(); ?>
                <input type="hidden" name="action" value="save_showtime">
                <input type="hidden" name="id" id="showtime_id" value="0">

                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="showtimeModalTitle">Thêm Suất Chiếu Mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Chọn Phim -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Phim Chiếu <span class="text-danger">*</span></label>
                            <select name="movie_id" id="modal_movie_id" class="form-select" required onchange="onMovieChange(this)">
                                <option value="">-- Chọn bộ phim --</option>
                                <?php 
                                $moviesList->data_seek(0);
                                while ($m = $moviesList->fetch_assoc()):
                                ?>
                                    <option value="<?= $m['id'] ?>" data-duration="<?= $m['duration'] ?>">
                                        <?= htmlspecialchars($m['title']) ?> (<?= $m['duration'] ?> phút)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Chọn Cụm Rạp -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Cụm Rạp <span class="text-danger">*</span></label>
                            <select id="modal_cinema_select" class="form-select" required onchange="loadRoomsByCinema(this.value)">
                                <option value="">-- Chọn cụm rạp --</option>
                                <?php 
                                $cinemasList->data_seek(0);
                                while ($c = $cinemasList->fetch_assoc()):
                                ?>
                                    <option value="<?= $c['id'] ?>">
                                        <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['city']) ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Chọn Phòng Chiếu -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Phòng Chiếu <span class="text-danger">*</span></label>
                            <select name="room_id" id="modal_room_select" class="form-select" required>
                                <option value="">-- Vui lòng chọn cụm rạp trước --</option>
                            </select>
                        </div>

                        <!-- Giá vé cơ bản -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Giá vé cơ bản (VNĐ) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="base_price" id="modal_base_price" class="form-control" required min="10000" step="5000" value="80000">
                                <span class="input-group-text">₫</span>
                            </div>
                        </div>

                        <!-- Giờ bắt đầu -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Thời gian bắt đầu <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="start_time" id="modal_start_time" class="form-control" required onchange="calculateEndTime()">
                        </div>

                        <!-- Giờ kết thúc -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Thời gian kết thúc (Gợi ý tự động)</label>
                            <input type="datetime-local" name="end_time" id="modal_end_time" class="form-control">
                            <small class="text-muted">Tự động cộng thêm thời lượng phim + 15p chuẩn bị</small>
                        </div>

                        <!-- Trạng thái -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">Trạng thái suất chiếu</label>
                            <select name="status" id="modal_status" class="form-select">
                                <option value="active">Đang mở bán vé (Active)</option>
                                <option value="completed">Đã kết thúc (Completed)</option>
                                <option value="cancelled">Hủy suất chiếu (Cancelled)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold" id="showtimeSubmitBtn">Lưu Suất Chiếu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL SƠ ĐỒ XEM NHANH TÌNH TRẠNG GHẾ CỦA SUẤT CHIẾU -->
<div class="modal fade" id="quickSeatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title fw-bold">
                        <i class="fa-solid fa-tv text-primary me-2"></i>Sơ Đồ Ghế & Tình Trạng Suất Chiếu
                    </h5>
                    <div id="seatModalSubtitle" class="text-muted small mt-1">Đang tải...</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <!-- THỐNG KÊ NHANH TRÊN ĐẦU MODAL -->
                <div class="row g-2 mb-4 text-center">
                    <div class="col-6 col-md-3">
                        <div class="p-2 border rounded bg-white">
                            <div class="text-muted small">Tổng số ghế</div>
                            <div class="fw-bold fs-5 text-dark" id="modalStatTotalSeats">0</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-2 border rounded bg-white">
                            <div class="text-muted small">Ghế đã đặt</div>
                            <div class="fw-bold fs-5 text-danger" id="modalStatBookedSeats">0</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-2 border rounded bg-white">
                            <div class="text-muted small">Ghế còn trống</div>
                            <div class="fw-bold fs-5 text-success" id="modalStatAvailableSeats">0</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-2 border rounded bg-white">
                            <div class="text-muted small">Tỷ lệ lấp đầy</div>
                            <div class="fw-bold fs-5 text-primary" id="modalStatOccupancy">0%</div>
                        </div>
                    </div>
                </div>

                <!-- KHUNG MÀN HÌNH CHIẾU -->
                <div class="screen-container">
                    <div class="cinema-screen"></div>
                    <div class="screen-text">MÀN HÌNH CHIẾU PHIM</div>
                </div>

                <!-- LƯỚI GHẾ NGỒI -->
                <div id="quickSeatGrid" class="seat-grid mb-4">
                    <div class="spinner-border text-primary my-4" role="status"></div>
                </div>

                <!-- CHÚ THÍCH TRẠNG THÁI GHẾ -->
                <div class="d-flex justify-content-center gap-4 flex-wrap border-top pt-3 small">
                    <div class="seat-legend-item">
                        <div class="seat-legend-box seat-standard"></div>
                        <span>Ghế thường (Trống)</span>
                    </div>
                    <div class="seat-legend-item">
                        <div class="seat-legend-box seat-vip"></div>
                        <span>Ghế VIP (Trống)</span>
                    </div>
                    <div class="seat-legend-item">
                        <div class="seat-legend-box seat-couple" style="width: 35px;"></div>
                        <span>Ghế Couple (Trống)</span>
                    </div>
                    <div class="seat-legend-item">
                        <div class="seat-legend-box seat-booked"></div>
                        <span>Ghế ĐÃ ĐẶT (Khách đã giữ)</span>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Đóng Sơ Đồ</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentMovieDuration = 120;

function onMovieChange(select) {
    const selectedOption = select.options[select.selectedIndex];
    currentMovieDuration = parseInt(selectedOption.getAttribute('data-duration') || '120');
    calculateEndTime();
}

function calculateEndTime() {
    const startTimeInput = document.getElementById('modal_start_time').value;
    if (!startTimeInput) return;

    const startDate = new Date(startTimeInput);
    if (isNaN(startDate.getTime())) return;

    // Cộng thêm thời lượng phim + 15 phút đệm
    const endDate = new Date(startDate.getTime() + (currentMovieDuration + 15) * 60000);

    // Định dạng yyyy-MM-ddThh:mm cho input datetime-local
    const yyyy = endDate.getFullYear();
    const mm = String(endDate.getMonth() + 1).padStart(2, '0');
    const dd = String(endDate.getDate()).padStart(2, '0');
    const hh = String(endDate.getHours()).padStart(2, '0');
    const min = String(endDate.getMinutes()).padStart(2, '0');

    document.getElementById('modal_end_time').value = `${yyyy}-${mm}-${dd}T${hh}:${min}`;
}

function loadRoomsByCinema(cinemaId, preselectedRoomId = 0) {
    const roomSelect = document.getElementById('modal_room_select');
    roomSelect.innerHTML = '<option value="">Đang tải phòng...</option>';

    if (!cinemaId) {
        roomSelect.innerHTML = '<option value="">-- Vui lòng chọn cụm rạp trước --</option>';
        return;
    }

    fetch('ajax_get_rooms.php?cinema_id=' + cinemaId)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.rooms.length > 0) {
                let html = '<option value="">-- Chọn phòng chiếu --</option>';
                data.rooms.forEach(r => {
                    const sel = (r.id == preselectedRoomId) ? 'selected' : '';
                    html += `<option value="${r.id}" ${sel}>${r.name} (${r.total_seats} ghế)</option>`;
                });
                roomSelect.innerHTML = html;
            } else {
                roomSelect.innerHTML = '<option value="">Không có phòng chiếu nào!</option>';
            }
        })
        .catch(err => {
            roomSelect.innerHTML = '<option value="">Lỗi tải dữ liệu phòng!</option>';
        });
}

function openAddShowtimeModal() {
    document.getElementById('showtimeModalTitle').innerText = 'Thêm Suất Chiếu Mới';
    document.getElementById('showtime_id').value = '0';
    document.getElementById('modal_movie_id').value = '';
    document.getElementById('modal_cinema_select').value = '';
    document.getElementById('modal_room_select').innerHTML = '<option value="">-- Vui lòng chọn cụm rạp trước --</option>';
    document.getElementById('modal_base_price').value = '80000';
    document.getElementById('modal_start_time').value = '';
    document.getElementById('modal_end_time').value = '';
    document.getElementById('modal_status').value = 'active';
    document.getElementById('showtimeSubmitBtn').innerText = 'Thêm Suất Chiếu';
}

function openEditShowtimeModal(st) {
    document.getElementById('showtimeModalTitle').innerText = 'Cập Nhật Suất Chiếu';
    document.getElementById('showtime_id').value = st.id;
    document.getElementById('modal_movie_id').value = st.movie_id;
    document.getElementById('modal_cinema_select').value = st.cinema_id;
    document.getElementById('modal_base_price').value = Math.round(st.base_price);
    
    // Định dạng datetime-local (thay khoảng trắng thành T)
    const stTime = st.start_time.replace(' ', 'T').substring(0, 16);
    const edTime = st.end_time.replace(' ', 'T').substring(0, 16);
    document.getElementById('modal_start_time').value = stTime;
    document.getElementById('modal_end_time').value = edTime;
    document.getElementById('modal_status').value = st.status;
    document.getElementById('showtimeSubmitBtn').innerText = 'Lưu Thay Đổi';

    // Tải danh sách phòng theo rạp và chọn phòng hiện tại
    loadRoomsByCinema(st.cinema_id, st.room_id);

    new bootstrap.Modal(document.getElementById('showtimeModal')).show();
}

// Sơ đồ xem nhanh tình trạng ghế qua AJAX
function openQuickSeatMap(showtimeId) {
    const modal = new bootstrap.Modal(document.getElementById('quickSeatModal'));
    modal.show();

    const grid = document.getElementById('quickSeatGrid');
    grid.innerHTML = '<div class="spinner-border text-primary my-4" role="status"></div>';

    fetch('ajax_get_showtime_seats.php?showtime_id=' + showtimeId)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                grid.innerHTML = '<div class="text-danger py-4">' + data.message + '</div>';
                return;
            }

            // Cập nhật thông tin tiêu đề và thống kê
            const st = data.showtime;
            document.getElementById('seatModalSubtitle').innerHTML = 
                `<strong>${st.movie_title}</strong> &bull; ${st.cinema_name} &bull; ${st.room_name} &bull; Giờ chiếu: <span class="text-primary fw-bold">${st.start_time}</span> &bull; Giá cơ bản: ${st.base_price_formatted}`;

            document.getElementById('modalStatTotalSeats').innerText = data.total_seats;
            document.getElementById('modalStatBookedSeats').innerText = data.booked_count;
            document.getElementById('modalStatAvailableSeats').innerText = data.available_count;
            document.getElementById('modalStatOccupancy').innerText = data.occupancy_rate + '%';

            // Nhóm theo hàng ghế (A, B, C...)
            const rows = {};
            data.seats.forEach(seat => {
                const rowKey = seat.seat_number.match(/^[A-Z]+/i)?.[0] || seat.seat_number.charAt(0);
                if (!rows[rowKey]) rows[rowKey] = [];
                rows[rowKey].push(seat);
            });

            let html = '';
            for (const [rowLetter, seatList] of Object.entries(rows)) {
                html += '<div class="seat-row">';
                html += '<span class="row-label">' + rowLetter + '</span>';
                seatList.forEach(s => {
                    let seatClass = 'seat-item';
                    if (s.is_booked) {
                        seatClass += ' seat-booked';
                        const info = s.booking_info;
                        const tooltip = `Ghế: ${s.seat_number} - ĐÃ ĐẶT\\nKhách: ${info.customer}\\nMã đơn: ${info.booking_code}\\nGiá: ${new Intl.NumberFormat('vi-VN').format(info.price)} đ`;
                        html += `<div class="${seatClass}" title="${tooltip}"><i class="fa-solid fa-lock" style="font-size: 0.65rem;"></i></div>`;
                    } else {
                        seatClass += ' seat-' + s.seat_type;
                        const tooltip = `Ghế: ${s.seat_number} (${s.seat_type.toUpperCase()})\\nGiá vé: ${new Intl.NumberFormat('vi-VN').format(s.price)} đ`;
                        html += `<div class="${seatClass}" title="${tooltip}">${s.seat_number}</div>`;
                    }
                });
                html += '<span class="row-label">' + rowLetter + '</span>';
                html += '</div>';
            }
            grid.innerHTML = html;
        })
        .catch(err => {
            grid.innerHTML = '<div class="text-danger py-4">Lỗi kết nối máy chủ: ' + err.message + '</div>';
        });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
