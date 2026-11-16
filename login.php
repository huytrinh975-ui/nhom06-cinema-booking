<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: profile.php', true, 303);
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if ($email === '' || $password === '') {
        $error = 'Vui lòng nhập email và mật khẩu.';
    } else {
        $stmt = $conn->prepare("SELECT id, fullname, password FROM users WHERE email = ? AND role = 'customer' AND status = 'active'");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            unset($_SESSION['csrf_token'], $_SESSION['kiet_demo_customer_id'], $_SESSION['cbp_payment_keys'], $_SESSION['cbp_demo_nonce'], $_SESSION['cbp_csrf']);
            $_SESSION['customer_user'] = ['id' => (int) $user['id']];
            setFlash('success', 'Đăng nhập thành công. Xin chào ' . $user['fullname'] . '!');
            header('Location: profile.php', true, 303);
            exit;
        }
        $error = 'Email hoặc mật khẩu chưa chính xác.';
    }
}

$pageTitle = 'Đăng nhập';
require_once __DIR__ . '/includes/header.php';
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
<?php require_once __DIR__ . '/includes/footer.php'; ?>
