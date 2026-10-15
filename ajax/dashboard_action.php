<?php
// ajax/dashboard_action.php
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!hasRole(['Super Admin', 'Admin', 'Staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
    exit();
}

$action = sanitize($_POST['action'] ?? '');
$branch_id = $_SESSION['branch_id'] ?? 1;
header('Content-Type: application/json');

$isSuperAdminOrStaff = hasRole(['Super Admin', 'Staff']);
$branchCond = $isSuperAdminOrStaff ? '' : 'AND branch_id = ' . (int)$branch_id;
$branchCondS = $isSuperAdminOrStaff ? '' : 'AND s.branch_id = ' . (int)$branch_id;
$branchCondE = $isSuperAdminOrStaff ? '' : 'AND e.branch_id = ' . (int)$branch_id;

if ($action === 'get_sales_details') {
    $period = $_POST['period'] ?? 'today';
    $today = date('Y-m-d');
    $currentMonth = date('m');
    $currentYear  = date('Y');

    if ($period === 'today') {
        $dateCond = "AND s.sale_date = :d1";
        $params = [':d1' => $today];
    } else { // month
        $dateCond = "AND MONTH(s.sale_date) = :m1 AND YEAR(s.sale_date) = :y1";
        $params = [':m1' => $currentMonth, ':y1' => $currentYear];
    }

    if (!$isSuperAdminOrStaff) $params[':branch_id'] = $branch_id;
    $branchFilter = $isSuperAdminOrStaff ? '' : 'AND s.branch_id = :branch_id';

    $sql = "SELECT s.id, s.invoice_no, s.sale_date, s.grand_total, s.paid_amount, s.payment_status,
                   c.customer_name
            FROM sales s
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE 1=1 $dateCond $branchFilter
            ORDER BY s.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $rows]);
    exit();
}

if ($action === 'get_pending_payments') {
    $params = [];
    if (!$isSuperAdminOrStaff) $params[':branch_id'] = $branch_id;
    $branchFilter = $isSuperAdminOrStaff ? '' : 'AND s.branch_id = :branch_id';

    $sql = "SELECT s.id, s.invoice_no, s.sale_date, s.grand_total, s.paid_amount, (s.grand_total - s.paid_amount) as pending,
                   c.customer_name
            FROM sales s
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.payment_status != 'Paid' $branchFilter
            ORDER BY pending DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $rows]);
    exit();
}

if ($action === 'get_low_stock') {
    $sql = "SELECT p.product_name, bs.quantity, p.min_stock_alert, p.sale_price
            FROM branch_stock bs
            JOIN products p ON bs.product_id = p.id
            WHERE bs.quantity <= p.min_stock_alert " . ($isSuperAdminOrStaff ? "" : "AND bs.branch_id = $branch_id") . "
            ORDER BY bs.quantity ASC";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $rows]);
    exit();
}

if ($action === 'get_emi_outstanding') {
    $sql = "SELECT es.id, es.due_date, es.amount, es.paid_amount, (es.amount + es.penalty_amount - es.paid_amount - es.discount) as pending,
                   e.customer_name, e.product_name
            FROM emi_schedule es
            JOIN emi e ON es.emi_id = e.id
            WHERE es.status != 'Paid' " . ($isSuperAdminOrStaff ? "" : "AND e.branch_id = $branch_id") . "
            ORDER BY es.due_date ASC";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $rows]);
    exit();
}

if ($action === 'get_emi_today') {
    $today = date('Y-m-d');
    $sql = "SELECT es.id, es.amount, es.paid_amount, (es.amount + es.penalty_amount - es.paid_amount - es.discount) as pending,
                   e.customer_name, e.product_name
            FROM emi_schedule es
            JOIN emi e ON es.emi_id = e.id
            WHERE es.due_date = '$today' AND es.status != 'Paid' " . ($isSuperAdminOrStaff ? "" : "AND e.branch_id = $branch_id") . "
            ORDER BY e.customer_name ASC";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $rows]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
