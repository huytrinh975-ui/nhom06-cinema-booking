<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: profile.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Vui lòng nhập email và mật khẩu.';
    } else {
        $stmt = $conn->prepare('SELECT id, fullname, email, password, role FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['full_name'] = $user['fullname'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            setFlash('success', 'Đăng nhập thành công. Xin chào ' . $user['fullname'] . '!');
            header('Location: profile.php');
            exit;
        }
        $error = 'Email hoặc mật khẩu chưa chính xác.';
    }
}

$pageTitle = 'Đăng nhập';
require_once 'includes/header.php';
?>
<section class="auth-section auth-login">
    <div class="auth-aside">
        <div class="aside-label">WELCOME BACK</div>
        <h1>Ánh đèn<br>sắp bật.<br><em>Bạn đã sẵn sàng?</em></h1>
        <p>Đăng nhập để tiếp tục hành trình cùng MovieBooking.</p>
        <div class="aside-stamp">M<br><small>MB CLUB</small></div>
    </div>
    <div class="auth-panel">
        <div class="form-heading">
            <div class="section-kicker">MEMBER SIGN IN</div>
            <h2>Chào mừng trở lại</h2>
            <p>Đăng nhập bằng tài khoản của bạn.</p>
        </div>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="login.php" class="form-stack">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <label>Email<input name="email" type="email" autocomplete="username" placeholder="you@example.com" value="<?= e($email) ?>" required></label>
            <label>Mật khẩu<input name="password" type="password" autocomplete="current-password" placeholder="Nhập mật khẩu" required></label>
            <button class="btn btn-primary btn-wide" type="submit">Đăng nhập <span>→</span></button>
        </form>
        <p class="form-switch">Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></p>
    </div>
</section>
<?php require_once 'includes/footer.php'; ?>
