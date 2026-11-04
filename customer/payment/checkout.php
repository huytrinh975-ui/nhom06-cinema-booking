<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
try {
    $customer = cbp_require_customer();
    $ownOrders = cbp_query("SELECT id, booking_code, payment_status FROM bookings WHERE user_id = ? ORDER BY (booking_status <> 'cancelled' AND payment_status <> 'paid') DESC, id DESC LIMIT 30", 'i', [(int) $customer['id']]);
    $bookingId = array_key_exists('booking_id', $_GET) ? cbp_request_id('booking_id', $_GET) : (int) ($ownOrders[0]['id'] ?? 0);
    $order = $bookingId ? cbp_owned_order($bookingId, (int) $customer['id']) : null;
    if ($order) {
        $seats = cbp_seats($bookingId);
        $state = cbp_order_state($order, cbp_order_issues($order, $seats));
        $issues = cbp_payment_issues($order, $seats);
        $ticket = cbp_ticket_for_order($bookingId);
        $cancellation = cbp_cancellation($bookingId);
        $cancelInfo = cbp_cancel_info($order, $ticket);
        $attempts = cbp_query('SELECT id, attempt_code, amount, result, created_at FROM cbp_payments WHERE booking_id = ? ORDER BY id DESC', 'i', [$bookingId]);
        if (!in_array($bookingId, array_map('intval', array_column($ownOrders, 'id')), true)) { array_unshift($ownOrders, $order); }
    }
} catch (Throwable $error) { cbp_handle_error($error); }
cbp_header('Kiểm tra đơn hàng', $customer, 'orders');
cbp_heading('Kiểm tra đơn hàng', 'Kiểm tra thông tin trước khi thanh toán vé xem phim.', $order['booking_code'] ?? '');
if (!$order) {
    cbp_empty('Bạn chưa có đơn đặt vé', 'Hãy đặt vé từ trang chọn phim hoặc tạo một đơn mẫu để chạy thử.', cbp_demo_allowed() ? 'demo.php' : 'history.php', cbp_demo_allowed() ? 'Tạo đơn demo' : 'Xem lịch sử');
    cbp_footer(); exit;
}
$seatLabels = ['standard'=>'Ghế thường', 'vip'=>'Ghế VIP', 'couple'=>'Ghế đôi'];
?>
<form method="get" action="checkout.php" class="order-picker panel"><label for="booking_id">Đơn hàng của tôi</label><div class="picker-controls"><select id="booking_id" name="booking_id"><?php foreach ($ownOrders as $item): ?><option value="<?= (int) $item['id'] ?>" <?= (int) $item['id'] === $bookingId ? 'selected' : '' ?>><?= cbp_e($item['booking_code']) ?> · <?= cbp_e(cbp_payment_label($item)) ?></option><?php endforeach; ?></select><button class="button button-secondary">Xem đơn</button></div></form>
<div class="checkout-grid"><div class="main-column">
<section class="panel" aria-labelledby="movie-heading"><div class="section-label"><span class="section-number">01</span><h2 id="movie-heading">Thông tin suất chiếu</h2></div>
<div class="movie-heading"><span class="movie-icon" aria-hidden="true">▶</span><div><h3><?= cbp_e($order['movie_title']) ?></h3><p class="muted"><?= (int) $order['duration'] ?> phút</p></div></div>
<dl class="info-grid"><div class="wide"><dt>Rạp chiếu</dt><dd><?= cbp_e($order['cinema_name']) ?></dd><dd class="address"><?= cbp_e($order['cinema_address']) ?> · <?= cbp_e($order['cinema_city']) ?></dd></div><div><dt>Ngày chiếu</dt><dd><?= cbp_e(cbp_date_label($order['start_time'], 'd/m/Y')) ?></dd></div><div><dt>Giờ chiếu</dt><dd><?= cbp_e(cbp_date_label($order['start_time'], 'H:i')) ?> – <?= cbp_e(cbp_date_label($order['end_time'], 'H:i')) ?></dd></div><div class="wide"><dt>Phòng chiếu</dt><dd><?= cbp_e($order['room_name']) ?></dd></div></dl>
<div class="seat-block"><p class="field-label">Ghế đã chọn</p><div class="seat-chips"><?php foreach ($seats as $seat): ?><span class="seat-chip"><?= cbp_e($seat['seat_number'] ?? 'Chưa xác định') ?></span><?php endforeach; ?></div></div></section>
<section class="panel"><div class="section-label"><span class="section-number">02</span><h2>Thông tin khách hàng</h2></div><dl class="info-grid customer-grid"><div><dt>Họ và tên</dt><dd><?= cbp_e($customer['fullname']) ?></dd></div><div><dt>Số điện thoại</dt><dd><?= cbp_e($customer['phone'] ?: 'Chưa cập nhật') ?></dd></div><div class="wide"><dt>Email</dt><dd><?= cbp_e($customer['email']) ?></dd></div></dl></section>
<?php if ($attempts): ?><section class="panel"><h2>Các lần thanh toán của đơn</h2><ul class="event-list"><?php foreach ($attempts as $attempt): ?><li><div><a href="result.php?attempt_id=<?= (int) $attempt['id'] ?>"><?= cbp_e($attempt['attempt_code']) ?></a><small><?= cbp_e(cbp_date_label($attempt['created_at'])) ?></small></div><span class="pill <?= $attempt['result'] === 'success' ? 'success' : 'danger' ?>"><?= $attempt['result'] === 'success' ? 'Thành công' : 'Thất bại' ?></span></li><?php endforeach; ?></ul></section><?php endif; ?>
</div>
<aside class="panel summary-panel"><div class="section-label"><span class="section-number">03</span><h2>Chi tiết số tiền</h2></div><div class="status-notice <?= cbp_e($state['tone']) ?>" role="status"><strong><?= cbp_e($state['title']) ?></strong><p><?= cbp_e($state['message']) ?></p></div>
<ul class="price-list"><?php foreach ($seats as $seat): ?><li><div><strong>Ghế <?= cbp_e($seat['seat_number'] ?? '') ?></strong><span><?= cbp_e($seatLabels[$seat['seat_type']] ?? 'Cần kiểm tra') ?></span></div><strong><?= cbp_e(cbp_money(cbp_cents($seat['price']))) ?></strong></li><?php endforeach; ?></ul>
<div class="total-row"><span>Tổng tiền đơn hàng</span><strong><?= cbp_e(cbp_money(cbp_cents($order['total_amount']))) ?></strong></div><dl class="status-list"><div><dt>Thanh toán</dt><dd><?= cbp_e(cbp_payment_label($order)) ?></dd></div><div><dt>Đơn đặt vé</dt><dd><?= cbp_e(cbp_booking_label($order['booking_status'])) ?></dd></div><div><dt>Thời gian đặt</dt><dd><?= cbp_e(cbp_date_label($order['created_at'])) ?></dd></div></dl>
<div class="stack-actions">
<?php if (!$issues): ?><a class="button" href="pay.php?booking_id=<?= $bookingId ?>"><?= $order['payment_status'] === 'failed' ? 'Thử thanh toán lại' : 'Tiếp tục thanh toán' ?></a><?php elseif ($order['payment_status'] !== 'paid' && $order['booking_status'] !== 'cancelled'): ?><p class="validation-note"><?= cbp_e($issues[0]) ?></p><?php endif; ?>
<?php if ($ticket): ?><a class="button button-secondary" href="ticket.php?id=<?= (int) $ticket['id'] ?>">Xem vé điện tử</a><?php endif; ?>
<?php if ($cancelInfo['allowed']): ?><a class="button button-danger-outline" href="cancel.php?booking_id=<?= $bookingId ?>">Yêu cầu hủy vé / đơn</a><?php endif; ?>
<a class="text-link" href="history.php">Xem lịch sử đặt vé</a></div>
<?php if ($cancellation): ?><div class="status-notice success cancel-receipt"><strong>Đã ghi nhận yêu cầu hủy</strong><p>Mã <?= cbp_e($cancellation['cancellation_code']) ?></p><p>Hoàn tiền mô phỏng: <strong><?= cbp_e(cbp_money(cbp_cents($cancellation['refund_amount']))) ?></strong></p><p><?= cbp_e($cancellation['reason']) ?></p><p><?= cbp_e(cbp_date_label($cancellation['created_at'])) ?></p></div><?php endif; ?>
<p class="payment-note">Thanh toán và hoàn tiền trong bản này đều là mô phỏng.</p></aside></div>
<?php cbp_footer(); ?>
