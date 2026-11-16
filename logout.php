<?php
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cinema%20booking%20web/index.php', true, 303);
    exit;
}
verifyCsrfToken();

unset($_SESSION['customer_user'], $_SESSION['user_id'], $_SESSION['full_name'], $_SESSION['fullname'], $_SESSION['email'], $_SESSION['role'],
    $_SESSION['csrf_token'], $_SESSION['flash'], $_SESSION['kiet_demo_customer_id'], $_SESSION['cbp_payment_keys'], $_SESSION['cbp_demo_nonce'], $_SESSION['cbp_csrf']);
session_regenerate_id(true);
header('Location: cinema%20booking%20web/index.php', true, 303);
exit;
