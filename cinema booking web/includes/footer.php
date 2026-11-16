<footer class="site-footer">
    <div class="container footer-inner">
        <div>
            <a class="logo" href="index.php">CINEMA<span>TIX</span></a>
            <p>Đặt vé xem phim trực tuyến</p>
        </div>
        <p class="footer-copy">&copy; <?= date('Y') ?> CINEMATIX. All rights reserved.</p>
    </div>
</footer>

<script>
// Header đổi nền khi cuộn trang
(function () {
  var h = document.querySelector('.site-header');
  if (!h) return;
  function f() { h.classList.toggle('scrolled', window.scrollY > 40); }
  f(); window.addEventListener('scroll', f, { passive: true });
})();
</script>
</body>
</html>
