<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
try {
    cbp_require_post();
    unset($_SESSION['customer_user'], $_SESSION['kiet_demo_customer_id'], $_SESSION['cbp_payment_keys'], $_SESSION['cbp_demo_nonce'], $_SESSION['cbp_csrf']);
    session_regenerate_id(true);
    cbp_redirect(cbp_demo_allowed() && is_file(__DIR__ . '/dev-login.php') ? 'dev-login.php' : 'checkout.php');
} catch (Throwable $error) { cbp_handle_error($error); }
