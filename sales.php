<?php
// sales.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

if (hasRole(['Super Admin', 'Staff'])) {
    $stmt = $pdo->prepare("
        SELECT s.*, c.customer_name, c.phone 
        FROM sales s 
        LEFT JOIN customers c ON s.customer_id = c.id 
        WHERE s.sale_date BETWEEN ? AND ?
        ORDER BY s.id DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $sales = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT s.*, c.customer_name, c.phone 
        FROM sales s 
        LEFT JOIN customers c ON s.customer_id = c.id 
        WHERE s.branch_id = ? AND s.sale_date BETWEEN ? AND ?
        ORDER BY s.id DESC
    ");
    $stmt->execute([$_SESSION['branch_id'], $start_date, $end_date]);
    $sales = $stmt->fetchAll();
}
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood"><i class="fas fa-file-invoice-dollar me-2"></i>Sales History</h2>
    <a href="pos.php" class="btn btn-wood">
        <i class="fas fa-plus-circle me-1"></i> New Sale
    </a>
</div>

<div class="card shadow-sm border-0 mb-4 bg-light">
    <div class="card-body">
        <form method="GET" class="row align-items-end g-2">
            <div class="col-md-3">
                <label class="form-label fw-bold small">From Date</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start_date) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small">To Date</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end_date) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-wood btn-sm w-100"><i class="fas fa-filter me-1"></i> Filter</button>
            </div>
            <div class="col-md-2">
                <a href="sales.php" class="btn btn-outline-secondary btn-sm w-100"><i class="fas fa-sync-alt me-1"></i> Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <table class="table table-striped table-hover datatable w-100 table-sm">
            <thead class="bg-wood text-white">
                <tr>
                    <th>Date</th>
                    <th>Invoice No</th>
                    <th>Customer</th>
                    <th>Type</th>
                    <th>Total (₹)</th>
                    <th>Paid (₹)</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales as $s): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($s['sale_date'])) ?></td>
                        <td><strong><?= htmlspecialchars($s['invoice_no']) ?></strong></td>
                        <td>
                            <?= htmlspecialchars($s['customer_name'] ?? 'Walk-in') ?><br>
                            <small class="text-muted"><?= htmlspecialchars($s['phone'] ?? '') ?></small>
                        </td>
                        <td>
                            <?php if ($s['sale_type'] == 'Estimate'): ?>
                                <span class="badge bg-secondary">Estimate</span>
                            <?php elseif ($s['sale_type'] == 'Sale Order'): ?>
                                <span class="badge bg-info text-dark">Order</span>
                            <?php else: ?>
                                <span class="badge bg-success">Invoice</span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-bold text-end"><?= number_format($s['grand_total'], 2) ?></td>
                        <td class="text-end"><?= number_format($s['paid_amount'], 2) ?></td>
                        <td>
                            <?php if ($s['payment_status'] == 'Paid'): ?>
                                <span class="badge bg-success">Paid</span>
                            <?php elseif ($s['payment_status'] == 'Partial'): ?>
                                <span class="badge bg-warning text-dark">Partial</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Unpaid</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <?php if ($s['payment_status'] != 'Paid' && $s['payment_status'] != 'Returned' && $s['sale_type'] != 'Estimate'): ?>
                                    <button class="btn btn-sm btn-success" onclick="collectSalePayment(<?= $s['id'] ?>, <?= $s['grand_total'] - $s['paid_amount'] ?>, '<?= htmlspecialchars($s['invoice_no']) ?>')" title="Collect Balance">
                                        <i class="fas fa-hand-holding-usd me-1"></i> Pay
                                    </button>
                                <?php endif; ?>
                                <a href="pos.php?edit_id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-info" title="Edit Invoice">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="print_invoice.php?id=<?= $s['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Print/View">
                                    <i class="fas fa-print"></i>
                                </a>
                                <button class="btn btn-sm btn-outline-success" onclick="shareWhatsApp('<?= htmlspecialchars($s['phone'] ?? '') ?>', '<?= htmlspecialchars($s['invoice_no']) ?>', <?= $s['grand_total'] ?>)" title="Share WhatsApp">
                                    <i class="fab fa-whatsapp"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function shareWhatsApp(phone, invoiceNo, amount) {
        if (!phone) {
            Swal.fire('Error', 'Customer phone number is missing.', 'error');
            return;
        }
        let msg = `Hello! Thank you for your purchase. Invoice ${invoiceNo} for Rs ${amount} has been generated.`;
        let url = `https://wa.me/91${phone}?text=${encodeURIComponent(msg)}`;
        window.open(url, '_blank');
    }

    function collectSalePayment(saleId, balance, invoiceNo) {
        Swal.fire({
            title: 'Collect Payment',
            text: 'Collect balance for Invoice: ' + invoiceNo,
            html: `
                <div class="text-start p-2">
                    <div class="alert alert-info py-2 small mb-3">Remaining Balance: <strong>₹${parseFloat(balance).toLocaleString()}</strong></div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Amount Paying (₹)</label>
                        <input type="number" id="pay_amount" class="form-control" value="${parseFloat(balance).toFixed(2)}" step="0.01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Payment Mode</label>
                        <select id="pay_mode" class="form-select">
                            <option value="Cash">Cash</option>
                            <option value="UPI">UPI</option>
                            <option value="Card">Card</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Record Payment',
            confirmButtonColor: '#28a745',
            preConfirm: () => {
                const amount = $('#pay_amount').val();
                const mode = $('#pay_mode').val();
                if (!amount || amount <= 0) {
                    Swal.showValidationMessage('Please enter a valid amount');
                    return false;
                }
                return { amount, mode };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax/pos_action.php', {
                    action: 'collect_sale_payment',
                    sale_id: saleId,
                    amount: result.value.amount,
                    payment_mode: result.value.mode
                }, function(res) {
                    if (res.success) {
                        Swal.fire('Success', res.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }, 'json');
            }
        });
    }
</script>

<?php require_once 'includes/footer.php'; ?>