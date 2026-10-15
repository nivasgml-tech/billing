<?php
// report_sales_return.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Fetch Sales Returns
$stmt = $pdo->prepare("
    SELECT sr.*, s.invoice_no, c.customer_name, u.full_name as returned_by_name
    FROM sales_returns sr
    JOIN sales s ON sr.sale_id = s.id
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN users u ON sr.returned_by = u.id
    WHERE sr.return_date BETWEEN ? AND ?
    ORDER BY sr.return_date DESC, sr.id DESC
");
$stmt->execute([$start_date, $end_date]);
$returns = $stmt->fetchAll();

$totalRefund = 0;
foreach ($returns as $r) {
    $totalRefund += $r['total_refund'];
}
?>

<div class="mt-4 mb-3">
    <h2 class="text-wood"><i class="fas fa-undo me-2"></i>Sales Return Report</h2>
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
    <div class="col-md-6">
        <div class="card border-0 shadow-sm bg-primary text-white">
            <div class="card-body">
                <h6>Total Return Transactions</h6>
                <h3><?= count($returns) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm bg-danger text-white">
            <div class="card-body">
                <h6>Total Refund Issued</h6>
                <h3>₹<?= number_format($totalRefund, 2) ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <table class="table table-striped datatable w-100 table-sm">
            <thead class="bg-wood text-white">
                <tr>
                    <th>Return Date</th>
                    <th>Invoice No</th>
                    <th>Customer</th>
                    <th>Reason</th>
                    <th class="text-end">Refund Amount</th>
                    <th>Type</th>
                    <th>Processed By</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($returns as $r): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($r['return_date'])) ?></td>
                        <td>
                            <a href="javascript:void(0)" onclick="viewInvoice(<?= $r['sale_id'] ?>)" class="text-primary fw-bold text-decoration-none">
                                <i class="fas fa-external-link-alt small me-1"></i><?= htmlspecialchars($r['invoice_no']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($r['customer_name'] ?? 'Walk-in') ?></td>
                        <td><?= htmlspecialchars($r['return_reason']) ?></td>
                        <td class="text-end fw-bold text-danger">₹<?= number_format($r['total_refund'], 2) ?></td>
                        <td>
                            <?php if ($r['is_full_return']): ?>
                                <span class="badge bg-danger">Full Return</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Partial Return</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($r['returned_by_name'] ?? 'System') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-light fw-bold">
                <tr>
                    <td colspan="4" class="text-end text-wood">Grand Total Refund:</td>
                    <td class="text-end text-danger fs-5">₹<?= number_format($totalRefund, 2) ?></td>
                    <td colspan="2"></td>
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
