<?php
/** Helper demo được chặn ngoài localhost và ngoài database cinema_booking_test. */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
if (!cbp_demo_allowed()) { cbp_notice(404, 'Không tìm thấy trang', 'Trang này không khả dụng.'); }
if (!cbp_ready()) { cbp_redirect('setup-demo.php'); }
try {
    $demo = cbp_query("SELECT id, fullname FROM users WHERE id = 2 AND role = 'customer' AND status = 'active'")[0] ?? null;
    if (!$demo) { throw new CBPUserError('Tài khoản mẫu mã 2 cần có vai trò khách hàng và đang hoạt động.', 409); }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        cbp_require_post();
        if (isset($_SESSION['customer_user'])) { throw new CBPUserError('Hãy đăng xuất tài khoản khách hàng hiện tại trước khi dùng tài khoản mẫu.', 409); }
        session_regenerate_id(true);
        $_SESSION['kiet_demo_customer_id'] = (int) $demo['id'];
        $_SESSION['cbp_csrf'] = bin2hex(random_bytes(32));
        cbp_redirect('checkout.php');
    }
} catch (Throwable $error) { cbp_handle_error($error); }
cbp_header('Mở bản demo', null);
?>
<section class="panel narrow"><p class="eyebrow">KIỂM THỬ TRÊN LOCALHOST</p><h1>Mở thử tài khoản khách hàng</h1><p class="muted space-top">Tài khoản mẫu: <strong><?= cbp_e($demo['fullname']) ?></strong>. Bạn có thể thanh toán mô phỏng, xem vé, xem lịch sử và hủy vé trong thời hạn.</p><form method="post" action="dev-login.php" class="space-top"><?php cbp_csrf_field(); ?><button class="button" type="submit">Vào tài khoản mẫu</button></form><p class="space-top"><a href="setup-demo.php">Tạo thêm một đơn demo có suất chiếu tương lai</a></p></section>
<?php cbp_footer(); ?>
