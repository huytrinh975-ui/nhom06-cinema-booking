<?php
require __DIR__.'/includes/data.php';
$id = (int)($_GET['id'] ?? 0);
if (!isset($movies[$id])) { $id = (int)array_key_first($movies); }
$m = $movies[$id];

$pageTitle = $m['title'].' - CINEMATIX'; $pageCss = ['detail.css']; $active = 'movies'; $bodyClass = 'solid-header';
include __DIR__.'/includes/header.php';

$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$days  = ['CN','T2','T3','T4','T5','T6','T7'];
$times = ['10:15','13:00','15:40','18:20','21:00'];
$related = array_filter($movies, fn($x, $k) => $k !== $id, ARRAY_FILTER_USE_BOTH);
$embed = trailer_embed($m['trailer_url']);

// Chỉ hiện các thẻ thông tin đã có dữ liệu thật
$chips = [];
if (is_numeric($m['rating'])) $chips[] = '&#9733; '.$e($m['rating']).'/10';
foreach (['duration','age','genre'] as $k) if (known($m[$k])) $chips[] = $e($m[$k]);
?>
<section class="d-hero">
  <img class="d-hero-bg" src="<?= banner($m) ?>" alt="">
  <div class="d-hero-inner">
    <img class="d-poster" src="<?= poster($m) ?>" alt="Poster phim <?= $e($m['title']) ?>">
    <div class="d-info">
      <h1><?= $e($m['title']) ?></h1>
      <?php if (known($m['en'])): ?><p class="d-en"><?= $e($m['en']) ?></p><?php endif; ?>
      <?php if ($chips): ?>
      <div class="meta-row"><?php foreach ($chips as $c): ?><span><?= $c ?></span><?php endforeach; ?></div>
      <?php endif; ?>
      <dl class="d-facts">
        <dt>Khởi chiếu</dt><dd><?= $e($m['release']) ?></dd>
        <?php foreach (['director'=>'Đạo diễn','cast'=>'Diễn viên','genre'=>'Thể loại','lang'=>'Ngôn ngữ','age'=>'Phân loại'] as $k => $label): ?>
          <?php if (known($m[$k])): ?><dt><?= $label ?></dt><dd><?= $e($m[$k]) ?></dd><?php endif; ?>
        <?php endforeach; ?>
      </dl>
      <a class="btn btn-primary" href="booking.php?id=<?= $id ?>">Đặt vé ngay</a>
    </div>
  </div>
</section>

<section class="section">
  <div class="container d-grid">
    <div>
      <h2 class="d-h">Giới thiệu phim</h2>
      <p class="d-desc"><?= nl2br($e($m['desc'] ?: 'Nội dung phim đang được cập nhật.')) ?></p>
      <h2 class="d-h">Trailer</h2>
      <div class="trailer" data-embed="<?= $e($embed) ?>" style="background-image:url('<?= banner($m) ?>')">
        <?php if ($embed): ?>
        <button type="button" class="play" aria-label="Phát trailer"><svg viewBox="0 0 24 24" width="30" height="30" fill="#fff"><path d="M8 5v14l11-7z"/></svg></button>
        <?php endif; ?>
      </div>
    </div>
    <aside class="d-schedule" id="lich-chieu">
      <h2 class="d-h">Lịch chiếu</h2>
      <div class="days" data-day-group>
        <?php for ($i = 0; $i < 7; $i++): $t = strtotime("+$i day"); ?>
        <button type="button" class="day <?= $i === 0 ? 'is-selected' : '' ?>"><small><?= $days[date('w', $t)] ?></small><b><?= date('d', $t) ?></b></button>
        <?php endfor; ?>
      </div>
      <?php foreach (['CINEMATIX Nguyễn Huệ'=>'Quận 1, TP.HCM','CINEMATIX Landmark'=>'Bình Thạnh, TP.HCM','CINEMATIX Thủ Đức'=>'TP. Thủ Đức'] as $cin => $addr): ?>
      <div class="cinema">
        <div class="cinema-head"><h3><?= $cin ?></h3><span><?= $addr ?></span></div>
        <div class="pills" data-pill-group><?php foreach ($times as $n => $t): ?><button type="button" class="pill <?= $n === 1 && $cin === 'CINEMATIX Nguyễn Huệ' ? 'is-selected' : '' ?>"><?= $t ?></button><?php endforeach; ?></div>
      </div>
      <?php endforeach; ?>
      <a class="btn btn-primary btn-block" href="booking.php?id=<?= $id ?>">Đặt vé</a>
    </aside>
  </div>
</section>

<?php if ($related): ?>
<section class="section" style="padding-top:0">
  <div class="container">
    <div class="section-head"><h2>PHIM LIÊN QUAN</h2></div>
    <div class="movie-grid">
      <?php foreach (array_slice($related, 0, 5, true) as $rid => $r): ?>
      <article class="movie-card"><a class="poster" href="detail.php?id=<?= $rid ?>"><img src="<?= poster($r) ?>" alt="Poster <?= $e($r['title']) ?>" loading="lazy"></a>
      <div class="movie-info"><h3><?= $e($r['title']) ?></h3><?php if (known($r['genre'])): ?><p><?= $e($r['genre']) ?></p><?php endif; ?></div></article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<script>
document.querySelectorAll('[data-day-group] .day, [data-pill-group] .pill').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var g = btn.closest('[data-day-group],[data-pill-group]');
    g.querySelectorAll('.is-selected').forEach(function (x) { x.classList.remove('is-selected'); });
    btn.classList.add('is-selected');
  });
});
var tr = document.querySelector('.trailer[data-embed]');
if (tr && tr.dataset.embed) {
  tr.addEventListener('click', function () {
    tr.innerHTML = '<iframe src="' + tr.dataset.embed + '" style="position:absolute;inset:0;width:100%;height:100%;border:0" allow="autoplay; encrypted-media; fullscreen" allowfullscreen></iframe>';
  }, { once: true });
}
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
