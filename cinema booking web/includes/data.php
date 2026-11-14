<?php
// Kết nối database bằng file config/database.php có sẵn ở thư mục gốc.
require_once __DIR__ . '/../config/database.php';

/* ---------- Cấu hình ---------- */
// Nơi chứa ảnh phim trên GitHub của nhóm (repo phải để chế độ Public).
// Khi không tìm thấy ảnh trong thư mục dự án, web sẽ tự lấy ảnh từ đây.
// Muốn tắt: đặt thành ''.
const GITHUB_IMG_BASE = 'https://raw.githubusercontent.com/huytrinh975-ui/nhom06-cinema-booking/main/uploads/movies/';

/* ---------- Hàm hỗ trợ ---------- */

// Giá trị có thật (không rỗng, không phải "Đang cập nhật")?
function known($v) {
    $v = trim((string)$v);
    return $v !== '' && $v !== '—' && $v !== 'Đang cập nhật';
}

// Tìm file ảnh thật sự tồn tại trong thư mục dự án. Không thấy thì trả về ''.
function resolve_image($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') return '';
    if (preg_match('~^(https?:)?//|^data:~i', $raw)) return $raw;

    $raw  = str_replace('\\', '/', $raw);
    $root = dirname(__DIR__);
    $path = ltrim($raw, '/');
    $base = basename($path);

    $candidates = [
        $path,
        'uploads/movies/' . $base,
        'upload/movies/' . $base,
        'upload/' . $base, 'uploads/' . $base,
        'upload/posters/' . $base, 'uploads/posters/' . $base,
        'assets/img/' . $base, 'assets/images/' . $base,
        'img/' . $base, 'images/' . $base,
    ];
    foreach ($candidates as $c) {
        if (is_file($root . '/' . $c)) return $c;
    }
    // Không có file trong máy -> lấy ảnh cùng tên từ GitHub của nhóm
    if (GITHUB_IMG_BASE !== '' && preg_match('~\.(jpe?g|png|webp|gif)$~i', $base)) {
        return GITHUB_IMG_BASE . rawurlencode($base);
    }
    return '';
}

/* ---------- Lấy phim từ database ---------- */
// Dùng SELECT * để không bị lỗi khi bảng chưa có các cột mới (genre, director...)
$movies = [];
$result = $conn->query("SELECT * FROM movies ORDER BY id DESC");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $status = $row['status'] ?? '';
        if ($status === 'showing')       $status = 'now';
        elseif ($status === 'upcoming')  $status = 'soon';

        $release = $row['release_date'] ?? '';
        $hasDate = $release && $release !== '0000-00-00' && strtotime($release) !== false;

        $text = fn($key) => known($row[$key] ?? '') ? trim($row[$key]) : 'Đang cập nhật';

        $movies[(int)$row['id']] = [
            'id'          => (int)$row['id'],
            'title'       => $row['title'] ?? '',
            'en'          => $row['title_en'] ?? '',
            'desc'        => $row['description'] ?? '',
            'description' => $row['description'] ?? '',
            'duration'    => !empty($row['duration']) ? (int)$row['duration'] . ' phút' : 'Đang cập nhật',
            'duration_min'=> (int)($row['duration'] ?? 0),
            'year'        => $hasDate ? date('Y', strtotime($release)) : '',
            'release'     => $hasDate ? date('d/m/Y', strtotime($release)) : 'Đang cập nhật',
            'release_date'=> $release,
            'poster'      => resolve_image($row['poster'] ?? ''),
            'banner'      => resolve_image($row['banner'] ?? ''),
            'trailer_url' => $row['trailer_url'] ?? '',
            'status'      => $status,
            // Các cột này tùy chọn: thêm bằng lệnh ALTER TABLE thì sẽ tự hiện
            'rating'      => is_numeric($row['rating'] ?? null) ? $row['rating'] : '—',
            'age'         => $text('age_rating'),
            'genre'       => $text('genre'),
            'director'    => $text('director'),
            'cast'        => $text('cast_list'),
            'lang'        => $text('language'),
        ];
    }
}

$news = [];

// Poster: tự mã hóa khoảng trắng/tiếng Việt trong tên file; không có thì dùng ảnh giữ chỗ.
function poster($movie) {
    $p = $movie['poster'] ?? '';
    if ($p !== '') {
        if (!preg_match('~^(https?:)?//|^data:~i', $p)) {
            $p = implode('/', array_map('rawurlencode', explode('/', $p)));
        }
        return htmlspecialchars($p, ENT_QUOTES, 'UTF-8');
    }
    return "data:image/svg+xml;charset=UTF-8," . rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="850" viewBox="0 0 600 850">
            <rect width="600" height="850" fill="#202536"/>
            <text x="300" y="410" text-anchor="middle" fill="#ffffff" font-size="38" font-family="Arial">CINEMATIX</text>
            <text x="300" y="465" text-anchor="middle" fill="#c5c9d3" font-size="22" font-family="Arial">Chưa có poster</text>
        </svg>'
    );
}

// Banner: dùng cột banner nếu có, không thì dùng poster.
function banner($movie) {
    if (!empty($movie['banner'])) {
        return poster(['poster' => $movie['banner']]);
    }
    return poster($movie);
}

// Đổi link YouTube thành link nhúng (rỗng nếu không phải YouTube).
function trailer_embed($url) {
    if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([\w-]{11})~', (string)$url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1] . '?autoplay=1&rel=0';
    }
    return '';
}
