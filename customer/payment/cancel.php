<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
try {
    $customer = cbp_require_customer();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        cbp_require_post();
        $bookingId = cbp_request_id('booking_id', $_POST);
        cbp_cancel($bookingId, (int) $customer['id'], cbp_text($_POST['reason'] ?? null));
        cbp_redirect('checkout.php?booking_id=' . $bookingId);
    }
    $bookingId = cbp_request_id('booking_id', $_GET);
    $order = cbp_owned_order($bookingId, (int) $customer['id']);
    $ticket = cbp_ticket_for_order($bookingId);
    $info = cbp_cancel_info($order, $ticket);
    $existing = cbp_cancellation($bookingId);
    $settings = cbp_settings();
} catch (Throwable $error) { cbp_handle_error($error); }
cbp_header('Yêu cầu hủy vé', $customer, 'orders');
cbp_heading('Yêu cầu hủy vé / đơn hàng', 'Kiểm tra quy định và khoản hoàn tiền trước khi xác nhận.', $order['booking_code']);
?>
<section class="panel narrow"><h2><?= cbp_e($order['movie_title']) ?></h2><p class="muted"><?= cbp_e($order['cinema_name']) ?> · <?= cbp_e(cbp_date_label($order['start_time'])) ?></p><div class="status-notice <?= $info['allowed'] ? 'info' : 'warning' ?> space-top"><strong><?= cbp_e($info['message']) ?></strong><p>Quy định demo: hủy khi còn ít nhất <?= $settings['cancel_cutoff_minutes'] ?> phút trước giờ chiếu. Đơn đã trả tiền được hoàn <?= $settings['refund_percent'] ?>% bằng mô phỏng.</p><?php if ($info['deadline']): ?><p>Hạn hủy: <?= cbp_e($info['deadline']->format('H:i · d/m/Y')) ?>.</p><?php endif; ?></div>
<?php if ($existing): ?><p>Yêu cầu <?= cbp_e($existing['cancellation_code']) ?> đã được xử lý.</p><?php elseif ($info['allowed']): ?><div class="total-row"><span>Tiền hoàn mô phỏng dự kiến</span><strong><?= cbp_e(cbp_money($info['refund'])) ?></strong></div><form method="post" action="cancel.php" class="space-top"><?php cbp_csrf_field(); ?><input type="hidden" name="booking_id" value="<?= $bookingId ?>"><label for="reason">Lý do hủy <span class="muted">(tối đa 500 ký tự)</span></label><textarea id="reason" name="reason" rows="4" maxlength="500" required placeholder="Ví dụ: Tôi thay đổi lịch cá nhân."></textarea><p class="muted small">Sau khi xác nhận, vé sẽ mất hiệu lực và ghế được giải phóng. Lịch sử đơn hàng vẫn được giữ lại.</p><button type="submit" class="button button-danger space-top">Xác nhận hủy vé / đơn hàng</button></form><?php endif; ?>
<div class="inline-actions space-top"><a href="checkout.php?booking_id=<?= $bookingId ?>">Quay lại đơn hàng</a><a href="policy.php">Đọc quy định hủy</a></div></section>
<?php cbp_footer(); ?>
