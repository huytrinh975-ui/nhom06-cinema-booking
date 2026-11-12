<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
requireLogin();

$stmt = $conn->prepare('SELECT id, full_name, email, phone, role, created_at FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

$pageTitle = 'Tài khoản của tôi';
require_once 'includes/header.php';
?>
<section class="container page-section">
    <div class="page-heading"><div><div class="section-kicker">YOUR ACCOUNT</div><h1>Hồ sơ cá nhân</h1><p>Quản lý thông tin tài khoản MovieBooking của bạn.</p></div><span class="profile-badge"><?= $user['role'] === 'admin' ? 'ADMIN' : 'MEMBER' ?></span></div>
    <div class="profile-layout">
        <aside class="profile-side">
            <div class="avatar"><?= e(mb_strtoupper(mb_substr($user['full_name'], 0, 1))) ?></div>
            <h2><?= e($user['full_name']) ?></h2>
            <p><?= e($user['email']) ?></p>
            <span class="status-pill"><i></i> Tài khoản hoạt động</span>
            <a class="btn btn-primary btn-wide" href="edit_profile.php">Chỉnh sửa hồ sơ</a>
            <form action="logout.php" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><button class="btn btn-outline btn-wide" type="submit">Đăng xuất</button></form>
        </aside>
        <div class="profile-details">
            <div class="detail-heading"><h2>Thông tin tài khoản</h2><span>PERSONAL INFORMATION</span></div>
            <div class="detail-row"><span>Họ và tên</span><strong><?= e($user['full_name']) ?></strong></div>
            <div class="detail-row"><span>Địa chỉ email</span><strong><?= e($user['email']) ?></strong></div>
            <div class="detail-row"><span>Số điện thoại</span><strong><?= e($user['phone']) ?></strong></div>
            <div class="detail-row"><span>Loại tài khoản</span><strong><?= $user['role'] === 'admin' ? 'Quản trị viên' : 'Khách hàng' ?></strong></div>
            <div class="detail-row"><span>Ngày tham gia</span><strong><?= e(date('d/m/Y', strtotime($user['created_at']))) ?></strong></div>
            <div class="notice">Email hiện được dùng làm thông tin đăng nhập nên chưa hỗ trợ chỉnh sửa tại trang hồ sơ.</div>
        </div>
    </div>
</section>
<?php require_once 'includes/footer.php'; ?>
