<?php
// emi_overdue.php
require_once 'includes/header.php';
// Accessible to all logged-in roles
require_once 'includes/auth.php';

// Auto-update overdue status
$pdo->exec("UPDATE emi_schedule SET status = 'Overdue' WHERE due_date < CURDATE() AND status = 'Pending'");

$days_filter = isset($_GET['days']) ? (int)$_GET['days'] : 0;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Fetch overdue installments
$query = "SELECT es.*, e.customer_name, e.mobile, e.product_name 
          FROM emi_schedule es 
          JOIN emi e ON es.emi_id = e.id 
          WHERE es.status != 'Paid' AND (es.status = 'Overdue' OR es.due_date < CURDATE())";

if (!hasRole(['Super Admin', 'Admin'])) {
    $branch_id = (int)($_SESSION['branch_id'] ?? 0);
    $query .= " AND (e.branch_id = $branch_id OR e.branch_id IS NULL OR e.branch_id = 0)";
}

if ($days_filter > 0) {
    $query .= " AND DATEDIFF(CURDATE(), es.due_date) >= $days_filter";
}
if ($start_date) {
    $query .= " AND es.due_date >= '$start_date'";
}
if ($end_date) {
    $query .= " AND es.due_date <= '$end_date'";
}

$query .= " ORDER BY es.due_date ASC";
$overdues = $pdo->query($query)->fetchAll();

$total_overdue_amt = 0;
$total_penalty_amt = 0;
foreach ($overdues as $o) {
    $total_overdue_amt += ($o['amount'] - $o['paid_amount'] - $o['discount']);
    $total_penalty_amt += $o['penalty_amount'];
}
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-danger fw-bold"><i class="fas fa-clock me-2"></i>EMI Overdue Report</h2>
    <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print me-1"></i> Print Report</button>
</div>

<!-- Filters -->
<div class="card shadow-sm border-0 mb-4 bg-light">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label small fw-bold">Overdue Days (Min)</label>
                <input type="number" name="days" class="form-control" value="<?= $days_filter ?>" placeholder="e.g. 30">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Due Date From</label>
                <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Due Date To</label>
                <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-danger w-100 fw-bold"><i class="fas fa-filter me-1"></i> Filter</button>
            </div>
            <div class="col-md-2">
                <a href="emi_overdue.php" class="btn btn-outline-secondary w-100"><i class="fas fa-sync-alt"></i> Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-danger text-white">
            <div class="card-body py-3">
                <h6 class="small mb-1">Total Overdue Cases</h6>
                <h3 class="mb-0 fw-bold"><?= count($overdues) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-dark text-white">
            <div class="card-body py-3">
                <h6 class="small mb-1">Principal Overdue</h6>
                <h3 class="mb-0 fw-bold">₹<?= number_format($total_overdue_amt, 2) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-warning text-dark">
            <div class="card-body py-3">
                <h6 class="small mb-1">Total Penalty Due</h6>
                <h3 class="mb-0 fw-bold">₹<?= number_format($total_penalty_amt, 2) ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover datatable w-100 table-borderless border-bottom align-middle">
                <thead class="bg-danger text-white rounded-top">
                    <tr class="text-center">
                        <th class="rounded-start">Customer</th>
                        <th>Product</th>
                        <th>No.</th>
                        <th>Due Date</th>
                        <th>EMI Amount</th>
                        <th>Penalty</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Days</th>
                        <th class="rounded-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overdues as $o): 
                        $days_overdue = (strtotime(date('Y-m-d')) - strtotime($o['due_date'])) / 86400;
                        $balance = $o['amount'] + $o['penalty_amount'] - $o['paid_amount'] - $o['discount'];
                    ?>
                        <tr class="border-bottom text-center">
                            <td class="text-start">
                                <h6 class="mb-0 fw-bold"><?= htmlspecialchars($o['customer_name']) ?></h6>
                                <small class="text-muted"><i class="fas fa-phone-alt me-1"></i> <?= htmlspecialchars($o['mobile']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($o['product_name']) ?></td>
                            <td>#<?= $o['installment_no'] ?></td>
                            <td class="text-danger fw-bold"><?= date('d-m-Y', strtotime($o['due_date'])) ?></td>
                            <td>₹<?= number_format($o['amount'], 2) ?></td>
                            <td class="text-danger">
                                ₹<?= number_format($o['penalty_amount'], 2) ?>
                                <?php if ($o['penalty_description']): ?>
                                    <br><small class="text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($o['penalty_description']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-success">₹<?= number_format($o['paid_amount'], 2) ?></td>
                            <td class="text-danger fw-bold">₹<?= number_format($balance, 2) ?></td>
                            <td>
                                <span class="badge bg-danger shadow-sm"><?= round($days_overdue) ?> Days</span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-success px-3 shadow-sm" onclick="collectPayment(<?= $o['id'] ?>, <?= $balance ?>)">
                                    <i class="fas fa-money-bill-wave me-1"></i> Pay
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function collectPayment(scheduleId, balance) {
        Swal.fire({
            title: 'Collect Payment',
            html: `
                <div class="text-start p-2">
                    <div class="alert alert-info py-2 small mb-3">Balance Due: <strong>₹${parseFloat(balance).toLocaleString()}</strong></div>
                    
                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold mb-1 small">Amount Paid (₹)</label>
                            <input type="number" id="pay_amount" class="form-control" value="${parseFloat(balance).toFixed(2)}" step="0.01">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold mb-1 small">Discount (₹)</label>
                            <input type="number" id="pay_discount" class="form-control" value="0.00" step="0.01">
                        </div>
                    </div>

                    <div class="mb-3 p-2 bg-light rounded border">
                        <label class="form-label fw-bold mb-1 small text-danger">Penalty Charge (₹)</label>
                        <input type="number" id="pay_penalty" class="form-control mb-2" value="0.00" step="0.01">
                        <label class="form-label fw-bold mb-1 small">Penalty Description</label>
                        <input type="text" id="pay_penalty_desc" class="form-control" placeholder="e.g. Late fee">
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
                };
                $('#pay_penalty, #pay_discount').on('input', updateNet);
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
</script>

<?php require_once 'includes/footer.php'; ?>
