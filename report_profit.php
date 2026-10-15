<?php
// report_profit.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin']);

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Fetch Sales Items with Product Purchase Price
if (hasRole(['Super Admin'])) {
    $stmt = $pdo->prepare("
        SELECT 
            si.*, 
            p.product_name, 
            p.purchase_price, 
            s.invoice_no, 
            s.sale_date,
            c.customer_name
        FROM sales_items si
        JOIN sales s ON si.sale_id = s.id
        JOIN products p ON si.product_id = p.id
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE s.sale_date BETWEEN ? AND ? 
          AND s.sale_type = 'Sale Invoice'
          AND s.payment_status != 'Returned'
        ORDER BY s.sale_date DESC, s.id DESC
    ");
    $stmt->execute([$start_date, $end_date]);
} else {
    $stmt = $pdo->prepare("
        SELECT 
            si.*, 
            p.product_name, 
            p.purchase_price, 
            s.invoice_no, 
            s.sale_date,
            c.customer_name
        FROM sales_items si
        JOIN sales s ON si.sale_id = s.id
        JOIN products p ON si.product_id = p.id
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE s.branch_id = ? AND s.sale_date BETWEEN ? AND ? 
          AND s.sale_type = 'Sale Invoice'
          AND s.payment_status != 'Returned'
        ORDER BY s.sale_date DESC, s.id DESC
    ");
    $stmt->execute([$_SESSION['branch_id'], $start_date, $end_date]);
}
$items = $stmt->fetchAll();

// Fetch Other Income
if (hasRole(['Super Admin'])) {
    $stmtIncome = $pdo->prepare("
        SELECT * FROM other_income 
        WHERE income_date BETWEEN ? AND ?
        ORDER BY income_date DESC
    ");
    $stmtIncome->execute([$start_date, $end_date]);
} else {
    $stmtIncome = $pdo->prepare("
        SELECT * FROM other_income 
        WHERE branch_id = ? AND income_date BETWEEN ? AND ?
        ORDER BY income_date DESC
    ");
    $stmtIncome->execute([$_SESSION['branch_id'], $start_date, $end_date]);
}
$otherIncomes = $stmtIncome->fetchAll();

$totalPurchaseValue = 0;
$totalSaleValue = 0;
$totalProfit = 0;
$totalOtherIncome = 0;

// Calculate Sales Profit
foreach ($items as $item) {
    $net_qty = $item['quantity'] - ($item['returned_qty'] ?? 0);
    if ($net_qty <= 0) continue;

    $proportional_discount = ($item['discount_amount'] / $item['quantity']) * $net_qty;
    $row_sale_value = ($item['unit_price'] * $net_qty) - $proportional_discount;
    $row_purchase_value = $item['purchase_price'] * $net_qty;
    $row_profit = $row_sale_value - $row_purchase_value;

    $totalSaleValue += $row_sale_value;
    $totalPurchaseValue += $row_purchase_value;
    $totalProfit += $row_profit;
}

// Add Other Income to Profit
foreach ($otherIncomes as $inc) {
    $totalOtherIncome += $inc['amount'];
    $totalProfit += $inc['amount'];
}
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1 fw-bold text-dark"><i class="fas fa-hand-holding-usd me-2 text-success"></i>Profit Report</h1>
            <p class="text-secondary small mb-0">Detailed profit calculation based on product purchase and sales values</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print me-1"></i> Print</button>
        </div>
    </div>

    <!-- Date Filter Form -->
    <div class="card shadow-sm border-0 mb-4 bg-light">
        <div class="card-body">
            <form method="GET" class="row align-items-end g-2">
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">From Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">To Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100 py-2 fw-bold"><i class="fas fa-filter me-1"></i> Generate Report</button>
                </div>
                <div class="col-md-2">
                    <a href="report_profit.php" class="btn btn-outline-secondary btn-sm w-100 py-2"><i class="fas fa-sync-alt me-1"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-info text-white">
                <div class="card-body p-4">
                    <h6 class="text-uppercase small mb-2 opacity-75">Total Sales Value (Net)</h6>
                    <h2 class="mb-0 fw-bold" id="dispTotalSale">₹0.00</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-secondary text-white">
                <div class="card-body p-4">
                    <h6 class="text-uppercase small mb-2 opacity-75">Total Purchase Cost</h6>
                    <h2 class="mb-0 fw-bold" id="dispTotalPurchase">₹0.00</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-warning text-dark">
                <div class="card-body p-4">
                    <h6 class="text-uppercase small mb-2 opacity-75">Other Income</h6>
                    <h2 class="mb-0 fw-bold" id="dispTotalOtherIncome">₹0.00</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-success text-white">
                <div class="card-body p-4">
                    <h6 class="text-uppercase small mb-2 opacity-75">Estimated Net Profit</h6>
                    <h2 class="mb-0 fw-bold" id="dispTotalProfit">₹0.00</h2>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 datatable">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Date & Invoice</th>
                            <th>Product</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Purchase Price</th>
                            <th class="text-end">Sale Price (Net)</th>
                            <th class="text-end">Profit/Unit</th>
                            <th class="text-end pe-4">Total Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach ($items as $item): 
                            $net_qty = $item['quantity'] - ($item['returned_qty'] ?? 0);
                            if ($net_qty <= 0) continue;

                            // Net Sale Price = (Original Unit Price * Net Qty - Proportional Discount) / Net Qty
                            // Or simply: Net Sale Value = (Unit Price * Net Qty) - (Discount * (Net Qty / Original Qty))
                            $proportional_discount = ($item['discount_amount'] / $item['quantity']) * $net_qty;
                            $row_sale_value = ($item['unit_price'] * $net_qty) - $proportional_discount;
                            $row_purchase_value = $item['purchase_price'] * $net_qty;
                            $row_profit = $row_sale_value - $row_purchase_value;
                            
                            $unit_sale_price = $row_sale_value / $net_qty;
                            $unit_profit = $unit_sale_price - $item['purchase_price'];
                        ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= date('d M Y', strtotime($item['sale_date'])) ?></div>
                                    <small class="text-primary">#<?= htmlspecialchars($item['invoice_no']) ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($item['product_name']) ?></div>
                                    <small class="text-muted">Cust: <?= htmlspecialchars($item['customer_name'] ?? 'Walk-in') ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border"><?= number_format($net_qty, 2) ?></span>
                                </td>
                                <td class="text-end text-muted">₹<?= number_format($item['purchase_price'], 2) ?></td>
                                <td class="text-end text-dark fw-semibold">₹<?= number_format($unit_sale_price, 2) ?></td>
                                <td class="text-end <?= $unit_profit >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= $unit_profit >= 0 ? '+' : '' ?>₹<?= number_format($unit_profit, 2) ?>
                                </td>
                                <td class="text-end pe-4 fw-bold <?= $row_profit >= 0 ? 'text-success' : 'text-danger' ?>">
                                    ₹<?= number_format($row_profit, 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php foreach ($otherIncomes as $inc): ?>
                            <tr class="table-warning">
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= date('d M Y', strtotime($inc['income_date'])) ?></div>
                                    <small class="text-muted">Other Income</small>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($inc['source']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($inc['description']) ?></small>
                                </td>
                                <td class="text-center">-</td>
                                <td class="text-end">-</td>
                                <td class="text-end text-dark fw-semibold">₹<?= number_format($inc['amount'], 2) ?></td>
                                <td class="text-end text-success">+₹<?= number_format($inc['amount'], 2) ?></td>
                                <td class="text-end pe-4 fw-bold text-success">
                                    ₹<?= number_format($inc['amount'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#dispTotalSale').text('₹' + <?= json_encode(number_format($totalSaleValue, 2)) ?>);
    $('#dispTotalPurchase').text('₹' + <?= json_encode(number_format($totalPurchaseValue, 2)) ?>);
    $('#dispTotalOtherIncome').text('₹' + <?= json_encode(number_format($totalOtherIncome, 2)) ?>);
    $('#dispTotalProfit').text('₹' + <?= json_encode(number_format($totalProfit, 2)) ?>);
});
</script>

<style>
@media print {
    #sidebar-wrapper, .navbar, .btn, #filterCard { display: none !important; }
    #page-content-wrapper { padding: 0 !important; }
    .card { border: 1px solid #eee !important; box-shadow: none !important; }
}
</style>

<?php require_once 'includes/footer.php'; ?>
