-- Chạy trong database hiện có của nhóm; KHÔNG xóa hoặc tạo lại bảng main.
-- Có thể nhập lại an toàn: chỉ CREATE TABLE IF NOT EXISTS.
CREATE TABLE IF NOT EXISTS cbp_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    attempt_code VARCHAR(32) NOT NULL UNIQUE,
    request_key CHAR(64) NOT NULL UNIQUE,
    amount DECIMAL(12,2) NOT NULL,
    result ENUM('success','failed') NOT NULL,
    method VARCHAR(20) NOT NULL DEFAULT 'DEMO',
    created_at DATETIME NOT NULL,
    INDEX idx_cbp_payment_booking (booking_id, id),
    CONSTRAINT fk_cbp_payment_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cbp_tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL UNIQUE,
    ticket_code VARCHAR(32) NOT NULL UNIQUE,
    status ENUM('valid','cancelled') NOT NULL DEFAULT 'valid',
    snapshot_json JSON NOT NULL,
    issued_at DATETIME NOT NULL,
    cancelled_at DATETIME NULL,
    CONSTRAINT fk_cbp_ticket_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cbp_cancellations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL UNIQUE,
    user_id INT NOT NULL,
    cancellation_code VARCHAR(32) NOT NULL UNIQUE,
    reason VARCHAR(500) NOT NULL,
    refund_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    refund_status ENUM('not_applicable','simulated_refunded') NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_cbp_cancel_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_cbp_cancel_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cbp_seat_claims (
    showtime_id INT NOT NULL,
    seat_id INT NOT NULL,
    booking_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (showtime_id, seat_id),
    INDEX idx_cbp_claim_booking (booking_id),
    CONSTRAINT fk_cbp_claim_showtime FOREIGN KEY (showtime_id) REFERENCES showtimes(id) ON DELETE CASCADE,
    CONSTRAINT fk_cbp_claim_seat FOREIGN KEY (seat_id) REFERENCES seats(id) ON DELETE CASCADE,
    CONSTRAINT fk_cbp_claim_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
