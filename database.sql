-- ============================================================
-- Hệ thống Đặt Vé Xem Phim Trực Tuyến (nhom06-cinema-booking)
-- Database: cinema_booking_db
-- ============================================================

CREATE DATABASE IF NOT EXISTS `cinema_booking_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `cinema_booking_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `booking_details`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `showtimes`;
DROP TABLE IF EXISTS `seats`;
DROP TABLE IF EXISTS `rooms`;
DROP TABLE IF EXISTS `cinemas`;
DROP TABLE IF EXISTS `movies`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Bảng users (Người dùng và Quản trị viên)
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `fullname` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `phone` VARCHAR(20) DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'customer') DEFAULT 'customer',
    `status` ENUM('active', 'locked') DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bảng movies (Danh sách phim)
CREATE TABLE `movies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `duration` INT NOT NULL COMMENT 'Thời lượng phim tính theo phút',
    `release_date` DATE NOT NULL,
    `poster` VARCHAR(255) DEFAULT NULL,
    `trailer_url` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('showing', 'upcoming', 'hidden') DEFAULT 'showing',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Bảng cinemas (Cụm rạp chiếu phim)
CREATE TABLE `cinemas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `address` VARCHAR(255) NOT NULL,
    `city` VARCHAR(100) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Bảng rooms (Phòng chiếu)
CREATE TABLE `rooms` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cinema_id` INT NOT NULL,
    `name` VARCHAR(50) NOT NULL,
    `total_seats` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_rooms_cinema` FOREIGN KEY (`cinema_id`) REFERENCES `cinemas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Bảng seats (Ghế ngồi trong phòng)
CREATE TABLE `seats` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `room_id` INT NOT NULL,
    `seat_number` VARCHAR(10) NOT NULL,
    `seat_type` ENUM('standard', 'vip', 'couple') DEFAULT 'standard',
    `price_multiplier` DECIMAL(3, 2) DEFAULT 1.00,
    CONSTRAINT `fk_seats_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_room_seat` (`room_id`, `seat_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Bảng showtimes (Suất chiếu)
CREATE TABLE `showtimes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `movie_id` INT NOT NULL,
    `room_id` INT NOT NULL,
    `start_time` DATETIME NOT NULL,
    `end_time` DATETIME NOT NULL,
    `base_price` DECIMAL(10, 2) NOT NULL DEFAULT 75000.00,
    `status` ENUM('active', 'cancelled', 'completed') DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_showtimes_movie` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_showtimes_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Bảng bookings (Đơn đặt vé)
CREATE TABLE `bookings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `booking_code` VARCHAR(20) UNIQUE NOT NULL,
    `user_id` INT NOT NULL,
    `showtime_id` INT NOT NULL,
    `total_amount` DECIMAL(12, 2) NOT NULL,
    `payment_status` ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    `booking_status` ENUM('confirmed', 'pending', 'cancelled') DEFAULT 'pending',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bookings_showtime` FOREIGN KEY (`showtime_id`) REFERENCES `showtimes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Bảng booking_details (Chi tiết ghế đã đặt trong đơn)
CREATE TABLE `booking_details` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `booking_id` INT NOT NULL,
    `seat_id` INT NOT NULL,
    `price` DECIMAL(10, 2) NOT NULL,
    CONSTRAINT `fk_booking_details_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_booking_details_seat` FOREIGN KEY (`seat_id`) REFERENCES `seats` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DỮ LIỆU MẪU (SEED DATA)
-- ============================================================

-- Dữ liệu users (Mật khẩu admin: admin123, Khách: 123456)
INSERT INTO `users` (`id`, `fullname`, `email`, `phone`, `password`, `role`, `status`, `created_at`) VALUES
(1, 'Trịnh Minh Huy', 'trinhminhhuy@gmail.com', '0901234567', '$2y$10$rmbl/JymRF4t6/CjrXY/FO6pJTs11rsZ/BfO.tdeWvLEIWmHdyBt.', 'admin', 'active', NOW()),
(2, 'Trần Văn Khách', 'khachhang@gmail.com', '0912345678', '$2y$10$0T5AiprK0yfE.xy8xTjUl.52EQvrkI.OfNngVu28bCnb0vMq50Mpm', 'customer', 'active', NOW()),
(3, 'Lê Thị Thu Thảo', 'thuthao@gmail.com', '0987654321', '$2y$10$0T5AiprK0yfE.xy8xTjUl.52EQvrkI.OfNngVu28bCnb0vMq50Mpm', 'customer', 'active', NOW()),
(4, 'Phạm Hoàng Long', 'hoanglong@gmail.com', '0933445566', '$2y$10$0T5AiprK0yfE.xy8xTjUl.52EQvrkI.OfNngVu28bCnb0vMq50Mpm', 'customer', 'locked', NOW());

-- Dữ liệu movies
INSERT INTO `movies` (`id`, `title`, `description`, `duration`, `release_date`, `poster`, `trailer_url`, `status`) VALUES
(1, 'Dune: Hành Tinh Cát - Phần Hai', 'Paul Atreides hợp lực cùng Chani và tộc người Fremen để trả thù những kẻ đã hủy diệt gia tộc anh, đồng thời đối mặt với lựa chọn giữa tình yêu và số phận vũ trụ.', 166, '2024-03-01', 'https://image.tmdb.org/t/p/w500/8b8R8l88Qje9dn9OE8PY05Nxl1X.jpg', 'https://www.youtube.com/watch?v=Way9Dexny3w', 'showing'),
(2, 'Mai', 'Mai là một người phụ nữ làm nghề massage, mang trong mình vết thương lòng trong quá khứ và khao khát được đón nhận hạnh phúc mới bên chàng nhạc công trẻ Dương.', 131, '2024-02-10', 'https://image.tmdb.org/t/p/w500/b1NxA3297i9lA2E8s4T7fNl6o0v.jpg', 'https://www.youtube.com/watch?v=dYk8q9gJj0k', 'showing'),
(3, 'Godzilla x Kong: Đế Chế Mới', 'Hai quái thú huyền thoại Godzilla và Kong buộc phải liên minh để đối đầu với một mối đe dọa tiềm ẩn khổng lồ đe dọa sự tồn vong của Trái Đất bên dưới lòng đất.', 115, '2024-03-29', 'https://image.tmdb.org/t/p/w500/bQ2ywkchCe026rmbtEMa59fy7R7.jpg', 'https://www.youtube.com/watch?v=lV1OOlGwExg', 'showing'),
(4, 'Kung Fu Panda 4', 'Po được chọn trở thành Thủ lĩnh Tinh thần của Thung lũng Bình Yên và phải tìm kiếm một Chiến binh Rồng mới, đồng thời chống lại mụ Phù thủy Tắc kè hoa xảo quyệt.', 94, '2024-03-08', 'https://image.tmdb.org/t/p/w500/kDp1vUBnMpe8ak4rjgl3cLELqjU.jpg', 'https://www.youtube.com/watch?v=_inKs4eeHiI', 'showing'),
(5, 'Lật Mặt 7: Một Điều Ước', 'Bộ phim về tình mẫu tử thiêng liêng xoay quanh câu chuyện gia đình bà Hai và 5 người con, mang thông điệp chữa lành sâu sắc cho mọi thế hệ người xem.', 138, '2024-04-26', 'https://image.tmdb.org/t/p/w500/1Xdd3ej0759f2pP79M7Lg5z1u4G.jpg', 'https://www.youtube.com/watch?v=sS_wXWc7cKk', 'upcoming'),
(6, 'Kẻ Trộm Mặt Trăng 4', 'Gru và gia đình bước vào một chương mới đầy phiêu lưu với sự chào đời của Gru Jr. và sự xuất hiện của kẻ thù truyền kiếp Maxime Le Mal.', 95, '2024-07-03', 'https://image.tmdb.org/t/p/w500/wWba3TaojhK7NdycRhoQpsG0cnH.jpg', 'https://www.youtube.com/watch?v=LtNYaH61dXY', 'upcoming');

-- Dữ liệu cinemas
INSERT INTO `cinemas` (`id`, `name`, `address`, `city`) VALUES
(1, 'CGV Landmark 81', 'Tầng B1, TTTM Vincom Center Landmark 81, 720A Điện Biên Phủ, P.22, Bình Thạnh', 'Hồ Chí Minh'),
(2, 'BHD Star Cineplex 3/2', 'Lầu 5, Siêu Thị Vincom 3/2, 3C Đường 3 Tháng 2, Phường 11, Quận 10', 'Hồ Chí Minh'),
(3, 'Beta Cinemas Mỹ Đình', 'Tầng hầm B1, Tòa nhà Golden Palace, Mễ Trì, Nam Từ Liêm', 'Hà Nội');

-- Dữ liệu rooms
INSERT INTO `rooms` (`id`, `cinema_id`, `name`, `total_seats`) VALUES
(1, 1, 'Phòng 01 (IMAX Laser)', 28),
(2, 1, 'Phòng 02 (Standard)', 28),
(3, 2, 'Phòng chiếu Cinema 01', 28),
(4, 3, 'Phòng chiếu Beta 01', 28);

-- Dữ liệu seats cho Phòng 1 (28 ghế: Hàng A, B standard; C VIP; D Couple)
INSERT INTO `seats` (`room_id`, `seat_number`, `seat_type`, `price_multiplier`) VALUES
(1, 'A1', 'standard', 1.00), (1, 'A2', 'standard', 1.00), (1, 'A3', 'standard', 1.00), (1, 'A4', 'standard', 1.00), (1, 'A5', 'standard', 1.00), (1, 'A6', 'standard', 1.00), (1, 'A7', 'standard', 1.00), (1, 'A8', 'standard', 1.00),
(1, 'B1', 'standard', 1.00), (1, 'B2', 'standard', 1.00), (1, 'B3', 'standard', 1.00), (1, 'B4', 'standard', 1.00), (1, 'B5', 'standard', 1.00), (1, 'B6', 'standard', 1.00), (1, 'B7', 'standard', 1.00), (1, 'B8', 'standard', 1.00),
(1, 'C1', 'vip', 1.25), (1, 'C2', 'vip', 1.25), (1, 'C3', 'vip', 1.25), (1, 'C4', 'vip', 1.25), (1, 'C5', 'vip', 1.25), (1, 'C6', 'vip', 1.25), (1, 'C7', 'vip', 1.25), (1, 'C8', 'vip', 1.25),
(1, 'D1-D2', 'couple', 1.80), (1, 'D3-D4', 'couple', 1.80), (1, 'D5-D6', 'couple', 1.80), (1, 'D7-D8', 'couple', 1.80);

-- Dữ liệu seats cho Phòng 2 (28 ghế)
INSERT INTO `seats` (`room_id`, `seat_number`, `seat_type`, `price_multiplier`) VALUES
(2, 'A1', 'standard', 1.00), (2, 'A2', 'standard', 1.00), (2, 'A3', 'standard', 1.00), (2, 'A4', 'standard', 1.00), (2, 'A5', 'standard', 1.00), (2, 'A6', 'standard', 1.00), (2, 'A7', 'standard', 1.00), (2, 'A8', 'standard', 1.00),
(2, 'B1', 'standard', 1.00), (2, 'B2', 'standard', 1.00), (2, 'B3', 'standard', 1.00), (2, 'B4', 'standard', 1.00), (2, 'B5', 'standard', 1.00), (2, 'B6', 'standard', 1.00), (2, 'B7', 'standard', 1.00), (2, 'B8', 'standard', 1.00),
(2, 'C1', 'vip', 1.20), (2, 'C2', 'vip', 1.20), (2, 'C3', 'vip', 1.20), (2, 'C4', 'vip', 1.20), (2, 'C5', 'vip', 1.20), (2, 'C6', 'vip', 1.20), (2, 'C7', 'vip', 1.20), (2, 'C8', 'vip', 1.20),
(2, 'D1-D2', 'couple', 1.75), (2, 'D3-D4', 'couple', 1.75), (2, 'D5-D6', 'couple', 1.75), (2, 'D7-D8', 'couple', 1.75);

-- Dữ liệu seats cho Phòng 3
INSERT INTO `seats` (`room_id`, `seat_number`, `seat_type`, `price_multiplier`) VALUES
(3, 'A1', 'standard', 1.00), (3, 'A2', 'standard', 1.00), (3, 'A3', 'standard', 1.00), (3, 'A4', 'standard', 1.00),
(3, 'B1', 'standard', 1.00), (3, 'B2', 'standard', 1.00), (3, 'B3', 'vip', 1.20), (3, 'B4', 'vip', 1.20);

-- Dữ liệu showtimes (Suất chiếu)
INSERT INTO `showtimes` (`id`, `movie_id`, `room_id`, `start_time`, `end_time`, `base_price`, `status`) VALUES
(1, 1, 1, DATE_ADD(CURRENT_DATE, INTERVAL '09:00:00' HOUR_SECOND), DATE_ADD(CURRENT_DATE, INTERVAL '11:46:00' HOUR_SECOND), 90000.00, 'active'),
(2, 1, 1, DATE_ADD(CURRENT_DATE, INTERVAL '14:30:00' HOUR_SECOND), DATE_ADD(CURRENT_DATE, INTERVAL '17:16:00' HOUR_SECOND), 110000.00, 'active'),
(3, 2, 2, DATE_ADD(CURRENT_DATE, INTERVAL '10:00:00' HOUR_SECOND), DATE_ADD(CURRENT_DATE, INTERVAL '12:11:00' HOUR_SECOND), 80000.00, 'active'),
(4, 3, 1, DATE_ADD(CURRENT_DATE, INTERVAL '19:00:00' HOUR_SECOND), DATE_ADD(CURRENT_DATE, INTERVAL '20:55:00' HOUR_SECOND), 105000.00, 'active'),
(5, 4, 3, DATE_ADD(CURRENT_DATE, INTERVAL '15:00:00' HOUR_SECOND), DATE_ADD(CURRENT_DATE, INTERVAL '16:34:00' HOUR_SECOND), 75000.00, 'active');

-- Dữ liệu bookings (Đơn đặt vé)
INSERT INTO `bookings` (`id`, `booking_code`, `user_id`, `showtime_id`, `total_amount`, `payment_status`, `booking_status`, `created_at`) VALUES
(1, 'BK2024040101', 2, 1, 180000.00, 'paid', 'confirmed', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(2, 'BK2024040102', 3, 1, 225000.00, 'paid', 'confirmed', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(3, 'BK2024040103', 2, 2, 110000.00, 'pending', 'pending', DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
(4, 'BK2024040104', 3, 3, 160000.00, 'paid', 'confirmed', DATE_SUB(NOW(), INTERVAL 15 MINUTE)),
(5, 'BK2024040105', 4, 4, 105000.00, 'failed', 'cancelled', DATE_SUB(NOW(), INTERVAL 5 MINUTE));

-- Dữ liệu booking_details (Chi tiết ghế của từng đơn)
-- Đơn 1: Ghế A3, A4 suất 1
INSERT INTO `booking_details` (`booking_id`, `seat_id`, `price`) VALUES
(1, 3, 90000.00),
(1, 4, 90000.00);

-- Đơn 2: Ghế C3, C4 (VIP) suất 1 (90,000 * 1.25 = 112,500/ghế -> 225,000)
INSERT INTO `booking_details` (`booking_id`, `seat_id`, `price`) VALUES
(2, 19, 112500.00),
(2, 20, 112500.00);

-- Đơn 3: Ghế B2 suất 2
INSERT INTO `booking_details` (`booking_id`, `seat_id`, `price`) VALUES
(3, 10, 110000.00);

-- Đơn 4: Ghế A1, A2 suất 3
INSERT INTO `booking_details` (`booking_id`, `seat_id`, `price`) VALUES
(4, 29, 80000.00),
(4, 30, 80000.00);

-- Đơn 5: Ghế B1 suất 4
INSERT INTO `booking_details` (`booking_id`, `seat_id`, `price`) VALUES
(5, 9, 105000.00);

