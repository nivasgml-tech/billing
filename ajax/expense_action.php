<?php
// ajax/expense_action.php
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!hasRole(['Super Admin', 'Admin', 'Staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
    exit();
}

$action = sanitize($_POST['action'] ?? '');
$branch_id = $_SESSION['branch_id'] ?? 1;
header('Content-Type: application/json');

if ($action === 'get_expense_report') {
    $period = $_POST['period'] ?? 'today';
    $isSuperAdminOrStaff = hasRole(['Super Admin', 'Staff']);
    $today = date('Y-m-d');
    $currentMonth = date('m');
    $currentYear  = date('Y');
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $weekEnd   = date('Y-m-d', strtotime('sunday this week'));

    $branchCond = $isSuperAdminOrStaff ? '' : 'AND e.branch_id = :branch_id';
    $params = [];
    if (!$isSuperAdminOrStaff) $params[':branch_id'] = $branch_id;

    if ($period === 'today') {
        $dateCond = "AND e.expense_date = :d1";
        $params[':d1'] = $today;
    } elseif ($period === 'week') {
        $dateCond = "AND e.expense_date BETWEEN :d1 AND :d2";
        $params[':d1'] = $weekStart;
        $params[':d2'] = $weekEnd;
    } else { // month
        $dateCond = "AND MONTH(e.expense_date) = :m1 AND YEAR(e.expense_date) = :y1";
        $params[':m1'] = $currentMonth;
        $params[':y1'] = $currentYear;
    }

    $sql = "SELECT e.expense_date, e.amount, e.description, e.payment_mode,
                   c.category_name
            FROM expenses e
            LEFT JOIN expense_categories c ON e.category_id = c.id
            WHERE 1=1 $dateCond $branchCond
            ORDER BY e.expense_date DESC, e.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $rows]);
    exit();
}

if ($action === 'add') {
    $category_id = (int)$_POST['category_id'];
    $amount = (float)$_POST['amount'];
    $expense_date = sanitize($_POST['expense_date']);
    $payment_mode = sanitize($_POST['payment_mode']);
    $description = sanitize($_POST['description'] ?? '');
    $account_type = sanitize($_POST['account_type'] ?? 'General');

    if ($category_id <= 0 || $amount <= 0 || empty($expense_date)) {
        echo json_encode(['success' => false, 'message' => 'Valid Date, Category and Amount are required']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO expenses (branch_id, category_id, account_type, amount, expense_date, payment_mode, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$branch_id, $category_id, $account_type, $amount, $expense_date, $payment_mode, $description]);
        $expense_id = $pdo->lastInsertId();

        // Also log in payments as Payment Out
        $stmtPay = $pdo->prepare("
            INSERT INTO payments (branch_id, transaction_type, reference_id, amount, payment_mode, payment_date, notes)
            VALUES (?, 'Payment Out', ?, ?, ?, ?, ?)
        ");
        $stmtPay->execute([$branch_id, $expense_id, $amount, $payment_mode, $expense_date, "Expense: $description"]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Expense added successfully']);
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

        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND branch_id = ?");
        $stmt->execute([$id, $branch_id]);

        // Remove from payments too
        $stmtPay = $pdo->prepare("DELETE FROM payments WHERE reference_id = ? AND transaction_type = 'Payment Out' AND notes LIKE 'Expense:%'");
        $stmtPay->execute([$id]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Expense deleted successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
