<?php
require_once __DIR__ . '/../config/database.php';

// Tìm kiếm phim theo tên
$keyword = trim($_GET['q'] ?? '');

$sql = "SELECT id, title, description, duration, release_date,
               poster, trailer_url, status
        FROM movies
        WHERE status IN ('showing', 'upcoming')";

if ($keyword !== '') {
    $sql .= " AND title LIKE ?";
    $stmt = $conn->prepare($sql . " ORDER BY release_date DESC");
    $search = "%" . $keyword . "%";
    $stmt->bind_param("s", $search);
} else {
    $stmt = $conn->prepare($sql . " ORDER BY release_date DESC");
}

$stmt->execute();
$result = $stmt->get_result();

$showingMovies = [];
$upcomingMovies = [];

while ($movie = $result->fetch_assoc()) {
    if ($movie['status'] === 'showing') {
        $showingMovies[] = $movie;
    } elseif ($movie['status'] === 'upcoming') {
        $upcomingMovies[] = $movie;
    }
}

$stmt->close();

// Hàm hiển thị nội dung an toàn
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Hiển thị danh sách phim
function displayMovies($movies, $statusText, $statusClass) {
    if (count($movies) === 0) {
        echo '<div class="movie-empty">';
        echo '<p>Chưa có phim trong danh sách này.</p>';
        echo '</div>';
        return;
    }

    foreach ($movies as $movie) {
        ?>
        <article class="movie-card">
            <div class="movie-poster">
                <?php if (!empty($movie['poster'])): ?>
                    <img
                        src="<?= e($movie['poster']) ?>"
                        alt="Poster <?= e($movie['title']) ?>"
                        loading="lazy"
                    >
                <?php else: ?>
                    <div class="movie-empty-poster">
                        Chưa có poster
                    </div>
                <?php endif; ?>

                <span class="movie-status <?= e($statusClass) ?>">
                    <?= e($statusText) ?>
                </span>
            </div>

            <div class="movie-info">
                <h3 class="movie-title">
                    <?= e($movie['title']) ?>
                </h3>

                <div class="movie-meta">
                    <span><?= (int)$movie['duration'] ?> phút</span>
                    <span>
                        <?= e(date('d/m/Y', strtotime($movie['release_date']))) ?>
                    </span>
                </div>

                <a
                    class="movie-detail-button"
                    href="movie-detail.php?id=<?= (int)$movie['id'] ?>"
                >
                    Xem chi tiết
                </a>
            </div>
        </article>
        <?php
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Danh sách phim - CINEMATIX</title>

    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/movie.css">
</head>

<body>

<main class="movie-page">
    <div class="container">

        <div class="movie-banner">
            <h1>KHÁM PHÁ PHIM HAY</h1>
            <p>
                Cập nhật những bộ phim đang chiếu và sắp khởi chiếu
                tại CINEMATIX.
            </p>
        </div>

        <!-- Tìm kiếm phim theo tên -->
        <form class="movie-tools" method="GET" action="">
            <div class="movie-search">
                <input
                    type="text"
                    name="q"
                    placeholder="Nhập tên phim cần tìm..."
                    value="<?= e($keyword) ?>"
                >
            </div>

            <button type="submit" class="movie-detail-button">
                Tìm kiếm
            </button>
        </form>

        <?php if ($keyword !== ''): ?>
            <p>
                Kết quả tìm kiếm cho:
                <strong><?= e($keyword) ?></strong>
            </p>
        <?php endif; ?>

        <!-- Phim đang chiếu -->
        <section class="movie-section">
            <div class="movie-section-header">
                <h2 class="movie-section-title">
                    PHIM ĐANG CHIẾU
                </h2>
            </div>

            <div class="movie-grid">
                <?php
                displayMovies(
                    $showingMovies,
                    'ĐANG CHIẾU',
                    ''
                );
                ?>
            </div>
        </section>

        <!-- Phim sắp chiếu -->
        <section class="movie-section">
            <div class="movie-section-header">
                <h2 class="movie-section-title">
                    PHIM SẮP CHIẾU
                </h2>
            </div>

            <div class="movie-grid">
                <?php
                displayMovies(
                    $upcomingMovies,
                    'SẮP CHIẾU',
                    'upcoming'
                );
                ?>
            </div>
        </section>

    </div>
</main>

</body>
</html>
