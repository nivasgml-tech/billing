<?php
// ajax/expense_category_action.php
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!hasRole(['Super Admin', 'Admin', 'Staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
    exit();
}

$action = sanitize($_POST['action'] ?? '');
$id = (int)($_POST['id'] ?? 0);
$category_name = sanitize($_POST['category_name'] ?? '');
$status = (int)($_POST['status'] ?? 1);

if ($action === 'add') {
    if (empty($category_name)) {
        echo json_encode(['success' => false, 'message' => 'Category name is required']);
        exit();
    }

    $stmt = $pdo->prepare("INSERT INTO expense_categories (category_name, status) VALUES (?, ?)");
    if ($stmt->execute([$category_name, $status])) {
        echo json_encode(['success' => true, 'message' => 'Category added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add category']);
    }
} elseif ($action === 'edit') {
    if (empty($category_name) || $id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE expense_categories SET category_name=?, status=? WHERE id=?");
    if ($stmt->execute([$category_name, $status, $id])) {
        echo json_encode(['success' => true, 'message' => 'Category updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update category']);
    }
} elseif ($action === 'delete') {
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        exit();
    }

    // Check usage in expenses
    $stmt = $pdo->prepare("SELECT id FROM expenses WHERE category_id = ? LIMIT 1");
    $stmt->execute([$id]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete: Category is in use by expenses']);
        exit();
    }

    $stmt = $pdo->prepare("DELETE FROM expense_categories WHERE id = ?");
    if ($stmt->execute([$id])) {
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete category']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
