<?php
/**
 * AJAX Handler: Cập nhật tài khoản người dùng
 * File: admin/ajax_update_user.php
 */
session_start();
require_once __DIR__ . '/../config/database.php'; // Điều chỉnh đường dẫn đến file cấu hình DB của bạn

// Kiểm tra quyền Admin
if (!isset($_SESSION['admin_user'])) {
    echo json_encode(['success' => false, 'message' => 'Bạn không có quyền thực hiện thao tác này!']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Thêm hoặc Sửa người dùng
    if ($action === 'save_user') {
        $id = (int)($_POST['id'] ?? 0);
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? 'customer';
        $status = $_POST['status'] ?? 'active';
        $password = trim($_POST['password'] ?? '');

        if (empty($fullname) || empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng nhập họ tên và email hợp lệ!']);
            exit;
        }

        // Kiểm tra trùng email (ngoại trừ chính user này)
        $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $chk->bind_param("si", $email, $id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email này đã được sử dụng bởi một tài khoản khác!']);
            exit;
        }
        $chk->close();

        if ($id > 0) {
            // Cập nhật
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, phone = ?, role = ?, status = ?, password = ? WHERE id = ?");
                $stmt->bind_param("ssssssi", $fullname, $email, $phone, $role, $status, $hashed, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, phone = ?, role = ?, status = ? WHERE id = ?");
                $stmt->bind_param("sssssi", $fullname, $email, $phone, $role, $status, $id);
            }

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Cập nhật tài khoản thành công!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $conn->error]);
            }
            $stmt->close();
        } else {
            // Thêm mới
            $passToHash = !empty($password) ? $password : '123456';
            $hashed = password_hash($passToHash, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (fullname, email, phone, role, status, password) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $fullname, $email, $phone, $role, $status, $hashed);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Thêm tài khoản thành công!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $conn->error]);
            }
            $stmt->close();
        }
    }
    // 2. Khóa hoặc Mở khóa tài khoản nhanh
    elseif ($action === 'toggle_status') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? 'active';

        // Không cho phép tự khóa chính tài khoản admin đang đăng nhập
        if (isset($_SESSION['admin_user']['id']) && $userId === (int)$_SESSION['admin_user']['id'] && $newStatus === 'locked') {
            echo json_encode(['success' => false, 'message' => 'Bạn không thể tự khóa tài khoản quản trị của chính mình!']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $userId);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => ($newStatus === 'locked' ? 'Đã khóa tài khoản!' : 'Đã mở khóa tài khoản thành công!')]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $conn->error]);
        }
        $stmt->close();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ!']);
}
