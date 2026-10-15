<?php
// report_partial_payments.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Fetch Partial Payments
$stmt = $pdo->prepare("
    SELECT s.*, c.customer_name, c.phone 
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    WHERE s.sale_date BETWEEN ? AND ? 
    AND s.sale_type = 'Sale Invoice' 
    AND s.payment_status = 'Partial'
    ORDER BY s.sale_date DESC, s.id DESC
");
$stmt->execute([$start_date, $end_date]);
$sales = $stmt->fetchAll();

$totalSales = 0;
$totalAdvance = 0;
$totalBalance = 0;
foreach ($sales as $s) {
    if ($s['payment_status'] != 'Returned') {
        $totalSales += $s['grand_total'];
        $totalAdvance += $s['paid_amount'];
        $totalBalance += ($s['grand_total'] - $s['paid_amount']);
    }
}
?>

<div class="mt-4 mb-3">
    <h2 class="text-wood"><i class="fas fa-hand-holding-usd me-2"></i>Partial Payment Report</h2>
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
                <h6>Total Advance Received</h6>
                <h3>₹<?= number_format($totalAdvance, 2) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-danger text-white">
            <div class="card-body">
                <h6>Total Balance Due</h6>
                <h3>₹<?= number_format($totalBalance, 2) ?></h3>
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
                    <th class="text-end">Total Amount</th>
                    <th class="text-end">Advance Paid</th>
                    <th class="text-end">Balance Due</th>
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
                        <td>
                            <?= htmlspecialchars($s['customer_name'] ?? 'Walk-in') ?><br>
                            <small class="text-muted"><?= htmlspecialchars($s['phone'] ?? '') ?></small>
                        </td>
                        <td class="text-end fw-bold">₹<?= number_format($s['grand_total'], 2) ?></td>
                        <td class="text-end text-success fw-bold">₹<?= number_format($s['paid_amount'], 2) ?></td>
                        <td class="text-end text-danger fw-bold">₹<?= number_format($s['grand_total'] - $s['paid_amount'], 2) ?></td>
                        <td>
                            <span class="badge bg-warning text-dark">Partial</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-light fw-bold">
                <tr>
                    <td colspan="3" class="text-end text-wood">Grand Total:</td>
                    <td class="text-end text-dark fs-6">₹<?= number_format($totalSales, 2) ?></td>
                    <td class="text-end text-success fs-6">₹<?= number_format($totalAdvance, 2) ?></td>
                    <td class="text-end text-danger fs-6">₹<?= number_format($totalBalance, 2) ?></td>
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
