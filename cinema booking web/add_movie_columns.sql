-- Chạy trong phpMyAdmin > cinema_booking_db > tab SQL (chỉ chạy 1 lần)
ALTER TABLE movies
  ADD COLUMN title_en   VARCHAR(150) NULL,
  ADD COLUMN genre      VARCHAR(100) NULL,
  ADD COLUMN age_rating VARCHAR(10)  NULL,
  ADD COLUMN director   VARCHAR(150) NULL,
  ADD COLUMN cast_list  TEXT         NULL,
  ADD COLUMN language   VARCHAR(50)  NULL,
  ADD COLUMN rating     DECIMAL(3,1) NULL,
  ADD COLUMN banner     VARCHAR(255) NULL;

-- Ví dụ điền dữ liệu cho phim id = 4 (Kung Fu Panda 4)
UPDATE movies SET
  title_en   = 'Kung Fu Panda 4',
  genre      = 'Hoạt hình, Hài, Hành động',
  age_rating = 'P',
  director   = 'Mike Mitchell',
  cast_list  = 'Jack Black, Awkwafina, Viola Davis',
  language   = 'Phụ đề tiếng Việt',
  rating     = 7.0
WHERE id = 4;
