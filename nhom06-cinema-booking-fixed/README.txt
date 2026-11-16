# CINEBOOK – Hệ thống đặt vé xem phim (Nhóm 06)

## 1. Đường dẫn chạy trên XAMPP
1. Giải nén thư mục `nhom06-cinema-booking-main` vào `C:\xampp\htdocs\`.
2. Mở XAMPP, bật **Apache** và **MySQL**.
3. Mở `http://localhost/nhom06-cinema-booking-main/`.

## 2. Cấu hình database
- Database mà mã nguồn chính sử dụng: `cinema_booking_db`.
- Mở `config/database.php` để kiểm tra host, port, username và password.
- Cấu hình mặc định cho XAMPP: `localhost:3306`, user `root`, mật khẩu để trống.
- File cấu hình tạo hai kết nối: `$conn` (MySQLi) cho Admin/thanh toán và `$pdo` (PDO) cho đăng nhập/đăng ký/hồ sơ.

**QUAN TRỌNG – dữ liệu của nhóm đã có:** Không import lại `database.sql` nếu database đang chứa dữ liệu. File này có lệnh `DROP TABLE IF EXISTS` và sẽ xóa các bảng/dữ liệu hiện tại trước khi tạo lại. Hãy sao lưu database trong phpMyAdmin trước mọi thay đổi cấu trúc.

Nếu phần thanh toán chưa có các bảng `cbp_*`, có thể chạy riêng `customer/payment/sql/001_payment_module.sql` sau khi sao lưu. File này chỉ dùng `CREATE TABLE IF NOT EXISTS`, không xóa các bảng chính.

## 3. Tài khoản và quyền
- Đăng ký tài khoản mới tại `register.php`; tài khoản mới có vai trò `customer`.
- Đăng nhập tại `login.php`.
- Chỉ tài khoản có `role = 'admin'` trong bảng `users` mới truy cập được `admin/`.
- Không có tài khoản admin giả lập tự động. Nếu cần cấp quyền admin, người quản trị có thể cập nhật đúng tài khoản trong phpMyAdmin sau khi xác minh danh tính:
  `UPDATE users SET role='admin' WHERE email='email-chinh-xac@example.com';`

## 4. Các phân hệ
- `admin/`: quản lý phim, rạp, phòng, suất chiếu, người dùng và đơn đặt vé.
- `customer/payment/`: thanh toán demo, lịch sử đơn, vé điện tử và các trang liên quan.
- `includes/`: xác thực, header và footer dùng chung.
- `config/`: cấu hình kết nối database.
- `css/`: giao diện trang khách hàng.
- `uploads/`: hình ảnh tải lên.

## 5. Lưu ý cấu trúc
Thư mục `nhom06-cinema-booking/` bên trong là bản mã nguồn tài khoản cũ/được lồng từ một phiên bản khác. Website chính phải chạy từ thư mục gốc `nhom06-cinema-booking-main`, không chạy bản lồng bên trong. Không xóa thư mục cũ trước khi nhóm xác nhận không còn cần dùng.
