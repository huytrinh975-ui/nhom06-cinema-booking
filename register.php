
<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: profile.php');
    exit;
}

$error = '';
$fullname = '';
$contact = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $fullname = trim($_POST['fullname'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $email = null;
    $phone = null;

    if ($fullname === '' || $contact === '' || $password === '' || $confirm === '') {
        $error = 'Vui lòng nhập đầy đủ thông tin.';
    } elseif (mb_strlen($fullname) > 100) {
        $error = 'Họ tên không được vượt quá 100 ký tự.';
    } elseif (filter_var($contact, FILTER_VALIDATE_EMAIL) && strlen($contact) <= 100) {
        $email = strtolower($contact);
    } elseif (preg_match('/^0[0-9]{9}$/', $contact)) {
        $phone = $contact;
    } else {
        $error = 'Email hoặc số điện thoại không hợp lệ.';
    }

    if ($error === '') {
        if (strlen($password) < 8) {
            $error = 'Mật khẩu phải có ít nhất 8 ký tự.';
        } elseif (!preg_match('/[A-Za-z]/', $password) ||
                  !preg_match('/[0-9]/', $password)) {
            $error = 'Mật khẩu cần có cả chữ và số.';
        } elseif ($password !== $confirm) {
            $error = 'Hai mật khẩu không trùng khớp.';
        }
    }

    if ($error === '') {
        try {
            $check = $conn->prepare(
                "SELECT id FROM users
                 WHERE (email = ? AND ? IS NOT NULL)
                    OR (phone = ? AND ? IS NOT NULL)
                 LIMIT 1"
            );

            $check->bind_param(
                'ssss',
                $email,
                $email,
                $phone,
                $phone
            );

            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();

            if ($exists) {
                $error = 'Email hoặc số điện thoại đã được sử dụng.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $insert = $conn->prepare(
                    "INSERT INTO users
                     (full_name, email, phone, password, role)
                     VALUES (?, ?, ?, ?, 'customer')"
                );

                $insert->bind_param(
                    'ssss',
                    $fullname,
                    $email,
                    $phone,
                    $hash
                );

                $insert->execute();
                $insert->close();

                setFlash(
                    'success',
                    'Đăng ký thành công! Hãy đăng nhập để tiếp tục.'
                );

                header('Location: login.php');
                exit;
            }
        } catch (Throwable $e) {
            error_log('Register error: ' . $e->getMessage());
            $error = 'Không thể đăng ký tài khoản. Vui lòng kiểm tra lại.';
        }
    }
}

$bodyClass = 'cinebook-register';
$pageCss = ['cinebook-register.css'];
$pageTitle = 'Đăng ký CINEBOOK';
require_once __DIR__ . '/includes/header.php';
?>

<style>
.cine-register {
    --gold: #f3be6c;
    --gold-light: #ffe0a1;
    --bg: #131019;
    --muted: #b9b2bd;
    min-height: calc(100vh - 90px);
    padding: 44px 24px;
    background:
        radial-gradient(ellipse at 10% 85%, rgba(122, 22, 34, .38), transparent 45%),
        radial-gradient(ellipse at 90% 30%, rgba(167, 92, 32, .15), transparent 40%),
        #100e16;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-family: Arial, sans-serif;
}

.cine-register *,
.cine-register *::before,
.cine-register *::after {
    box-sizing: border-box;
}

.cine-register-card {
    width: min(1250px, 100%);
    display: grid;
    grid-template-columns: 45% 55%;
    border: 1px solid rgba(243, 190, 108, .25);
    border-radius: 28px;
    overflow: hidden;
    background: linear-gradient(125deg, #1b1520, #231b24);
    box-shadow: 0 28px 90px rgba(0, 0, 0, .45);
}

.cine-register-aside {
    position: relative;
    min-height: 690px;
    padding: 64px 55px;
    overflow: hidden;
    background:
        linear-gradient(155deg, rgba(76, 23, 31, .9), rgba(20, 16, 25, .9)),
        #2b1821;
}

.cine-register-aside::before {
    content: "";
    position: absolute;
    width: 350px;
    height: 350px;
    right: -150px;
    top: -170px;
    border: 25px dashed rgba(243, 190, 108, .22);
    border-radius: 50%;
    transform: rotate(-25deg);
}

.cine-register-aside::after {
    content: "🎟️";
    position: absolute;
    bottom: 20px;
    right: 40px;
    font-size: 150px;
    opacity: .35;
    transform: rotate(-15deg);
}

.cine-aside-content {
    position: relative;
    z-index: 1;
}

.cine-eyebrow {
    font-size: 12px;
    letter-spacing: 3px;
    font-weight: 800;
    color: var(--gold-light);
    margin-bottom: 30px;
}

.cine-register-aside h1 {
    font-size: clamp(35px, 4vw, 56px);
    line-height: 1.15;
    font-weight: 800;
    color: #fff;
    margin: 0 0 25px;
    letter-spacing: -1px;
}

.cine-register-aside h1 span {
    color: var(--gold);
}

.cine-register-aside p {
    color: #ded3d7;
    font-size: 16px;
    line-height: 1.75;
    max-width: 390px;
}

.cine-benefits {
    margin-top: 42px;
    display: grid;
    gap: 19px;
}

.cine-benefit {
    display: flex;
    align-items: center;
    gap: 14px;
    font-size: 15px;
    color: #f5ebee;
}

.cine-benefit-icon {
    width: 45px;
    height: 45px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    border-radius: 12px;
    background: rgba(243, 190, 108, .12);
    color: var(--gold);
    font-size: 22px;
}

.cine-register-panel {
    padding: 50px 52px;
    background:
        radial-gradient(circle at 100% 100%, rgba(133, 74, 39, .12), transparent 50%),
        rgba(19, 16, 25, .85);
}

.cine-panel-heading {
    display: flex;
    align-items: center;
    gap: 18px;
    margin-bottom: 30px;
}

.cine-heading-icon {
    width: 65px;
    height: 65px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    border-radius: 17px;
    color: var(--gold);
    border: 1px solid rgba(243, 190, 108, .45);
    background: rgba(243, 190, 108, .09);
    font-size: 29px;
}

.cine-panel-heading h2 {
    font-size: clamp(25px, 2.7vw, 34px);
    color: #fff;
    margin: 0 0 7px;
    font-weight: 800;
}

.cine-panel-heading h2 span {
    color: var(--gold);
}

.cine-panel-heading p {
    color: var(--muted);
    margin: 0;
    font-size: 14px;
}

.cine-field {
    margin-bottom: 23px;
}

.cine-field label {
    display: block;
    color: #f6f0f4;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 10px;
}

.cine-input-wrap {
    position: relative;
}

.cine-input-icon {
    position: absolute;
    top: 50%;
    left: 17px;
    transform: translateY(-50%);
    font-size: 18px;
    color: #bfb6c1;
    pointer-events: none;
}

.cine-input {
    display: block;
    width: 100%;
    min-height: 55px;
    padding: 14px 44px 14px 48px;
    border-radius: 13px;
    border: 1px solid #514650;
    background: rgba(255, 255, 255, .045);
    color: #fff;
    font-size: 14px;
    outline: none;
    transition: .2s ease;
}

.cine-input::placeholder {
    color: #a99fae;
}

.cine-input:focus {
    border-color: var(--gold);
    background: rgba(255, 255, 255, .07);
    box-shadow: 0 0 0 3px rgba(243, 190, 108, .12);
}

.cine-password-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.cine-toggle-pass {
    position: absolute;
    right: 9px;
    top: 50%;
    transform: translateY(-50%);
    border: 0;
    background: transparent;
    color: #c5bac8;
    cursor: pointer;
    padding: 10px;
    font-size: 17px;
}

.cine-toggle-pass:hover {
    color: var(--gold);
}

.cine-password-rules {
    padding: 15px 19px;
    margin: 2px 0 24px;
    border: 1px solid rgba(243, 190, 108, .18);
    background: rgba(255, 255, 255, .035);
    border-radius: 13px;
    display: grid;
    gap: 8px;
}

.cine-rule {
    color: #b9b2bd;
    font-size: 13px;
}

.cine-rule span {
    display: inline-block;
    width: 22px;
    color: #99836a;
    font-weight: 700;
}

.cine-rule.valid,
.cine-rule.valid span {
    color: #8ed5a5;
}

.cine-submit {
    width: 100%;
    min-height: 56px;
    border: 0;
    border-radius: 14px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 800;
    color: #21151a;
    background: linear-gradient(110deg, #ffe09a, #f1ac52);
    box-shadow: 0 9px 28px rgba(226, 153, 67, .18);
    transition: transform .2s, box-shadow .2s;
}

.cine-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 35px rgba(226, 153, 67, .3);
}

.cine-login-link {
    margin-top: 22px;
    text-align: center;
    color: #c5bcc9;
    font-size: 14px;
}

.cine-login-link a {
    color: var(--gold);
    text-decoration: none;
    font-weight: 700;
}

.cine-login-link a:hover {
    text-decoration: underline;
}

.cine-error {
    padding: 13px 16px;
    margin-bottom: 22px;
    border: 1px solid rgba(255, 110, 110, .4);
    border-radius: 12px;
    background: rgba(160, 32, 44, .16);
    color: #ffb8b8;
    font-size: 14px;
}

@media (max-width: 950px) {
    .cine-register-card {
        grid-template-columns: 1fr;
    }

    .cine-register-aside {
        min-height: auto;
        padding: 35px;
    }

    .cine-register-aside h1 {
        font-size: 35px;
    }

    .cine-benefits {
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin-top: 25px;
    }

    .cine-benefit {
        font-size: 12px;
    }

    .cine-register-panel {
        padding: 35px;
    }
}

@media (max-width: 600px) {
    .cine-register {
        padding: 18px 12px;
    }

    .cine-register-card {
        border-radius: 18px;
    }

    .cine-register-aside {
        padding: 28px 24px;
    }

    .cine-benefits {
        grid-template-columns: 1fr;
    }

    .cine-register-panel {
        padding: 30px 22px;
    }

    .cine-panel-heading {
        align-items: flex-start;
    }

    .cine-heading-icon {
        width: 48px;
        height: 48px;
    }

    .cine-password-grid {
        grid-template-columns: 1fr;
        gap: 0;
    }
}
</style>

<section class="cine-register">
    <div class="cine-register-card">

        <!-- BÊN TRÁI -->
        <aside class="cine-register-aside">
            <div class="cine-aside-content">
                <div class="cine-eyebrow">
                    JOIN THE CINEBOOK EXPERIENCE
                </div>

                <h1>
                    Đặt vé xem phim<br>
                    <span>dễ dàng hơn.</span>
                </h1>

                <p>
                    Tạo tài khoản CINEBOOK để quản lý vé,
                    lưu phim yêu thích và tận hưởng
                    trải nghiệm điện ảnh thuận tiện.
                </p>

                <div class="cine-benefits">
                    <div class="cine-benefit">
                        <div class="cine-benefit-icon">▦</div>
                        <span>Đặt vé nhanh chóng</span>
                    </div>

                    <div class="cine-benefit">
                        <div class="cine-benefit-icon">♡</div>
                        <span>Lưu phim yêu thích</span>
                    </div>

                    <div class="cine-benefit">
                        <div class="cine-benefit-icon">◇</div>
                        <span>Nhận ưu đãi riêng</span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- BÊN PHẢI -->
        <div class="cine-register-panel">
            <div class="cine-panel-heading">
                <div class="cine-heading-icon">♙</div>
                <div>
                    <h2>Tạo tài khoản <span>CINEBOOK</span></h2>
                    <p>Bắt đầu hành trình điện ảnh chỉ với vài bước.</p>
                </div>
            </div>

            <?php if ($error !== ''): ?>
                <div class="cine-error" role="alert">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" id="registerForm">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrfToken()) ?>"
                >

                <!-- HỌ TÊN -->
                <div class="cine-field">
                    <label for="fullname">Họ và tên</label>
                    <div class="cine-input-wrap">
                        <span class="cine-input-icon">♙</span>
                        <input
                            class="cine-input"
                            type="text"
                            name="fullname"
                            id="fullname"
                            placeholder="Nhập họ và tên của bạn"
                            maxlength="100"
                            autocomplete="name"
                            value="<?= e($fullname) ?>"
                            required
                        >
                    </div>
                </div>

                <!-- EMAIL HOẶC SỐ ĐIỆN THOẠI -->
                <div class="cine-field">
                    <label for="contact">
                        Email hoặc số điện thoại
                    </label>

                    <div class="cine-input-wrap">
                        <span class="cine-input-icon">✉</span>
                        <input
                            class="cine-input"
                            type="text"
                            name="contact"
                            id="contact"
                            placeholder="Nhập email hoặc số điện thoại"
                            maxlength="100"
                            autocomplete="username"
                            value="<?= e($contact) ?>"
                            required
                        >
                    </div>
                </div>

                <!-- MẬT KHẨU -->
                <div class="cine-password-grid">
                    <div class="cine-field">
                        <label for="password">Mật khẩu</label>
                        <div class="cine-input-wrap">
                            <span class="cine-input-icon">♧</span>
                            <input
                                class="cine-input"
                                type="password"
                                name="password"
                                id="password"
                                placeholder="Nhập mật khẩu"
                                minlength="8"
                                autocomplete="new-password"
                                required
                            >
                            <button
                                type="button"
                                class="cine-toggle-pass"
                                data-target="password"
                                aria-label="Hiện hoặc ẩn mật khẩu"
                            >◉</button>
                        </div>
                    </div>

                    <div class="cine-field">
                        <label for="confirm_password">
                            Nhập lại mật khẩu
                        </label>
                        <div class="cine-input-wrap">
                            <span class="cine-input-icon">♧</span>
                            <input
                                class="cine-input"
                                type="password"
                                name="confirm_password"
                                id="confirm_password"
                                placeholder="Xác nhận mật khẩu"
                                minlength="8"
                                autocomplete="new-password"
                                required
                            >
                            <button
                                type="button"
                                class="cine-toggle-pass"
                                data-target="confirm_password"
                                aria-label="Hiện hoặc ẩn mật khẩu xác nhận"
                            >◉</button>
                        </div>
                    </div>
                </div>

                <!-- KIỂM TRA MẬT KHẨU -->
                <div class="cine-password-rules">
                    <div class="cine-rule" id="ruleLength">
                        <span>✓</span> Ít nhất 8 ký tự
                    </div>

                    <div class="cine-rule" id="ruleCharacters">
                        <span>✓</span> Bao gồm chữ và số
                    </div>

                    <div class="cine-rule" id="ruleMatch">
                        <span>✓</span> Hai mật khẩu trùng khớp
                    </div>
                </div>

                <button class="cine-submit" type="submit">
                    Đăng ký ngay &nbsp; →
                </button>
            </form>

            <div class="cine-login-link">
                Đã có tài khoản?
                <a href="login.php">Đăng nhập</a>
            </div>
        </div>
    </div>
</section>

<script>
document.querySelectorAll('.cine-toggle-pass').forEach(button => {
    button.addEventListener('click', function () {
        const input = document.getElementById(this.dataset.target);

        if (input.type === 'password') {
            input.type = 'text';
            this.textContent = '◎';
        } else {
            input.type = 'password';
            this.textContent = '◉';
        }
    });
});

const passwordInput = document.getElementById('password');
const confirmInput = document.getElementById('confirm_password');

function updatePasswordRules() {
    const password = passwordInput.value;
    const confirm = confirmInput.value;

    document.getElementById('ruleLength')
        .classList.toggle('valid', password.length >= 8);

    document.getElementById('ruleCharacters')
        .classList.toggle(
            'valid',
            /[A-Za-z]/.test(password) && /[0-9]/.test(password)
        );

    document.getElementById('ruleMatch')
        .classList.toggle(
            'valid',
            confirm.length > 0 && password === confirm
        );
}

passwordInput.addEventListener('input', updatePasswordRules);
confirmInput.addEventListener('input', updatePasswordRules);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
