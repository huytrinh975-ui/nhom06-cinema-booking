<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
try {
    $customer = cbp_require_customer();
    $status = cbp_text($_GET['status'] ?? 'all', 20);
    $search = cbp_text($_GET['search'] ?? '', 80);
    $page = isset($_GET['page']) ? cbp_request_id('page', $_GET) : 1;
    if ($page > 100000) { throw new CBPUserError('Trang yêu cầu không hợp lệ.', 400); }
    $filters = ['all'=>'Tất cả đơn', 'pending'=>'Chờ thanh toán', 'paid'=>'Đã thanh toán', 'failed'=>'Thanh toán thất bại', 'cancelled'=>'Đã hủy'];
    if (!isset($filters[$status])) { throw new CBPUserError('Bộ lọc không hợp lệ.', 400); }
    $where = 'b.user_id = ?'; $types = 'i'; $params = [(int) $customer['id']];
    if ($status === 'cancelled') { $where .= " AND b.booking_status = 'cancelled'"; }
    elseif ($status !== 'all') { $where .= " AND b.payment_status = ? AND b.booking_status <> 'cancelled'"; $types .= 's'; $params[] = $status; }
    if ($search !== '') { $where .= ' AND b.booking_code LIKE ?'; $types .= 's'; $params[] = '%' . $search . '%'; }
    $count = (int) cbp_query('SELECT COUNT(*) AS n FROM bookings b WHERE ' . $where, $types, $params)[0]['n'];
    $orders = cbp_query('SELECT b.*, m.title AS movie_title, st.start_time, c.name AS cinema_name, r.name AS room_name,
        t.id AS ticket_id, ca.refund_status, ca.refund_amount,
        (SELECT GROUP_CONCAT(s.seat_number ORDER BY s.seat_number SEPARATOR ", ") FROM booking_details d JOIN seats s ON s.id = d.seat_id WHERE d.booking_id = b.id) AS seat_list
        FROM bookings b JOIN showtimes st ON st.id = b.showtime_id JOIN movies m ON m.id = st.movie_id
        JOIN rooms r ON r.id = st.room_id JOIN cinemas c ON c.id = r.cinema_id
        LEFT JOIN cbp_tickets t ON t.booking_id = b.id LEFT JOIN cbp_cancellations ca ON ca.booking_id = b.id
        WHERE ' . $where . ' ORDER BY b.created_at DESC, b.id DESC LIMIT ? OFFSET ?', $types . 'ii', array_merge($params, [12, ($page - 1) * 12]));
    $stats = cbp_query("SELECT COUNT(*) AS orders_count, COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN b.total_amount ELSE 0 END),0) AS paid_total,
        COALESCE(SUM(ca.refund_amount),0) AS refunds FROM bookings b LEFT JOIN cbp_cancellations ca ON ca.booking_id = b.id WHERE b.user_id = ?", 'i', [(int) $customer['id']])[0];
} catch (Throwable $error) { cbp_handle_error($error); }
cbp_header('Lịch sử đặt vé', $customer, 'history');
cbp_heading('Lịch sử đặt vé', 'Theo dõi đơn hàng, giao dịch và các lần hủy của bạn.');
?>
<div class="stats-grid"><div class="panel"><span>Tổng số đơn</span><strong><?= (int) $stats['orders_count'] ?></strong></div><div class="panel"><span>Tổng tiền đã trả</span><strong><?= cbp_e(cbp_money(cbp_cents($stats['paid_total']))) ?></strong></div><div class="panel"><span>Đã hoàn tiền mô phỏng</span><strong><?= cbp_e(cbp_money(cbp_cents($stats['refunds']))) ?></strong></div></div>
<form class="panel filter-bar" method="get" action="history.php"><div><label for="search">Tìm mã đơn</label><input id="search" name="search" maxlength="80" value="<?= cbp_e($search) ?>" placeholder="Ví dụ: DM hoặc BK2024"></div><div><label for="status">Trạng thái</label><select id="status" name="status"><?php foreach ($filters as $key=>$label): ?><option value="<?= cbp_e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= cbp_e($label) ?></option><?php endforeach; ?></select></div><button type="submit" class="button">Lọc lịch sử</button></form>
<?php if (!$orders): cbp_empty('Chưa có đơn phù hợp', 'Thử đổi bộ lọc hoặc từ khóa tìm kiếm.', 'history.php', 'Xem tất cả đơn'); else: ?>
<p class="muted small space-bottom">Tìm thấy <?= $count ?> đơn · Trang <?= $page ?>/<?= max(1, (int) ceil($count / 12)) ?></p>
<div class="order-list"><?php foreach ($orders as $order): ?><article class="panel order-card"><div class="order-card-main"><p class="eyebrow"><?= cbp_e($order['booking_code']) ?></p><h2><?= cbp_e($order['movie_title']) ?></h2><p class="muted"><?= cbp_e($order['cinema_name']) ?> · <?= cbp_e($order['room_name']) ?></p><p><?= cbp_e(cbp_date_label($order['start_time'])) ?></p><p class="small muted">Ghế: <?= cbp_e($order['seat_list'] ?? 'Chưa cập nhật') ?></p><p class="small muted">Đặt lúc <?= cbp_e(cbp_date_label($order['created_at'])) ?></p></div><div class="order-card-side"><strong class="price"><?= cbp_e(cbp_money(cbp_cents($order['total_amount']))) ?></strong><span class="pill <?= $order['booking_status'] === 'cancelled' ? 'neutral' : ($order['payment_status'] === 'paid' ? 'success' : ($order['payment_status'] === 'failed' ? 'danger' : 'warning')) ?>"><?= cbp_e(cbp_payment_label($order)) ?></span><span class="small muted"><?= cbp_e(cbp_booking_label($order['booking_status'])) ?></span><?php if ($order['refund_status'] === 'simulated_refunded'): ?><span class="small">Hoàn: <?= cbp_e(cbp_money(cbp_cents($order['refund_amount']))) ?></span><?php endif; ?><div class="inline-actions"><a class="button button-secondary" href="checkout.php?booking_id=<?= (int) $order['id'] ?>">Xem đơn</a><?php if ($order['ticket_id']): ?><a class="text-link" href="ticket.php?id=<?= (int) $order['ticket_id'] ?>">Xem vé</a><?php endif; ?></div></div></article><?php endforeach; ?></div>
<nav class="pagination" aria-label="Phân trang lịch sử"><?php if ($page > 1): ?><a class="button button-secondary" href="history.php?<?= cbp_e(http_build_query(['status'=>$status, 'search'=>$search, 'page'=>$page-1])) ?>">Trang trước</a><?php endif; ?><?php if ($page * 12 < $count): ?><a class="button button-secondary" href="history.php?<?= cbp_e(http_build_query(['status'=>$status, 'search'=>$search, 'page'=>$page+1])) ?>">Trang sau</a><?php endif; ?></nav>
<?php endif; cbp_footer(); ?>
