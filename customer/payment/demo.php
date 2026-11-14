<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
if (!cbp_demo_allowed()) { cbp_notice(404, 'Không tìm thấy trang', 'Chức năng này chỉ mở trên localhost và database kiểm thử.'); }
try {
    $customer = cbp_require_customer();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        cbp_require_post();
        $nonce = cbp_text($_POST['demo_nonce'] ?? null, 64);
        if (!isset($_SESSION['cbp_demo_nonce']) || !hash_equals($_SESSION['cbp_demo_nonce'], $nonce)) { throw new CBPUserError('Biểu mẫu tạo đơn đã cũ. Vui lòng mở lại trang tạo đơn demo.', 403); }
        $kind = cbp_text($_POST['kind'] ?? 'normal', 10);
        if (!in_array($kind, ['normal', 'near'], true)) { throw new CBPUserError('Kiểu dữ liệu demo chưa hợp lệ.', 400); }
        unset($_SESSION['cbp_demo_nonce']);
        $bookingId = cbp_create_demo((int) $customer['id'], $kind);
        cbp_redirect('checkout.php?booking_id=' . $bookingId);
    }
    $_SESSION['cbp_demo_nonce'] = bin2hex(random_bytes(32));
} catch (Throwable $error) { cbp_handle_error($error); }
cbp_header('Tạo đơn demo', $customer, 'demo');
cbp_heading('Tạo thêm đơn để chạy thử', 'Mỗi đơn mới có suất chiếu và ghế phù hợp để thử một tình huống riêng.');
?>
<section class="panel narrow"><h2>Chọn tình huống thử nghiệm</h2><form method="post" action="demo.php" class="space-top"><?php cbp_csrf_field(); ?><input type="hidden" name="demo_nonce" value="<?= cbp_e($_SESSION['cbp_demo_nonce']) ?>"><label for="kind">Thời gian suất chiếu</label><select id="kind" name="kind"><option value="normal">Suất trong tương lai — thử thanh toán và hủy hợp lệ</option><option value="near">Suất sau khoảng 30 phút — thử chặn hủy sát giờ</option></select><p class="muted space-top">Bạn có thể tạo một đơn để thử thanh toán thất bại rồi thử lại; tạo đơn khác để thử thanh toán thành công và hủy vé.</p><button class="button space-top" type="submit">Tạo đơn demo mới</button></form></section>
<?php cbp_footer(); ?>
