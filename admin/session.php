<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'use_strict_mode' => true,
        'cookie_httponly' => true,
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'cookie_samesite' => 'Lax',
    ]);
}

function admin_csrf_field(): void
{
    $_SESSION['admin_csrf'] ??= bin2hex(random_bytes(32));
    echo '<input type="hidden" name="csrf_token" value="' . $_SESSION['admin_csrf'] . '">';
}

function admin_check_csrf(): void
{
    $token = $_POST['csrf_token'] ?? null;
    if (!isset($_SESSION['admin_csrf']) || !is_string($token) || !hash_equals($_SESSION['admin_csrf'], $token)) {
        http_response_code(403);
        exit('Phiên làm việc đã thay đổi. Vui lòng tải lại trang rồi thử lại.');
    }
}

function admin_redirect(string $page): never
{
    header('Location: ' . $page, true, 303);
    exit;
}
