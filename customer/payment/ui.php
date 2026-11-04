<?php
declare(strict_types=1);

function cbp_header(string $title, ?array $customer, string $active = ''): void
{
    ?>
    <!doctype html><html lang="vi"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= cbp_e($title) ?> · CINEBOOK</title>
    <link rel="stylesheet" href="checkout.css?v=2"><script src="app.js" defer></script>
    </head><body>
    <a class="skip-link" href="#main">Đến nội dung chính</a>
    <header class="site-header"><div class="container header-inner">
        <a class="brand" href="checkout.php">CINE<span>BOOK</span><i></i></a>
        <?php if ($customer): ?><div class="account"><span class="account-icon" aria-hidden="true">KH</span><span><?= cbp_e($customer['fullname']) ?></span><form method="post" action="logout.php"><?php cbp_csrf_field(); ?><button class="text-button" type="submit">Thoát</button></form></div><?php endif; ?>
    </div>
    <nav class="container nav" aria-label="Điều hướng tài khoản">
        <?php foreach (['checkout.php'=>['orders','Đơn hàng'], 'tickets.php'=>['tickets','Vé của tôi'], 'history.php'=>['history','Lịch sử'], 'policy.php'=>['policy','Quy định hủy']] as $href=>$item): ?>
            <a href="<?= cbp_e($href) ?>" <?= $active === $item[0] ? 'aria-current="page"' : '' ?>><?= cbp_e($item[1]) ?></a>
        <?php endforeach; ?>
        <?php if (cbp_demo_allowed() && is_file(__DIR__ . '/demo.php')): ?><a href="demo.php" <?= $active === 'demo' ? 'aria-current="page"' : '' ?>>Tạo đơn demo</a><?php endif; ?>
    </nav></header>
    <main id="main" class="container page">
    <div class="demo-note"><strong>BẢN DEMO HỌC TẬP</strong> · Thanh toán và hoàn tiền đều được mô phỏng, không thu tiền thật.</div>
    <?php
}

function cbp_footer(): void
{
    ?></main><footer class="site-footer"><div class="container">CINEBOOK · Hệ thống đặt vé xem phim · Nhóm 06 · Thanh toán mô phỏng</div></footer></body></html><?php
}

function cbp_csrf_field(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . cbp_e(cbp_csrf_token()) . '">';
}

function cbp_heading(string $title, string $subtitle = '', string $code = ''): void
{
    ?><div class="page-heading"><div><p class="eyebrow">TÀI KHOẢN CINEBOOK</p><h1><?= cbp_e($title) ?></h1><?php if ($subtitle): ?><p class="muted"><?= cbp_e($subtitle) ?></p><?php endif; ?></div><?php if ($code): ?><div class="order-code"><span>Mã tham chiếu</span><strong><?= cbp_e($code) ?></strong></div><?php endif; ?></div><?php
}

function cbp_payment_label(array $order): string
{
    if (($order['refund_status'] ?? '') === 'simulated_refunded') { return 'Đã hoàn tiền demo'; }
    return ['pending'=>'Chờ thanh toán', 'paid'=>'Đã thanh toán', 'failed'=>'Thanh toán thất bại'][$order['payment_status']] ?? 'Cần kiểm tra';
}

function cbp_booking_label(string $value): string
{
    return ['pending'=>'Chờ xử lý', 'confirmed'=>'Đã xác nhận', 'cancelled'=>'Đã hủy'][$value] ?? 'Cần kiểm tra';
}

function cbp_empty(string $title, string $message, string $url = 'checkout.php', string $label = 'Xem đơn hàng'): void
{
    ?><section class="panel empty-state"><span class="empty-icon" aria-hidden="true">◇</span><h2><?= cbp_e($title) ?></h2><p class="muted"><?= cbp_e($message) ?></p><a class="button button-secondary" href="<?= cbp_e($url) ?>"><?= cbp_e($label) ?></a></section><?php
}

function cbp_ticket_markup(array $ticket, array $snapshot, bool $standalone = false): void
{
    $state = cbp_ticket_state($ticket, $snapshot);
    ?>
    <article class="ticket-paper <?= $state['tone'] === 'neutral' ? 'ticket-muted' : '' ?>">
        <div class="ticket-top"><div><p class="eyebrow">CINEBOOK · VÉ ĐIỆN TỬ DEMO</p><h2><?= cbp_e($snapshot['movie']) ?></h2></div><span class="pill <?= cbp_e($state['tone']) ?>"><?= cbp_e($state['label']) ?></span></div>
        <dl class="info-grid">
            <div class="wide"><dt>Rạp / phòng chiếu</dt><dd><?= cbp_e($snapshot['cinema'] ?? '') ?> · <?= cbp_e($snapshot['room'] ?? '') ?></dd><dd class="address"><?= cbp_e($snapshot['address'] ?? '') ?></dd></div>
            <div><dt>Ngày chiếu</dt><dd><?= cbp_e(cbp_date_label($snapshot['start'], 'd/m/Y')) ?></dd></div>
            <div><dt>Giờ chiếu</dt><dd><?= cbp_e(cbp_date_label($snapshot['start'], 'H:i')) ?> – <?= cbp_e(cbp_date_label($snapshot['end'], 'H:i')) ?></dd></div>
            <div><dt>Khách hàng</dt><dd><?= cbp_e($snapshot['name'] ?? '') ?></dd></div>
            <div><dt>Tổng tiền đã thanh toán</dt><dd><?= cbp_e(cbp_money(cbp_cents($snapshot['total_amount']))) ?></dd></div>
        </dl>
        <div class="seat-block"><p class="field-label">Ghế trên vé</p><div class="seat-chips"><?php foreach ($snapshot['seats'] as $seat): ?><span class="seat-chip"><?= cbp_e($seat['number']) ?></span><?php endforeach; ?></div></div>
        <div class="ticket-stub"><span>MÃ VÉ ĐIỆN TỬ</span><strong><?= cbp_e($ticket['ticket_code']) ?></strong><small>Đơn <?= cbp_e($snapshot['booking_code'] ?? '') ?> · Phát hành <?= cbp_e(cbp_date_label($ticket['issued_at'])) ?></small></div>
        <p class="ticket-footnote">Vé phục vụ bài tập lập trình web. Không có giá trị vào rạp thật. Trạng thái hiển thị tại <?= cbp_e(cbp_now()) ?> (giờ Việt Nam); bản tải/in không tự cập nhật khi vé bị hủy.</p>
    </article>
    <?php
}
