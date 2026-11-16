<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

try {
    if (cbp_demo_allowed()
        && !isset($_SESSION['customer_user'])
        && cbp_customer() === null) {
        cbp_redirect('dev-login.php');
    }

    cbp_require_customer();
    cbp_redirect('checkout.php');
} catch (Throwable $error) {
    cbp_handle_error($error);
}
