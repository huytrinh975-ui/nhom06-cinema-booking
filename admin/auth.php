<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../config/database.php';

header('Cache-Control: no-store');
$current_user = null;
$adminId = (int) ($_SESSION['admin_user_id'] ?? 0);
if ($adminId > 0) {
    $stmt = $conn->prepare("SELECT id, fullname, email FROM users WHERE id = ? AND role = 'admin' AND status = 'active'");
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $current_user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$current_user) {
    unset($_SESSION['admin_user_id']);
    if (str_starts_with(basename($_SERVER['SCRIPT_NAME'] ?? ''), 'ajax_')) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập quản trị.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    admin_redirect('login.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    admin_check_csrf();
}

function admin_valid_date(mixed $date): bool
{
    if (!is_string($date) || !preg_match('/^[1-9]\d{3}-\d{2}-\d{2}$/D', $date)) {
        return false;
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}
