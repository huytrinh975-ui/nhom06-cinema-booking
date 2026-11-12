    </main>

    <!-- FOOTER -->
    <footer class="bg-white border-top py-3 px-4 text-center text-muted small mt-auto">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
            <div>&copy; <?= date('Y') ?> <strong>Hệ Thống Đặt Vé Xem Phim (CINEBOOK)</strong> - Phân hệ Quản trị.</div>
            <div>Phiên bản 1.0 &bull; Nhóm 06</div>
        </div>
    </footer>
</div>

<!-- Bootstrap 5.3.3 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Xử lý bật/tắt sidebar trên thiết bị di động
    const toggleBtn = document.getElementById('toggle-sidebar');
    const sidebar = document.getElementById('sidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('show');
        });

        // Nhấn ra ngoài để đóng sidebar trên mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 992 && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        });
    }

    // Tự động gán xác nhận SweetAlert2 cho các liên kết hoặc nút có class .btn-delete hoặc data-confirm
    document.querySelectorAll('[data-confirm]').forEach(element => {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            const message = this.getAttribute('data-confirm') || 'Bạn có chắc chắn muốn thực hiện hành động này?';
            const targetUrl = this.getAttribute('href');

            const submit = () => {
                if (targetUrl && targetUrl !== '#') {
                    window.location.href = targetUrl;
                } else if (this.closest('form')) {
                    this.closest('form').requestSubmit();
                }
            };
            if (typeof Swal === 'undefined') {
                if (window.confirm(message)) submit();
                return;
            }

            Swal.fire({
                title: 'Xác nhận thao tác',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Đồng ý, thực hiện!',
                cancelButtonText: 'Hủy bỏ',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) submit();
            });
        });
    });

    // Kích hoạt bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
</script>
</body>
</html>

