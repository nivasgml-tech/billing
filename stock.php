<?php
// stock.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

// Fetch Stock
// To show accurate stock, we join branch_stock with products and categories
$branch_id = $_SESSION['branch_id'] ?? 1;

$stmt = $pdo->prepare("
    SELECT p.id, p.product_name, p.barcode, c.category_name, u.unit_name, 
           p.purchase_price, p.sale_price, p.min_stock_alert, p.gst_rate,
           COALESCE(bs.quantity, 0) as current_stock
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN units u ON p.unit_id = u.id
    LEFT JOIN branch_stock bs ON p.id = bs.product_id AND bs.branch_id = ?
    WHERE p.status = 1
    ORDER BY p.product_name
");
$stmt->execute([$branch_id]);
$stock = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood"><i class="fas fa-cubes me-2"></i>Inventory Stock</h2>
    <div class="d-flex align-items-center">
        <label class="me-2 fw-bold text-wood small text-uppercase" style="letter-spacing:1px;">Filter by Tax:</label>
        <select id="taxFilter" class="form-select form-select-sm" style="width: 150px; border-color: #8b5a2b;">
            <option value="">All Tax Rates</option>
            <?php 
            $taxes = array_unique(array_column($stock, 'gst_rate'));
            sort($taxes);
            foreach ($taxes as $t): ?>
                <option value="<?= number_format($t, 1) ?>%"><?= number_format($t, 1) ?>%</option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <table class="table table-striped table-hover datatable w-100">
            <thead class="bg-wood text-white">
                <tr>
                    <th>Product Name</th>
                    <th>Barcode</th>
                    <th>Category</th>
                    <th class="text-end">Tax (%)</th>
                    <th class="text-end">Sale Price (₹)</th>
                    <th class="text-center">Current Stock</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stock as $s): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($s['product_name']) ?></strong></td>
                        <td><?= htmlspecialchars($s['barcode']) ?></td>
                        <td><?= htmlspecialchars($s['category_name']) ?></td>
                        <td class="text-end"><?= number_format($s['gst_rate'], 1) ?>%</td>
                        <td class="text-end"><?= number_format($s['sale_price'], 2) ?></td>
                        <td class="text-center fw-bold fs-5 <?= $s['current_stock'] <= $s['min_stock_alert'] ? 'text-danger' : 'text-success' ?>">
                            <?= (float)$s['current_stock'] ?> <small class="fs-6 fw-normal text-muted"><?= htmlspecialchars($s['unit_name']) ?></small>
                        </td>
                        <td class="text-center">
                            <?php if ($s['current_stock'] <= 0): ?>
                                <span class="badge bg-danger">Out of Stock</span>
                            <?php elseif ($s['current_stock'] <= $s['min_stock_alert']): ?>
                                <span class="badge bg-warning text-dark">Low Stock</span>
                            <?php else: ?>
                                <span class="badge bg-success">In Stock</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Wait for DataTable to be initialized by main.js
    setTimeout(function() {
        var table = $('.datatable').DataTable();
        
        $('#taxFilter').on('change', function() {
            var val = $(this).val();
            // Filter column 3 (Tax) with exact match regex if value is selected
            table.column(3).search(val ? '^' + val + '$' : '', true, false).draw();
        });
    }, 200);
});
</script>