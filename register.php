<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (isLoggedIn()) { header('Location: profile.php'); exit; }

$error = '';
$fullname = $email = $phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($fullname === '' || $email === '' || $phone === '' || $password === '' || $confirm === '') {
        $error = 'Vui lòng điền đầy đủ các trường thông tin.';
    } elseif (mb_strlen($fullname) > 100) {
        $error = 'Họ tên không được vượt quá 100 ký tự.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Địa chỉ email không hợp lệ.';
    } elseif (!preg_match('/^[0-9+\s().-]{8,20}$/', $phone)) {
        $error = 'Số điện thoại không hợp lệ.';
    } elseif (strlen($password) < 8) {
        $error = 'Mật khẩu cần có ít nhất 8 ký tự.';
    } elseif ($password !== $confirm) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } else {
        $check = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'Email này đã được sử dụng. Vui lòng đăng nhập hoặc dùng email khác.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, 'customer')");
            $insert->execute([$fullname, $email, $phone, $hash]);
            setFlash('success', 'Đăng ký thành công. Hãy đăng nhập để tiếp tục.');
            header('Location: login.php');
            exit;
        }
    }
}

$pageTitle = 'Đăng ký';
require_once 'includes/header.php';
?>
<section class="auth-section">
    <div class="auth-aside">
        <div class="aside-label">JOIN THE EXPERIENCE</div>
        <h1>Khởi đầu<br>một hành trình<br><em>điện ảnh.</em></h1>
        <p>Tạo tài khoản MovieBooking để quản lý hồ sơ cá nhân nhanh chóng và thuận tiện.</p>
        <div class="aside-stamp">M<br><small>MB CLUB</small></div>
    </div>
    <div class="auth-panel">
        <div class="form-heading"><div class="section-kicker">CREATE ACCOUNT</div><h2>Tạo tài khoản</h2><p>Điền thông tin bên dưới để đăng ký.</p></div>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="form-stack" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <label>Họ và tên<input name="fullname" type="text" maxlength="100" autocomplete="name" placeholder="Nguyễn Văn A" value="<?= e($fullname) ?>" required></label>
            <label>Email<input name="email" type="email" maxlength="150" autocomplete="email" placeholder="you@example.com" value="<?= e($email) ?>" required></label>
            <label>Số điện thoại<input name="phone" type="tel" maxlength="20" autocomplete="tel" placeholder="09xxxxxxxx" value="<?= e($phone) ?>" required></label>
            <div class="form-two">
                <label>Mật khẩu<input name="password" type="password" autocomplete="new-password" placeholder="Ít nhất 8 ký tự" required></label>
                <label>Nhập lại mật khẩu<input name="confirm_password" type="password" autocomplete="new-password" placeholder="Nhập lại" required></label>
            </div>
            <button class="btn btn-primary btn-wide" type="submit">Đăng ký ngay <span>→</span></button>
        </form>
        <p class="form-switch">Đã có tài khoản? <a href="login.php">Đăng nhập</a></p>
    </div>
</section>
<?php require_once 'includes/footer.php'; ?>
