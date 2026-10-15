<?php
// ajax/unit_action.php
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!hasRole(['Super Admin', 'Admin', 'Staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
    exit();
}

$action = sanitize($_POST['action'] ?? '');
$id = (int)($_POST['id'] ?? 0);
$name = sanitize($_POST['unit_name'] ?? '');
$is_fractional = (int)($_POST['is_fractional'] ?? 0);
$status = (int)($_POST['status'] ?? 1);

if ($action === 'add') {
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Unit name is required']);
        exit();
    }

    // Check if exists
    $stmt = $pdo->prepare("SELECT id FROM units WHERE unit_name = ?");
    $stmt->execute([$name]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Unit already exists']);
        exit();
    }

    $stmt = $pdo->prepare("INSERT INTO units (unit_name, is_fractional, status) VALUES (?, ?, ?)");
    if ($stmt->execute([$name, $is_fractional, $status])) {
        echo json_encode(['success' => true, 'message' => 'Unit added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add unit']);
    }
} elseif ($action === 'edit') {
    if (empty($name) || $id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        exit();
    }

    // Check if another exists with same name
    $stmt = $pdo->prepare("SELECT id FROM units WHERE unit_name = ? AND id != ?");
    $stmt->execute([$name, $id]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Unit already exists']);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE units SET unit_name = ?, is_fractional = ?, status = ? WHERE id = ?");
    if ($stmt->execute([$name, $is_fractional, $status, $id])) {
        echo json_encode(['success' => true, 'message' => 'Unit updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update unit']);
    }
} elseif ($action === 'delete') {
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        exit();
    }

    // Check usage in products
    $stmt = $pdo->prepare("SELECT id FROM products WHERE unit_id = ? LIMIT 1");
    $stmt->execute([$id]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete: Unit is in use by products']);
        exit();
    }

    $stmt = $pdo->prepare("DELETE FROM units WHERE id = ?");
    if ($stmt->execute([$id])) {
        echo json_encode(['success' => true, 'message' => 'Unit deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete unit']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
