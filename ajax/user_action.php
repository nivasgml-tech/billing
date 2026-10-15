<?php
// ajax/user_action.php
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!hasRole(['Super Admin', 'Admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
    exit();
}

$action = sanitize($_POST['action'] ?? '');
$id = (int)($_POST['id'] ?? 0);

$full_name = sanitize($_POST['full_name'] ?? '');
$username = sanitize($_POST['username'] ?? '');
$email = sanitize($_POST['email'] ?? '');
$role_id = (int)($_POST['role_id'] ?? 0);
$branch_id = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
$status = (int)($_POST['status'] ?? 1);
$phone = sanitize($_POST['phone'] ?? '');
$salary = (float)($_POST['salary'] ?? 0);
$password_raw = trim($_POST['password'] ?? '');

if ($action === 'add') {
    if (empty($username) || empty($password_raw) || $role_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Username, Password and Role are required']);
        exit();
    }

    // Check if username exists
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmtCheck->execute([$username]);
    if ($stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        exit();
    }

    $hashed = password_hash($password_raw, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (role_id, branch_id, username, password, full_name, email, phone, salary, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$role_id, $branch_id, $username, $hashed, $full_name, $email, $phone, $salary, $status])) {
        echo json_encode(['success' => true, 'message' => 'Staff added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add staff']);
    }
} elseif ($action === 'edit') {
    if (empty($username) || $id <= 0 || $role_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        exit();
    }

    // Check if username exists for OTHER user
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmtCheck->execute([$username, $id]);
    if ($stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Username belongs to another user']);
        exit();
    }

    if (!empty($password_raw)) {
        $hashed = password_hash($password_raw, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET role_id=?, branch_id=?, username=?, password=?, full_name=?, email=?, phone=?, salary=?, status=? WHERE id=?");
        $res = $stmt->execute([$role_id, $branch_id, $username, $hashed, $full_name, $email, $phone, $salary, $status, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET role_id=?, branch_id=?, username=?, full_name=?, email=?, phone=?, salary=?, status=? WHERE id=?");
        $res = $stmt->execute([$role_id, $branch_id, $username, $full_name, $email, $phone, $salary, $status, $id]);
    }

    if ($res) {
        echo json_encode(['success' => true, 'message' => 'Staff updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update staff']);
    }
} elseif ($action === 'delete') {
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit();
    }

    // Prevent self-deletion
    if ($id === (int)($_SESSION['user_id'] ?? 0)) {
        echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
        exit();
    }

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    if ($stmt->execute([$id])) {
        echo json_encode(['success' => true, 'message' => 'Staff deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete staff']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
