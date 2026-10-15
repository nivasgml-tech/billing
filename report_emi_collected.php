<?php
// report_emi_collected.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin']);

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// SQL Query to fetch paid/partial installments
$query = "
    SELECT es.*, e.customer_name, e.product_name, e.mobile, e.manual_bill_no
    FROM emi_schedule es
    JOIN emi e ON es.emi_id = e.id
    WHERE es.payment_date BETWEEN ? AND ? AND es.paid_amount > 0
";

if (!hasRole(['Super Admin'])) {
    $query .= " AND e.branch_id = " . (int)$_SESSION['branch_id'];
}

$query .= " ORDER BY es.payment_date DESC, es.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute([$start_date, $end_date]);
$collections = $stmt->fetchAll();

$totalCollected = 0;
$totalDiscount = 0;
$totalPenalty = 0;

foreach ($collections as $c) {
    $totalCollected += $c['paid_amount'];
    $totalDiscount += $c['discount'];
    $totalPenalty += $c['penalty_amount'];
}
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood fw-bold"><i class="fas fa-money-check-alt me-2"></i>EMI Collected Report</h2>
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
        <i class="fas fa-print me-1"></i> Print Report
    </button>
</div>

<!-- Filters -->
<div class="card shadow-sm border-0 mb-4 bg-light">
    <div class="card-body">
        <form method="GET" class="row align-items-end g-3">
            <div class="col-md-3">
                <label class="form-label fw-bold small">From Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small">To Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-wood w-100"><i class="fas fa-filter me-1"></i> Filter</button>
            </div>
            <div class="col-md-2">
                <a href="report_emi_collected.php" class="btn btn-outline-secondary w-100"><i class="fas fa-sync-alt me-1"></i> Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4 g-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-success text-white">
            <div class="card-body text-center">
                <h6 class="text-uppercase small fw-bold mb-1">Total Collected</h6>
                <h3 class="mb-0">₹<?= number_format($totalCollected, 2) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-primary text-white">
            <div class="card-body text-center">
                <h6 class="text-uppercase small fw-bold mb-1">Total Discount</h6>
                <h3 class="mb-0">₹<?= number_format($totalDiscount, 2) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-danger text-white">
            <div class="card-body text-center">
                <h6 class="text-uppercase small fw-bold mb-1">Total Penalty</h6>
                <h3 class="mb-0">₹<?= number_format($totalPenalty, 2) ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0 datatable w-100">
                <thead class="bg-wood text-white">
                    <tr>
                        <th>Payment Date</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th class="text-center">EMI No.</th>
                        <th class="text-end">Collected (₹)</th>
                        <th class="text-end">Discount (₹)</th>
                        <th class="text-end">Penalty (₹)</th>
                        <th class="text-end">Net Total (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($collections as $c): 
                        $netTotal = $c['paid_amount'] + $c['penalty_amount'] - $c['discount'];
                    ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($c['payment_date'])) ?></td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($c['customer_name']) ?></div>
                                <small class="text-muted">Bill: <?= htmlspecialchars($c['manual_bill_no'] ?? 'N/A') ?></small>
                            </td>
                            <td><?= htmlspecialchars($c['product_name']) ?></td>
                            <td class="text-center"><?= $c['installment_no'] ?></td>
                            <td class="text-end text-success fw-bold"><?= number_format($c['paid_amount'], 2) ?></td>
                            <td class="text-end text-primary"><?= number_format($c['discount'], 2) ?></td>
                            <td class="text-end text-danger"><?= number_format($c['penalty_amount'], 2) ?></td>
                            <td class="text-end fw-bold">₹<?= number_format($netTotal, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($collections)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No collections found for the selected period.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="bg-light fw-bold">
                    <tr>
                        <td colspan="4" class="text-end">Grand Totals:</td>
                        <td class="text-end text-success">₹<?= number_format($totalCollected, 2) ?></td>
                        <td class="text-end text-primary">₹<?= number_format($totalDiscount, 2) ?></td>
                        <td class="text-end text-danger">₹<?= number_format($totalPenalty, 2) ?></td>
                        <td class="text-end">₹<?= number_format($totalCollected + $totalPenalty - $totalDiscount, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
