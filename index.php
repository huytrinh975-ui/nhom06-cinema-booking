<?php
require_once 'includes/auth.php';
$pageTitle = 'Trang chủ';
require_once 'includes/header.php';
?>
<section class="hero">
    <div class="hero-glow"></div>
    <div class="container hero-content">
        <div class="eyebrow"><span></span> YOUR NEXT MOVIE EXPERIENCE</div>
        <h1>Mỗi bộ phim,<br><em>một thế giới</em> riêng.</h1>
        <p>Chào mừng bạn đến với MovieBooking – nơi hành trình điện ảnh của bạn bắt đầu. Tạo tài khoản để quản lý thông tin cá nhân và sẵn sàng cho những trải nghiệm tiếp theo.</p>
        <div class="hero-actions">
            <?php if (isLoggedIn()): ?>
                <a class="btn btn-primary" href="profile.php">Xem tài khoản <span>→</span></a>
            <?php else: ?>
                <a class="btn btn-primary" href="register.php">Tạo tài khoản <span>→</span></a>
                <a class="btn btn-outline" href="login.php">Đăng nhập</a>
            <?php endif; ?>
        </div>
        <div class="hero-meta"><span>✦</span> CINEMA • STORIES • EXPERIENCE</div>
    </div>
    <div class="hero-art" aria-hidden="true">
        <div class="poster poster-back"><span>THE<br>UNKNOWN</span><small>COMING SOON</small></div>
        <div class="poster poster-front"><div class="poster-orbit"></div><span>BEYOND<br>THE SCREEN</span><small>A MOVIE EXPERIENCE</small></div>
        <div class="ticket-float">ADMIT ONE <b>✦</b></div>
    </div>
</section>
<section class="container intro-section">
    <div class="section-kicker">WELCOME TO MOVIEBOOKING</div>
    <h2>Một tài khoản. <span>Nhiều trải nghiệm.</span></h2>
    <div class="feature-grid">
        <article class="feature-card"><div class="feature-icon">◎</div><h3>Tài khoản cá nhân</h3><p>Quản lý họ tên, email và số điện thoại trong một nơi.</p></article>
        <article class="feature-card"><div class="feature-icon">⌁</div><h3>An toàn & riêng tư</h3><p>Mật khẩu được mã hóa trước khi lưu vào cơ sở dữ liệu.</p></article>
        <article class="feature-card"><div class="feature-icon">✦</div><h3>Trải nghiệm thuận tiện</h3><p>Đăng nhập, cập nhật hồ sơ và đăng xuất dễ dàng.</p></article>
    </div>
</section>
<?php require_once 'includes/footer.php'; ?>
