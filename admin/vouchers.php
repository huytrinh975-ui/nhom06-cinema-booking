<?php
$page_title = 'Quản Lý Voucher';
require_once __DIR__ . '/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'delete' && $id > 0) {
            $stmt = $conn->prepare('DELETE FROM vouchers WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $_SESSION['flash_success'] = 'Đã xóa voucher.';
        } elseif ($action === 'save') {
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $type = $_POST['discount_type'] ?? '';
            $value = trim($_POST['discount_value'] ?? '');
            $minimum = trim($_POST['min_order_amount'] ?? '0');
            $startsOn = $_POST['starts_on'] ?? '';
            $endsOn = $_POST['ends_on'] ?? '';
            $active = isset($_POST['active']) ? 1 : 0;

            if (!preg_match('/^[A-Z0-9_-]{3,32}$/D', $code)
                || !in_array($type, ['percent', 'fixed'], true)
                || !preg_match('/^\d{1,10}(\.\d{1,2})?$/D', $value) || (float) $value <= 0
                || ($type === 'percent' && (float) $value > 100)
                || !preg_match('/^\d{1,10}(\.\d{1,2})?$/D', $minimum)
                || !admin_valid_date($startsOn) || !admin_valid_date($endsOn) || $endsOn < $startsOn) {
                $_SESSION['flash_error'] = 'Kiểm tra mã voucher, mức giảm, giá trị đơn tối thiểu và thời gian áp dụng.';
                admin_redirect($id > 0 ? 'vouchers.php?edit=' . $id : 'vouchers.php');
            }

            if ($id > 0) {
                $stmt = $conn->prepare('UPDATE vouchers SET code = ?, discount_type = ?, discount_value = ?, min_order_amount = ?, starts_on = ?, ends_on = ?, active = ? WHERE id = ?');
                $stmt->bind_param('ssssssii', $code, $type, $value, $minimum, $startsOn, $endsOn, $active, $id);
            } else {
                $stmt = $conn->prepare('INSERT INTO vouchers (code, discount_type, discount_value, min_order_amount, starts_on, ends_on, active) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('ssssssi', $code, $type, $value, $minimum, $startsOn, $endsOn, $active);
            }
            $stmt->execute();
            $_SESSION['flash_success'] = $id > 0 ? 'Đã cập nhật voucher.' : 'Đã thêm voucher.';
        }
    } catch (mysqli_sql_exception $e) {
        if ((int) $e->getCode() !== 1062) {
            throw $e;
        }
        $_SESSION['flash_error'] = 'Mã voucher này đã tồn tại.';
        admin_redirect($id > 0 ? 'vouchers.php?edit=' . $id : 'vouchers.php');
    }
    admin_redirect('vouchers.php');
}

$vouchers = $conn->query('SELECT * FROM vouchers ORDER BY id DESC')->fetch_all(MYSQLI_ASSOC);
$editing = null;
$editId = (int) ($_GET['edit'] ?? 0);
foreach ($vouchers as $voucher) {
    if ((int) $voucher['id'] === $editId) {
        $editing = $voucher;
        break;
    }
}
$today = date('Y-m-d');
require_once __DIR__ . '/header.php';
?>
<h4 class="fw-bold mb-4">Quản lý voucher</h4>
<div class="row g-3">
    <div class="col-12 col-xl-4">
        <div class="card p-3">
            <h5 class="h6 fw-bold mb-3"><?= $editing ? 'Sửa voucher' : 'Thêm voucher' ?></h5>
            <form method="post" action="vouchers.php" class="row g-3">
                <?php admin_csrf_field(); ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
                <div class="col-12">
                    <label for="voucher-code" class="form-label">Mã voucher</label>
                    <input id="voucher-code" name="code" class="form-control text-uppercase" minlength="3" maxlength="32"
                           pattern="[A-Za-z0-9_\-]{3,32}" aria-describedby="code-help"
                           value="<?= htmlspecialchars($editing['code'] ?? '') ?>" required>
                    <div id="code-help" class="form-text">Từ 3 đến 32 ký tự, gồm chữ cái, số, dấu gạch ngang hoặc gạch dưới.</div>
                </div>
                <div class="col-6">
                    <label for="discount-type" class="form-label">Kiểu giảm</label>
                    <select id="discount-type" name="discount_type" class="form-select">
                        <option value="percent">Phần trăm (%)</option>
                        <option value="fixed" <?= ($editing['discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Cố định (₫)</option>
                    </select>
                </div>
                <div class="col-6">
                    <label for="discount-value" class="form-label">Mức giảm</label>
                    <input id="discount-value" name="discount_value" type="number" min="0.01" max="9999999999.99" step="0.01"
                           class="form-control" value="<?= htmlspecialchars($editing['discount_value'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                    <label for="minimum" class="form-label">Đơn tối thiểu (₫)</label>
                    <input id="minimum" name="min_order_amount" type="number" min="0" max="9999999999.99" step="0.01"
                           class="form-control" value="<?= htmlspecialchars($editing['min_order_amount'] ?? '0') ?>" required>
                </div>
                <div class="col-6">
                    <label for="starts-on" class="form-label">Từ ngày</label>
                    <input id="starts-on" name="starts_on" type="date" class="form-control"
                           value="<?= htmlspecialchars($editing['starts_on'] ?? $today) ?>" required>
                </div>
                <div class="col-6">
                    <label for="ends-on" class="form-label">Đến ngày</label>
                    <input id="ends-on" name="ends_on" type="date" class="form-control"
                           value="<?= htmlspecialchars($editing['ends_on'] ?? $today) ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-check">
                        <input type="checkbox" name="active" class="form-check-input" <?= ($editing['active'] ?? 1) ? 'checked' : '' ?>>
                        <span class="form-check-label">Bật voucher</span>
                    </label>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit"><?= $editing ? 'Lưu thay đổi' : 'Thêm voucher' ?></button>
                    <?php if ($editing): ?><a href="vouchers.php" class="btn btn-light">Hủy</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="card table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead><tr><th>Mã</th><th>Mức giảm</th><th>Đơn tối thiểu</th><th>Hiệu lực</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead>
                <tbody>
                <?php foreach ($vouchers as $voucher): ?>
                    <?php
                    $status = match (true) {
                        !$voucher['active'] => 'Đã tắt',
                        $today < $voucher['starts_on'] => 'Sắp áp dụng',
                        $today > $voucher['ends_on'] => 'Hết hạn',
                        default => 'Đang áp dụng',
                    };
                    $discount = $voucher['discount_type'] === 'percent'
                        ? rtrim(rtrim(number_format($voucher['discount_value'], 2, ',', '.'), '0'), ',') . '%'
                        : format_currency($voucher['discount_value']);
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($voucher['code']) ?></td>
                        <td><?= $discount ?></td>
                        <td><?= format_currency($voucher['min_order_amount']) ?></td>
                        <td class="small text-nowrap"><?= format_date($voucher['starts_on']) ?> – <?= format_date($voucher['ends_on']) ?></td>
                        <td><span class="badge <?= $status === 'Đang áp dụng' ? 'text-bg-success' : 'text-bg-light' ?>"><?= $status ?></span></td>
                        <td class="text-end text-nowrap">
                            <a href="vouchers.php?edit=<?= (int) $voucher['id'] ?>" class="btn btn-sm btn-outline-primary">Sửa</a>
                            <form method="post" action="vouchers.php" class="d-inline">
                                <?php admin_csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $voucher['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Xóa voucher này?">Xóa</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$vouchers): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Chưa có voucher nào.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
