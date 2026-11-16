<?php
require_once __DIR__ . '/../../includes/auth.php';
$customer = currentCustomer();

$pageTitle = $pageTitle ?? 'CINEBOOK - Đặt vé xem phim trực tuyến';
$active = $active ?? '';
$bodyClass = $bodyClass ?? '';
$pageCss = $pageCss ?? [];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <link rel="stylesheet" href="css/style.css">

    <?php foreach ($pageCss as $cssFile): ?>
        <link rel="stylesheet" href="css/<?= htmlspecialchars($cssFile, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>
</head>
<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?>">

<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="index.php">CINE<span>BOOK</span></a>

        <nav class="main-nav" aria-label="Điều hướng chính">
            <a href="index.php" class="<?= $active === 'home' ? 'is-active' : '' ?>">Trang chủ</a>
            <a href="index.php#phim-dang-chieu">Phim đang chiếu</a>
            <a href="index.php#phim-sap-chieu">Phim sắp chiếu</a>
            <a href="index.php#lich-chieu">Lịch chiếu</a>
            <a href="index.php#tin-tuc">Tin tức</a>
        </nav>

        <div class="header-actions">
            <?php if ($customer): ?>
                <a class="btn btn-ghost" href="../customer/payment/tickets.php">Vé của tôi</a>
<a class="btn btn-ghost" href="../profile.php">Tài khoản</a>
<form action="../logout.php" method="post" class="cinebook-logout-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <button type="submit" class="btn btn-ghost cinebook-logout">Đăng xuất</button>
</form>
            <?php else: ?>
                <a class="btn btn-ghost" href="../register.php">Đăng ký</a>
<a class="btn btn-primary" href="../login.php">Đăng nhập</a>
            <?php endif; ?>
        </div>
    </div>
</header>
