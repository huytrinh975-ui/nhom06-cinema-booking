<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
try {
    $customer = cbp_require_customer();
    $filter = cbp_text($_GET['status'] ?? 'all', 20);
    $filters = ['all'=>'Tất cả vé', 'upcoming'=>'Sắp chiếu', 'ongoing'=>'Đang chiếu', 'past'=>'Đã kết thúc', 'cancelled'=>'Vé đã hủy'];
    if (!isset($filters[$filter])) { throw new CBPUserError('Bộ lọc vé không hợp lệ.', 400); }
    $page = isset($_GET['page']) ? cbp_request_id('page', $_GET) : 1;
    if ($page > 100000) { throw new CBPUserError('Trang yêu cầu không hợp lệ.', 400); }
    $where = 'b.user_id = ?'; $types = 'i'; $params = [(int) $customer['id']];
    if ($filter === 'cancelled') { $where .= " AND (t.status = 'cancelled' OR b.booking_status = 'cancelled')"; }
    elseif ($filter !== 'all') {
        $where .= " AND t.status = 'valid' AND b.booking_status = 'confirmed' AND b.payment_status = 'paid' AND st.status <> 'cancelled'";
        if ($filter === 'upcoming') { $where .= " AND JSON_UNQUOTE(JSON_EXTRACT(t.snapshot_json, '$.start')) > ?"; $types .= 's'; $params[] = cbp_now(); }
        elseif ($filter === 'past') { $where .= " AND JSON_UNQUOTE(JSON_EXTRACT(t.snapshot_json, '$.end')) <= ?"; $types .= 's'; $params[] = cbp_now(); }
        else { $where .= " AND JSON_UNQUOTE(JSON_EXTRACT(t.snapshot_json, '$.start')) <= ? AND JSON_UNQUOTE(JSON_EXTRACT(t.snapshot_json, '$.end')) > ?"; $types .= 'ss'; $params[] = cbp_now(); $params[] = cbp_now(); }
    }
    $from = ' FROM cbp_tickets t JOIN bookings b ON b.id = t.booking_id JOIN showtimes st ON st.id = b.showtime_id WHERE ';
    $count = (int) cbp_query('SELECT COUNT(*) AS n' . $from . $where, $types, $params)[0]['n'];
    $tickets = cbp_query('SELECT t.*, b.booking_status, b.payment_status, st.status AS showtime_status' . $from . $where . ' ORDER BY t.id DESC LIMIT ? OFFSET ?', $types . 'ii', array_merge($params, [12, ($page-1)*12]));
    foreach ($tickets as &$item) { $item['_snapshot'] = cbp_snapshot($item); $item['_state'] = cbp_ticket_state($item, $item['_snapshot']); } unset($item);
} catch (Throwable $error) { cbp_handle_error($error); }
cbp_header('Vé của tôi', $customer, 'tickets');
cbp_heading('Vé của tôi', 'Các vé điện tử đã được phát hành sau khi thanh toán thành công.');
?>
<form class="panel filter-bar" method="get" action="tickets.php"><div><label for="status">Lọc vé</label><select id="status" name="status"><?php foreach ($filters as $key=>$label): ?><option value="<?= cbp_e($key) ?>" <?= $filter === $key ? 'selected' : '' ?>><?= cbp_e($label) ?></option><?php endforeach; ?></select></div><button class="button" type="submit">Xem vé</button></form>
<?php if (!$tickets): cbp_empty('Chưa có vé phù hợp', 'Vé điện tử được tạo khi giao dịch thanh toán demo thành công. Bạn có thể đổi bộ lọc hoặc kiểm tra các đơn đặt vé.'); else: ?>
<p class="muted small space-bottom"><?= $count ?> vé · Trang <?= $page ?></p><div class="ticket-grid"><?php foreach ($tickets as $ticket): $snap=$ticket['_snapshot']; $state=$ticket['_state']; ?><article class="panel mini-ticket"><div><span class="pill <?= cbp_e($state['tone']) ?>"><?= cbp_e($state['label']) ?></span><p class="eyebrow space-top"><?= cbp_e($ticket['ticket_code']) ?></p><h2><?= cbp_e($snap['movie']) ?></h2><p class="muted"><?= cbp_e($snap['cinema']) ?> · <?= cbp_e($snap['room']) ?></p><p class="space-top"><?= cbp_e(cbp_date_label($snap['start'])) ?></p><div class="seat-chips"><?php foreach ($snap['seats'] as $seat): ?><span class="seat-chip"><?= cbp_e($seat['number']) ?></span><?php endforeach; ?></div></div><div class="inline-actions space-top"><a class="button" href="ticket.php?id=<?= (int) $ticket['id'] ?>">Mở vé điện tử</a><a class="text-link" href="checkout.php?booking_id=<?= (int) $ticket['booking_id'] ?>">Xem đơn</a></div></article><?php endforeach; ?></div><nav class="pagination" aria-label="Phân trang vé"><?php if ($page>1): ?><a class="button button-secondary" href="tickets.php?<?= cbp_e(http_build_query(['status'=>$filter,'page'=>$page-1])) ?>">Trang trước</a><?php endif; ?><?php if ($page*12<$count): ?><a class="button button-secondary" href="tickets.php?<?= cbp_e(http_build_query(['status'=>$filter,'page'=>$page+1])) ?>">Trang sau</a><?php endif; ?></nav>
<?php endif; cbp_footer(); ?>
