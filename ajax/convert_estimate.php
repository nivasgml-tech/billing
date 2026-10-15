<?php
// ajax/convert_estimate.php
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!hasRole(['Super Admin', 'Admin', 'Staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
    exit();
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Check if it's actually an estimate
    $stmt = $pdo->prepare("SELECT branch_id, sale_type FROM sales WHERE id = ?");
    $stmt->execute([$id]);
    $sale = $stmt->fetch();

    if (!$sale || !in_array($sale['sale_type'], ['Estimate', 'Sale Order'])) {
        throw new Exception("Only Estimates or Sale Orders can be converted.");
    }

    // Update type to Sale Invoice
    $stmtUpdate = $pdo->prepare("UPDATE sales SET sale_type = 'Sale Invoice' WHERE id = ?");
    $stmtUpdate->execute([$id]);

    // Deduct Stock
    $stmtItems = $pdo->prepare("SELECT product_id, quantity FROM sales_items WHERE sale_id = ?");
    $stmtItems->execute([$id]);
    $items = $stmtItems->fetchAll();

    $branch_id = $sale['branch_id'];
    $stmtStockCheck = $pdo->prepare("SELECT id FROM branch_stock WHERE branch_id = ? AND product_id = ?");
    $stmtStockUpdate = $pdo->prepare("UPDATE branch_stock SET quantity = quantity - ? WHERE id = ?");
    $stmtStockInsert = $pdo->prepare("INSERT INTO branch_stock (branch_id, product_id, quantity) VALUES (?, ?, ?)");

    foreach ($items as $item) {
        $stmtStockCheck->execute([$branch_id, $item['product_id']]);
        if ($row = $stmtStockCheck->fetch()) {
            $stmtStockUpdate->execute([$item['quantity'], $row['id']]);
        } else {
            $stmtStockInsert->execute([$branch_id, $item['product_id'], -$item['quantity']]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
