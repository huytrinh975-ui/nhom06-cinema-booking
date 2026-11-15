
<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: profile.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Vui lòng nhập email và mật khẩu.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không đúng định dạng.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'SELECT id, full_name, email, password, role
                 FROM users
                 WHERE email = :email
                 LIMIT 1'
            );

            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'Email không tồn tại hoặc chưa được đăng ký.';
            } else {
                $storedPassword = (string) $user['password'];

                $passwordValid = password_verify(
                    $password,
                    $storedPassword
                );

                $passwordInfo = password_get_info($storedPassword);

                // Hỗ trợ tài khoản cũ lưu mật khẩu dạng chữ thường.
                if (
                    !$passwordValid &&
                    ($passwordInfo['algoName'] ?? 'unknown') === 'unknown' &&
                    hash_equals($storedPassword, $password)
                ) {
                    $passwordValid = true;
                }

                if (!$passwordValid) {
                    $error = 'Mật khẩu không chính xác, vui lòng thử lại.';
                } else {
                    // Mã hóa lại mật khẩu cũ nếu cần.
                    if (
                        ($passwordInfo['algoName'] ?? 'unknown') === 'unknown' ||
                        password_needs_rehash($storedPassword, PASSWORD_DEFAULT)
                    ) {
                        $newHash = password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );

                        $update = $pdo->prepare(
                            'UPDATE users SET password = :password WHERE id = :id'
                        );

                        $update->execute([
                            'password' => $newHash,
                            'id' => $user['id']
                        ]);
                    }

                    session_regenerate_id(true);

                    $_SESSION['user_id'] = (int) $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['fullname'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];

                    // Tương thích với module khách hàng.
                    if ($user['role'] === 'customer') {
                        $_SESSION['customer_user'] = [
                            'id' => (int) $user['id'],
                            'fullname' => $user['full_name'],
                            'email' => $user['email']
                        ];
                    } else {
                        unset($_SESSION['customer_user']);
                    }

                    setFlash(
                        'success',
                        'Đăng nhập thành công. Xin chào ' .
                        $user['full_name'] . '!'
                    );

                    header('Location: profile.php');
                    exit;
                }
            }
        } catch (Throwable $e) {
            error_log('[CINEBOOK login] ' . $e->getMessage());
            $error = 'Không thể xử lý đăng nhập lúc này. Vui lòng thử lại.';
        }
    }
}

$pageTitle = 'Đăng nhập';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section auth-login">

    <div class="auth-aside">
        <div class="aside-label">WELCOME BACK</div>

        <h1>
            Ánh đèn<br>
            sắp bật.<br>
            <em>Bạn đã sẵn sàng?</em>
        </h1>

        <p>
            Đăng nhập để tiếp tục hành trình cùng MovieBooking.
        </p>

        <div class="aside-stamp">
            M<br>
            <small>MB CLUB</small>
        </div>
    </div>

    <div class="auth-panel">

        <div class="form-heading">
            <div class="section-kicker">MEMBER SIGN IN</div>
            <h2>Chào mừng trở lại</h2>
            <p>Đăng nhập bằng tài khoản của bạn.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error" role="alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="form-stack">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrfToken()) ?>"
            >

            <label>
                Email
                <input
                    name="email"
                    type="email"
                    autocomplete="username"
                    placeholder="you@example.com"
                    value="<?= e($email) ?>"
                    maxlength="150"
                    required
                >
            </label>

            <label>
                Mật khẩu
                <input
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    placeholder="Nhập mật khẩu"
                    required
                >
            </label>

            <button
                class="btn btn-primary btn-wide"
                type="submit"
            >
                Đăng nhập <span>→</span>
            </button>

        </form>

        <p class="form-switch">
            Chưa có tài khoản?
            <a href="register.php">Đăng ký ngay</a>
        </p>

    </div>

</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
