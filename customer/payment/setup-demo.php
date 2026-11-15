<?php
/** Cài thêm bảng và dữ liệu minh họa. Chỉ localhost + cinema_booking_test. */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
if (!cbp_demo_allowed()) { cbp_notice(404, 'Không tìm thấy trang', 'Trang này chỉ dành cho bản kiểm thử trên máy cá nhân.'); }
try {
    if (isset($_SESSION['customer_user'])) { throw new CBPUserError('Hãy đăng xuất tài khoản khách hàng thật trước khi mở tài khoản mẫu.', 409); }
    $demo = cbp_query("SELECT id, fullname FROM users WHERE id = 2 AND role = 'customer' AND status = 'active'")[0] ?? null;
    if (!$demo) { throw new CBPUserError('Cần tài khoản khách hàng đang hoạt động có mã 2 trong dữ liệu main.', 409); }
    $ready = cbp_ready();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        cbp_require_post();
        cbp_install();
        $bookingId = cbp_create_demo((int) $demo['id']);
        session_regenerate_id(true);
        $_SESSION['kiet_demo_customer_id'] = (int) $demo['id'];
        $_SESSION['cbp_csrf'] = bin2hex(random_bytes(32));
        cbp_redirect('checkout.php?booking_id=' . $bookingId);
    }
} catch (Throwable $error) { cbp_handle_error($error); }
cbp_header('Cài đặt bản demo', null, 'demo');
cbp_heading('Cài đặt bản demo hoàn chỉnh', 'Bổ sung chức năng thanh toán, vé điện tử và hủy vé vào dự án hiện có.');
?>
<section class="panel narrow"><span class="pill <?= $ready ? 'success' : 'info' ?>"><?= $ready ? 'Các bảng module đã có' : 'Chưa cài đặt bảng module' ?></span><h2 class="space-top">Chuẩn bị để chạy thử</h2><p class="muted space-top">Nút bên dưới thêm 4 bảng riêng nếu chưa có và tạo một đơn mới cho <?= cbp_e($demo['fullname']) ?>. Suất chiếu được đặt trong tương lai, gồm hai ghế, để thử thanh toán và hủy vé.</p><ul class="plain-list"><li>Thêm các bảng: giao dịch, vé, yêu cầu hủy và ghế đã thanh toán.</li><li>Giữ các dữ liệu đang có trong database.</li><li>Tạo thêm suất chiếu và đơn mẫu mới mỗi lần bấm cài đặt/tạo đơn.</li><li>Quy định demo: hủy trước giờ chiếu ít nhất <?= cbp_settings()['cancel_cutoff_minutes'] ?> phút, hoàn <?= cbp_settings()['refund_percent'] ?>% tiền mô phỏng.</li></ul><form method="post" action="setup-demo.php"><?php cbp_csrf_field(); ?><button class="button" type="submit">Cài đặt và tạo đơn demo</button></form><?php if ($ready): ?><p class="space-top"><a href="dev-login.php">Vào tài khoản mẫu hiện có</a></p><?php endif; ?></section>
<?php cbp_footer(); ?>
