<?php
// report_purchase.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Fetch Purchases with Products
$stmt = $pdo->prepare("
    SELECT p.*, s.supplier_name, GROUP_CONCAT(pr.product_name SEPARATOR ', ') as products
    FROM purchases p 
    LEFT JOIN suppliers s ON p.supplier_id = s.id 
    LEFT JOIN purchase_items pi ON p.id = pi.purchase_id
    LEFT JOIN products pr ON pi.product_id = pr.id
    WHERE p.purchase_date BETWEEN ? AND ?
    GROUP BY p.id
    ORDER BY p.purchase_date DESC, p.id DESC
");
$stmt->execute([$start_date, $end_date]);
$purchases = $stmt->fetchAll();

$totalPurchase = 0;
$totalTax = 0;
$totalDiscount = 0;
$totalPaid = 0;
$totalBalance = 0;

foreach ($purchases as $p) {
    if ($p['payment_status'] != 'Returned') {
        $totalPurchase += $p['grand_total'];
        $totalTax += $p['tax_amount'];
        $totalDiscount += $p['discount_amount'];
        $totalPaid += $p['paid_amount'];
        $totalBalance += ($p['grand_total'] - $p['paid_amount']);
    }
}
?>

<div class="mt-4 mb-3">
    <h2 class="text-wood"><i class="fas fa-file-invoice me-2"></i>Purchase Report</h2>
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
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-primary text-white">
            <div class="card-body">
                <h6>Total Bills</h6>
                <h3><?= count($purchases) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-success text-white">
            <div class="card-body">
                <h6>Total Purchase</h6>
                <h3>₹<?= number_format($totalPurchase, 2) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-info text-white">
            <div class="card-body">
                <h6>Tax Paid</h6>
                <h3>₹<?= number_format($totalTax, 2) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-danger text-white">
            <div class="card-body">
                <h6>Balance Due</h6>
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
                    <th>Bill No</th>
                    <th>Supplier</th>
                    <th>Products</th>
                    <th class="text-end">Sub Total</th>
                    <th class="text-end">Tax</th>
                    <th class="text-end">Discount</th>
                    <th class="text-end">Grand Total</th>
                    <th class="text-end">Paid</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($purchases as $p): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($p['purchase_date'])) ?></td>
                        <td>
                            <a href="javascript:void(0)" onclick="viewPurchase(<?= $p['id'] ?>)" class="text-primary fw-bold text-decoration-none">
                                <i class="fas fa-external-link-alt small me-1"></i><?= htmlspecialchars($p['bill_no']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($p['supplier_name']) ?></td>
                        <td><small class="text-muted"><?= htmlspecialchars($p['products'] ?? 'N/A') ?></small></td>
                        <td class="text-end"><?= number_format($p['sub_total'], 2) ?></td>
                        <td class="text-end"><?= number_format($p['tax_amount'], 2) ?></td>
                        <td class="text-end"><?= number_format($p['discount_amount'], 2) ?></td>
                        <td class="text-end fw-bold">₹<?= number_format($p['grand_total'], 2) ?></td>
                        <td class="text-end text-success">₹<?= number_format($p['paid_amount'], 2) ?></td>
                        <td>
                            <?php if ($p['payment_status'] == 'Returned'): ?>
                                <span class="badge bg-danger">Returned</span>
                            <?php elseif ($p['payment_status'] == 'Partial'): ?>
                                <span class="badge bg-warning text-dark">Partial</span>
                            <?php elseif ($p['payment_status'] == 'Paid'): ?>
                                <span class="badge bg-success">Paid</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Unpaid</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-light fw-bold">
                <tr>
                    <td colspan="7" class="text-end text-wood">Grand Total:</td>
                    <td class="text-end text-success fs-5">₹<?= number_format($totalPurchase, 2) ?></td>
                    <td class="text-end text-success">₹<?= number_format($totalPaid, 2) ?></td>
                    <td class="text-end text-danger">₹<?= number_format($totalBalance, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Purchase Modal -->
<div class="modal fade" id="purchaseModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-wood text-white">
                <h5 class="modal-title">Purchase Bill Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="purchaseFrame" src="" style="width:100%; height:80vh; border:none;"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
function viewPurchase(id) {
    $('#purchaseFrame').attr('src', 'print_purchase.php?id=' + id + '&modal=1');
    $('#purchaseModal').modal('show');
}
</script>

<?php require_once 'includes/footer.php'; ?>
