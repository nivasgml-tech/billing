<?php
// emi_collection_sheet.php
require_once 'includes/header.php';
// Accessible to all logged-in roles
require_once 'includes/auth.php';

$target_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$center_filter = isset($_GET['center']) ? $_GET['center'] : '';

// Fetch distinct center names for filter
$centers_stmt = $pdo->query("SELECT DISTINCT center_name FROM emi WHERE center_name IS NOT NULL AND center_name != '' ORDER BY center_name");
$centers = $centers_stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch installments due on the target date
$query = "SELECT es.*, e.customer_name, e.mobile, e.center_name, e.product_name, e.loan_amount,
          (SELECT COUNT(*) FROM emi_schedule WHERE emi_id = e.id) as total_inst,
          (SELECT status FROM collection_approvals WHERE schedule_id = es.id ORDER BY id DESC LIMIT 1) as approval_status,
          (SELECT paid_amount FROM collection_approvals WHERE schedule_id = es.id ORDER BY id DESC LIMIT 1) as approval_amount
          FROM emi_schedule es
          JOIN emi e ON es.emi_id = e.id
          WHERE es.due_date = ? AND es.status != 'Paid'";

$params = [$target_date];

if ($center_filter) {
    $query .= " AND e.center_name = ?";
    $params[] = $center_filter;
}

if (!hasRole(['Super Admin', 'Admin'])) {
    $branch_id = (int)($_SESSION['branch_id'] ?? 0);
    $query .= " AND (e.branch_id = $branch_id OR e.branch_id IS NULL OR e.branch_id = 0)";
}

$query .= " ORDER BY e.center_name ASC, e.customer_name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$dues = $stmt->fetchAll();

$total_expected = 0;
foreach ($dues as $d) {
    $total_expected += ($d['amount'] + $d['penalty_amount'] - $d['paid_amount'] - $d['discount']);
}
?>

<style>
    @media print {
        .no-print { display: none !important; }
        .card { border: none !important; shadow: none !important; }
        .table th { background-color: #f8f9fa !important; color: black !important; }
        body { background: white !important; }
    }
    .center-group {
        background-color: #f1f5f9;
        font-weight: bold;
        padding: 8px 15px;
        border-left: 5px solid var(--primary);
        margin-top: 15px;
        border-radius: 4px;
    }
</style>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood"><i class="fas fa-clipboard-list me-2"></i>Daily Collection Sheet</h2>
    <div class="no-print">
        <button class="btn btn-outline-secondary me-2" onclick="window.print()"><i class="fas fa-print me-1"></i> Print Sheet</button>
        <a href="emi_collection.php" class="btn btn-wood"><i class="fas fa-coins me-1"></i> Go to Collection</a>
    </div>
</div>

<!-- Filters -->
<div class="card shadow-sm border-0 mb-4 bg-light no-print">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold">Collection Date</label>
                <input type="date" name="date" class="form-control" value="<?= $target_date ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold">Filter by Center</label>
                <select name="center" class="form-select select2">
                    <option value="">All Centers</option>
                    <?php foreach ($centers as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= $center_filter == $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fas fa-search me-1"></i> Generate</button>
            </div>
            <div class="col-md-2">
                <a href="emi_collection_sheet.php" class="btn btn-outline-secondary w-100"><i class="fas fa-sync-alt"></i> Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="alert alert-info py-2 shadow-sm d-flex justify-content-between align-items-center">
    <span>Collection Date: <strong><?= date('d M Y', strtotime($target_date)) ?></strong></span>
    <span>Total Expected: <strong class="text-danger fs-5">₹<?= number_format($total_expected, 2) ?></strong></span>
</div>

<div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead class="bg-dark text-white">
                    <tr class="text-center">
                        <th width="5%">S.No</th>
                        <th width="20%" class="text-start">Customer Details</th>
                        <th width="15%">Loan / Product</th>
                        <th width="8%">Inst.</th>
                        <th width="12%">Due Amount</th>
                        <th width="10%">Penalty</th>
                        <th width="10%">Prev. Paid</th>
                        <th width="10%">Balance</th>
                        <th width="10%" class="no-print">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $current_center = null;
                    $sno = 1;
                    if (empty($dues)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted">No collections due for this date.</td></tr>
                    <?php else:
                        foreach ($dues as $d): 
                            if ($current_center !== $d['center_name']) {
                                $current_center = $d['center_name'];
                                echo '<tr><td colspan="9" class="center-group"><i class="fas fa-map-marker-alt me-2 text-primary"></i>Center: ' . htmlspecialchars($current_center ?: 'General / Unassigned') . '</td></tr>';
                            }
                            $balance = $d['amount'] + $d['penalty_amount'] - $d['paid_amount'] - $d['discount'];
                    ?>
                        <tr class="text-center">
                            <td><?= $sno++ ?></td>
                            <td class="text-start">
                                <div class="fw-bold"><?= htmlspecialchars($d['customer_name']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($d['mobile']) ?></small>
                            </td>
                            <td>
                                <div class="small fw-bold"><?= htmlspecialchars($d['product_name']) ?></div>
                                <div class="text-muted small">₹<?= number_format($d['loan_amount'], 0) ?></div>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= $d['installment_no'] ?> / <?= $d['total_inst'] ?></span></td>
                            <td class="fw-bold">₹<?= number_format($d['amount'], 2) ?></td>
                            <td class="text-danger">₹<?= number_format($d['penalty_amount'], 2) ?></td>
                            <td class="text-success">₹<?= number_format($d['paid_amount'], 2) ?></td>
                            <td class="bg-light fw-bold text-danger">₹<?= number_format($balance, 2) ?></td>
                            <td class="no-print">
                                <?php if ($d['approval_status'] === 'Pending'): ?>
                                    <div class="alert alert-warning py-1 px-2 mb-0 small text-center">
                                        <i class="fas fa-clock me-1"></i>Pending Appr.<br>
                                        <strong>₹<?= number_format($d['approval_amount'], 2) ?></strong>
                                    </div>
                                <?php elseif ($d['approval_status'] === 'Rejected'): ?>
                                    <div class="alert alert-danger py-1 px-2 mb-1 small text-center">
                                        <i class="fas fa-times-circle me-1"></i>Rejected
                                    </div>
                                    <button class="btn btn-sm btn-success w-100" onclick="collectPayment(<?= $d['id'] ?>, <?= $balance ?>)">
                                        <i class="fas fa-undo"></i> Retry
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-success w-100" onclick="collectPayment(<?= $d['id'] ?>, <?= $balance ?>)">
                                        <i class="fas fa-check"></i> Collect
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; 
                    endif; ?>
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
