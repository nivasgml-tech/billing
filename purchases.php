<?php
// purchases.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

if (hasRole(['Super Admin', 'Staff'])) {
    $stmt = $pdo->query("
        SELECT p.*, s.supplier_name, s.phone,
               (SELECT GROUP_CONCAT(DISTINCT tax_rate SEPARATOR ', ') FROM purchase_items WHERE purchase_id = p.id) as tax_rates
        FROM purchases p 
        LEFT JOIN suppliers s ON p.supplier_id = s.id 
        ORDER BY p.id DESC
    ");
    $purchases = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT p.*, s.supplier_name, s.phone,
               (SELECT GROUP_CONCAT(DISTINCT tax_rate SEPARATOR ', ') FROM purchase_items WHERE purchase_id = p.id) as tax_rates
        FROM purchases p 
        LEFT JOIN suppliers s ON p.supplier_id = s.id 
        WHERE p.branch_id = ?
        ORDER BY p.id DESC
    ");
    $stmt->execute([$_SESSION['branch_id']]);
    $purchases = $stmt->fetchAll();
}
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood"><i class="fas fa-file-invoice me-2"></i>Purchase Bills</h2>
    <a href="purchase.php" class="btn btn-wood">
        <i class="fas fa-plus-circle me-1"></i> New Purchase
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <table class="table table-striped table-hover datatable w-100 table-sm">
            <thead class="bg-wood text-white">
                <tr>
                    <th>Date</th>
                    <th>Bill No</th>
                    <th>Supplier</th>
                    <th class="text-center">Tax %</th>
                    <th>Total (₹)</th>
                    <th>Paid (₹)</th>
                    <th>Status</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($purchases as $p): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($p['purchase_date'])) ?></td>
                        <td><strong><?= htmlspecialchars($p['bill_no']) ?></strong></td>
                        <td>
                            <?= htmlspecialchars($p['supplier_name']) ?><br>
                            <small class="text-muted"><?= htmlspecialchars($p['phone'] ?? '') ?></small>
                        </td>
                        <td class="text-center">
                            <?php 
                                $rates = $p['tax_rates'];
                                if ($rates === null || $rates === '') {
                                    echo '<span class="badge bg-light text-dark border">0%</span>';
                                } else {
                                    $arr = explode(', ', $rates);
                                    $formatted = array_map(function($val) {
                                        return floatval($val) . '%';
                                    }, $arr);
                                    echo '<span class="badge bg-light text-dark border">' . implode(', ', $formatted) . '</span>';
                                }
                            ?>
                        </td>
                        <td class="fw-bold text-end"><?= number_format($p['grand_total'], 2) ?></td>
                        <td class="text-end"><?= number_format($p['paid_amount'], 2) ?></td>
                        <td>
                            <?php if ($p['payment_status'] == 'Paid'): ?>
                                <span class="badge bg-success">Paid</span>
                            <?php elseif ($p['payment_status'] == 'Partial'): ?>
                                <span class="badge bg-warning text-dark">Partial</span>
                            <?php elseif ($p['payment_status'] == 'Returned'): ?>
                                <span class="badge bg-danger">Returned</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Unpaid</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($p['payment_status'] != 'Paid' && $p['payment_status'] != 'Returned'): ?>
                                <button class="btn btn-sm btn-success me-1" onclick="openPaymentModal(<?= $p['id'] ?>, <?= $p['grand_total'] ?>, <?= $p['paid_amount'] ?>, '<?= $p['bill_no'] ?>')" title="Add Payment">
                                    <i class="fas fa-money-bill-wave"></i> Pay
                                </button>
                            <?php endif; ?>
                            <a href="print_barcode.php?purchase_id=<?= $p['id'] ?>" target="_blank" class="btn btn-sm btn-outline-wood" title="Print Barcodes" data-bs-toggle="tooltip">
                                <i class="fas fa-barcode"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-money-bill-wave me-2"></i>Record Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="paymentForm">
                    <input type="hidden" id="pay_purchase_id">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold text-uppercase">Bill Number</label>
                        <input type="text" id="pay_bill_no" class="form-control bg-light border-0 fw-bold" readonly>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small fw-bold text-uppercase">Total Amount</label>
                            <div class="h5 fw-bold text-dark">₹<span id="pay_total_amount">0.00</span></div>
                        </div>
                        <div class="col-6 text-end">
                            <label class="form-label text-muted small fw-bold text-uppercase">Remaining Balance</label>
                            <div class="h5 fw-bold text-danger">₹<span id="pay_balance">0.00</span></div>
                        </div>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Amount to Pay <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">₹</span>
                            <input type="number" id="pay_amount" class="form-control form-control-lg fw-bold text-success" step="0.01" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Payment Mode</label>
                            <select id="pay_mode" class="form-select">
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="UPI">UPI</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Date</label>
                            <input type="date" id="pay_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-success btn-lg shadow-sm" onclick="submitPayment()">
                            <i class="fas fa-check-circle me-1"></i> Confirm Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function openPaymentModal(id, total, paid, billNo) {
        let balance = (total - paid).toFixed(2);
        $('#pay_purchase_id').val(id);
        $('#pay_bill_no').val(billNo);
        $('#pay_total_amount').text(parseFloat(total).toLocaleString('en-IN', {minimumFractionDigits: 2}));
        $('#pay_balance').text(parseFloat(balance).toLocaleString('en-IN', {minimumFractionDigits: 2}));
        $('#pay_amount').val(balance).attr('max', balance);
        $('#paymentModal').modal('show');
    }

    function submitPayment() {
        let id = $('#pay_purchase_id').val();
        let amount = $('#pay_amount').val();
        let mode = $('#pay_mode').val();
        let date = $('#pay_date').val();

        if (!amount || amount <= 0) {
            Swal.fire('Error', 'Please enter a valid payment amount.', 'error');
            return;
        }

        let btn = $('#paymentModal .btn-success').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Processing...');

        $.post('ajax/purchase_action.php', {
            action: 'add_purchase_payment',
            purchase_id: id,
            amount_paid: amount,
            payment_mode: mode,
            payment_date: date
        }, function(res) {
            btn.prop('disabled', false).html('<i class="fas fa-check-circle me-1"></i> Confirm Payment');
            if (res.success) {
                Swal.fire('Success', res.message, 'success').then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    }
</script>