<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'use_strict_mode' => true,
        'cookie_httponly' => true,
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'cookie_samesite' => 'Lax',
    ]);
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function currentCustomer(): ?array {
    $id = $_SESSION['customer_user']['id'] ?? null;
    if (!is_int($id) || $id <= 0) {
        return null;
    }

    static $customers = [];
    if (!array_key_exists($id, $customers)) {
        global $conn;
        if (!isset($conn)) {
            require_once __DIR__ . '/../config/database.php';
        }
        $stmt = $conn->prepare("SELECT id, full_name AS fullname, email, phone, created_at FROM users WHERE id = ? AND role = 'customer'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $customers[$id] = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$customers[$id]) {
            unset($_SESSION['customer_user']);
        }
    }
    return $customers[$id];
}

function isLoggedIn(): bool {
    return currentCustomer() !== null;
}

function requireLogin(): array {
    $customer = currentCustomer();
    if (!$customer) {
        setFlash('warning', 'Vui lòng đăng nhập để tiếp tục.');
        header('Location: login.php', true, 303);
        exit;
    }
    return $customer;
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(): void {
    $sent = $_POST['csrf_token'] ?? '';
    $saved = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sent) || $saved === '' || !hash_equals($saved, $sent)) {
        http_response_code(403);
        exit('Phiên gửi biểu mẫu không hợp lệ. Vui lòng tải lại trang.');
    }
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (empty($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}
