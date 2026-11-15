<?php
require_once __DIR__ . '/auth.php';
$pageTitle = $pageTitle ?? 'MovieBooking';
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#100d18">
    <title><?= e($pageTitle) ?> | MovieBooking</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="site-header">
    <div class="container nav-wrap">
        <a class="brand" href="index.php"><span class="brand-mark">M</span><span>MOVIE<span class="brand-light">BOOKING</span></span></a>
        <button class="menu-toggle" type="button" aria-label="Mở menu" onclick="document.querySelector('.nav-links').classList.toggle('open')">☰</button>
        <nav class="nav-links">
            <a href="index.php">Trang chủ</a>
            <?php if (isLoggedIn()): ?>
                <a href="profile.php">Tài khoản</a>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <span class="role-chip">Quản trị viên</span>
                <?php endif; ?>
                <form class="nav-logout" action="logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <button class="btn btn-small btn-ghost" type="submit">Đăng xuất</button>
                </form>
            <?php else: ?>
                <a href="login.php">Đăng nhập</a>
                <a class="btn btn-small btn-primary" href="register.php">Tạo tài khoản</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main>
<?php if ($flash): ?>
    <div class="container flash-wrap">
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    </div>
<?php endif; ?>
