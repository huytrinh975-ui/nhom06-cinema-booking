```php
<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Nếu đã đăng nhập thì chuyển đến trang cá nhân
if (isLoggedIn()) {
    header('Location: profile.php');
    exit;
}

$error = '';
$email = '';

// Xử lý khi gửi form đăng nhập
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Kiểm tra CSRF
    verifyCsrfToken();

    // Nhận dữ liệu
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    // Kiểm tra email trống
    if ($email === '') {
        $error = 'Vui lòng nhập email.';

    // Kiểm tra mật khẩu trống
    } elseif ($password === '') {
        $error = 'Vui lòng nhập mật khẩu.';

    // Kiểm tra định dạng email
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không đúng định dạng.';

    } else {
        try {
            // Tìm tài khoản trong Database
            $stmt = $conn->prepare(
                'SELECT id, full_name, email, password, role
                 FROM users
                 WHERE email = ?
                 LIMIT 1'
            );

            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Trường hợp 1: Không tìm thấy email
            if (!$user) {
                $error = 'Email không tồn tại hoặc chưa được đăng ký.';

            } else {

                $storedPassword = (string) $user['password'];

                // Kiểm tra mật khẩu đã mã hóa
                $passwordValid = password_verify(
                    $password,
                    $storedPassword
                );

                // Hỗ trợ tài khoản cũ đang lưu mật khẩu dạng chữ thường
                // Nếu đăng nhập đúng, sẽ mã hóa lại ngay lập tức
                $passwordInfo = password_get_info($storedPassword);

                if (
                    !$passwordValid &&
                    $passwordInfo['algoName'] === 'unknown' &&
                    hash_equals($storedPassword, $password)
                ) {
                    $passwordValid = true;
                }

                // Trường hợp 2: Mật khẩu không chính xác
                if (!$passwordValid) {
                    $error = 'Mật khẩu không chính xác, vui lòng thử lại.';

                } else {

                    // Tạo session mới
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = (int) $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];

                    // Tạo hash mới nếu mật khẩu cũ chưa được mã hóa
                    // hoặc thuật toán mã hóa cần nâng cấp
                    if (
                        $passwordInfo['algoName'] === 'unknown' ||
                        password_needs_rehash(
                            $storedPassword,
                            PASSWORD_DEFAULT
                        )
                    ) {
                        $newHash = password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );

                        if ($newHash === false) {
                            throw new RuntimeException(
                                'Không thể mã hóa mật khẩu.'
                            );
                        }

                        $update = $conn->prepare(
                            'UPDATE users
                             SET password = ?
                             WHERE id = ?'
                        );

                        $update->execute([
                            $newHash,
                            $user['id']
                        ]);
                    }

                    // Thông báo thành công
                    setFlash(
                        'success',
                        'Đăng nhập thành công. Xin chào ' .
                        $user['full_name'] . '!'
                    );

                    // Chuyển đến trang cá nhân
                    header('Location: profile.php');
                    exit;
                }
            }

        } catch (PDOException $e) {
            error_log('Login database error: ' . $e->getMessage());

            $error = 'Có lỗi kết nối Database. Vui lòng thử lại.';

        } catch (RuntimeException $e) {
            error_log('Login password error: ' . $e->getMessage());

            $error = 'Không thể xử lý mật khẩu. Vui lòng thử lại.';
        }
    }
}

// Tiêu đề trang
$pageTitle = 'Đăng nhập';

// Nạp header sau khi xử lý PHP
require_once 'includes/header.php';
?>

<section class="auth-section auth-login">

    <!-- Khu vực giới thiệu -->
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

    <!-- Khu vực đăng nhập -->
    <div class="auth-panel">

        <div class="form-heading">

            <div class="section-kicker">
                MEMBER SIGN IN
            </div>

            <h2>Chào mừng trở lại</h2>

            <p>Đăng nhập bằng tài khoản của bạn.</p>

        </div>

        <!-- Hiển thị thông báo lỗi -->
        <?php if ($error !== ''): ?>

            <div class="alert alert-error" role="alert">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <!-- Form đăng nhập -->
        <form method="POST" action="login.php" class="form-stack">

            <!-- CSRF Token -->
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrfToken()) ?>"
            >

            <!-- Email -->
            <label>
                Email

                <input
                    name="email"
                    type="email"
                    autocomplete="username"
                    placeholder="you@example.com"
                    value="<?= e($email) ?>"
                    maxlength="255"
                    required
                >
            </label>

            <!-- Mật khẩu -->
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

            <!-- Nút đăng nhập -->
            <button
                class="btn btn-primary btn-wide"
                type="submit"
            >
                Đăng nhập <span>→</span>
            </button>

        </form>

        <!-- Chuyển sang đăng ký -->
        <p class="form-switch">
            Chưa có tài khoản?
            <a href="register.php">Đăng ký ngay</a>
        </p>

    </div>

</section>

<?php require_once 'includes/footer.php'; ?>
```