<?php
/**
 * Quản lý Tài Khoản Khách Hàng & Phân Quyền
 * File: admin/users.php
 */
$page_title = 'Quản Lý Người Dùng & Khách Hàng';
require_once __DIR__ . '/auth.php';

// Xử lý các thao tác POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Thêm hoặc Sửa người dùng
    if ($action === 'save_user') {
        $id = (int)($_POST['id'] ?? 0);
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? 'customer';
        $status = $_POST['status'] ?? 'active';
        $password = trim($_POST['password'] ?? '');

        if ($id === (int)$current_user['id'] && ($role !== 'admin' || $status !== 'active')) {
            $_SESSION['flash_error'] = 'Bạn không thể tự khóa hoặc hạ quyền tài khoản đang đăng nhập.';
        } elseif ($id <= 0 && $role === 'admin' && strlen($password) < 8) {
            $_SESSION['flash_error'] = 'Mật khẩu quản trị mới cần có ít nhất 8 ký tự.';
        } elseif ($fullname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || !in_array($role, ['admin', 'customer'], true) || !in_array($status, ['active', 'locked'], true)) {
            $_SESSION['flash_error'] = 'Vui lòng nhập họ tên và email hợp lệ!';
        } else {
            if ($id > 0) {
                // Kiểm tra trùng email (ngoại trừ chính user này)
                $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $chk->bind_param("si", $email, $id);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) {
                    $_SESSION['flash_error'] = 'Email này đã được sử dụng bởi một tài khoản khác!';
                } else {
                    if (!empty($password)) {
                        $hashed = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, phone = ?, role = ?, status = ?, password = ? WHERE id = ?");
                        $stmt->bind_param("ssssssi", $fullname, $email, $phone, $role, $status, $hashed, $id);
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, phone = ?, role = ?, status = ? WHERE id = ?");
                        $stmt->bind_param("sssssi", $fullname, $email, $phone, $role, $status, $id);
                    }
                    $stmt->execute();
                    $stmt->close();
                    $_SESSION['flash_success'] = 'Cập nhật tài khoản người dùng thành công!';
                }
                $chk->close();
            } else {
                // Thêm mới tài khoản
                $chk = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $chk->bind_param("s", $email);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) {
                    $_SESSION['flash_error'] = 'Email này đã tồn tại trong hệ thống!';
                } else {
                    $passToHash = !empty($password) ? $password : '123456';
                    $hashed = password_hash($passToHash, PASSWORD_BCRYPT);
                    $stmt = $conn->prepare("INSERT INTO users (fullname, email, phone, role, status, password) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssss", $fullname, $email, $phone, $role, $status, $hashed);
                    $stmt->execute();
                    $stmt->close();
                    $_SESSION['flash_success'] = 'Thêm tài khoản người dùng mới thành công!';
                }
                $chk->close();
            }
        }
        admin_redirect('users.php');
    }

    // 2. Khóa hoặc Mở khóa tài khoản nhanh
    if ($action === 'toggle_status') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? 'active';

        // Không cho phép tự khóa chính tài khoản admin đang đăng nhập
        if ($userId === (int)$current_user['id'] && $newStatus === 'locked') {
            $_SESSION['flash_error'] = 'Bạn không thể tự khóa tài khoản quản trị của chính mình!';
        } else {
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $newStatus, $userId);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash_success'] = ($newStatus === 'locked' ? 'Đã khóa tài khoản!' : 'Đã mở khóa tài khoản thành công!');
        }
        admin_redirect('users.php');
    }
}

// Xử lý Xóa người dùng
if (isset($_POST['delete_id'])) {
    $delId = (int)$_POST['delete_id'];
    if ($delId === (int)$current_user['id']) {
        $_SESSION['flash_error'] = 'Bạn không thể xóa tài khoản của chính mình!';
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $delId);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = 'Xóa tài khoản thành công!';
        } else {
            $_SESSION['flash_error'] = 'Không thể xóa tài khoản này: ' . $conn->error;
        }
        $stmt->close();
    }
    admin_redirect('users.php');
}

// Lọc và Tìm kiếm
$search = trim($_GET['search'] ?? '');
$filter_role = $_GET['role'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';

// Thống kê người dùng
$statTotal = $conn->query("SELECT COUNT(*) AS cnt FROM users")->fetch_assoc()['cnt'];
$statCustomers = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE role = 'customer'")->fetch_assoc()['cnt'];
$statActive = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE status = 'active'")->fetch_assoc()['cnt'];
$statLocked = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE status = 'locked'")->fetch_assoc()['cnt'];

// Xây dựng câu truy vấn
$sql = "
    SELECT u.*, 
           (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id) AS total_bookings,
           (SELECT COALESCE(SUM(b.total_amount), 0) FROM bookings b WHERE b.user_id = u.id AND b.payment_status = 'paid') AS total_spent
    FROM users u
    WHERE 1=1
";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (u.fullname LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $like = "%" . $search . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

if ($filter_role !== 'all' && in_array($filter_role, ['admin', 'customer'])) {
    $sql .= " AND u.role = ?";
    $params[] = $filter_role;
    $types .= "s";
}

if ($filter_status !== 'all' && in_array($filter_status, ['active', 'locked'])) {
    $sql .= " AND u.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

$sql .= " ORDER BY u.id DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();
require_once __DIR__ . '/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h4 class="fw-bold mb-1">Quản lý Tài Khoản Người Dùng</h4>
        <p class="text-muted mb-0 small">Danh sách khách hàng, quản trị viên, lịch sử đặt vé và phân quyền bảo mật</p>
    </div>
    <button class="btn btn-primary rounded-pill px-3 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openAddUserModal()">
        <i class="fa-solid fa-user-plus"></i> Thêm Tài Khoản Mới
    </button>
</div>

<!-- 4 THẺ THỐNG KÊ -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card p-3 h-100">
            <div class="text-muted small">Tổng tài khoản</div>
            <div class="fw-bold fs-4 text-dark mt-1"><?= number_format($statTotal) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card p-3 h-100">
            <div class="text-muted small">Khách hàng thành viên</div>
            <div class="fw-bold fs-4 text-primary mt-1"><?= number_format($statCustomers) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card p-3 h-100">
            <div class="text-muted small">Đang hoạt động</div>
            <div class="fw-bold fs-4 text-success mt-1"><?= number_format($statActive) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card p-3 h-100">
            <div class="text-muted small">Đang bị khóa</div>
            <div class="fw-bold fs-4 text-danger mt-1"><?= number_format($statLocked) ?></div>
        </div>
    </div>
</div>

<!-- BỘ LỌC VÀ TÌM KIẾM -->
<div class="card p-3 mb-4">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-12 col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-white text-muted border-end-0"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Tìm theo tên, email, SĐT..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>

        <div class="col-12 col-md-3">
            <select name="role" class="form-select">
                <option value="all" <?= $filter_role === 'all' ? 'selected' : '' ?>>-- Vai trò: Tất cả --</option>
                <option value="customer" <?= $filter_role === 'customer' ? 'selected' : '' ?>>Khách hàng (Customer)</option>
                <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>Quản trị viên (Admin)</option>
            </select>
        </div>

        <div class="col-12 col-md-2">
            <select name="status" class="form-select">
                <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>-- Trạng thái: Tất cả --</option>
                <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Hoạt động</option>
                <option value="locked" <?= $filter_status === 'locked' ? 'selected' : '' ?>>Đang bị khóa</option>
            </select>
        </div>

        <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-secondary w-100">Lọc</button>
            <?php if (!empty($search) || $filter_role !== 'all' || $filter_status !== 'all'): ?>
                <a href="users.php" class="btn btn-outline-secondary" title="Hủy bộ lọc"><i class="fa-solid fa-rotate-left"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- DANH SÁCH NGƯỜI DÙNG -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-custom align-middle">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Người Dùng</th>
                    <th>Liên Hệ</th>
                    <th>Vai Trò</th>
                    <th>Đơn Đặt Vé</th>
                    <th>Trạng Thái</th>
                    <th>Ngày Tạo</th>
                    <th class="text-end" style="width: 170px;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users && $users->num_rows > 0): ?>
                    <?php while ($u = $users->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php 
                                $nameArr = explode(' ', trim($u['fullname']));
                                $initialLetter = strtoupper(mb_substr(end($nameArr), 0, 1, 'UTF-8'));
                                ?>
                                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white <?= $u['role'] === 'admin' ? 'bg-danger' : 'bg-primary' ?>" style="width: 38px; height: 38px; font-size: 0.85rem;">
                                    <?= $initialLetter ?>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold text-slate-800"><?= htmlspecialchars($u['fullname']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($u['email']) ?></div>
                            </td>
                            <td>
                                <div><i class="fa-solid fa-phone text-muted me-1 small"></i><?= htmlspecialchars($u['phone'] ?: 'Chưa cập nhật') ?></div>
                            </td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                        <i class="fa-solid fa-shield-halved me-1"></i>Quản trị viên
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">
                                        <i class="fa-solid fa-user me-1"></i>Khách hàng
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="fa-solid fa-ticket text-primary me-1"></i><?= $u['total_bookings'] ?> đơn
                                </span>
                                <?php if ($u['total_spent'] > 0): ?>
                                    <div class="text-success small fw-semibold mt-1"><?= format_currency($u['total_spent']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['status'] === 'active'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                        <i class="fa-solid fa-circle-check me-1"></i>Hoạt động
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                        <i class="fa-solid fa-lock me-1"></i>Bị khóa
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small">
                                <?= format_date($u['created_at']) ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <!-- Nút Khóa / Mở Khóa -->
                                    <form method="POST" class="d-inline">
                                        <?php admin_csrf_field(); ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= $u['status'] === 'active' ? 'locked' : 'active' ?>">
                                        <button type="submit" 
                                                class="btn <?= $u['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success' ?>" 
                                                title="<?= $u['status'] === 'active' ? 'Khóa tài khoản này' : 'Mở khóa tài khoản' ?>">
                                            <i class="fa-solid <?= $u['status'] === 'active' ? 'fa-lock' : 'fa-lock-open' ?>"></i>
                                        </button>
                                    </form>

                                    <!-- Nút Sửa -->
                                    <button class="btn btn-outline-primary" 
                                            onclick='openEditUserModal(<?= json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                            title="Sửa thông tin">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>

                                    <!-- Nút Xóa -->
                                    <form method="POST" class="d-inline">
                                        <?php admin_csrf_field(); ?>
                                        <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                data-confirm="Bạn có chắc chắn muốn xóa tài khoản '<?= htmlspecialchars(addslashes($u['fullname'])) ?>'?"
                                                title="Xóa tài khoản">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-users-slash fs-1 mb-2 d-block opacity-25"></i>
                            Không tìm thấy tài khoản người dùng nào.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL THÊM / SỬA TÀI KHOẢN -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <?php admin_csrf_field(); ?>
                <input type="hidden" name="action" value="save_user">
                <input type="hidden" name="id" id="modal_user_id" value="0">

                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="userModalTitle">Thêm Tài Khoản Mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Họ và tên <span class="text-danger">*</span></label>
                        <input type="text" name="fullname" id="modal_fullname" class="form-control" required placeholder="VD: Nguyễn Văn A">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Địa chỉ Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="modal_email" class="form-control" required placeholder="VD: email@example.com">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Số điện thoại</label>
                        <input type="text" name="phone" id="modal_phone" class="form-control" placeholder="VD: 0901234567">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Vai trò</label>
                            <select name="role" id="modal_role" class="form-select">
                                <option value="customer">Khách hàng</option>
                                <option value="admin">Quản trị viên</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Trạng thái</label>
                            <select name="status" id="modal_status" class="form-select">
                                <option value="active">Hoạt động</option>
                                <option value="locked">Bị khóa</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" id="passLabel">Mật khẩu</label>
                        <input type="password" name="password" id="modal_password" class="form-control" placeholder="Để trống nếu không đổi mật khẩu">
                        <small class="text-muted" id="passHelpText">Mặc định khi tạo mới là <code>123456</code> nếu để trống.</small>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold" id="userSubmitBtn">Lưu Tài Khoản</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddUserModal() {
    document.getElementById('userModalTitle').innerText = 'Thêm Tài Khoản Mới';
    document.getElementById('modal_user_id').value = '0';
    document.getElementById('modal_fullname').value = '';
    document.getElementById('modal_email').value = '';
    document.getElementById('modal_phone').value = '';
    document.getElementById('modal_role').value = 'customer';
    document.getElementById('modal_status').value = 'active';
    document.getElementById('modal_password').value = '';
    document.getElementById('passHelpText').innerText = 'Mặc định khi tạo mới là 123456 nếu để trống.';
    document.getElementById('userSubmitBtn').innerText = 'Thêm Tài Khoản';
}

function openEditUserModal(u) {
    document.getElementById('userModalTitle').innerText = 'Cập Nhật Tài Khoản';
    document.getElementById('modal_user_id').value = u.id;
    document.getElementById('modal_fullname').value = u.fullname;
    document.getElementById('modal_email').value = u.email;
    document.getElementById('modal_phone').value = u.phone || '';
    document.getElementById('modal_role').value = u.role;
    document.getElementById('modal_status').value = u.status;
    document.getElementById('modal_password').value = '';
    document.getElementById('passHelpText').innerText = 'Chỉ nhập nếu muốn đổi mật khẩu mới cho tài khoản này.';
    document.getElementById('userSubmitBtn').innerText = 'Lưu Thay Đổi';
    new bootstrap.Modal(document.getElementById('userModal')).show();
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
