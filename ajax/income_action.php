<?php
// ajax/income_action.php
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!hasRole(['Super Admin', 'Admin', 'Staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
    exit();
}

$action = sanitize($_POST['action'] ?? '');
$branch_id = $_SESSION['branch_id'] ?? 1;

if ($action === 'add') {
    $source = sanitize($_POST['source']);
    $amount = (float)$_POST['amount'];
    $income_date = sanitize($_POST['income_date']);
    $payment_mode = sanitize($_POST['payment_mode']);
    $description = sanitize($_POST['description'] ?? '');
    $account_type = sanitize($_POST['account_type'] ?? 'General');

    if (empty($source) || $amount <= 0 || empty($income_date)) {
        echo json_encode(['success' => false, 'message' => 'Valid Date, Source and Amount are required']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO other_income (branch_id, source, account_type, amount, income_date, payment_mode, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$branch_id, $source, $account_type, $amount, $income_date, $payment_mode, $description]);
        $income_id = $pdo->lastInsertId();

        // Also log in payments as Payment In
        $stmtPay = $pdo->prepare("
            INSERT INTO payments (branch_id, transaction_type, reference_id, amount, payment_mode, payment_date, notes)
            VALUES (?, 'Payment In', ?, ?, ?, ?, ?)
        ");
        $stmtPay->execute([$branch_id, $income_id, $amount, $payment_mode, $income_date, "Other Income: $source"]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Income logged successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} elseif ($action === 'edit') {
    $id = (int)$_POST['id'];
    $source = sanitize($_POST['source']);
    $amount = (float)$_POST['amount'];
    $income_date = sanitize($_POST['income_date']);
    $payment_mode = sanitize($_POST['payment_mode']);
    $description = sanitize($_POST['description'] ?? '');
    $account_type = sanitize($_POST['account_type'] ?? 'General');

    if ($id <= 0 || empty($source) || $amount <= 0 || empty($income_date)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Update other_income
        $stmt = $pdo->prepare("
            UPDATE other_income 
            SET source = ?, account_type = ?, amount = ?, income_date = ?, payment_mode = ?, description = ?
            WHERE id = ? AND branch_id = ?
        ");
        $stmt->execute([$source, $account_type, $amount, $income_date, $payment_mode, $description, $id, $branch_id]);

        // Update associated payment
        $stmtPay = $pdo->prepare("
            UPDATE payments 
            SET amount = ?, payment_mode = ?, payment_date = ?, notes = ?
            WHERE reference_id = ? AND transaction_type = 'Payment In' AND notes LIKE 'Other Income:%'
        ");
        $stmtPay->execute([$amount, $payment_mode, $income_date, "Other Income: $source", $id]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Income record updated successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} elseif ($action === 'delete') {
    $id = (int)$_POST['id'];
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM other_income WHERE id = ? AND branch_id = ?");
        $stmt->execute([$id, $branch_id]);

        // Remove from payments too
        $stmtPay = $pdo->prepare("DELETE FROM payments WHERE reference_id = ? AND transaction_type = 'Payment In' AND notes LIKE 'Other Income:%'");
        $stmtPay->execute([$id]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Income record deleted successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
