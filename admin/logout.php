<?php
require_once __DIR__ . '/session.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}
admin_check_csrf();
unset($_SESSION['admin_user_id'], $_SESSION['admin_user'], $_SESSION['admin_csrf'], $_SESSION['flash_success'], $_SESSION['flash_error']);
session_regenerate_id(true);
admin_redirect('login.php');
