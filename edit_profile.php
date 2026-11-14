<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
requireLogin();

$userId = (int)$_SESSION['user_id'];
$stmt = $conn->prepare('SELECT fullname, fullname AS full_name, email, phone FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$user) { header('Location: logout.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $fullname = trim($_POST['fullname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($fullname === '' || $phone === '') {
        $error = 'Vui lòng nhập họ tên và số điện thoại.';
    } elseif (mb_strlen($fullname) > 100) {
        $error = 'Họ tên không được vượt quá 100 ký tự.';
    } elseif (!preg_match('/^[0-9+\s().-]{8,20}$/', $phone)) {
        $error = 'Số điện thoại không hợp lệ.';
    } else {
        $update = $conn->prepare('UPDATE users SET fullname = ?, phone = ? WHERE id = ?');
        $update->bind_param('ssi', $fullname, $phone, $userId);
        $update->execute();
        $update->close();
        $_SESSION['full_name'] = $fullname;
        $_SESSION['fullname'] = $fullname;
        setFlash('success', 'Thông tin cá nhân đã được cập nhật.');
        header('Location: profile.php');
        exit;
    }
    $user['fullname'] = $fullname;
    $user['full_name'] = $fullname;
    $user['phone'] = $phone;
}

$pageTitle = 'Chỉnh sửa hồ sơ';
require_once 'includes/header.php';
?>
<section class="container page-section narrow-page">
    <a class="back-link" href="profile.php">← Quay lại hồ sơ</a>
    <div class="form-heading"><div class="section-kicker">EDIT YOUR PROFILE</div><h1>Chỉnh sửa thông tin</h1><p>Cập nhật thông tin liên hệ của bạn.</p></div>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <div class="edit-card">
        <form method="post" action="edit_profile.php" class="form-stack">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <label>Họ và tên<input name="fullname" type="text" maxlength="100" value="<?= e($user['fullname']) ?>" required></label>
            <label>Email<input type="email" value="<?= e($user['email']) ?>" disabled><small class="field-help">Email đăng nhập hiện chưa thể thay đổi.</small></label>
            <label>Số điện thoại<input name="phone" type="tel" maxlength="20" value="<?= e($user['phone']) ?>" required></label>
            <div class="form-actions"><a class="btn btn-outline" href="profile.php">Hủy</a><button class="btn btn-primary" type="submit">Lưu thay đổi <span>→</span></button></div>
        </form>
    </div>
</section>
<?php require_once 'includes/footer.php'; ?>
