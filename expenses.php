<?php
// expenses.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

if (hasRole(['Super Admin', 'Staff'])) {
    $stmt = $pdo->query("
        SELECT e.*, c.category_name 
        FROM expenses e 
        LEFT JOIN expense_categories c ON e.category_id = c.id 
        ORDER BY e.expense_date DESC, e.id DESC
    ");
    $expenses = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT e.*, c.category_name 
        FROM expenses e 
        LEFT JOIN expense_categories c ON e.category_id = c.id 
        WHERE e.branch_id = ?
        ORDER BY e.expense_date DESC, e.id DESC
    ");
    $stmt->execute([$_SESSION['branch_id']]);
    $expenses = $stmt->fetchAll();
}

// Fetch active categories
$categories = $pdo->query("SELECT * FROM expense_categories WHERE status = 1 ORDER BY category_name")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood"><i class="fas fa-money-bill-wave me-2"></i>Expenses Management</h2>
    <button class="btn btn-wood" data-bs-toggle="modal" data-bs-target="#expenseModal" onclick="resetForm()">
        <i class="fas fa-plus-circle me-1"></i> Add Expense
    </button>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <table class="table table-striped table-hover datatable w-100">
            <thead class="bg-wood text-white">
                <tr>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Account</th>
                    <th>Details</th>
                    <th>Payment Mode</th>
                    <th>Amount (₹)</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expenses as $e): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($e['expense_date'])) ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($e['category_name']) ?></span></td>
                        <td>
                            <?php 
                            $accClass = 'bg-info';
                            if($e['account_type'] == 'POS') $accClass = 'bg-primary';
                            if($e['account_type'] == 'EMI') $accClass = 'bg-warning text-dark';
                            ?>
                            <span class="badge <?= $accClass ?>"><?= $e['account_type'] ?></span>
                        </td>
                        <td><?= htmlspecialchars($e['description']) ?></td>
                        <td><?= htmlspecialchars($e['payment_mode']) ?></td>
                        <td class="fw-bold text-end">₹<?= number_format($e['amount'], 2) ?></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteExpense(<?= $e['id'] ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="expenseModal">
    <div class="modal-dialog">
        <form id="expenseForm" class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-minus-circle me-1"></i> Add New Expense</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add">

                <div class="mb-3">
                    <label class="form-label fw-bold">Business Account <span class="text-danger">*</span></label>
                    <select class="form-select" name="account_type" required>
                        <option value="General">General Business</option>
                        <option value="POS">POS Sales</option>
                        <option value="EMI">EMI Management</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Expense Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="expense_date" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Category <span class="text-danger">*</span></label>
                    <select class="form-select select2 w-100" name="category_id" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Payment Mode <span class="text-danger">*</span></label>
                    <select class="form-select" name="payment_mode" required>
                        <option value="Cash">Cash</option>
                        <option value="UPI">UPI</option>
                        <option value="Card">Card</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Amount (₹) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" class="form-control fs-4 fw-bold text-danger" name="amount" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Description / Notes</label>
                    <textarea class="form-control" name="description" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-danger" id="saveBtn"><i class="fas fa-save me-1"></i> Save Expense</button>
            </div>
        </form>
    </div>
</div>

<script>
    function resetForm() {
        $('#expenseForm')[0].reset();
        $('.select2').val('').trigger('change');
    }

    function deleteExpense(id) {
        Swal.fire({
            title: 'Delete Expense?',
            text: "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax/expense_action.php', {
                    action: 'delete',
                    id: id
                }, function(res) {
                    if (res.success) {
                        Swal.fire('Deleted!', res.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }, 'json');
            }
        });
    }

    $(document).ready(function() {
        // Initialize Select2 when modal is shown to fix z-index
        $('#expenseModal').on('shown.bs.modal', function() {
            $('.select2', this).select2({
                theme: 'bootstrap-5',
                width: '100%',
                dropdownParent: $('#expenseModal')
            });
        });

        $('#expenseForm').submit(function(e) {
            e.preventDefault();
            $('#saveBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: 'ajax/expense_action.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: res.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                        $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Expense');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Server error occurred', 'error');
                    $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Expense');
                }
            });
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>