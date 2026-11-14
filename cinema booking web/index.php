<?php
require __DIR__ . '/includes/data.php';

$pageTitle = 'CINEMATIX - Đặt vé xem phim trực tuyến';
$active = 'home';

include __DIR__ . '/includes/header.php';

// Lọc phim theo trạng thái trong database
$now = array_filter($movies, fn($m) => $m['status'] === 'now');
$soon = array_filter($movies, fn($m) => $m['status'] === 'soon');

// Lấy tối đa 3 phim đang chiếu làm banner
$heroMovies = array_slice($now, 0, 3, true);

// Nếu chưa có phim đang chiếu, lấy phim sắp chiếu làm banner
if (empty($heroMovies)) {
    $heroMovies = array_slice($soon, 0, 3, true);
}

$times = ['09:30', '11:45', '14:20', '16:45', '19:30', '22:10'];
?>

<!-- BANNER PHIM NỔI BẬT -->
<section class="hero" aria-label="Phim nổi bật">

    <?php if (!empty($heroMovies)): ?>
        <?php foreach ($heroMovies as $n => $m): $hid = $n; ?>
            <div class="hero-slide <?= $n === array_key_first($heroMovies) ? 'active' : '' ?>"
                 style="background-image:url('<?= banner($m) ?>')">

                <div class="container hero-content">
                    <h1><?= htmlspecialchars($m['title']) ?></h1>

                    <p><?= htmlspecialchars($m['desc']) ?></p>

                    <div class="meta-row">
                        <?php foreach ([$m['genre'], $m['duration'], $m['year'], $m['age']] as $chip): ?>
                            <?php if (known($chip)): ?><span><?= htmlspecialchars((string)$chip) ?></span><?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <div class="hero-actions">
                        <a class="btn btn-ghost"
                           href="detail.php?id=<?= (int)$hid ?>">
                            Xem chi tiết
                        </a>

                        <a class="btn btn-primary"
                           href="booking.php?id=<?= (int)$hid ?>">
                            Đặt vé ngay
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="hero-dots">
            <?php foreach ($heroMovies as $n => $m): ?>
                <button type="button"
                        class="<?= $n === array_key_first($heroMovies) ? 'active' : '' ?>"
                        aria-label="Banner <?= $n + 1 ?>">
                </button>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
        <div class="container hero-content">
            <h1>Chào mừng đến với CINEMATIX</h1>
            <p>Khám phá những bộ phim hấp dẫn và đặt vé xem phim trực tuyến.</p>
        </div>
    <?php endif; ?>

</section>

<!-- PHIM ĐANG CHIẾU -->
<section class="section reveal" id="phim-dang-chieu">
    <div class="container">

        <div class="section-head">
            <h2>PHIM ĐANG CHIẾU</h2>
            <a href="#lich-chieu">Xem lịch chiếu</a>
        </div>

        <div class="movie-grid">

            <?php if (!empty($now)): ?>

                <?php foreach (array_slice($now, 0, 5, true) as $id => $m): ?>

                    <article class="movie-card">

                        <div class="poster">
                            <span class="badge"><?= known($m['age']) ? htmlspecialchars($m['age']) : 'ĐANG CHIẾU' ?></span>

                            <img src="<?= poster($m) ?>"
                                 alt="Poster phim <?= htmlspecialchars($m['title']) ?>"
                                 loading="lazy">

                            <div class="poster-overlay">
                                <a class="btn btn-ghost btn-sm"
                                   href="detail.php?id=<?= (int)$id ?>">
                                    Xem chi tiết
                                </a>
                            </div>
                        </div>

                        <div class="movie-info">
                            <h3><?= htmlspecialchars($m['title']) ?></h3>
                            <?php if (known($m['genre'])): ?><p><?= htmlspecialchars($m['genre']) ?></p><?php endif; ?>
                            <p><?= htmlspecialchars($m['duration']) ?></p>

                            <a class="btn btn-primary btn-sm"
                               href="booking.php?id=<?= (int)$id ?>">
                                Mua vé
                            </a>
                        </div>

                    </article>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="movie-empty">
                    <p>Hiện chưa có phim đang chiếu.</p>
                </div>

            <?php endif; ?>

        </div>
    </div>
</section>

<!-- PHIM SẮP CHIẾU -->
<section class="section reveal" id="phim-sap-chieu" style="padding-top:0">
    <div class="container">

        <div class="section-head">
            <h2>PHIM SẮP CHIẾU</h2>
        </div>

        <div class="movie-grid">

            <?php if (!empty($soon)): ?>

                <?php foreach ($soon as $id => $m): ?>

                    <article class="movie-card">

                        <div class="poster">
                            <span class="badge soon">SẮP KHỞI CHIẾU</span>

                            <img src="<?= poster($m) ?>"
                                 alt="Poster phim <?= htmlspecialchars($m['title']) ?>"
                                 loading="lazy">

                            <div class="poster-overlay">
                                <a class="btn btn-ghost btn-sm"
                                   href="detail.php?id=<?= (int)$id ?>">
                                    Xem chi tiết
                                </a>
                            </div>
                        </div>

                        <div class="movie-info">
                            <h3><?= htmlspecialchars($m['title']) ?></h3>
                            <?php if (known($m['genre'])): ?><p><?= htmlspecialchars($m['genre']) ?></p><?php endif; ?>
                            <p>Khởi chiếu: <?= htmlspecialchars($m['release']) ?></p>
                        </div>

                    </article>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="movie-empty">
                    <p>Hiện chưa có phim sắp chiếu.</p>
                </div>

            <?php endif; ?>

        </div>
    </div>
</section>

<!-- LỊCH CHIẾU NỔI BẬT -->
<section class="section reveal" id="lich-chieu" style="padding-top:0">
    <div class="container">

        <div class="section-head">
            <h2>LỊCH CHIẾU NỔI BẬT</h2>
        </div>

        <div class="showtime-list">

            <?php
            $showtimeMovies = array_slice($now, 0, 3, true);
            ?>

            <?php foreach ($showtimeMovies as $k => $m): ?>

                <div class="showtime-row">

                    <img src="<?= poster($m) ?>"
                         alt="<?= htmlspecialchars($m['title']) ?>"
                         loading="lazy">

                    <div>
                        <h3><?= htmlspecialchars($m['title']) ?></h3>

                        <p class="sub">
                            <?= htmlspecialchars(implode(' · ', array_filter([$m['genre'], $m['duration']], 'known'))) ?>
                        </p>

                        <div class="pills" data-pill-group>
                            <?php foreach ($times as $n => $t): ?>
                                <button type="button"
                                        class="pill <?= $n === 1 ? 'is-selected' : '' ?>">
                                    <?= $t ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>

            <?php endforeach; ?>

        </div>
    </div>
</section>

<script>
(function () {
  /* Tự lật banner */
  var slides = Array.prototype.slice.call(document.querySelectorAll('.hero-slide'));
  var dots = Array.prototype.slice.call(document.querySelectorAll('.hero-dots button'));
  var cur = 0, timer = null;

  function go(n) {
    cur = (n + slides.length) % slides.length;
    slides.forEach(function (s, i) { s.classList.toggle('active', i === cur); });
    dots.forEach(function (d, i) { d.classList.toggle('active', i === cur); });
  }
  function start() {
    clearInterval(timer);
    if (slides.length > 1) timer = setInterval(function () { go(cur + 1); }, 5000);
  }
  dots.forEach(function (d, i) {
    d.addEventListener('click', function () { go(i); start(); });
  });
  start();

  /* Hiện các section .reveal (CSS đang để opacity:0 cho đến khi có class "in") */
  var items = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
      });
    }, { threshold: 0.05 });
    items.forEach(function (el) { io.observe(el); });
  } else {
    items.forEach(function (el) { el.classList.add('in'); });
  }

  /* Chọn giờ chiếu */
  document.querySelectorAll('[data-pill-group] .pill').forEach(function (btn) {
    btn.addEventListener('click', function () {
      btn.parentNode.querySelectorAll('.is-selected').forEach(function (x) { x.classList.remove('is-selected'); });
      btn.classList.add('is-selected');
    });
  });
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
