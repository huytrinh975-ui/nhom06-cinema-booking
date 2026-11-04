<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
try {
    $customer = cbp_require_customer();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        cbp_require_post();
        $bookingId = cbp_request_id('booking_id', $_POST);
        $attemptId = cbp_pay($bookingId, (int) $customer['id'], cbp_text($_POST['request_key'] ?? null, 64), cbp_text($_POST['outcome'] ?? null, 10));
        cbp_redirect('result.php?attempt_id=' . $attemptId);
    }
    $bookingId = cbp_request_id('booking_id', $_GET);
    $order = cbp_owned_order($bookingId, (int) $customer['id']);
    $seats = cbp_seats($bookingId);
    $issues = cbp_payment_issues($order, $seats);
    if ($issues) { throw new CBPUserError($issues[0], 409); }
    $requestKey = cbp_payment_key($bookingId);
} catch (Throwable $error) { cbp_handle_error($error); }
cbp_header('Thanh toán mô phỏng', $customer, 'orders');
cbp_heading('Thanh toán mô phỏng', 'Chọn kết quả để trình diễn luồng xử lý của website.', $order['booking_code']);
?>
<div class="checkout-grid"><section class="panel"><span class="pill info">PHƯƠNG THỨC: DEMO</span><h2 class="space-top"><?= cbp_e($order['movie_title']) ?></h2><p class="muted"><?= cbp_e($order['cinema_name']) ?> · <?= cbp_e($order['room_name']) ?></p><p><?= cbp_e(cbp_date_label($order['start_time'])) ?></p><div class="seat-chips"><?php foreach ($seats as $seat): ?><span class="seat-chip"><?= cbp_e($seat['seat_number']) ?></span><?php endforeach; ?></div><div class="total-row"><span>Số tiền mô phỏng</span><strong><?= cbp_e(cbp_money(cbp_cents($order['total_amount']))) ?></strong></div><p class="muted space-top">Không cần nhập số thẻ, mật khẩu ngân hàng hoặc chuyển khoản. Đây là chức năng phục vụ bài tập.</p></section>
<section class="panel"><h2>Chọn kết quả giao dịch</h2><p class="muted space-top">Thành công sẽ xác nhận đơn và phát hành vé điện tử. Thất bại sẽ lưu lần thử để bạn có thể thanh toán lại.</p><form method="post" action="pay.php" class="stack-actions space-top"><?php cbp_csrf_field(); ?><input type="hidden" name="booking_id" value="<?= $bookingId ?>"><input type="hidden" name="request_key" value="<?= cbp_e($requestKey) ?>"><button class="button" name="outcome" value="success" type="submit">Mô phỏng thanh toán thành công</button><button class="button button-danger-outline" name="outcome" value="failed" type="submit">Mô phỏng thanh toán thất bại</button><a class="text-link" href="checkout.php?booking_id=<?= $bookingId ?>">Quay lại kiểm tra đơn hàng</a></form><p class="payment-note">Một yêu cầu gửi lại sẽ không phát hành thêm vé hoặc ghi nhận thanh toán trùng.</p></section></div>
<?php cbp_footer(); ?>
