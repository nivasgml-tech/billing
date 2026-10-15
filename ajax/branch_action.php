<?php
// ajax/branch_action.php
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!hasRole(['Super Admin', 'Admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
    exit();
}

$action = sanitize($_POST['action'] ?? '');
$id = (int)($_POST['id'] ?? 0);

$branch_name = sanitize($_POST['branch_name'] ?? '');
$address = sanitize($_POST['address'] ?? '');
$phone = sanitize($_POST['phone'] ?? '');
$gst_number = sanitize($_POST['gst_number'] ?? '');
$status = (int)($_POST['status'] ?? 1);

if ($action === 'add') {
    if (empty($branch_name)) {
        echo json_encode(['success' => false, 'message' => 'Branch name is required']);
        exit();
    }

    $stmt = $pdo->prepare("INSERT INTO branches (branch_name, address, phone, gst_number, status) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$branch_name, $address, $phone, $gst_number, $status])) {
        echo json_encode(['success' => true, 'message' => 'Branch added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add branch']);
    }
} elseif ($action === 'edit') {
    if (empty($branch_name) || $id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE branches SET branch_name=?, address=?, phone=?, gst_number=?, status=? WHERE id=?");
    if ($stmt->execute([$branch_name, $address, $phone, $gst_number, $status, $id])) {
        echo json_encode(['success' => true, 'message' => 'Branch updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update branch']);
    }
} elseif ($action === 'delete') {
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid branch ID']);
        exit();
    }

    // Check for associated users
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE branch_id = ?");
    $stmtCheck->execute([$id]);
    if ($stmtCheck->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete branch because it has associated users. Please move or delete users first.']);
        exit();
    }

    $stmt = $pdo->prepare("DELETE FROM branches WHERE id = ?");
    if ($stmt->execute([$id])) {
        echo json_encode(['success' => true, 'message' => 'Branch deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete branch']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
