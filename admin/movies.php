<?php
/**
 * Quản lý danh sách Phim
 * File: admin/movies.php
 */
$page_title = 'Quản Lý Phim';
require_once __DIR__ . '/header.php';

// Xử lý các thao tác Thêm / Sửa / Xóa / Đổi trạng thái
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Thêm hoặc Sửa Phim
    if ($action === 'save') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $duration = (int)($_POST['duration'] ?? 0);
        $release_date = trim($_POST['release_date'] ?? '');
        $trailer_url = trim($_POST['trailer_url'] ?? '');
        $status = $_POST['status'] ?? 'showing';
        $poster = trim($_POST['poster_url'] ?? '');

        // Xử lý upload file ảnh poster nếu có
        if (isset($_FILES['poster_file']) && $_FILES['poster_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['poster_file']['tmp_name'];
            $fileName = $_FILES['poster_file']['name'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($fileExt, $allowedExts)) {
                $newFileName = 'movie_' . time() . '_' . rand(100, 999) . '.' . $fileExt;
                $uploadDir = __DIR__ . '/../uploads/movies/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $destination = $uploadDir . $newFileName;
                if (move_uploaded_file($fileTmp, $destination)) {
                    $poster = '../uploads/movies/' . $newFileName;
                }
            }
        }

        if (empty($title) || $duration <= 0 || empty($release_date)) {
            $_SESSION['flash_error'] = 'Vui lòng điền đầy đủ tên phim, thời lượng hợp lệ và ngày phát hành!';
        } else {
            if ($id > 0) {
                // Cập nhật phim
                $stmt = $conn->prepare("UPDATE movies SET title = ?, description = ?, duration = ?, release_date = ?, poster = ?, trailer_url = ?, status = ? WHERE id = ?");
                $stmt->bind_param("ssissssi", $title, $description, $duration, $release_date, $poster, $trailer_url, $status, $id);
                if ($stmt->execute()) {
                    $_SESSION['flash_success'] = 'Cập nhật thông tin phim thành công!';
                } else {
                    $_SESSION['flash_error'] = 'Có lỗi xảy ra: ' . $conn->error;
                }
                $stmt->close();
            } else {
                // Thêm mới phim
                $stmt = $conn->prepare("INSERT INTO movies (title, description, duration, release_date, poster, trailer_url, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssissss", $title, $description, $duration, $release_date, $poster, $trailer_url, $status);
                if ($stmt->execute()) {
                    $_SESSION['flash_success'] = 'Thêm phim mới thành công!';
                } else {
                    $_SESSION['flash_error'] = 'Có lỗi xảy ra khi thêm phim: ' . $conn->error;
                }
                $stmt->close();
            }
        }
        header("Location: movies.php");
        exit;
    }

    // 2. Chuyển đổi trạng thái nhanh
    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        $new_status = $_POST['new_status'] ?? 'showing';
        if (in_array($new_status, ['showing', 'upcoming', 'hidden'])) {
            $stmt = $conn->prepare("UPDATE movies SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash_success'] = 'Cập nhật trạng thái phim thành công!';
        }
        header("Location: movies.php");
        exit;
    }
}

// Xử lý Xóa phim qua GET
if (isset($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    
    // Kiểm tra xem phim có suất chiếu liên quan không
    $checkStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM showtimes WHERE movie_id = ?");
    $checkStmt->bind_param("i", $deleteId);
    $checkStmt->execute();
    $showtimeCount = $checkStmt->get_result()->fetch_assoc()['cnt'];
    $checkStmt->close();

    if ($showtimeCount > 0) {
        $_SESSION['flash_error'] = "Không thể xóa phim này vì đã có {$showtimeCount} suất chiếu liên quan. Hãy ẩn phim hoặc xóa các suất chiếu trước!";
    } else {
        $delStmt = $conn->prepare("DELETE FROM movies WHERE id = ?");
        $delStmt->bind_param("i", $deleteId);
        if ($delStmt->execute()) {
            $_SESSION['flash_success'] = 'Xóa phim thành công!';
        } else {
            $_SESSION['flash_error'] = 'Lỗi khi xóa phim: ' . $conn->error;
        }
        $delStmt->close();
    }
    header("Location: movies.php");
    exit;
}

// Lọc và Tìm kiếm
$search = trim($_GET['search'] ?? '');
$filter_status = $_GET['status'] ?? 'all';

$sql = "SELECT m.*, (SELECT COUNT(*) FROM showtimes s WHERE s.movie_id = m.id) AS total_showtimes 
        FROM movies m WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND m.title LIKE ?";
    $params[] = "%" . $search . "%";
    $types .= "s";
}

if ($filter_status !== 'all' && in_array($filter_status, ['showing', 'upcoming', 'hidden'])) {
    $sql .= " AND m.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

$sql .= " ORDER BY m.id DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$movies = $stmt->get_result();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h4 class="fw-bold mb-1">Quản lý Phim</h4>
        <p class="text-muted mb-0 small">Thêm, chỉnh sửa, cập nhật trạng thái và thông tin áp phích, thời lượng phim</p>
    </div>
    <button class="btn btn-primary rounded-pill px-3 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#movieModal" onclick="openAddMovieModal()">
        <i class="fa-solid fa-circle-plus"></i> Thêm Phim Mới
    </button>
</div>

<!-- THANH TÌM KIẾM & BỘ LỌC -->
<div class="card p-3 mb-4">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-12 col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-white text-muted border-end-0"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Tìm theo tên phim..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div class="col-12 col-md-4">
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>-- Tất cả trạng thái --</option>
                <option value="showing" <?= $filter_status === 'showing' ? 'selected' : '' ?>>Đang chiếu</option>
                <option value="upcoming" <?= $filter_status === 'upcoming' ? 'selected' : '' ?>>Sắp chiếu</option>
                <option value="hidden" <?= $filter_status === 'hidden' ? 'selected' : '' ?>>Đã ẩn</option>
            </select>
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-secondary px-3 w-100">Lọc dữ liệu</button>
            <?php if (!empty($search) || $filter_status !== 'all'): ?>
                <a href="movies.php" class="btn btn-outline-secondary px-3" title="Xóa bộ lọc"><i class="fa-solid fa-rotate-left"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- DANH SÁCH PHIM -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-custom align-middle">
            <thead>
                <tr>
                    <th style="width: 80px;">Poster</th>
                    <th>Tên Phim & Thời Lượng</th>
                    <th>Ngày Khởi Chiếu</th>
                    <th>Suất Chiếu</th>
                    <th>Trailer</th>
                    <th>Trạng Thái</th>
                    <th class="text-end" style="width: 140px;">Hành Động</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($movies && $movies->num_rows > 0): ?>
                    <?php while ($m = $movies->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <img src="<?= !empty($m['poster']) ? htmlspecialchars($m['poster']) : 'https://placehold.co/80x120?text=No+Poster' ?>" 
                                     alt="<?= htmlspecialchars($m['title']) ?>" 
                                     class="rounded object-fit-cover shadow-sm" 
                                     style="width: 60px; height: 85px;"
                                     onerror="this.src='https://placehold.co/80x120?text=No+Poster'">
                            </td>
                            <td>
                                <div class="fw-bold text-slate-800 fs-6"><?= htmlspecialchars($m['title']) ?></div>
                                <div class="text-muted small mt-1">
                                    <i class="fa-regular fa-clock me-1 text-primary"></i><?= (int)$m['duration'] ?> phút
                                </div>
                                <div class="text-muted small text-truncate" style="max-width: 320px;" title="<?= htmlspecialchars($m['description'] ?? '') ?>">
                                    <?= htmlspecialchars($m['description'] ?? 'Chưa có mô tả') ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="fa-regular fa-calendar me-1"></i><?= format_date($m['release_date']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                    <i class="fa-solid fa-film me-1"></i><?= $m['total_showtimes'] ?> suất
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($m['trailer_url'])): ?>
                                    <a href="<?= htmlspecialchars($m['trailer_url']) ?>" target="_blank" class="btn btn-xs btn-outline-danger btn-sm rounded-pill px-2">
                                        <i class="fa-brands fa-youtube me-1"></i>Trailer
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">Không có</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                    <select name="new_status" class="form-select form-select-sm fw-semibold rounded-pill" 
                                            style="width: 135px; <?= $m['status'] == 'showing' ? 'color:#15803d;background:#dcfce7;' : ($m['status'] == 'upcoming' ? 'color:#b45309;background:#fef3c7;' : 'color:#64748b;background:#f1f5f9;') ?>"
                                            onchange="this.form.submit()">
                                        <option value="showing" <?= $m['status'] === 'showing' ? 'selected' : '' ?>>● Đang chiếu</option>
                                        <option value="upcoming" <?= $m['status'] === 'upcoming' ? 'selected' : '' ?>>● Sắp chiếu</option>
                                        <option value="hidden" <?= $m['status'] === 'hidden' ? 'selected' : '' ?>>● Đã ẩn</option>
                                    </select>
                                </form>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" 
                                            onclick='openEditMovieModal(<?= json_encode($m, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                            title="Sửa thông tin">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <a href="movies.php?delete_id=<?= $m['id'] ?>" 
                                       class="btn btn-outline-danger" 
                                       data-confirm="Bạn có chắc chắn muốn xóa bộ phim '<?= htmlspecialchars(addslashes($m['title'])) ?>'?"
                                       title="Xóa phim">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-film fs-1 mb-2 d-block opacity-25"></i>
                            Không tìm thấy bộ phim nào phù hợp.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL THÊM / SỬA PHIM -->
<div class="modal fade" id="movieModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="movie_id" value="0">

                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="movieModalTitle">
                        <i class="fa-solid fa-film text-primary me-2"></i>Thêm Phim Mới
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <label class="form-label fw-semibold">Tên phim <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="movie_title" class="form-control" required placeholder="Nhập tên phim...">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold">Thời lượng (phút) <span class="text-danger">*</span></label>
                            <input type="number" name="duration" id="movie_duration" class="form-control" required min="1" placeholder="VD: 120">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Ngày khởi chiếu <span class="text-danger">*</span></label>
                            <input type="date" name="release_date" id="movie_release_date" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Trạng thái phát hành</label>
                            <select name="status" id="movie_status" class="form-select">
                                <option value="showing">Đang chiếu (Hiển thị bán vé)</option>
                                <option value="upcoming">Sắp chiếu (Hiển thị teaser)</option>
                                <option value="hidden">Ẩn (Tạm ngưng)</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">URL Poster (Ảnh bìa)</label>
                            <input type="url" name="poster_url" id="movie_poster_url" class="form-control" placeholder="https://example.com/poster.jpg">
                            <small class="text-muted">Hoặc có thể tải ảnh lên ở bên dưới</small>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Tải lên Poster từ máy tính</label>
                            <input type="file" name="poster_file" id="movie_poster_file" class="form-control" accept="image/*">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Đường dẫn Trailer (YouTube URL)</label>
                            <input type="url" name="trailer_url" id="movie_trailer_url" class="form-control" placeholder="https://www.youtube.com/watch?v=...">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Mô tả tóm tắt nội dung phim</label>
                            <textarea name="description" id="movie_description" class="form-control" rows="4" placeholder="Nhập tóm tắt cốt truyện, diễn viên, đạo diễn..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold" id="movieSubmitBtn">Lưu Phim</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddMovieModal() {
    document.getElementById('movieModalTitle').innerHTML = '<i class="fa-solid fa-film text-primary me-2"></i>Thêm Phim Mới';
    document.getElementById('movie_id').value = '0';
    document.getElementById('movie_title').value = '';
    document.getElementById('movie_duration').value = '120';
    document.getElementById('movie_release_date').value = '<?= date('Y-m-d') ?>';
    document.getElementById('movie_status').value = 'showing';
    document.getElementById('movie_poster_url').value = '';
    document.getElementById('movie_trailer_url').value = '';
    document.getElementById('movie_description').value = '';
    document.getElementById('movieSubmitBtn').innerText = 'Thêm Phim';
}

function openEditMovieModal(movie) {
    document.getElementById('movieModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square text-primary me-2"></i>Cập Nhật Phim';
    document.getElementById('movie_id').value = movie.id;
    document.getElementById('movie_title').value = movie.title || '';
    document.getElementById('movie_duration').value = movie.duration || 120;
    document.getElementById('movie_release_date').value = movie.release_date || '';
    document.getElementById('movie_status').value = movie.status || 'showing';
    document.getElementById('movie_poster_url').value = movie.poster || '';
    document.getElementById('movie_trailer_url').value = movie.trailer_url || '';
    document.getElementById('movie_description').value = movie.description || '';
    document.getElementById('movieSubmitBtn').innerText = 'Lưu Thay Đổi';

    const modal = new bootstrap.Modal(document.getElementById('movieModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
