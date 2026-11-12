<?php
/**
 * Quản lý Cụm Rạp & Phòng Chiếu
 * File: admin/cinemas.php
 */
$page_title = 'Quản Lý Cụm Rạp & Phòng Chiếu';
require_once __DIR__ . '/auth.php';

// Xử lý các thao tác POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Thêm hoặc Sửa Rạp Chiếu
    if ($action === 'save_cinema') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');

        if (empty($name) || empty($address) || empty($city)) {
            $_SESSION['flash_error'] = 'Vui lòng điền đầy đủ tên rạp, địa chỉ và thành phố!';
        } else {
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE cinemas SET name = ?, address = ?, city = ? WHERE id = ?");
                $stmt->bind_param("sssi", $name, $address, $city, $id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['flash_success'] = 'Cập nhật cụm rạp thành công!';
            } else {
                $stmt = $conn->prepare("INSERT INTO cinemas (name, address, city) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $name, $address, $city);
                $stmt->execute();
                $stmt->close();
                $_SESSION['flash_success'] = 'Thêm cụm rạp mới thành công!';
            }
        }
        admin_redirect('cinemas.php');
    }

    // 2. Thêm hoặc Sửa Phòng Chiếu
    if ($action === 'save_room') {
        $id = (int)($_POST['room_id'] ?? 0);
        $cinema_id = (int)($_POST['cinema_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $auto_generate_seats = isset($_POST['auto_generate_seats']);

        if (empty($name) || $cinema_id <= 0) {
            $_SESSION['flash_error'] = 'Vui lòng chọn cụm rạp và nhập tên phòng chiếu!';
        } else {
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE rooms SET cinema_id = ?, name = ? WHERE id = ?");
                $stmt->bind_param("isi", $cinema_id, $name, $id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['flash_success'] = 'Cập nhật phòng chiếu thành công!';
            } else {
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("INSERT INTO rooms (cinema_id, name) VALUES (?, ?)");
                    $stmt->bind_param("is", $cinema_id, $name);
                    $stmt->execute();
                    if ($auto_generate_seats) {
                        generateDefaultSeatsForRoom($conn, $stmt->insert_id);
                    }
                    $conn->commit();
                    $_SESSION['flash_success'] = 'Thêm phòng chiếu mới thành công!';
                } catch (mysqli_sql_exception $e) {
                    $conn->rollback();
                    if ((int) $e->getCode() !== 1452) {
                        throw $e;
                    }
                    $_SESSION['flash_error'] = 'Cụm rạp không còn tồn tại.';
                }
            }
        }
        admin_redirect('cinemas.php' . ($cinema_id ? "?cinema_id=$cinema_id" : ""));
    }

    // 3. Khởi tạo lại sơ đồ ghế cho một phòng
    if ($action === 'regenerate_seats') {
        $room_id = (int) ($_POST['room_id'] ?? 0);
        $conn->begin_transaction();
        try {
            $room = $conn->prepare("SELECT id FROM rooms WHERE id = ? FOR UPDATE");
            $room->bind_param('i', $room_id);
            $room->execute();
            if (!$room->get_result()->fetch_row()) {
                throw new DomainException('Không tìm thấy phòng chiếu.');
            }
            $check = $conn->prepare("SELECT id FROM showtimes WHERE room_id = ? LIMIT 1");
            $check->bind_param('i', $room_id);
            $check->execute();
            if ($check->get_result()->fetch_row()) {
                throw new DomainException('Phòng đã có suất chiếu. Hãy sửa từng ghế trong sơ đồ.');
            }
            $delete = $conn->prepare("DELETE FROM seats WHERE room_id = ?");
            $delete->bind_param('i', $room_id);
            $delete->execute();
            generateDefaultSeatsForRoom($conn, $room_id);
            $conn->commit();
            $_SESSION['flash_success'] = 'Đã khôi phục sơ đồ 28 vị trí mặc định.';
        } catch (DomainException $e) {
            $conn->rollback();
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            throw $e;
        }
        admin_redirect('cinemas.php');
    }
}

// Xử lý Xóa Rạp hoặc Xóa Phòng
if (isset($_POST['delete_cinema'])) {
    $delCinemaId = (int)$_POST['delete_cinema'];
    $stmt = $conn->prepare("DELETE FROM cinemas WHERE id = ?");
    $stmt->bind_param("i", $delCinemaId);
    if ($stmt->execute()) {
        $_SESSION['flash_success'] = 'Đã xóa cụm rạp!';
    } else {
        $_SESSION['flash_error'] = 'Không thể xóa cụm rạp: ' . $conn->error;
    }
    $stmt->close();
    admin_redirect('cinemas.php');
}

if (isset($_POST['delete_room'])) {
    $delRoomId = (int)$_POST['delete_room'];
    $check = $conn->prepare("SELECT COUNT(*) as cnt FROM showtimes WHERE room_id = ?");
    $check->bind_param("i", $delRoomId);
    $check->execute();
    $cnt = $check->get_result()->fetch_assoc()['cnt'];
    $check->close();

    if ($cnt > 0) {
        $_SESSION['flash_error'] = 'Phòng chiếu này đang có suất chiếu liên kết, không thể xóa!';
    } else {
        $del = $conn->prepare("DELETE FROM rooms WHERE id = ?");
        $del->bind_param("i", $delRoomId);
        $del->execute();
        $del->close();
        $_SESSION['flash_success'] = 'Xóa phòng chiếu thành công!';
    }
    admin_redirect('cinemas.php');
}

// Hàm hỗ trợ tạo cấu trúc ghế chuẩn cho phòng
function generateDefaultSeatsForRoom(mysqli $conn, int $roomId): void
{
    $rows = [
        'A' => ['standard', 1.00, 1],
        'B' => ['standard', 1.00, 1],
        'C' => ['vip', 1.25, 1],
        'D' => ['couple', 1.80, 2],
    ];
    $stmt = $conn->prepare('INSERT INTO seats (room_id, seat_number, seat_type, price_multiplier) VALUES (?, ?, ?, ?)');
    $total = 0;
    foreach ($rows as $row => [$type, $price, $step]) {
        for ($i = 1; $i <= 8; $i += $step) {
            $number = $row . $i;
            if ($step === 2) {
                $number .= '-' . $row . ($i + 1);
            }
            $stmt->bind_param('issd', $roomId, $number, $type, $price);
            $stmt->execute();
            $total++;
        }
    }
    $update = $conn->prepare('UPDATE rooms SET total_seats = ? WHERE id = ?');
    $update->bind_param('ii', $total, $roomId);
    $update->execute();
}

// Lấy danh sách rạp
$cinemas = $conn->query("
    SELECT c.*, 
           COUNT(r.id) AS total_rooms,
           COALESCE(SUM(r.total_seats), 0) AS total_capacity
    FROM cinemas c
    LEFT JOIN rooms r ON c.id = r.cinema_id
    GROUP BY c.id
    ORDER BY c.id ASC
");

// Lọc phòng chiếu theo rạp được chọn
$selectedCinemaId = isset($_GET['cinema_id']) ? (int)$_GET['cinema_id'] : 0;
$roomQuery = "
    SELECT r.*, c.name AS cinema_name, c.city,
           (SELECT COUNT(*) FROM seats s WHERE s.room_id = r.id) AS actual_seats_count,
           (SELECT COUNT(*) FROM showtimes st WHERE st.room_id = r.id) AS total_showtimes
    FROM rooms r
    JOIN cinemas c ON r.cinema_id = c.id
";
if ($selectedCinemaId > 0) {
    $roomQuery .= " WHERE r.cinema_id = $selectedCinemaId";
}
$roomQuery .= " ORDER BY r.cinema_id ASC, r.id ASC";
$rooms = $conn->query($roomQuery);
require_once __DIR__ . '/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h4 class="fw-bold mb-1">Cụm Rạp & Phòng Chiếu</h4>
        <p class="text-muted mb-0 small">Quản lý các rạp trên toàn quốc, danh sách phòng và sơ đồ phân bổ ghế</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#cinemaModal" onclick="openAddCinemaModal()">
            <i class="fa-solid fa-plus me-1"></i> Thêm Cụm Rạp
        </button>
        <button class="btn btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#roomModal" onclick="openAddRoomModal()">
            <i class="fa-solid fa-door-open me-1"></i> Thêm Phòng Chiếu
        </button>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- CỘT 1: DANH SÁCH CỤM RẠP -->
    <div class="col-12 col-xl-5">
        <div class="card h-100">
            <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">
                    <i class="fa-solid fa-building text-primary me-2"></i>Danh Sách Cụm Rạp
                </h6>
                <span class="badge bg-secondary-subtle text-secondary rounded-pill"><?= $cinemas ? $cinemas->num_rows : 0 ?> rạp</span>
            </div>
            <div class="p-3">
                <?php if ($cinemas && $cinemas->num_rows > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php while ($c = $cinemas->fetch_assoc()): ?>
                            <div class="list-group-item p-3 border rounded-3 mb-3 bg-white <?= $selectedCinemaId === (int)$c['id'] ? 'border-primary shadow-sm bg-primary-subtle' : '' ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold text-slate-800 fs-6"><?= htmlspecialchars($c['name']) ?></div>
                                        <div class="text-muted small mt-1">
                                            <i class="fa-solid fa-location-dot text-danger me-1"></i><?= htmlspecialchars($c['address']) ?>, <strong><?= htmlspecialchars($c['city']) ?></strong>
                                        </div>
                                        <div class="d-flex gap-2 mt-2">
                                            <span class="badge bg-light text-dark border">
                                                <i class="fa-solid fa-door-closed text-primary me-1"></i><?= $c['total_rooms'] ?> phòng
                                            </span>
                                            <span class="badge bg-light text-dark border">
                                                <i class="fa-solid fa-chair text-success me-1"></i><?= $c['total_capacity'] ?> ghế
                                            </span>
                                        </div>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <a href="cinemas.php?cinema_id=<?= $c['id'] ?>" class="btn btn-outline-secondary" title="Xem các phòng thuộc rạp này">
                                            <i class="fa-solid fa-filter"></i>
                                        </a>
                                        <button class="btn btn-outline-primary" 
                                                onclick='openEditCinemaModal(<?= json_encode($c, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                                title="Sửa rạp">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <form method="POST" class="d-inline">
                                            <?php admin_csrf_field(); ?>
                                            <input type="hidden" name="delete_cinema" value="<?= $c['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    data-confirm="Bạn có chắc chắn muốn xóa rạp '<?= htmlspecialchars(addslashes($c['name'])) ?>'? Toàn bộ phòng chiếu sẽ bị xóa!"
                                                    title="Xóa rạp">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        Chưa có cụm rạp nào. Bấm nút Thêm Rạp để tạo mới.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- CỘT 2: DANH SÁCH PHÒNG CHIẾU CỦA RẠP -->
    <div class="col-12 col-xl-7">
        <div class="card h-100">
            <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <h6 class="fw-bold mb-0">
                        <i class="fa-solid fa-door-open text-primary me-1"></i>Danh Sách Phòng Chiếu
                    </h6>
                    <?php if ($selectedCinemaId > 0): ?>
                        <span class="badge bg-info-subtle text-info">Đang lọc</span>
                        <a href="cinemas.php" class="small text-decoration-none text-muted"><i class="fa-solid fa-xmark"></i> Bỏ lọc</a>
                    <?php endif; ?>
                </div>
                <button class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#roomModal" onclick="openAddRoomModal()">
                    <i class="fa-solid fa-plus"></i> Thêm phòng
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-custom align-middle">
                    <thead>
                        <tr>
                            <th>Tên Phòng</th>
                            <th>Cụm Rạp</th>
                            <th>Sức Chứa</th>
                            <th>Suất Chiếu</th>
                            <th class="text-end">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rooms && $rooms->num_rows > 0): ?>
                            <?php while ($r = $rooms->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-slate-800">
                                            <i class="fa-solid fa-film text-secondary me-2"></i><?= htmlspecialchars($r['name']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold small"><?= htmlspecialchars($r['cinema_name']) ?></div>
                                        <div class="text-muted" style="font-size: 0.78rem;"><?= htmlspecialchars($r['city']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-success-subtle text-success px-2 py-1">
                                            <?= $r['actual_seats_count'] ?> / <?= $r['total_seats'] ?> ghế
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= $r['total_showtimes'] ?> suất
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a class="btn btn-outline-primary" href="seats.php?room_id=<?= (int) $r['id'] ?>"
                                               title="Chỉnh sửa sơ đồ ghế" aria-label="Chỉnh sửa sơ đồ ghế">
                                                <i class="fa-solid fa-chair"></i>
                                            </a>
                                            <!-- Nút xem sơ đồ ghế của phòng -->
                                            <button class="btn btn-outline-info" 
                                                    onclick="viewRoomSeats(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['name'])) ?>')"
                                                    title="Xem sơ đồ ghế">
                                                <i class="fa-solid fa-border-all"></i>
                                            </button>
                                            <!-- Nút sửa phòng -->
                                            <button class="btn btn-outline-primary" 
                                                    onclick='openEditRoomModal(<?= json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                                    title="Sửa phòng">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            <!-- Nút xóa phòng -->
                                            <form method="POST" class="d-inline">
                                                <?php admin_csrf_field(); ?>
                                                <input type="hidden" name="delete_room" value="<?= $r['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        data-confirm="Bạn có chắc muốn xóa phòng chiếu '<?= htmlspecialchars(addslashes($r['name'])) ?>'?"
                                                        title="Xóa phòng">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    Không có phòng chiếu nào phù hợp.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL THÊM / SỬA CỤM RẠP -->
<div class="modal fade" id="cinemaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <?php admin_csrf_field(); ?>
                <input type="hidden" name="action" value="save_cinema">
                <input type="hidden" name="id" id="cinema_id" value="0">

                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="cinemaModalTitle">Thêm Cụm Rạp Mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tên Cụm Rạp <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="cinema_name" class="form-control" required placeholder="VD: CGV Vincom Landmark 81">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Địa chỉ chi tiết <span class="text-danger">*</span></label>
                        <input type="text" name="address" id="cinema_address" class="form-control" required placeholder="VD: 720A Điện Biên Phủ, P.22, Bình Thạnh">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tỉnh / Thành phố <span class="text-danger">*</span></label>
                        <input type="text" name="city" id="cinema_city" class="form-control" required placeholder="VD: Hồ Chí Minh, Hà Nội, Đà Nẵng...">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold" id="cinemaSubmitBtn">Lưu Cụm Rạp</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL THÊM / SỬA PHÒNG CHIẾU -->
<div class="modal fade" id="roomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <?php admin_csrf_field(); ?>
                <input type="hidden" name="action" value="save_room">
                <input type="hidden" name="room_id" id="modal_room_id" value="0">

                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="roomModalTitle">Thêm Phòng Chiếu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Thuộc Cụm Rạp <span class="text-danger">*</span></label>
                        <select name="cinema_id" id="modal_cinema_id" class="form-select" required>
                            <option value="">-- Chọn cụm rạp --</option>
                            <?php 
                            $cinemasList = $conn->query("SELECT id, name, city FROM cinemas ORDER BY name ASC");
                            while ($cl = $cinemasList->fetch_assoc()):
                            ?>
                                <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['name']) ?> (<?= htmlspecialchars($cl['city']) ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tên Phòng Chiếu <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="modal_room_name" class="form-control" required placeholder="VD: Phòng 01 (IMAX), Cinema 02...">
                    </div>
                    <div class="form-check form-switch mt-3" id="auto_seats_container">
                        <input class="form-check-input" type="checkbox" name="auto_generate_seats" id="auto_generate_seats" checked>
                        <label class="form-check-label fw-semibold small" for="auto_generate_seats">
                            Tự động khởi tạo 28 ghế tiêu chuẩn (Standard: A, B; VIP: C; Couple: D)
                        </label>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold" id="roomSubmitBtn">Lưu Phòng Chiếu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL XEM SƠ ĐỒ GHẾ CỦA PHÒNG CHIẾU -->
<div class="modal fade" id="viewSeatsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-chair text-primary me-2"></i>Sơ đồ ghế: <span id="viewSeatRoomName" class="text-primary"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <!-- Màn hình -->
                <div class="screen-container">
                    <div class="cinema-screen"></div>
                    <div class="screen-text">MÀN HÌNH CHIẾU</div>
                </div>

                <!-- Lưới hiển thị ghế -->
                <div id="roomSeatGrid" class="seat-grid mb-4">
                    <div class="spinner-border text-primary my-4" role="status"></div>
                </div>

                <!-- Chú thích loại ghế -->
                <div class="d-flex justify-content-center gap-4 flex-wrap border-top pt-3">
                    <div class="seat-legend-item">
                        <div class="seat-legend-box" style="background:#e2e8f0; border:1px solid #cbd5e1;"></div>
                        <span>Ghế thường (Standard - x1.0)</span>
                    </div>
                    <div class="seat-legend-item">
                        <div class="seat-legend-box" style="background:#fef3c7; border:1px solid #fcd34d;"></div>
                        <span>Ghế VIP (VIP - x1.25)</span>
                    </div>
                    <div class="seat-legend-item">
                        <div class="seat-legend-box" style="background:#fce7f3; border:1px solid #fbcfe8; width: 35px;"></div>
                        <span>Ghế Đôi (Couple - x1.8)</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <form method="POST" id="regenSeatForm" class="d-inline">
                    <?php admin_csrf_field(); ?>
                    <input type="hidden" name="action" value="regenerate_seats">
                    <input type="hidden" name="room_id" id="regen_room_id" value="0">
                    <button type="submit" class="btn btn-outline-warning btn-sm" data-confirm="Hành động này sẽ tạo lại cấu hình 28 ghế tiêu chuẩn cho phòng. Bạn có chắc chắn không?">
                        <i class="fa-solid fa-arrows-rotate me-1"></i> Khởi tạo lại sơ đồ ghế mặc định
                    </button>
                </form>
                <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script>
function openAddCinemaModal() {
    document.getElementById('cinemaModalTitle').innerText = 'Thêm Cụm Rạp Mới';
    document.getElementById('cinema_id').value = '0';
    document.getElementById('cinema_name').value = '';
    document.getElementById('cinema_address').value = '';
    document.getElementById('cinema_city').value = '';
    document.getElementById('cinemaSubmitBtn').innerText = 'Thêm Cụm Rạp';
}

function openEditCinemaModal(c) {
    document.getElementById('cinemaModalTitle').innerText = 'Cập Nhật Cụm Rạp';
    document.getElementById('cinema_id').value = c.id;
    document.getElementById('cinema_name').value = c.name;
    document.getElementById('cinema_address').value = c.address;
    document.getElementById('cinema_city').value = c.city;
    document.getElementById('cinemaSubmitBtn').innerText = 'Lưu Thay Đổi';
    new bootstrap.Modal(document.getElementById('cinemaModal')).show();
}

function openAddRoomModal() {
    document.getElementById('roomModalTitle').innerText = 'Thêm Phòng Chiếu Mới';
    document.getElementById('modal_room_id').value = '0';
    document.getElementById('modal_room_name').value = '';
    document.getElementById('auto_seats_container').style.display = 'block';
    document.getElementById('roomSubmitBtn').innerText = 'Thêm Phòng';
}

function openEditRoomModal(r) {
    document.getElementById('roomModalTitle').innerText = 'Cập Nhật Phòng Chiếu';
    document.getElementById('modal_room_id').value = r.id;
    document.getElementById('modal_cinema_id').value = r.cinema_id;
    document.getElementById('modal_room_name').value = r.name;
    document.getElementById('auto_seats_container').style.display = 'none';
    document.getElementById('roomSubmitBtn').innerText = 'Lưu Thay Đổi';
    new bootstrap.Modal(document.getElementById('roomModal')).show();
}

// Tải và hiển thị sơ đồ ghế của phòng
function viewRoomSeats(roomId, roomName) {
    document.getElementById('viewSeatRoomName').innerText = roomName;
    document.getElementById('regen_room_id').value = roomId;
    const grid = document.getElementById('roomSeatGrid');
    grid.innerHTML = '<div class="spinner-border text-primary my-4" role="status"></div>';

    const modal = new bootstrap.Modal(document.getElementById('viewSeatsModal'));
    modal.show();

    fetch('ajax_get_room_seats.php?room_id=' + roomId)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                grid.innerHTML = '<div class="text-danger py-3">' + data.message + '</div>';
                return;
            }

            if (data.seats.length === 0) {
                grid.innerHTML = '<div class="text-muted py-3">Phòng này chưa có ghế nào. Hãy bấm "Khởi tạo lại sơ đồ ghế mặc định" bên dưới.</div>';
                return;
            }

            // Nhóm theo hàng (ký tự đầu tiên của số ghế)
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
                    const typeClass = 'seat-' + s.seat_type;
                    html += `<div class="seat-item ${typeClass}" title="Ghế: ${s.seat_number} (${s.seat_type.toUpperCase()})">${s.seat_number}</div>`;
                });
                html += '<span class="row-label">' + rowLetter + '</span>';
                html += '</div>';
            }
            grid.innerHTML = html;
        })
        .catch(err => {
            grid.innerHTML = '<div class="text-danger py-3">Lỗi tải danh sách ghế: ' + err.message + '</div>';
        });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
