<?php
// income.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

if (hasRole(['Super Admin', 'Staff'])) {
    $stmt = $pdo->prepare("
        SELECT * FROM other_income 
        WHERE income_date BETWEEN ? AND ?
        ORDER BY income_date DESC, id DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $incomes = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT * FROM other_income 
        WHERE branch_id = ? AND income_date BETWEEN ? AND ?
        ORDER BY income_date DESC, id DESC
    ");
    $stmt->execute([$_SESSION['branch_id'], $start_date, $end_date]);
    $incomes = $stmt->fetchAll();
}
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood"><i class="fas fa-hand-holding-usd me-2"></i>Other Income</h2>
    <button class="btn btn-wood" data-bs-toggle="modal" data-bs-target="#incomeModal" onclick="resetForm()">
        <i class="fas fa-plus-circle me-1"></i> Add Income
    </button>
</div>

<!-- Date Filter Form -->
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
                <a href="other_income.php" class="btn btn-outline-secondary btn-sm w-100"><i class="fas fa-sync-alt me-1"></i> Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <table class="table table-striped table-hover datatable w-100">
            <thead class="bg-wood text-white">
                <tr>
                    <th>Date</th>
                    <th>Account</th>
                    <th>Source / Title</th>
                    <th>Details</th>
                    <th>Payment Mode</th>
                    <th>Amount (₹)</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($incomes as $i): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($i['income_date'])) ?></td>
                        <td>
                            <?php 
                            $accClass = 'bg-info';
                            if($i['account_type'] == 'POS') $accClass = 'bg-primary';
                            if($i['account_type'] == 'EMI') $accClass = 'bg-warning text-dark';
                            ?>
                            <span class="badge <?= $accClass ?>"><?= $i['account_type'] ?></span>
                        </td>
                        <td><strong><?= htmlspecialchars($i['source']) ?></strong></td>
                        <td><?= htmlspecialchars($i['description']) ?></td>
                        <td><?= htmlspecialchars($i['payment_mode']) ?></td>
                        <td class="fw-bold text-end text-success">+ ₹<?= number_format($i['amount'], 2) ?></td>
                        <td class="text-center">
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-info" onclick='editIncome(<?= json_encode($i) ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteIncome(<?= $i['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="incomeModal">
    <div class="modal-dialog">
        <form id="incomeForm" class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-1"></i> Record Other Income</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="incomeId" value="">

                <div class="mb-3">
                    <label class="form-label fw-bold">Business Account <span class="text-danger">*</span></label>
                    <select class="form-select" name="account_type" required>
                        <option value="General">General Business</option>
                        <option value="POS">POS Sales</option>
                        <option value="EMI">EMI Management</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Income Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="income_date" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Source Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="source" placeholder="e.g. Scrap Sale, Rent Received" required>
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
                    <input type="number" step="0.01" class="form-control fs-4 fw-bold text-success" name="amount" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Description / Notes</label>
                    <textarea class="form-control" name="description" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-success" id="saveBtn"><i class="fas fa-save me-1"></i> Save Income</button>
            </div>
        </form>
    </div>
</div>

<script>
    function resetForm() {
        $('#incomeForm')[0].reset();
        $('#formAction').val('add');
        $('#incomeId').val('');
        $('.modal-title').html('<i class="fas fa-plus-circle me-1"></i> Record Other Income');
        $('.modal-header').removeClass('bg-info').addClass('bg-success');
    }

    function editIncome(data) {
        resetForm();
        $('#formAction').val('edit');
        $('#incomeId').val(data.id);
        $('.modal-title').html('<i class="fas fa-edit me-1"></i> Edit Other Income');
        $('.modal-header').removeClass('bg-success').addClass('bg-info');

        $('[name="account_type"]').val(data.account_type);
        $('[name="income_date"]').val(data.income_date);
        $('[name="source"]').val(data.source);
        $('[name="payment_mode"]').val(data.payment_mode);
        $('[name="amount"]').val(data.amount);
        $('[name="description"]').val(data.description);

        $('#incomeModal').modal('show');
    }

    function deleteIncome(id) {
        Swal.fire({
            title: 'Delete Income Record?',
            text: "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax/income_action.php', {
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
        $('#incomeForm').submit(function(e) {
            e.preventDefault();
            $('#saveBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: 'ajax/income_action.php',
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
                        $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Income');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Server error occurred', 'error');
                    $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Income');
                }
            });
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>