<?php
/**
 * Kết nối Cơ sở dữ liệu MySQL (MySQLi & PDO)
 * Dự án: Hệ thống đặt vé xem phim trực tuyến (nhom06-cinema-booking)
 */

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'cinema_booking_db';
$db_port = 3306;

// Khởi tạo kết nối MySQLi ($conn)
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

// Kiểm tra lỗi kết nối
if ($conn->connect_error) {
    die("<div style='font-family: Arial; padding: 20px; background: #ffebee; border: 1px solid #f44336; border-radius: 8px; margin: 20px auto; max-width: 600px;'>
            <h3 style='color: #c62828; margin-top: 0;'><i class='fa fa-exclamation-triangle'></i> Lỗi kết nối Cơ sở dữ liệu!</h3>
            <p><strong>Chi tiết:</strong> " . htmlspecialchars($conn->connect_error) . "</p>
            <p>Vui lòng đảm bảo dịch vụ MySQL trong XAMPP đang chạy và bạn đã nhập file <code>database.sql</code> vào phpMyAdmin.</p>
        </div>");
}

// Thiết lập bảng mã utf8mb4 hỗ trợ tiếng Việt có dấu và biểu tượng đầy đủ
$conn->set_charset("utf8mb4");

// Khởi tạo thêm kết nối PDO ($pdo) nếu cần dùng
try {
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
} catch (PDOException $e) {
    // Không dừng chương trình nếu PDO lỗi để MySQLi vẫn hoạt động
}

/**
 * Hàm định dạng tiền tệ VND
 */
if (!function_exists('format_currency')) {
    function format_currency($amount) {
        return number_format((float)$amount, 0, ',', '.') . ' ₫';
    }
}

/**
 * Hàm định dạng ngày giờ chuẩn Việt Nam
 */
if (!function_exists('format_datetime')) {
    function format_datetime($datetime) {
        if (!$datetime) return '';
        $time = strtotime($datetime);
        return date('d/m/Y H:i', $time);
    }
}

if (!function_exists('format_date')) {
    function format_date($date) {
        if (!$date) return '';
        $time = strtotime($date);
        return date('d/m/Y', $time);
    }
}
