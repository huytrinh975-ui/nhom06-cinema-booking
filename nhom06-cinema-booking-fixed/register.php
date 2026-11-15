
<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Nếu đã đăng nhập thì chuyển đến trang hồ sơ.
if (isLoggedIn()) {
    header('Location: profile.php');
    exit;
}

// Khởi tạo dữ liệu.
$error = '';
$fullname = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Kiểm tra CSRF.
    verifyCsrfToken();

    // Nhận và chuẩn hóa dữ liệu.
    $fullname = trim($_POST['fullname'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Kiểm tra họ tên.
    if ($fullname === '') {
        $error = 'Vui lòng nhập họ và tên.';
    } elseif (mb_strlen($fullname, 'UTF-8') > 100) {
        $error = 'Họ và tên không được vượt quá 100 ký tự.';

    // Kiểm tra email.
    } elseif (
        $email === '' ||
        strlen($email) > 150 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $error = 'Vui lòng nhập địa chỉ email hợp lệ.';

    // Kiểm tra số điện thoại.
    } elseif (
        $phone !== '' &&
        !preg_match('/^[0-9+\s().-]{8,20}$/', $phone)
    ) {
        $error = 'Số điện thoại không hợp lệ.';

    // Kiểm tra mật khẩu.
    } elseif (strlen($password) < 8) {
        $error = 'Mật khẩu phải có ít nhất 8 ký tự.';
    } elseif ($password !== $confirm) {
        $error = 'Mật khẩu xác nhận không khớp.';

    } else {
        try {
            // Kiểm tra email đã tồn tại.
            $check = $pdo->prepare(
                'SELECT id FROM users WHERE email = :email LIMIT 1'
            );

            $check->execute([
                'email' => $email
            ]);

            if ($check->fetch()) {
                $error = 'Email này đã được sử dụng. Vui lòng dùng email khác.';

            } else {
                // Mã hóa mật khẩu.
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Thêm tài khoản mới.
                // Đồng bộ với cấu trúc bảng users hiện tại.
                $insert = $pdo->prepare(
                    "INSERT INTO users
                    (full_name, email, phone, password, role)
                    VALUES
                    (:full_name, :email, :phone, :password, 'customer')"
                );

                $insert->execute([
                    'full_name' => $fullname,
                    'email' => $email,
                    'phone' => $phone !== '' ? $phone : null,
                    'password' => $hashedPassword
                ]);

                // Thông báo và chuyển đến đăng nhập.
                setFlash(
                    'success',
                    'Đăng ký thành công. Hãy đăng nhập để tiếp tục.'
                );

                header('Location: login.php');
                exit;
            }

        } catch (PDOException $e) {
            error_log('[CINEBOOK register] ' . $e->getMessage());

            // Xử lý trường hợp email bị trùng do hai yêu cầu đồng thời.
            if ($e->getCode() === '23000') {
                $error = 'Email hoặc dữ liệu đăng ký đã tồn tại.';
            } else {
                $error = 'Không thể tạo tài khoản. Vui lòng thử lại.';
            }

        } catch (Throwable $e) {
            error_log('[CINEBOOK register] ' . $e->getMessage());

            $error = 'Đã xảy ra lỗi. Vui lòng thử lại sau.';
        }
    }
}

$pageTitle = 'Đăng ký';

require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section">

    <div class="auth-aside">

        <div class="aside-label">
            JOIN THE EXPERIENCE
        </div>

        <h1>
            Khởi đầu<br>
            một hành trình<br>
            <em>điện ảnh.</em>
        </h1>

        <p>
            Tạo tài khoản MovieBooking để quản lý hồ sơ cá nhân
            nhanh chóng và thuận tiện.
        </p>

        <div class="aside-stamp">
            M<br>
            <small>MB CLUB</small>
        </div>

    </div>

    <div class="auth-panel">

        <div class="form-heading">

            <div class="section-kicker">
                CREATE ACCOUNT
            </div>

            <h2>Tạo tài khoản</h2>

            <p>
                Đăng ký bằng email và thông tin liên hệ của bạn.
            </p>

        </div>

        <?php if ($error !== ''): ?>

            <div class="alert alert-error" role="alert">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <form
            method="POST"
            action="register.php"
            class="form-stack"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrfToken()) ?>"
            >

            <label>
                Họ và tên

                <input
                    name="fullname"
                    type="text"
                    maxlength="100"
                    autocomplete="name"
                    placeholder="Nguyễn Văn A"
                    value="<?= e($fullname) ?>"
                    required
                >
            </label>

            <label>
                Email

                <input
                    name="email"
                    type="email"
                    maxlength="150"
                    autocomplete="email"
                    placeholder="you@example.com"
                    value="<?= e($email) ?>"
                    required
                >
            </label>

            <label>
                Số điện thoại (không bắt buộc)

                <input
                    name="phone"
                    type="tel"
                    maxlength="20"
                    autocomplete="tel"
                    placeholder="09xxxxxxxx"
                    value="<?= e($phone) ?>"
                >
            </label>

            <div class="form-two">

                <label>
                    Mật khẩu

                    <input
                        name="password"
                        type="password"
                        minlength="8"
                        autocomplete="new-password"
                        placeholder="Ít nhất 8 ký tự"
                        required
                    >
                </label>

                <label>
                    Nhập lại mật khẩu

                    <input
                        name="confirm_password"
                        type="password"
                        minlength="8"
                        autocomplete="new-password"
                        placeholder="Nhập lại mật khẩu"
                        required
                    >
                </label>

            </div>

            <button
                class="btn btn-primary btn-wide"
                type="submit"
            >
                Đăng ký ngay <span>→</span>
            </button>

        </form>

        <p class="form-switch">
            Đã có tài khoản?
            <a href="login.php">Đăng nhập</a>
        </p>

    </div>

</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
