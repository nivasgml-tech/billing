<?php
// emi_collection.php
require_once 'includes/header.php';
// Accessible to all logged-in roles
require_once 'includes/auth.php';

$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';

// Auto-update overdue status
$pdo->exec("UPDATE emi_schedule SET status = 'Overdue' WHERE due_date < CURDATE() AND status = 'Pending'");

// Fetch EMIs
if ($filter_date) {
    // If date is selected, show EMIs that have an installment due on that date
    $query = "SELECT e.*, es.installment_no, es.amount as due_amount, es.status as inst_status,
              (SELECT COUNT(*) FROM emi_schedule WHERE emi_id = e.id AND status != 'Paid') as pending_installments,
              (SELECT SUM(amount - paid_amount) FROM emi_schedule WHERE emi_id = e.id AND status != 'Paid') as balance_amount
              FROM emi e 
              JOIN emi_schedule es ON e.id = es.emi_id
              WHERE es.due_date = ? AND es.status != 'Paid'";
    $params = [$filter_date];
} else {
    // Default: Show all EMIs with pending installments
    $query = "SELECT e.*, 
              (SELECT COUNT(*) FROM emi_schedule es WHERE es.emi_id = e.id AND es.status != 'Paid') as pending_installments,
              (SELECT SUM(amount - paid_amount) FROM emi_schedule es WHERE es.emi_id = e.id AND es.status != 'Paid') as balance_amount
              FROM emi e 
              WHERE EXISTS (SELECT 1 FROM emi_schedule WHERE emi_id = e.id AND status != 'Paid')";
    $params = [];
}

if (!hasRole(['Super Admin', 'Admin'])) {
    $branch_id = (int)($_SESSION['branch_id'] ?? 0);
    $query .= " AND (e.branch_id = $branch_id OR e.branch_id IS NULL OR e.branch_id = 0)";
}
$query .= " ORDER BY e.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$emis = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood fw-bold"><i class="fas fa-money-bill-wave me-2"></i>EMI Collection</h2>
    <div class="d-flex align-items-center">
        <form method="GET" class="d-flex gap-2">
            <input type="date" name="filter_date" class="form-control" value="<?= $filter_date ?>" onchange="this.form.submit()">
            <?php if ($filter_date): ?>
                <a href="emi_collection.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover datatable w-100 table-borderless border-bottom">
                <thead class="bg-wood text-white rounded-top">
                    <tr>
                        <th class="rounded-start">Customer</th>
                        <th>Product</th>
                        <th>Total Loan</th>
                        <th>Balance</th>
                        <th>Pending EMIs</th>
                        <th class="rounded-end text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($emis as $e): ?>
                        <tr class="border-bottom">
                            <td class="align-middle">
                                <h6 class="mb-0 fw-bold"><?= htmlspecialchars($e['customer_name']) ?></h6>
                                <small class="text-muted"><?= htmlspecialchars($e['mobile']) ?></small>
                            </td>
                            <td class="align-middle"><?= htmlspecialchars($e['product_name']) ?></td>
                            <td class="align-middle fw-bold">₹<?= number_format($e['loan_amount'], 2) ?></td>
                            <td class="align-middle text-danger fw-bold">₹<?= number_format($e['balance_amount'], 2) ?></td>
                            <td class="align-middle">
                                <span class="badge bg-warning text-dark"><?= $e['pending_installments'] ?> Left</span>
                            </td>
                            <td class="align-middle text-center">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-wood" onclick="viewSchedule(<?= $e['id'] ?>, '<?= addslashes($e['customer_name']) ?>')">
                                        <i class="fas fa-calendar-alt me-1"></i> Collect Payment
                                    </button>
                                    <a href="emi_statement.php?id=<?= $e['id'] ?>" class="btn btn-outline-info" title="Statement" target="_blank">
                                        <i class="fas fa-file-alt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- EMI Schedule & Collection Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1" data-bs-focus="false">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-wood text-white border-0">
                <h5 class="modal-title fw-bold">EMI Schedule: <span id="modalCustomerName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="scheduleContainer">
                    <div class="text-center p-5">
                        <i class="fas fa-spinner fa-spin fa-2x text-wood"></i>
                        <p class="mt-2">Loading schedule...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function viewSchedule(emiId, customerName) {
        $('#modalCustomerName').text(customerName);
        $('#scheduleModal').modal('show');
        $('#scheduleContainer').html('<div class="text-center p-5"><i class="fas fa-spinner fa-spin fa-2x text-wood"></i><p class="mt-2">Loading schedule...</p></div>');

        $.get('ajax/emi_collection_action.php', {
            action: 'get_schedule',
            emi_id: emiId
        }, function(html) {
            $('#scheduleContainer').html(html);
        });
    }

    function collectPayment(scheduleId, balance) {
        Swal.fire({
            title: 'Collect Payment',
            html: `
                <div class="text-start p-2">
                    <div class="alert alert-info py-2 small mb-3">Principal Balance: <strong>₹${parseFloat(balance).toLocaleString()}</strong></div>
                    
                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold mb-1 small">Amount Paid (₹)</label>
                            <input type="number" id="pay_amount" class="form-control" value="${parseFloat(balance).toFixed(2)}" step="0.01">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold mb-1 small text-primary">Discount (₹)</label>
                            <input type="number" id="pay_discount" class="form-control" value="0.00" step="0.01">
                        </div>
                    </div>

                    <div class="mb-3 p-2 bg-light rounded border">
                        <label class="form-label fw-bold mb-1 small text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Penalty Charge (₹)</label>
                        <input type="number" id="pay_penalty" class="form-control mb-2" value="0.00" step="0.01">
                        <label class="form-label fw-bold mb-1 small">Penalty Description</label>
                        <input type="text" id="pay_penalty_desc" class="form-control" placeholder="e.g. Late payment fee">
                    </div>

                    <div class="text-end fw-bold text-success mt-2">
                        Net Payable: <span id="dialog_net_payable">₹${parseFloat(balance).toLocaleString()}</span>
                    </div>
                </div>
            `,
            didOpen: () => {
                const updateNet = () => {
                    const bal = parseFloat(balance) || 0;
                    const penalty = parseFloat($('#pay_penalty').val()) || 0;
                    const disc = parseFloat($('#pay_discount').val()) || 0;
                    const net = bal + penalty - disc;
                    $('#dialog_net_payable').text('₹' + net.toLocaleString());
                    $('#pay_amount').val(net.toFixed(2));
                };
                
                $('#pay_penalty, #pay_discount').on('input', updateNet);
                
                // Focus on amount paid
                setTimeout(() => {
                    $('#pay_amount').focus().select();
                }, 300);
            },
            showCancelButton: true,
            confirmButtonText: 'Record Payment',
            confirmButtonColor: '#28a745',
            preConfirm: () => {
                const amount = $('#pay_amount').val();
                const discount = $('#pay_discount').val();
                const penalty = $('#pay_penalty').val();
                const penalty_desc = $('#pay_penalty_desc').val();

                if (!amount || amount < 0) {
                    Swal.showValidationMessage('Please enter a valid amount');
                    return false;
                }
                return { amount, discount, penalty, penalty_desc };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax/emi_collection_action.php', {
                    action: 'pay_installment',
                    id: scheduleId,
                    paid_amount: result.value.amount,
                    discount: result.value.discount,
                    penalty_amount: result.value.penalty,
                    penalty_description: result.value.penalty_desc
                }, function(res) {
                    if (res.success) {
                        Swal.fire('Success', res.message, 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }, 'json');
            }
        });
    }

    function settleAll(emiId, totalBalance) {
        Swal.fire({
            title: 'Settle All EMIs',
            html: `
                <div class="text-start p-2">
                    <p class="text-muted small">This will mark all pending installments as fully paid.</p>
                    <div class="alert alert-danger py-2 small">Total Remaining: <strong>₹${parseFloat(totalBalance).toLocaleString()}</strong></div>
                    <div class="mb-3">
                        <label class="form-label fw-bold mb-1 small">Settlement Discount (₹)</label>
                        <input type="number" id="settle_discount" class="form-control" value="0.00" step="0.01">
                    </div>
                    <div class="text-info small">Net Payable: <strong id="net_payable">₹${parseFloat(totalBalance).toLocaleString()}</strong></div>
                </div>
            `,
            didOpen: () => {
                $('#settle_discount').on('input', function() {
                    const disc = parseFloat($(this).val()) || 0;
                    const net = Math.max(0, totalBalance - disc);
                    $('#net_payable').text('₹' + net.toLocaleString());
                });
            },
            showCancelButton: true,
            confirmButtonText: 'Confirm Settlement',
            confirmButtonColor: '#dc3545',
            preConfirm: () => {
                const discount = $('#settle_discount').val();
                return { discount };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax/emi_collection_action.php', {
                    action: 'settle_all',
                    emi_id: emiId,
                    discount: result.value.discount
                }, function(res) {
                    if (res.success) {
                        Swal.fire('Success', res.message, 'success').then(() => {
                            location.reload();
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
