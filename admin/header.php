<?php
/**
 * Header & Sidebar dùng chung cho phân hệ Admin
 * Dự án: Hệ thống Đặt Vé Xem Phim Trực Tuyến (nhom06-cinema-booking)
 */
require_once __DIR__ . '/auth.php';

$userInitial = mb_substr($current_user['fullname'], 0, 1);
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = $page_title ?? 'Hệ Thống Quản Trị';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - CINEBOOK Admin</title>

    <!-- Google Font: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- FontAwesome 6.5.2 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        :root {
            --sidebar-width: 260px;
            --primary-color: #6366f1;
            --primary-hover: #4f46e5;
            --sidebar-bg: #0f172a;
            --sidebar-hover: #1e293b;
            --sidebar-active: #4f46e5;
            --text-muted: #94a3b8;
            --body-bg: #f8fafc;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--body-bg);
            color: #334155;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Sidebar Styling */
        #sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, #0f172a 0%, #1e1e2f 100%);
            color: #fff;
            z-index: 1040;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
        }

        #sidebar .brand {
            padding: 24px 20px;
            font-size: 1.25rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #fff;
            text-decoration: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        #sidebar .brand .brand-badge {
            background: linear-gradient(135deg, #ef4444 0%, #f97316 100%);
            padding: 8px 12px;
            border-radius: 10px;
            color: #fff;
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.4);
        }

        #sidebar .nav-menu {
            padding: 16px 12px;
            flex: 1;
            overflow-y: auto;
        }

        .nav-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            padding: 12px 14px 6px;
            font-weight: 700;
        }

        #sidebar .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 16px;
            color: #cbd5e1;
            font-size: 0.92rem;
            font-weight: 500;
            border-radius: 10px;
            margin-bottom: 4px;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        #sidebar .nav-link i {
            width: 22px;
            text-align: center;
            font-size: 1.1rem;
            color: #94a3b8;
            transition: color 0.2s;
        }

        #sidebar .nav-link:hover {
            background-color: var(--sidebar-hover);
            color: #fff;
        }

        #sidebar .nav-link:hover i {
            color: #818cf8;
        }

        #sidebar .nav-link.active {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: #fff;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35);
        }

        #sidebar .nav-link.active i {
            color: #fff;
        }

        #sidebar .sidebar-footer {
            padding: 16px 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Main Content Wrapper */
        #main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }

        /* Top Navbar */
        .top-navbar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 14px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1020;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }

        .content-body {
            padding: 28px;
            flex: 1;
        }

        /* Cards & Components */
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            background-color: #fff;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card {
            border-radius: 16px;
            padding: 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, 0.8);
            background: #fff;
        }

        .stat-card .stat-icon {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-card.revenue .stat-icon { background: rgba(16, 185, 129, 0.12); color: #10b981; }
        .stat-card.tickets .stat-icon { background: rgba(99, 102, 241, 0.12); color: #6366f1; }
        .stat-card.movies .stat-icon { background: rgba(245, 158, 11, 0.12); color: #f59e0b; }
        .stat-card.users .stat-icon { background: rgba(239, 68, 68, 0.12); color: #ef4444; }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
            margin-top: 4px;
        }

        .stat-title {
            color: #64748b;
            font-size: 0.88rem;
            font-weight: 500;
        }

        /* Modern Table */
        .table-custom {
            margin-bottom: 0;
        }

        .table-custom thead th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 0.83rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #e2e8f0;
            padding: 14px 16px;
            vertical-align: middle;
        }

        .table-custom tbody td {
            padding: 14px 16px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            font-size: 0.92rem;
        }

        .table-custom tbody tr:hover {
            background-color: #f8fafc;
        }

        /* Seat Matrix Visual Styles */
        .screen-container {
            perspective: 400px;
            margin-bottom: 25px;
            text-align: center;
        }

        .cinema-screen {
            background: linear-gradient(180deg, #94a3b8 0%, #cbd5e1 100%);
            height: 16px;
            width: 85%;
            margin: 0 auto 12px;
            border-radius: 50% 50% 0 0 / 100% 100% 0 0;
            transform: rotateX(-15deg);
            box-shadow: 0 8px 18px rgba(100, 116, 139, 0.4);
        }

        .screen-text {
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .seat-grid {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: center;
            padding: 15px;
            background: #f8fafc;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            overflow-x: auto;
        }

        .seat-row {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .row-label {
            font-weight: 700;
            width: 24px;
            color: #64748b;
            font-size: 0.85rem;
            text-align: center;
        }

        .seat-item {
            width: 36px;
            height: 34px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: default;
            transition: all 0.2s;
            position: relative;
            user-select: none;
        }

        .seat-item.seat-standard {
            background-color: #e2e8f0;
            color: #334155;
            border: 1px solid #cbd5e1;
        }

        .seat-item.seat-vip {
            background-color: #fef3c7;
            color: #b45309;
            border: 1px solid #fcd34d;
        }

        .seat-item.seat-couple {
            width: 78px;
            background-color: #fce7f3;
            color: #be185d;
            border: 1px solid #fbcfe8;
        }

        .seat-item.seat-booked {
            background-color: #ef4444 !important;
            color: #ffffff !important;
            border-color: #dc2626 !important;
            opacity: 0.85;
            cursor: not-allowed;
        }

        .seat-legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #475569;
        }

        .seat-legend-box {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }

        /* Responsive Mobile Sidebar */
        @media (max-width: 991.98px) {
            #sidebar {
                transform: translateX(-100%);
            }
            #sidebar.show {
                transform: translateX(0);
            }
            #main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside id="sidebar">
    <a href="index.php" class="brand">
        <span class="brand-badge"><i class="fa-solid fa-film"></i></span>
        <div>
            <div>CINEBOOK</div>
            <div style="font-size: 0.72rem; color: #94a3b8; font-weight: 500;">Admin Dashboard</div>
        </div>
    </a>

    <div class="nav-menu">
        <div class="nav-label">Tổng Quan</div>
        <a href="index.php" class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-chart-pie"></i>
            <span>Bảng Điều Khiển</span>
        </a>

        <a href="revenue.php" class="nav-link <?= $current_page === 'revenue.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-chart-line"></i>
            <span>Thống Kê Doanh Thu</span>
        </a>

        <div class="nav-label">Quản Lý Phim & Chiếu</div>
        <a href="movies.php" class="nav-link <?= $current_page == 'movies.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-clapperboard"></i>
            <span>Quản Lý Phim</span>
        </a>
        <a href="genres.php" class="nav-link <?= $current_page === 'genres.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-tags"></i>
            <span>Thể Loại Phim</span>
        </a>
        <a href="cinemas.php" class="nav-link <?= in_array($current_page, ['cinemas.php', 'seats.php'], true) ? 'active' : '' ?>">
            <i class="fa-solid fa-building"></i>
            <span>Cụm Rạp & Phòng Chiếu</span>
        </a>
        <a href="showtimes.php" class="nav-link <?= $current_page == 'showtimes.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-calendar-days"></i>
            <span>Lịch & Suất Chiếu</span>
        </a>

        <div class="nav-label">Giao Dịch & Khách Hàng</div>
        <a href="orders.php" class="nav-link <?= $current_page == 'orders.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-ticket"></i>
            <span>Đơn Đặt Vé</span>
        </a>
        <a href="vouchers.php" class="nav-link <?= $current_page === 'vouchers.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-tag"></i>
            <span>Voucher</span>
        </a>
        <a href="users.php" class="nav-link <?= $current_page == 'users.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-users"></i>
            <span>Tài Khoản Khách Hàng</span>
        </a>
    </div>

    <div class="sidebar-footer">
        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; font-weight: 700;">
            <?= htmlspecialchars($userInitial) ?>
        </div>
        <div class="overflow-hidden me-auto">
            <div class="text-truncate fw-semibold text-white" style="font-size: 0.88rem;"><?= htmlspecialchars($current_user['fullname']) ?></div>
            <div class="text-truncate text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($current_user['email']) ?></div>
        </div>
        <a href="../cinema%20booking%20web/index.php" class="text-muted" title="Về trang chủ khách hàng" target="_blank">
            <i class="fa-solid fa-arrow-up-right-from-square"></i>
        </a>
    </div>
</aside>

<!-- MAIN CONTENT WRAPPER -->
<div id="main-content">
    <!-- TOP NAVBAR -->
    <header class="top-navbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light d-lg-none border" id="toggle-sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="d-none d-md-flex align-items-center text-muted" style="font-size: 0.9rem;">
                <i class="fa-regular fa-calendar me-2"></i>
                <span id="current-datetime"><?= date('l, d/m/Y') ?></span>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <a href="showtimes.php" class="btn btn-outline-primary btn-sm rounded-pill px-3 d-none d-sm-inline-flex align-items-center gap-2">
                <i class="fa-solid fa-plus"></i> Lên lịch chiếu
            </a>
            <div class="dropdown">
                <button class="btn btn-light border dropdown-toggle d-flex align-items-center gap-2 rounded-pill px-3 py-1" data-bs-toggle="dropdown">
                    <span class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center fw-bold" style="width: 24px; height: 24px; font-size: 0.75rem;"><?= htmlspecialchars($userInitial) ?></span>
                    <span class="fw-medium" style="font-size: 0.9rem;"><?= htmlspecialchars($current_user['fullname']) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                    <li class="px-3 py-2 border-bottom">
                        <div class="fw-bold"><?= htmlspecialchars($current_user['fullname']) ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($current_user['email']) ?></div>
                        <span class="badge bg-success-subtle text-success mt-1">Quản trị viên</span>
                    </li>
                    <li><a class="dropdown-menu-item dropdown-item py-2 mt-1" href="users.php"><i class="fa-solid fa-user-gear me-2 text-muted"></i>Quản lý tài khoản</a></li>
                    <li><a class="dropdown-menu-item dropdown-item py-2" href="../cinema%20booking%20web/index.php" target="_blank"><i class="fa-solid fa-globe me-2 text-muted"></i>Xem website khách</a></li>
                    <li>
                        <form method="post" action="logout.php">
                            <?php admin_csrf_field(); ?>
                            <button type="submit" class="dropdown-item py-2"><i class="fa-solid fa-right-from-bracket me-2 text-muted"></i>Đăng xuất</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <!-- CONTENT BODY CONTAINER -->
    <main class="content-body">
        <?php if (isset($_SESSION['flash_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 d-flex align-items-center gap-2" role="alert">
                <i class="fa-solid fa-circle-check fs-5"></i>
                <div><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['flash_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 d-flex align-items-center gap-2" role="alert">
                <i class="fa-solid fa-circle-exclamation fs-5"></i>
                <div><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

