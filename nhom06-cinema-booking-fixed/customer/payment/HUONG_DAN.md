# CINEBOOK — Phần thanh toán, vé điện tử và hủy vé DEMO

Hoàn thiện 7 công việc được phân công, dùng PHP + MySQLi và cấu trúc main `nhom06-cinema-booking-main` đã cung cấp. Toàn bộ thanh toán/hoàn tiền là mô phỏng, phục vụ bài tập; không kết nối ngân hàng.

## A. Cập nhật trên máy đang chạy của bạn

1. Giải nén ZIP bản hoàn chỉnh.
2. Trong XAMPP → **Explorer** → `htdocs` → **`cinema-test`**, chép thư mục **`customer`** từ ZIP vào. Chọn thay thế các file cùng tên của bản bước 1.
3. Đường dẫn phải là `htdocs/cinema-test/customer/payment/setup-demo.php`. `customer` nằm cùng cấp với `admin`, `config`, `uploads` và `index.php`.
4. Giữ cấu hình `config/database.php` đang kết nối thành công trên máy bạn: database **`cinema_booking_test`**, MySQL Server riêng cổng **3306**, mật khẩu của chính bạn. ZIP này không chứa cấu hình/mật khẩu đó.
5. Bật Apache và giữ MySQL Server riêng đang chạy như trước.
6. Mở: **http://localhost/cinema-test/customer/payment/setup-demo.php**
7. Bấm **Cài đặt và tạo đơn demo**. Trang sẽ thêm 4 bảng còn thiếu, tạo một đơn có suất chiếu tương lai và mở tài khoản mẫu Trần Văn Khách (`users.id = 2`).
8. Bấm **Tiếp tục thanh toán** để bắt đầu thử.

Không nhập lại `database.sql` gốc cho bước cập nhật này: script đó có lệnh xóa và tạo lại các bảng main. Trang setup chỉ thêm bảng module và dữ liệu demo mới.

Nếu bạn đổi tên thư mục dự án, thay `cinema-test` trong URL bằng tên thư mục đó. Nếu trình duyệt vẫn giữ giao diện cũ, nhấn **Ctrl + F5**.

## B. Chạy thử toàn bộ chức năng

### Luồng 1 — Thanh toán thất bại rồi thử lại thành công

1. Mở đơn vừa tạo; kiểm tra phim, rạp, phòng, ngày giờ, hai ghế và tổng tiền. Với hai ghế thường mẫu, tổng là **200.000 ₫**.
2. Bấm **Tiếp tục thanh toán** → **Mô phỏng thanh toán thất bại**.
3. Trang kết quả báo thất bại. Vào **Kiểm tra đơn / thử lại**, rồi **Thử thanh toán lại**.
4. Chọn **Mô phỏng thanh toán thành công**.
5. Trang kết quả báo thành công; đơn được xác nhận và một vé điện tử được phát hành cho toàn bộ ghế trong đơn.
6. Bấm **Xem vé điện tử**. Thử **In vé / Lưu PDF** hoặc **Tải vé HTML**. File HTML tải xuống mở riêng được và không cần mạng.

### Luồng 2 — Vé của tôi và lịch sử

1. Bấm **Vé của tôi** trên thanh điều hướng: vé vừa phát hành phải xuất hiện.
2. Thử các bộ lọc sắp chiếu, đang chiếu, đã kết thúc, đã hủy.
3. Bấm **Lịch sử**: thấy các đơn cũ và mới, số tiền, trạng thái, liên kết xem đơn/vé. Có tìm mã đơn, lọc trạng thái và phân trang.
4. Trong trang chi tiết đơn, khu vực **Các lần thanh toán của đơn** thể hiện cả lần thất bại và lần thành công.

### Luồng 3 — Hủy vé và hoàn tiền mô phỏng

1. Từ vé hoặc đơn vừa thanh toán, chọn **Hủy vé** / **Yêu cầu hủy vé / đơn**.
2. Xem hạn hủy và số tiền hoàn dự kiến, nhập lý do → **Xác nhận hủy vé / đơn hàng**.
3. Đơn chuyển thành **Đã hủy**; vé hiển thị **Vé đã hủy**; ghế được giải phóng.
4. Trang đơn có biên nhận hủy, mã yêu cầu, lý do, thời điểm và số tiền hoàn mô phỏng. Trong lịch sử, đơn hiển thị **Đã hoàn tiền demo**.
5. Hủy đơn chưa trả tiền vẫn được chấp nhận khi còn trong hạn, nhưng tiền hoàn bằng 0.
6. Gửi lại thao tác hủy hoặc tải lại biên nhận không tạo thêm một khoản hoàn.

### Luồng 4 — Chặn hủy sát giờ

1. Bấm **Tạo đơn demo**.
2. Chọn **Suất sau khoảng 30 phút — thử chặn hủy sát giờ**, rồi tạo đơn.
3. Có thể thanh toán vì phim chưa bắt đầu. Mở trang hủy bằng `cancel.php?booking_id=ID_ĐƠN` để xem thông báo đã hết hạn hủy; gửi POST hủy cũng bị máy chủ từ chối.
4. Nếu chưa tìm được giờ trống gần hiện tại do lịch phòng, trang báo để chọn lại kiểu suất tương lai.

Để thử thêm, bấm **Tạo đơn demo** và tạo đơn mới; không cần nhập lại SQL hoặc sửa các suất chiếu cũ. Các đơn BK trong SQL gốc có thể đã quá giờ, và sẽ bị chặn thanh toán đúng theo thời gian thực.

## C. Quy định của bản demo

- Hủy khi còn **ít nhất 60 phút** trước giờ bắt đầu; đúng mốc 60 phút vẫn được chấp nhận.
- Đơn đã trả tiền được hoàn **100% bằng mô phỏng**, ghi nhận ngay trong giao dịch hủy.
- Đơn chưa trả tiền có khoản hoàn bằng 0.
- Đơn đã hủy không thanh toán lại; khách tạo một đơn mới nếu muốn đặt tiếp.
- Nếu lịch chiếu thay đổi sau khi phát hành, lấy mốc sớm hơn giữa giờ trên vé và giờ suất chiếu hiện tại để tính hạn hủy.
- Đây là quy định được đặt cho bản demo vì main chưa có quy định hủy. Nhóm có thể chỉnh hai giá trị trong **`settings.php`**; trang **Quy định hủy** và kiểm tra máy chủ cùng dùng cấu hình đó.

## D. Cơ sở dữ liệu bổ sung

| Bảng | Nghiệp vụ |
| --- | --- |
| `cbp_payments` | Lưu từng lần thanh toán demo, số tiền, kết quả và khóa chống xử lý trùng |
| `cbp_tickets` | Vé điện tử của đơn đã trả tiền; lưu bản chụp thông tin tại thời điểm phát hành |
| `cbp_cancellations` | Lý do hủy, tiền hoàn mô phỏng và thời gian xử lý; mỗi đơn tối đa một yêu cầu đã xử lý |
| `cbp_seat_claims` | Khóa duy nhất theo suất chiếu + ghế đã thanh toán; xóa khóa khi hủy |

Các bảng dùng InnoDB, UTF-8 và khóa ngoại với bảng main. File **`sql/001_payment_module.sql`** có thể nhập thủ công trong phpMyAdmin sau khi chọn đúng database của nhóm. File này chỉ tạo bảng nếu chưa có, không có `DROP`, không đặt lại dữ liệu main, không tự đổi tên database.

Khi kiểm tra bằng phpMyAdmin:

```sql
SELECT * FROM cbp_payments ORDER BY id DESC;
SELECT id, booking_id, ticket_code, status, issued_at, cancelled_at
FROM cbp_tickets ORDER BY id DESC;
SELECT * FROM cbp_cancellations ORDER BY id DESC;
SELECT * FROM cbp_seat_claims ORDER BY booking_id DESC;
SELECT id, booking_code, payment_status, booking_status
FROM bookings ORDER BY id DESC;
```

Sau khi hủy một đơn đã thanh toán, `bookings.payment_status` giữ `paid` để phản ánh lần thanh toán trước đó; `booking_status` thành `cancelled`, tiền hoàn nằm trong `cbp_cancellations`. Giao diện khách hàng hiển thị khoản hoàn riêng. Không ép tiền đã trả thành `failed` chỉ để giấu giao dịch cũ.

## E. Ghép với code nhóm

### Phạm vi thay đổi

Chỉ thêm/cập nhật thư mục **`customer/payment`**. Không ghi đè `admin`, `config/database.php`, `index.php`, ảnh hoặc file `database.sql` của main. Không có thư viện tải từ CDN, Composer hoặc npm cần cài để chạy module.

### Session đăng nhập khách hàng thật

Main được gửi hiện dùng `admin_user` cho quản trị, chưa có đăng nhập khách hàng. Module giữ hợp đồng session riêng:

```php
// Chỉ chạy sau khi đã xác thực mật khẩu người dùng tại máy chủ.
session_regenerate_id(true);
$_SESSION['customer_user'] = ['id' => (int) $user['id']];
unset($_SESSION['kiet_demo_customer_id']);
```

Hàm `cbp_customer()` trong `bootstrap.php` là chỗ đổi nếu nhóm dùng tên session khác. Mỗi request vẫn kiểm tra lại vai trò `customer` và trạng thái `active` trong database. Không lấy ID khách từ URL/form để xác thực. Không dùng `admin_user` làm khách hàng.

Phiên mẫu chỉ được công nhận khi địa chỉ truy cập là loopback và tên database đúng **`cinema_booking_test`**. Ba trang helper `setup-demo.php`, `dev-login.php`, `demo.php` đều bị chặn ngoài môi trường đó. Các helper được gửi trong gói để thành viên nhóm tự chạy demo; không dùng chúng làm chức năng đăng nhập thật. Bản triển khai thật cần loại bỏ helper, bỏ cơ chế phiên mẫu và thay bộ xử lý DEMO bằng cổng thanh toán phù hợp.

### Chuyển từ phần chọn ghế sang phần thanh toán

Khi phần đặt ghế đã lưu thành công `bookings` và `booking_details`, chuyển tới:

```php
// Ví dụ file gọi nằm ở thư mục gốc của dự án.
header('Location: customer/payment/checkout.php?booking_id=' . (int) $bookingId);
exit;
```

Nếu file gọi nằm trong thư mục khác, đổi đường dẫn tương đối cho đúng vị trí. Module đọc số tiền từ DB và đối chiếu giá từng ghế; không nhận giá tiền từ trình duyệt.

### Đồng bộ giữ ghế

Trong transaction thanh toán/hủy, module khóa suất chiếu trước, rồi khóa đơn, kiểm tra quyền sở hữu và dữ liệu. Mức cô lập dùng `READ COMMITTED` để các request chờ khóa đọc được trạng thái vừa commit. Khóa chính `(showtime_id, seat_id)` của `cbp_seat_claims` ngăn hai giao dịch trong module sở hữu cùng ghế; một yêu cầu thanh toán lặp lại không tạo vé/giao dịch trùng.

Thành viên viết chức năng đặt ghế cần dùng cùng khóa `SELECT id FROM showtimes WHERE id = ? FOR UPDATE` trong transaction tạo đơn, đồng thời kiểm tra các đơn chưa hủy/ghế đã được claim trước khi chèn chi tiết ghế. Module không thể buộc các file khác tuân thủ khóa nếu chúng ghi trực tiếp vào DB. Thời hạn tự hết giữ ghế của đơn chưa trả tiền thuộc phần đặt ghế; bản này dùng quy ước sẵn có của main: đơn chưa hủy vẫn giữ ghế.

### Đồng bộ thống kê admin

Main hiện cộng tất cả đơn `paid` và chưa trừ khoản hoàn. Phần khách hàng trong module tách tổng đã trả và tổng đã hoàn. Nếu người phụ trách admin muốn doanh thu sau hoàn demo, dùng truy vấn:

```sql
SELECT COALESCE(SUM(CASE WHEN b.payment_status = 'paid'
                        THEN b.total_amount ELSE 0 END), 0)
       - COALESCE(SUM(c.refund_amount), 0) AS net_revenue
FROM bookings b
LEFT JOIN cbp_cancellations c ON c.booking_id = b.id;
```

Trang admin của main có thể sửa/xóa đơn trực tiếp, nằm ngoài quy trình hủy của khách hàng này. Xóa cứng đơn trong admin sẽ xóa các bản ghi module liên quan theo khóa ngoại; khách hủy bằng module thì lịch sử được giữ. Để trình diễn quy trình, hãy thao tác hủy từ trang khách hàng.

## F. Đưa lên GitHub nhóm

1. Chạy thử 4 luồng ở phần B trước.
2. Nếu thư mục đang dùng là bản giải nén ZIP, clone repository nhóm để có thư mục chứa `.git`. Sao chép phần **`customer/payment`** đã hoàn thiện sang bản clone.
3. Tạo nhánh riêng `feature/payment-tickets-kiet`. Chỉ thêm thư mục module vào commit:

```bash
git switch -c feature/payment-tickets-kiet
git add customer/payment
git diff --cached --stat
git diff --cached
git commit -m "Hoan thien thanh toan demo, ve dien tu va huy ve"
git push -u origin feature/payment-tickets-kiet
```

Nếu nhánh đó đã tồn tại, chuyển về nhánh đang làm thay vì tạo lại. Kiểm tra remote trỏ đúng repository nhóm trước khi push. Không đưa mật khẩu trong `config/database.php`, database cá nhân hoặc file rác vào commit.

4. Tạo Pull Request về `main` theo quy trình của nhóm. Có thể dùng phần mô tả sau:

> Bổ sung module khách hàng trong customer/payment: kiểm tra đơn, thanh toán demo thành công/thất bại, kết quả giao dịch, phát hành và tải/in vé điện tử, vé đã đặt, lịch sử và hủy/hoàn tiền mô phỏng theo mốc 60 phút. SQL bổ sung tạo 4 bảng, giữ dữ liệu main. Có setup và dữ liệu demo trên localhost/cinema_booking_test. Đã kiểm tra quyền sở hữu, CSRF, thử lại, giao dịch lặp/đồng thời, hủy sát giờ và giải phóng ghế.

Repository nhóm: https://github.com/huytrinh975-ui/nhom06-cinema-booking . Gói nộp chỉ chứa thư mục module để thêm vào repo trên nhánh riêng; tạo Pull Request về `main` sau khi commit. Website PHP/MySQL chạy trên XAMPP của từng máy khi demo.

## G. Kết quả kiểm tra trước khi giao

- Kiểm tra cú pháp 17 file PHP.
- 130 kiểm tra chức năng/HTTP và dữ liệu với **PHP 8.3.6, MySQL 8.0.46**, database riêng, đã đạt.
- Có thử hai tiến trình PHP với hai session thanh toán đồng thời cho cùng đơn: một giao dịch thành công, một vé, hai ghế được claim.
- Có kiểm tra CSRF, người chưa đăng nhập, đơn/vé/giao dịch của người khác, ID sai/dạng mảng, số tiền phía trình duyệt bị sửa, tổng DB không khớp, tài khoản khóa, sát giờ/đúng mốc 60 phút, các request lặp và quyền tải vé.
- Có kiểm tra dữ liệu gốc không bị reset, SQL bổ sung nhập lại an toàn, các GET không thay đổi dữ liệu nghiệp vụ, vé giữ snapshot và lịch sử hoàn tiền chính xác.
- Code main gốc được đối chiếu byte, không thay đổi.

Người thực hiện đã xác nhận chạy thử các chức năng trên XAMPP cá nhân. Kiểm tra tự động bố cục qua trình duyệt chưa thực hiện được trong môi trường chuẩn bị file; CSS có breakpoint 850px/500px và chế độ in. Phần PHP/CSS/JS/SQL trong gói nộp được giữ nguyên so với bản chức năng đã chạy thử; lần hoàn thiện để nộp cập nhật tài liệu và đối chiếu nguồn main.

## H. Nếu gặp lỗi

| Hiện tượng | Kiểm tra |
| --- | --- |
| Trang Apache “Not Found” | Tên thư mục trên URL phải đúng `cinema-test`; file thật phải nằm trong `htdocs/cinema-test/customer/payment` |
| Trang helper báo không khả dụng | Dùng localhost và tên DB `cinema_booking_test`; không phải lỗi thiếu file khi đã có giao diện CINEBOOK |
| “Cần cài đặt phần thanh toán” | Mở `setup-demo.php` và bấm nút cài đặt |
| Lỗi kết nối DB | Giữ đúng host/cổng/mật khẩu của MySQL Server riêng trong cấu hình hiện có |
| Đơn cũ báo suất đã bắt đầu | Tạo đơn mới bằng trang Tạo đơn demo thay vì sửa/nhập lại toàn bộ SQL |
| Thanh toán bị báo ghế trùng | Kiểm tra các đơn chưa hủy của cùng suất; tạo đơn demo mới nếu chỉ cần trình diễn |
| Không thấy vé sau lần thất bại | Chỉ thanh toán thành công mới tạo vé; mở đơn và thử lại |
| Đơn BK được admin đánh dấu paid nhưng chưa có vé | Các đơn seed/admin cũ chưa đi qua module không tự có vé; tạo và trả tiền một đơn demo để trình diễn phát hành vé |
| Không hủy được suất gần giờ | Đúng quy định 60 phút; tạo loại suất tương lai để thử hủy hợp lệ |
