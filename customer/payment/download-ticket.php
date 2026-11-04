<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
try {
    $customer = cbp_require_customer();
    $ticket = cbp_owned_ticket(cbp_request_id('id', $_GET), (int) $customer['id']);
    $snapshot = cbp_snapshot($ticket);
    $css = file_get_contents(__DIR__ . '/checkout.css');
    if ($css === false) { throw new RuntimeException('Ticket stylesheet missing'); }
    ob_start();
    cbp_ticket_markup($ticket, $snapshot, true);
    $markup = ob_get_clean();
} catch (Throwable $error) { cbp_handle_error($error); }
$name = preg_replace('/[^A-Z0-9-]/', '', $ticket['ticket_code']);
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="ve-' . $name . '.html"');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Vé <?= cbp_e($ticket['ticket_code']) ?></title><style><?= $css ?></style></head><body><main class="container page"><div class="ticket-wrap"><?= $markup ?></div></main></body></html>
