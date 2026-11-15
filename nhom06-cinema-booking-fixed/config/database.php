<?php
/**
 * Cấu hình kết nối dùng chung cho dự án CINEBOOK.
 * $conn: MySQLi cho admin, đặt vé và module thanh toán.
 * $pdo : PDO cho đăng nhập, đăng ký và hồ sơ tài khoản.
 */
declare(strict_types=1);

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'movie_booking';
$db_port = 3306;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
    $conn->set_charset('utf8mb4');

    $pdo = new PDO(
        "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (Throwable $e) {
    error_log('[CINEBOOK database] ' . $e->getMessage());
    http_response_code(500);
    exit('Không thể kết nối cơ sở dữ liệu. Hãy kiểm tra MySQL và thông tin trong config/database.php.');
}

if (!function_exists('format_currency')) {
    function format_currency($amount): string {
        return number_format((float)$amount, 0, ',', '.') . ' ₫';
    }
}
if (!function_exists('format_datetime')) {
    function format_datetime($datetime): string {
        if (!$datetime) return '';
        return date('d/m/Y H:i', strtotime((string)$datetime));
    }
}
if (!function_exists('format_date')) {
    function format_date($date): string {
        if (!$date) return '';
        return date('d/m/Y', strtotime((string)$date));
    }
}
