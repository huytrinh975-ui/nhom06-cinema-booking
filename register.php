<<<<<<< HEAD

=======
>>>>>>> 149e6a191053cfc6574a646ed3f89a21c72c0d24
<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

<<<<<<< HEAD
// Nếu đã đăng nhập thì chuyển đến trang hồ sơ
if (isLoggedIn()) {
    header('Location: profile.php');
    exit;
}

// Khởi tạo biến
$error = '';
$fullname = '';
$contact = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Kiểm tra CSRF
    verifyCsrfToken();

    // Nhận dữ liệu từ form
    $fullname = trim($_POST['fullname'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Khởi tạo Email và số điện thoại
    $email = null;
    $phone = null;

    // 1. Kiểm tra họ tên
    if ($fullname === '') {
        $error = 'Vui lòng nhập họ và tên.';

    } elseif (mb_strlen($fullname, 'UTF-8') > 100) {
        $error = 'Họ và tên không được vượt quá 100 ký tự.';

    // 2. Kiểm tra thông tin Email hoặc số điện thoại
    } elseif ($contact === '') {
        $error = 'Vui lòng nhập Email hoặc số điện thoại.';

    } else {

        // Tự nhận diện Email hoặc số điện thoại
        if (filter_var(strtolower($contact), FILTER_VALIDATE_EMAIL)) {

            $email = strtolower($contact);

            if (strlen($email) > 150) {
                $error = 'Email không được vượt quá 150 ký tự.';
            }

        } elseif (strpos($contact, '@') !== false) {

            $error = 'Địa chỉ Email không hợp lệ.';

        } elseif (preg_match('/^[0-9+\s().-]{8,20}$/', $contact)) {

            $phone = $contact;

        } else {

            $error = 'Email hoặc số điện thoại không hợp lệ.';
        }
    }

    // 3. Kiểm tra mật khẩu
    if ($error === '') {

        if ($password === '') {
            $error = 'Vui lòng nhập mật khẩu.';

        } elseif (strlen($password) < 8) {
            $error = 'Mật khẩu phải có ít nhất 8 ký tự.';

        } elseif ($confirm === '') {
            $error = 'Vui lòng xác nhận mật khẩu.';

        } elseif ($password !== $confirm) {
            $error = 'Mật khẩu xác nhận không khớp.';
        }
    }

    // 4. Kiểm tra thông tin đã tồn tại
    if ($error === '') {

        try {

            if ($email !== null) {

                $check = $conn->prepare(
                    "SELECT id FROM users WHERE email = ? LIMIT 1"
                );

                $check->execute([$email]);

                if ($check->fetch(PDO::FETCH_ASSOC)) {
                    $error = 'Email này đã được sử dụng.';
                }

            } elseif ($phone !== null) {

                $check = $conn->prepare(
                    "SELECT id FROM users WHERE phone = ? LIMIT 1"
                );

                $check->execute([$phone]);

                if ($check->fetch(PDO::FETCH_ASSOC)) {
                    $error = 'Số điện thoại này đã được sử dụng.';
                }
            }

            // 5. Tạo tài khoản
            if ($error === '') {

                // Mã hóa mật khẩu
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                if ($hashedPassword === false) {
                    throw new RuntimeException(
                        'Không thể mã hóa mật khẩu.'
                    );
                }

                // Lưu vào Database
                $sql = "
                    INSERT INTO users
                    (full_name, email, phone, password, role)
                    VALUES (?, ?, ?, ?, 'customer')
                ";

                $insert = $conn->prepare($sql);

                $insert->execute([
                    $fullname,
                    $email,
                    $phone,
                    $hashedPassword
                ]);

                // Thông báo thành công
                setFlash(
                    'success',
                    'Đăng ký thành công. Hãy đăng nhập để tiếp tục.'
                );

                header('Location: login.php');
                exit;
            }

        } catch (PDOException $e) {

            error_log('Register database error: ' . $e->getMessage());

            if ($e->getCode() === '23000') {
                $error = 'Email hoặc số điện thoại đã được sử dụng.';
            } else {
                $error = 'Lỗi Database. Vui lòng kiểm tra lại thông tin.';
            }

        } catch (RuntimeException $e) {

            error_log('Register password error: ' . $e->getMessage());

            $error = 'Không thể tạo tài khoản. Vui lòng thử lại.';
=======
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
>>>>>>> 149e6a191053cfc6574a646ed3f89a21c72c0d24
        }
    }
}

<<<<<<< HEAD
// Tiêu đề trang
$pageTitle = 'Đăng ký';

require_once 'includes/header.php';
?>

<section class="auth-section">

    <!-- Khu vực giới thiệu -->
    <div class="auth-aside">

        <div class="aside-label">JOIN THE EXPERIENCE</div>

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

    <!-- Khu vực đăng ký -->
    <div class="auth-panel">

        <div class="form-heading">

            <div class="section-kicker">
                CREATE ACCOUNT
            </div>

            <h2>Tạo tài khoản</h2>

            <p>
                Đăng ký bằng Email hoặc số điện thoại của bạn.
            </p>

        </div>

        <!-- Hiển thị lỗi -->
        <?php if ($error !== ''): ?>

            <div class="alert alert-error" role="alert">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <!-- Form đăng ký -->
        <form method="POST" action="register.php" class="form-stack">

            <!-- CSRF Token -->
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrfToken()) ?>"
            >

            <!-- Họ và tên -->
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

            <!-- Email hoặc số điện thoại -->
            <label>
                Email hoặc số điện thoại

                <input
                    name="contact"
                    type="text"
                    maxlength="150"
                    autocomplete="username"
                    placeholder="Nhập Email hoặc số điện thoại"
                    value="<?= e($contact) ?>"
                    required
                >
            </label>

            <!-- Mật khẩu và xác nhận mật khẩu -->
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

            <!-- Nút đăng ký -->
            <button
                class="btn btn-primary btn-wide"
                type="submit"
            >
                Đăng ký ngay <span>→</span>
            </button>

        </form>

        <!-- Chuyển sang đăng nhập -->
        <p class="form-switch">
            Đã có tài khoản?
            <a href="login.php">Đăng nhập</a>
        </p>

    </div>

</section>

=======
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
>>>>>>> 149e6a191053cfc6574a646ed3f89a21c72c0d24
<?php require_once 'includes/footer.php'; ?>
