<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Chuyen huong neu da dang nhap
if (isLoggedIn()) {
    header('Location: cinema booking web/index.php', true, 303);
    exit;
}

if (!empty($_SESSION['admin_user_id'])) {
    header('Location: admin/index.php', true, 303);
    exit;
}

$error = '';
$contact = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrfToken();

    $contact = is_string($_POST['contact'] ?? null)
        ? trim($_POST['contact'])
        : '';

    $password = is_string($_POST['password'] ?? null)
        ? $_POST['password']
        : '';

    if ($contact === '' || $password === '') {
        $error = 'Vui lòng nhập email hoặc số điện thoại và mật khẩu.';
    } else {
        try {
            // LUU Y: Database su dung full_name, khong phai fullname
            $stmt = $conn->prepare(
                "SELECT id, full_name AS fullname, password, role
                 FROM users
                 WHERE (email = ? OR phone = ?)
                 AND role IN ('customer', 'admin')
                 LIMIT 1"
            );

            if (!$stmt) {
                throw new RuntimeException($conn->error);
            }

            $stmt->bind_param('ss', $contact, $contact);

            if (!$stmt->execute()) {
                throw new RuntimeException($stmt->error);
            }

            $result = $stmt->get_result();
            $user = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if (!$user || !password_verify($password, $user['password'])) {
                $error = 'Email, số điện thoại hoặc mật khẩu không chính xác.';
            } else {
                session_regenerate_id(true);

                // Xoa phien dang nhap cu
                unset(
                    $_SESSION['customer_user'],
                    $_SESSION['admin_user'],
                    $_SESSION['admin_user_id'],
                    $_SESSION['kiet_demo_customer_id'],
                    $_SESSION['cbp_payment_keys'],
                    $_SESSION['cbp_demo_nonce'],
                    $_SESSION['cbp_csrf']
                );

                if ($user['role'] === 'admin') {
                    $_SESSION['admin_user_id'] = (int)$user['id'];

                    header('Location: admin/index.php', true, 303);
                    exit;
                }

                if ($user['role'] === 'customer') {
                    $_SESSION['customer_user'] = [
                        'id' => (int)$user['id']
                    ];

                    setFlash(
                        'success',
                        'Đăng nhập thành công! Chào mừng bạn đến với CINEBOOK.'
                    );

                    header('Location: cinema booking web/index.php', true, 303);
                    exit;
                }

                $error = 'Vai trò tài khoản không hợp lệ.';
            }

        } catch (Throwable $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'Hệ thống đang gặp lỗi. Vui lòng thử lại sau.';
        }
    }
}

$bodyClass = "cinebook-login";
$pageCss = ["cinebook-login.css"];
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
            Đăng nhập để tiếp tục hành trình
            cùng <strong>CINEBOOK</strong>.
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

        <form method="post" action="login.php" class="form-stack">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrfToken()) ?>"
            >

            <label>
                Email hoặc số điện thoại

                <input
                    type="text"
                    name="contact"
                    autocomplete="username"
                    placeholder="Nhập email hoặc số điện thoại"
                    value="<?= e($contact) ?>"
                    maxlength="100"
                    required
                >
            </label>

            <label>
                Mật khẩu

                <input
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    placeholder="Nhập mật khẩu"
                    required
                >
            </label>

            <button class="btn btn-primary btn-wide" type="submit">
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