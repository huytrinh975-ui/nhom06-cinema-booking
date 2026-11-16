<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
try {
    $customer = cbp_require_customer();
    $attemptId = cbp_request_id('attempt_id', $_GET);
    $attempt = cbp_query('SELECT p.*, b.booking_code FROM cbp_payments p JOIN bookings b ON b.id = p.booking_id WHERE p.id = ? AND b.user_id = ?', 'ii', [$attemptId, (int) $customer['id']])[0] ?? null;
    if (!$attempt) { throw new CBPUserError('Giao dịch không tồn tại hoặc không thuộc tài khoản của bạn.', 404); }
    $order = cbp_owned_order((int) $attempt['booking_id'], (int) $customer['id']);
    $ticket = cbp_ticket_for_order((int) $order['id']);
    $cancellation = cbp_cancellation((int) $order['id']);
} catch (Throwable $error) { cbp_handle_error($error); }
$success = $attempt['result'] === 'success';
cbp_header('Kết quả thanh toán', $customer, 'orders');
?>
<section class="panel result-panel"><span class="result-symbol <?= $success ? 'success' : 'danger' ?>" aria-hidden="true"><?= $success ? '✓' : '×' ?></span><p class="eyebrow">KẾT QUẢ GIAO DỊCH DEMO</p><h1><?= $success ? 'Thanh toán thành công' : 'Thanh toán chưa thành công' ?></h1><p class="muted"><?= $success ? 'Giao dịch đã được ghi nhận và vé điện tử đã được tạo.' : 'Lần thanh toán này thất bại. Bạn có thể kiểm tra đơn và thử lại nếu suất chiếu vẫn còn khả dụng.' ?></p><dl class="receipt"><div><dt>Mã đơn</dt><dd><?= cbp_e($attempt['booking_code']) ?></dd></div><div><dt>Mã giao dịch</dt><dd><?= cbp_e($attempt['attempt_code']) ?></dd></div><div><dt>Số tiền</dt><dd><?= cbp_e(cbp_money(cbp_cents($attempt['amount']))) ?></dd></div><div><dt>Thời điểm</dt><dd><?= cbp_e(cbp_date_label($attempt['created_at'])) ?></dd></div></dl>
<?php if ($cancellation): ?><div class="status-notice neutral"><strong>Đơn hàng sau đó đã được hủy</strong><p>Hoàn tiền mô phỏng: <?= cbp_e(cbp_money(cbp_cents($cancellation['refund_amount']))) ?>.</p></div><?php elseif ($order['booking_status'] === 'cancelled'): ?><div class="status-notice neutral">Đơn hàng hiện đã bị hủy.</div><?php elseif (!$success && $order['payment_status'] === 'paid'): ?><div class="status-notice success">Đơn hàng đã được thanh toán thành công ở một lần thử sau.</div><?php endif; ?>
<div class="inline-actions"><?php if ($success && $ticket): ?><a class="button" href="ticket.php?id=<?= (int) $ticket['id'] ?>">Xem vé điện tử</a><?php endif; ?><a class="button button-secondary" href="checkout.php?booking_id=<?= (int) $order['id'] ?>"><?= !$success && $order['payment_status'] !== 'paid' && $order['booking_status'] !== 'cancelled' ? 'Kiểm tra đơn / thử lại' : 'Xem đơn hàng' ?></a><a class="text-link" href="history.php">Lịch sử đặt vé</a></div></section>
<?php cbp_footer(); ?>
