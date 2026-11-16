# Nhóm 06 · Hệ thống đặt vé xem phim

## Chạy dự án với XAMPP

1. Bật Apache và MySQL trong XAMPP. Đặt thư mục chứa `index.php` và `database.sql` vào `htdocs`.
2. Trong phpMyAdmin, nhập `database.sql` ở thư mục gốc. File này tạo các bảng còn thiếu và chỉ nạp dữ liệu mẫu khi cơ sở dữ liệu chưa có bảng nào. Với cơ sở dữ liệu cũ có cột `movies.genre`, nhập `admin_upgrade.sql` trước để chuyển thể loại cũ.
3. Mở `http://localhost/nhom06-cinema-booking-main/admin/login.php`, thay tên thư mục theo tên đã đặt trong `htdocs`.
4. Với dữ liệu mẫu, đăng nhập bằng email `trinhminhhuy@gmail.com` và mật khẩu `admin123`. Đổi mật khẩu trong **Tài khoản khách hàng** trước khi dùng ngoài máy thử nghiệm.

Trong **Quản lý phim**, chọn các thể loại khi thêm hoặc sửa phim. Vào **Cụm rạp & Phòng chiếu**, mở nút **Chỉnh sửa sơ đồ ghế** của phòng để thêm, sửa hoặc xóa từng ghế. Ghế đã có đơn đặt được giữ nguyên để bảo toàn lịch sử. **Thống kê doanh thu** lọc theo ngày đặt và rạp, tính đơn đã thanh toán còn hiệu lực. **Voucher** quản lý mã giảm giá, mức giảm, giá trị đơn tối thiểu và thời gian áp dụng ở phía Admin.

## Trang khách hàng

- Trang phim: `http://localhost/nhom06-cinema-booking-main/cinema%20booking%20web/index.php`.
- Đăng ký và đăng nhập: `register.php` và `login.php` ở thư mục gốc của dự án.
- Trang **Tài khoản** cho phép xem hồ sơ, sửa họ tên và số điện thoại, hoặc đăng xuất.

Nếu một lần nhập SQL cũ dừng giữa chừng, nhập `database.sql` đã cập nhật để tạo các bảng còn thiếu. `repair_missing_tables.sql` khôi phục riêng các bảng rạp, phòng, ghế, suất chiếu và đơn vé khi các bảng phim và tài khoản vẫn còn.
