<?php
$page_title = 'Quản Lý Thể Loại Phim';
require_once __DIR__ . '/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'delete' && $id > 0) {
            $stmt = $conn->prepare('DELETE FROM genres WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $_SESSION['flash_success'] = 'Đã xóa thể loại và gỡ thể loại này khỏi các phim.';
        } elseif ($action === 'save') {
            $name = trim($_POST['name'] ?? '');
            if ($name === '' || mb_strlen($name) > 100) {
                $_SESSION['flash_error'] = 'Tên thể loại phải có từ 1 đến 100 ký tự.';
                admin_redirect($id > 0 ? 'genres.php?edit=' . $id : 'genres.php');
            }
            if ($id > 0) {
                $stmt = $conn->prepare('UPDATE genres SET name = ? WHERE id = ?');
                $stmt->bind_param('si', $name, $id);
            } else {
                $stmt = $conn->prepare('INSERT INTO genres (name) VALUES (?)');
                $stmt->bind_param('s', $name);
            }
            $stmt->execute();
            $_SESSION['flash_success'] = $id > 0 ? 'Đã cập nhật thể loại.' : 'Đã thêm thể loại.';
        }
    } catch (mysqli_sql_exception $e) {
        if ((int) $e->getCode() !== 1062) {
            throw $e;
        }
        $_SESSION['flash_error'] = 'Tên thể loại này đã có.';
        admin_redirect($id > 0 ? 'genres.php?edit=' . $id : 'genres.php');
    }
    admin_redirect('genres.php');
}

$genres = $conn->query('SELECT g.id, g.name, COUNT(mg.movie_id) AS movie_count
                        FROM genres g LEFT JOIN movie_genres mg ON mg.genre_id = g.id
                        GROUP BY g.id, g.name ORDER BY g.name')->fetch_all(MYSQLI_ASSOC);
$editId = (int) ($_GET['edit'] ?? 0);
$editing = null;
foreach ($genres as $genre) {
    if ((int) $genre['id'] === $editId) {
        $editing = $genre;
        break;
    }
}
require_once __DIR__ . '/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Quản lý thể loại phim</h4>
    <a href="movies.php" class="btn btn-outline-primary">Quản lý phim</a>
</div>
<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="card p-3">
            <h5 class="h6 fw-bold mb-3"><?= $editing ? 'Sửa thể loại' : 'Thêm thể loại' ?></h5>
            <form method="post" action="genres.php">
                <?php admin_csrf_field(); ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
                <label for="genre-name" class="form-label">Tên thể loại</label>
                <input id="genre-name" name="name" class="form-control mb-3" maxlength="100" required
                       value="<?= htmlspecialchars($editing['name'] ?? '') ?>">
                <button type="submit" class="btn btn-primary"><?= $editing ? 'Lưu thay đổi' : 'Thêm thể loại' ?></button>
                <?php if ($editing): ?><a href="genres.php" class="btn btn-light">Hủy</a><?php endif; ?>
            </form>
        </div>
    </div>
    <div class="col-12 col-lg-8">
        <div class="card table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead><tr><th>Thể loại</th><th>Số phim</th><th class="text-end">Thao tác</th></tr></thead>
                <tbody>
                <?php foreach ($genres as $genre): ?>
                    <tr>
                        <td><?= htmlspecialchars($genre['name']) ?></td>
                        <td><?= (int) $genre['movie_count'] ?></td>
                        <td class="text-end">
                            <a href="genres.php?edit=<?= (int) $genre['id'] ?>" class="btn btn-sm btn-outline-primary">Sửa</a>
                            <form method="post" action="genres.php" class="d-inline">
                                <?php admin_csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $genre['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit"
                                        data-confirm="Xóa thể loại này sẽ gỡ nó khỏi các phim đang sử dụng. Bạn có muốn tiếp tục?">Xóa</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$genres): ?>
                    <tr><td colspan="3" class="text-center text-muted py-4">Chưa có thể loại nào.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
