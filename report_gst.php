<?php
// report_gst.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin']);

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Output GST (Sales Tax Collected)
$stmtSales = $pdo->prepare("
    SELECT SUM(sub_total) as total_taxable, SUM(tax_amount) as total_tax
    FROM sales 
    WHERE sale_date BETWEEN ? AND ? AND sale_type = 'Sale Invoice' AND is_gst_bill = 1 AND payment_status != 'Returned'
");
$stmtSales->execute([$start_date, $end_date]);
$outward = $stmtSales->fetch();

// Input GST (Purchase Tax Paid)
$stmtPurchases = $pdo->prepare("
    SELECT SUM(sub_total) as total_taxable, SUM(tax_amount) as total_tax
    FROM purchases 
    WHERE purchase_date BETWEEN ? AND ? AND payment_status != 'Returned'
");
$stmtPurchases->execute([$start_date, $end_date]);
$inward = $stmtPurchases->fetch();

$outwardTax = $outward['total_tax'] ?? 0;
$inwardTax = $inward['total_tax'] ?? 0;
$netGst = $outwardTax - $inwardTax;
?>

<div class="mt-4 mb-3">
    <h2 class="text-wood"><i class="fas fa-file-alt me-2"></i>GST Report</h2>
</div>

<div class="card shadow-sm border-0 mb-4 bg-light">
    <div class="card-body">
        <form method="GET" class="row align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold">From Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">To Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-wood w-100"><i class="fas fa-filter"></i> Filter</button>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-outline-secondary w-100" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-success text-white fw-bold">
                <i class="fas fa-arrow-up me-1"></i> Outward Supplies (Sales)
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <td class="text-muted">Total Taxable Value:</td>
                        <td class="text-end fw-bold">₹<?= number_format($outward['total_taxable'] ?? 0, 2) ?></td>
                    </tr>
                    <tr class="fs-4">
                        <td class="text-wood">Output Tax Collected:</td>
                        <td class="text-end fw-bold text-success">₹<?= number_format($outwardTax, 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-danger text-white fw-bold">
                <i class="fas fa-arrow-down me-1"></i> Inward Supplies (Purchases)
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <td class="text-muted">Total Taxable Value:</td>
                        <td class="text-end fw-bold">₹<?= number_format($inward['total_taxable'] ?? 0, 2) ?></td>
                    </tr>
                    <tr class="fs-4">
                        <td class="text-wood">Input Tax Paid (ITC):</td>
                        <td class="text-end fw-bold text-danger">₹<?= number_format($inwardTax, 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow mb-4">
    <div class="card-body text-center p-5">
        <h4 class="text-muted mb-3">Net GST Payable</h4>
        <?php if ($netGst > 0): ?>
            <h1 class="text-danger fw-bold display-4">₹<?= number_format($netGst, 2) ?></h1>
            <p class="text-danger"><i class="fas fa-exclamation-circle"></i> Tax Liability (Pay to Gov)</p>
        <?php else: ?>
            <h1 class="text-success fw-bold display-4">₹<?= number_format(abs($netGst), 2) ?></h1>
            <p class="text-success"><i class="fas fa-check-circle"></i> Input Tax Credit (Carry Forward)</p>
        <?php endif; ?>
    </div>
</div>

<!-- Exportable GST Details Table -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white py-3 border-0">
        <h5 class="card-title mb-0 fw-bold"><i class="fas fa-file-export me-2 text-primary"></i>Exportable Data Details</h5>
    </div>
    <div class="card-body">
        <table id="gstTable" class="table table-striped datatable w-100 table-bordered">
            <thead class="bg-wood text-white">
                <tr>
                    <th>Type</th>
                    <th>Particulars</th>
                    <th class="text-end">Taxable Value (₹)</th>
                    <th class="text-end">Tax Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Outward</td>
                    <td>Sales (Output Tax Collected)</td>
                    <td class="text-end fw-bold text-dark"><?= number_format($outward['total_taxable'] ?? 0, 2) ?></td>
                    <td class="text-end fw-bold text-success"><?= number_format($outwardTax, 2) ?></td>
                </tr>
                <tr>
                    <td>Inward</td>
                    <td>Purchases (Input Tax Paid / ITC)</td>
                    <td class="text-end fw-bold text-dark"><?= number_format($inward['total_taxable'] ?? 0, 2) ?></td>
                    <td class="text-end fw-bold text-danger"><?= number_format($inwardTax, 2) ?></td>
                </tr>
                <tr class="table-<?= $netGst > 0 ? 'danger' : 'success' ?> border-top border-dark">
                    <td class="fw-bold">Summary</td>
                    <td class="fw-bold fs-5">Net GST <?= $netGst > 0 ? 'Payable' : 'Credit' ?></td>
                    <td class="text-end fw-bold fs-5"><?= number_format(abs(($outward['total_taxable'] ?? 0) - ($inward['total_taxable'] ?? 0)), 2) ?></td>
                    <td class="text-end fw-bold fs-5"><?= number_format(abs($netGst), 2) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>