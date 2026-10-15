<?php
// report_sales.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Fetch Sales with Products
$stmt = $pdo->prepare("
    SELECT s.*, c.customer_name, GROUP_CONCAT(p.product_name SEPARATOR ', ') as products
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    LEFT JOIN sales_items si ON s.id = si.sale_id
    LEFT JOIN products p ON si.product_id = p.id
    WHERE s.sale_date BETWEEN ? AND ? AND s.sale_type = 'Sale Invoice'
    GROUP BY s.id
    ORDER BY s.sale_date DESC, s.id DESC
");
$stmt->execute([$start_date, $end_date]);
$sales = $stmt->fetchAll();

$totalSales = 0;
$totalTax = 0;
$totalDiscount = 0;
$totalAdvance = 0;
$totalBalance = 0;
foreach ($sales as $s) {
    if ($s['payment_status'] != 'Returned') {
        $totalSales += $s['grand_total'];
        $totalTax += $s['tax_amount'];
        $totalDiscount += $s['discount_amount'];
        $totalAdvance += $s['paid_amount'];
        $totalBalance += ($s['grand_total'] - $s['paid_amount']);
    }
}
?>

<div class="mt-4 mb-3">
    <h2 class="text-wood"><i class="fas fa-chart-line me-2"></i>Sales Report</h2>
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

<div class="row mb-4 text-center">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-primary text-white">
            <div class="card-body">
                <h6>Total Invoices</h6>
                <h3><?= count($sales) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-success text-white">
            <div class="card-body">
                <h6>Net Revenue</h6>
                <h3>₹<?= number_format($totalSales, 2) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-info text-white">
            <div class="card-body">
                <h6>Tax Collected</h6>
                <h3>₹<?= number_format($totalTax, 2) ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <table class="table table-striped datatable w-100 table-sm">
            <thead class="bg-wood text-white">
                <tr>
                    <th>Date</th>
                    <th>Invoice No</th>
                    <th>Customer</th>
                    <th>Products</th>
                    <th class="text-end">Sub Total</th>
                    <th class="text-end">Tax</th>
                    <th class="text-end">Total Amount</th>
                    <th class="text-end">Advance</th>
                    <th class="text-end">Balance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales as $s): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($s['sale_date'])) ?></td>
                        <td>
                            <a href="javascript:void(0)" onclick="viewInvoice(<?= $s['id'] ?>)" class="text-primary fw-bold text-decoration-none">
                                <i class="fas fa-external-link-alt small me-1"></i><?= htmlspecialchars($s['invoice_no']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($s['customer_name'] ?? 'Walk-in') ?></td>
                        <td><small class="text-muted"><?= htmlspecialchars($s['products'] ?? 'N/A') ?></small></td>
                        <td class="text-end"><?= number_format($s['sub_total'], 2) ?></td>
                        <td class="text-end"><?= number_format($s['tax_amount'], 2) ?></td>
                        <td class="text-end fw-bold">₹<?= number_format($s['grand_total'], 2) ?></td>
                        <td class="text-end text-success">₹<?= number_format($s['paid_amount'], 2) ?></td>
                        <td class="text-end text-danger">₹<?= number_format($s['grand_total'] - $s['paid_amount'], 2) ?></td>
                        <td>
                            <?php if ($s['payment_status'] == 'Returned'): ?>
                                <span class="badge bg-danger">Returned</span>
                            <?php elseif ($s['payment_status'] == 'Partial'): ?>
                                <span class="badge bg-warning text-dark">Partial</span>
                            <?php else: ?>
                                <span class="badge bg-success">Completed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-light fw-bold">
                <tr>
                    <td colspan="6" class="text-end text-wood">Grand Total:</td>
                    <td class="text-end text-success fs-5">₹<?= number_format($totalSales, 2) ?></td>
                    <td class="text-end text-success">₹<?= number_format($totalAdvance, 2) ?></td>
                    <td class="text-end text-danger">₹<?= number_format($totalBalance, 2) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Invoice Modal -->
<div class="modal fade" id="invoiceModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-wood text-white">
                <h5 class="modal-title">Invoice Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="invoiceFrame" src="" style="width:100%; height:80vh; border:none;"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
function viewInvoice(id) {
    $('#invoiceFrame').attr('src', 'print_invoice.php?id=' + id + '&modal=1');
    $('#invoiceModal').modal('show');
}
</script>

<?php require_once 'includes/footer.php'; ?>