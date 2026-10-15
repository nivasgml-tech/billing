<?php
// ajax/category_action.php
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!hasRole(['Super Admin', 'Admin', 'Staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
    exit();
}

$action = sanitize($_POST['action'] ?? '');
$id = (int)($_POST['id'] ?? 0);
$name = sanitize($_POST['category_name'] ?? '');
$status = (int)($_POST['status'] ?? 1);

if ($action === 'add') {
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Category name is required']);
        exit();
    }

    // Check if exists
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE category_name = ?");
    $stmt->execute([$name]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Category already exists']);
        exit();
    }

    $stmt = $pdo->prepare("INSERT INTO categories (category_name, status) VALUES (?, ?)");
    if ($stmt->execute([$name, $status])) {
        echo json_encode(['success' => true, 'message' => 'Category added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add category']);
    }
} elseif ($action === 'edit') {
    if (empty($name) || $id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        exit();
    }

    // Check if another exists with same name
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE category_name = ? AND id != ?");
    $stmt->execute([$name, $id]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Category already exists']);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE categories SET category_name = ?, status = ? WHERE id = ?");
    if ($stmt->execute([$name, $status, $id])) {
        echo json_encode(['success' => true, 'message' => 'Category updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update category']);
    }
} elseif ($action === 'delete') {
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        exit();
    }

    // Check usage in products
    $stmt = $pdo->prepare("SELECT id FROM products WHERE category_id = ? LIMIT 1");
    $stmt->execute([$id]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete: Category is in use by products']);
        exit();
    }

    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    if ($stmt->execute([$id])) {
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete category']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
