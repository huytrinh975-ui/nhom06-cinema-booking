<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../config/database.php';

header('Cache-Control: no-store');
$error = '';
$email = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    admin_check_csrf();
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ? AND role = 'admin' AND status = 'active'");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($admin && password_verify($password, $admin['password'])) {
        session_regenerate_id(true);
        unset($_SESSION['admin_user'], $_SESSION['admin_csrf']);
        $_SESSION['admin_user_id'] = (int) $admin['id'];
        admin_redirect('index.php');
    }
    $error = 'Email hoặc mật khẩu quản trị không hợp lệ.';
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập Admin | CINEBOOK</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { min-height: 100vh; display: grid; place-items: center; background: #0f172a; }
        main { width: min(100% - 2rem, 28rem); }
        .brand { color: #4338ca; font-size: .8rem; font-weight: 800; letter-spacing: .12em; }
        .btn-primary { --bs-btn-bg: #4f46e5; --bs-btn-border-color: #4f46e5; --bs-btn-hover-bg: #4338ca; }
    </style>
</head>
<body>
<main class="card border-0 shadow rounded-4 p-4 p-sm-5">
    <div class="brand mb-2">CINEBOOK</div>
    <h1 class="h3 fw-bold mb-4">Đăng nhập quản trị</h1>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <?php admin_csrf_field(); ?>
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input id="email" name="email" type="email" class="form-control" autocomplete="username" maxlength="100"
                   value="<?= htmlspecialchars($email) ?>" required>
        </div>
        <div class="mb-4">
            <label for="password" class="form-label">Mật khẩu</label>
            <input id="password" name="password" type="password" class="form-control" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Đăng nhập</button>
    </form>
</main>
</body>
</html>
