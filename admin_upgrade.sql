-- Bổ sung tính năng quản trị vào cơ sở dữ liệu hiện có.
USE cinema_booking_db;

SET @import_legacy_genres = (
    EXISTS(SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'movies' AND COLUMN_NAME = 'genre')
    AND NOT EXISTS(SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'movie_genres')
);

CREATE TABLE IF NOT EXISTS genres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS movie_genres (
    movie_id INT NOT NULL,
    genre_id INT NOT NULL,
    PRIMARY KEY (movie_id, genre_id),
    CONSTRAINT fk_movie_genres_movie FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    CONSTRAINT fk_movie_genres_genre FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL UNIQUE,
    discount_type ENUM('percent', 'fixed') NOT NULL,
    discount_value DECIMAL(12,2) NOT NULL,
    min_order_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    starts_on DATE NOT NULL,
    ends_on DATE NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nhập các thể loại cũ nếu trước đây đã chạy add_movie_columns.sql.
SET @legacy_sql = IF(@import_legacy_genres,
    'CREATE TEMPORARY TABLE legacy_movie_genres AS
        WITH RECURSIVE genre_parts (movie_id, name, remainder) AS (
            SELECT id, TRIM(SUBSTRING_INDEX(genre, '','', 1)),
                   IF(LOCATE('','', genre), SUBSTRING(genre, LOCATE('','', genre) + 1), '''')
            FROM movies WHERE genre IS NOT NULL
            UNION ALL
            SELECT movie_id, TRIM(SUBSTRING_INDEX(remainder, '','', 1)),
                   IF(LOCATE('','', remainder), SUBSTRING(remainder, LOCATE('','', remainder) + 1), '''')
            FROM genre_parts WHERE remainder <> ''''
        )
        SELECT DISTINCT movie_id, name FROM genre_parts WHERE name <> ''''',
    'CREATE TEMPORARY TABLE legacy_movie_genres (movie_id INT, name VARCHAR(100))');
PREPARE migrate_genres FROM @legacy_sql;
EXECUTE migrate_genres;
DEALLOCATE PREPARE migrate_genres;

INSERT IGNORE INTO genres (name) SELECT DISTINCT name FROM legacy_movie_genres;
INSERT IGNORE INTO movie_genres (movie_id, genre_id)
    SELECT legacy.movie_id, g.id FROM legacy_movie_genres legacy JOIN genres g ON g.name = legacy.name;

DROP TEMPORARY TABLE legacy_movie_genres;
