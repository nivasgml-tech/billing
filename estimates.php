<?php
// estimates.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

// Fetch Estimates & Quotations
$stmt = $pdo->query("
    SELECT s.*, c.customer_name, c.phone 
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    WHERE s.sale_type = 'Estimate' OR s.sale_type = 'Sale Order'
    ORDER BY s.id DESC
");
$estimates = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood"><i class="fas fa-file-signature me-2"></i>Estimates & Quotations</h2>
    <a href="pos.php" class="btn btn-wood">
        <i class="fas fa-plus-circle me-1"></i> New Estimate
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <table class="table table-striped table-hover datatable w-100 table-sm">
            <thead class="bg-wood text-white">
                <tr>
                    <th>Date</th>
                    <th>Doc No</th>
                    <th>Customer</th>
                    <th>Type</th>
                    <th>Total (₹)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($estimates as $e): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($e['sale_date'])) ?></td>
                        <td><strong><?= htmlspecialchars($e['invoice_no']) ?></strong></td>
                        <td>
                            <?= htmlspecialchars($e['customer_name'] ?? 'Walk-in') ?><br>
                            <small class="text-muted"><?= htmlspecialchars($e['phone'] ?? '') ?></small>
                        </td>
                        <td>
                            <?php if ($e['sale_type'] == 'Estimate'): ?>
                                <span class="badge bg-secondary">Estimate</span>
                            <?php else: ?>
                                <span class="badge bg-info text-dark">Order</span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-bold text-end"><?= number_format($e['grand_total'], 2) ?></td>
                        <td>
                            <div class="btn-group">
                                <a href="print_invoice.php?id=<?= $e['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Print/View">
                                    <i class="fas fa-print"></i>
                                </a>
                                <button class="btn btn-sm btn-outline-success" onclick="convertEstimate(<?= $e['id'] ?>)" title="Convert to Invoice">
                                    <i class="fas fa-file-invoice-dollar"></i>
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
    function convertEstimate(id) {
        Swal.fire({
            title: 'Convert to Sale Invoice?',
            text: 'This will convert the estimate and deduct stock.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, convert it'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax/convert_estimate.php', {
                    id: id
                }, function(res) {
                    if (res.success) {
                        Swal.fire('Converted!', 'Estimate successfully converted to Sale Invoice.', 'success').then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }, 'json');
            }
        });
    }
</script>

<?php require_once 'includes/footer.php'; ?>