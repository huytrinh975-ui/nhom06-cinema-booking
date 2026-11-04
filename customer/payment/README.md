# Thanh toán và vé điện tử — CINEBOOK

Module khách hàng của dự án [nhom06-cinema-booking](https://github.com/huytrinh975-ui/nhom06-cinema-booking), dùng PHP/MySQLi và kết nối sẵn có trong `config/database.php`.

**Phạm vi:** thanh toán và hoàn tiền mô phỏng cho bài tập; không chuyển tiền thật.

## Chức năng

| Chức năng | Trang chính |
| --- | --- |
| Kiểm tra phim, suất chiếu, ghế, khách hàng và số tiền của đơn | `checkout.php` |
| Thanh toán demo thành công/thất bại, cho phép thử lại | `pay.php` |
| Kết quả và lịch sử từng lần thanh toán | `result.php`, `checkout.php` |
| Phát hành vé khi thanh toán thành công; in/lưu PDF, tải HTML | `ticket.php`, `download-ticket.php` |
| Xem vé đã đặt và lọc theo trạng thái | `tickets.php` |
| Lịch sử đơn hàng, tìm mã đơn, bộ lọc và phân trang | `history.php` |
| Hủy đơn/vé, thu hồi vé, giải phóng ghế và hoàn tiền demo | `cancel.php` |

## Chạy demo trên máy đã có dữ liệu main

1. Đặt dự án trong `htdocs/cinema-test` của XAMPP. Thư mục module là `customer/payment`.
2. Trong cấu hình cá nhân, kết nối tới database kiểm thử **`cinema_booking_test`** đã có 8 bảng main và dữ liệu mẫu. Apache và MySQL Server cần đang chạy; không bật hai MySQL Server trùng cổng.
3. Mở **http://localhost/cinema-test/customer/payment/setup-demo.php**.
4. Bấm **Cài đặt và tạo đơn demo**. Trang bổ sung 4 bảng module nếu chưa có, tạo một đơn có suất chiếu tương lai và mở tài khoản khách mẫu mã 2.
5. Từ đơn mới, thử thất bại → thử lại thành công → xem/tải/in vé → lịch sử → hủy vé.

Nếu tên thư mục dự án khác, thay `cinema-test` trong URL. Với người clone lần đầu, tạo và nạp 8 bảng/dữ liệu main vào database kiểm thử trước khi mở setup. File `database.sql` gốc có các lệnh xóa bảng: chỉ dùng cho database khởi tạo/được phép đặt lại, không dùng để nâng cấp dữ liệu đang chạy.

Hướng dẫn thao tác, tình huống thử, SQL kiểm tra và xử lý lỗi: **[HUONG_DAN.md](HUONG_DAN.md)**.

## Cơ sở dữ liệu bổ sung

Nhập `sql/001_payment_module.sql` sau khi chọn database hiện có, hoặc để trang setup demo thực hiện. SQL dùng `CREATE TABLE IF NOT EXISTS`, không xóa bảng và không đặt lại dữ liệu main.

| Bảng | Mục đích |
| --- | --- |
| `cbp_payments` | Các lần thanh toán, khóa chống xử lý trùng |
| `cbp_tickets` | Vé và snapshot thông tin tại thời điểm phát hành |
| `cbp_cancellations` | Lý do hủy, số tiền hoàn và thời điểm xử lý |
| `cbp_seat_claims` | Ghế đã thanh toán, khóa duy nhất theo suất chiếu + ghế |

## Quy định demo

- Hủy khi còn ít nhất **60 phút** trước giờ bắt đầu; đúng mốc 60 phút được chấp nhận.
- Hoàn **100% bằng mô phỏng** đối với đơn đã trả tiền; đơn chưa trả có tiền hoàn bằng 0.
- Hủy giữ lịch sử, thu hồi vé và giải phóng ghế. Gửi lại yêu cầu không tạo thêm hoàn tiền.
- Các giá trị nằm trong `settings.php`; trang `policy.php` hiển thị cùng cấu hình máy chủ sử dụng.

## Ghép với phần đăng nhập và đặt ghế

Sau khi thành viên phụ trách đăng nhập xác thực thành công tài khoản khách hàng:

```php
session_regenerate_id(true);
$_SESSION['customer_user'] = ['id' => (int) $user['id']];
unset($_SESSION['kiet_demo_customer_id']);
```

Module kiểm tra lại vai trò `customer`, trạng thái `active` và quyền sở hữu đơn ở máy chủ. Phiên `admin_user` không xác thực khách hàng. Nếu nhóm dùng session khác, điều chỉnh hàm `cbp_customer()` trong `bootstrap.php`.

Sau khi phần chọn ghế lưu xong đơn và chi tiết ghế, chuyển tới `customer/payment/checkout.php?booking_id=ID` bằng đường dẫn tương đối phù hợp với file gọi. Để đồng bộ chống đặt trùng với module, transaction tạo đơn cần khóa cùng hàng suất chiếu bằng `SELECT ... FOR UPDATE` và kiểm tra các ghế đang được giữ/đã thanh toán. Chi tiết hợp đồng ghép mã nằm trong `HUONG_DAN.md`.

Các trang `setup-demo.php`, `dev-login.php`, `demo.php` chỉ hoạt động từ loopback và khi database có tên `cinema_booking_test`. Đây là công cụ chạy thử; bản triển khai thật cần đăng nhập khách hàng thật và cổng thanh toán thay cho bộ xử lý DEMO.

## Kiểm tra và giới hạn tích hợp

- 17 file PHP đã qua kiểm tra cú pháp.
- 130 kiểm tra HTTP/dữ liệu đã đạt với PHP 8.3.6 và MySQL 8.0.46: quyền sở hữu, CSRF, giá tiền, thử lại, request lặp, giao dịch đồng thời, phát hành/thu hồi vé, hủy sát giờ, hoàn tiền và giải phóng ghế.
- Người thực hiện đã xác nhận chạy thử các chức năng trên XAMPP cá nhân.
- Bản chuẩn bị được đối chiếu với `main` tại commit `a9524814b3bd16c80691cd2320b8b8ad0b0cd6cd`; các file main gốc không thay đổi.
- Admin main hiện cộng tổng đơn `paid` mà chưa trừ khoản hoàn. Truy vấn doanh thu sau hoàn và lưu ý với thao tác sửa/xóa trực tiếp trong admin được trình bày trong `HUONG_DAN.md` để thành viên phụ trách admin tích hợp.

Module không cung cấp trang chọn phim/ghế hoặc đăng nhập khách hàng thật; các chức năng đó thuộc phần khác của nhóm. Dữ liệu demo cho phép chạy và trình diễn độc lập phần việc này.
