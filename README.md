# Nhóm 06 · Hệ thống đặt vé xem phim

## Chạy phần quản trị

1. Bật Apache và MySQL trong XAMPP. Đặt thư mục chứa `index.php` và `database.sql` vào `htdocs`.
2. Trong phpMyAdmin, nhập `database.sql` nếu cài mới. File này tạo lại các bảng và nạp dữ liệu mẫu. Nếu đã có `cinema_booking_db`, nhập `admin_upgrade.sql` để thêm bảng thể loại và voucher, đồng thời chuyển dữ liệu thể loại cũ nếu có.
3. Mở `http://localhost/nhom06-cinema-booking-main/admin/login.php`, thay tên thư mục theo tên đã đặt trong `htdocs`.
4. Với dữ liệu mẫu, đăng nhập bằng email `trinhminhhuy@gmail.com` và mật khẩu `admin123`. Đổi mật khẩu trong **Tài khoản khách hàng** trước khi dùng ngoài máy thử nghiệm.

Trong **Quản lý phim**, chọn các thể loại khi thêm hoặc sửa phim. Vào **Cụm rạp & Phòng chiếu**, mở nút **Chỉnh sửa sơ đồ ghế** của phòng để thêm, sửa hoặc xóa từng ghế. Ghế đã có đơn đặt được giữ nguyên để bảo toàn lịch sử. **Thống kê doanh thu** lọc theo ngày đặt và rạp, tính đơn đã thanh toán còn hiệu lực. **Voucher** quản lý mã giảm giá, mức giảm, giá trị đơn tối thiểu và thời gian áp dụng ở phía Admin.
