MOVIEBOOKING - PHAN QUAN LY TAI KHOAN (PHP + MYSQL)

1. Giai nen movie_booking_complete.zip.
2. Copy thu muc movie_booking_complete vao C:\xampp\htdocs\
   Nen doi ten thu muc thanh movie_booking neu muon dung URL mau ben duoi.
3. Mo XAMPP, Start Apache va MySQL.
4. Mo http://localhost/phpmyadmin
5. Chon Import va import database/movie_booking.sql.
6. Kiem tra config/database.php:
   host=localhost, database=movie_booking, username=root, password rong
   (neu XAMPP cua ban co dat mat khau root thi cap nhat password).
7. Mo http://localhost/movie_booking_complete/ (hoac URL theo ten thu muc ban dat).

CAC TRANG:
- index.php: Trang chu
- register.php: Dang ky
- login.php: Dang nhap
- profile.php: Ho so ca nhan
- edit_profile.php: Cap nhat ho so
- logout.php: Dang xuat

CAP QUYEN ADMIN:
- Dang ky mot tai khoan qua website truoc.
- Trong phpMyAdmin, chon database movie_booking, mo tab SQL va chay:
  UPDATE users SET role='admin' WHERE email='email-cua-ban@example.com';

LUU Y:
- Moi tai khoan dang ky tu website mac dinh la customer.
- Khong cho phep nguoi dung tu chon role admin khi dang ky.
- Day la module tai khoan; chua bao gom quan ly phim, lich chieu, ghe, don dat ve hay thanh toan.
- Khi dua len hosting, phai thay thong tin ket noi database bang thong tin hosting cap.
