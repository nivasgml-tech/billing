<?php
// report_collection_approvals.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

$status_filter = $_GET['status'] ?? '';
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');

$query = "SELECT ca.*, es.installment_no, e.customer_name, e.product_name, u.username as staff_name 
          FROM collection_approvals ca
          JOIN emi_schedule es ON ca.schedule_id = es.id
          JOIN emi e ON es.emi_id = e.id
          JOIN users u ON ca.requested_by = u.id
          WHERE ca.created_at BETWEEN ? AND ?";
$params = [$from_date . ' 00:00:00', $to_date . ' 23:59:59'];

if ($status_filter) {
    $query .= " AND ca.status = ?";
    $params[] = $status_filter;
}

if (!hasRole(['Super Admin', 'Admin', 'Staff'])) {
    $query .= " AND ca.requested_by = ?";
    $params[] = $_SESSION['user_id'];
}

$query .= " ORDER BY ca.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i>Collection Approvals Report</h2>
</div>

<!-- Filters -->
<div class="card shadow-sm border-0 rounded-4 mb-4">
    <div class="card-body p-3">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold small">From Date</label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="<?= $from_date ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small">To Date</label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="<?= $to_date ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="Pending" <?= $status_filter == 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Approved" <?= $status_filter == 'Approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="Rejected" <?= $status_filter == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-wood flex-grow-1">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
                <a href="report_collection_approvals.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-redo"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover datatable w-100 table-borderless border-bottom">
                <thead class="bg-wood text-white rounded-top">
                    <tr>
                        <th class="rounded-start">Date</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Inst. No</th>
                        <th>Collected</th>
                        <th>Disc/Penalty</th>
                        <th>Staff</th>
                        <th>Status</th>
                        <th class="rounded-end text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $r): 
                        $statusBadge = 'bg-warning text-dark';
                        if ($r['status'] === 'Approved') $statusBadge = 'bg-success';
                        if ($r['status'] === 'Rejected') $statusBadge = 'bg-danger';
                    ?>
                        <tr class="border-bottom">
                            <td class="align-middle"><?= date('d-m-Y H:i', strtotime($r['created_at'])) ?></td>
                            <td class="align-middle">
                                <div class="fw-bold"><?= htmlspecialchars($r['customer_name']) ?></div>
                            </td>
                            <td class="align-middle small"><?= htmlspecialchars($r['product_name']) ?></td>
                            <td class="align-middle text-center"><?= $r['installment_no'] ?></td>
                            <td class="align-middle fw-bold text-success">₹<?= number_format($r['paid_amount'], 2) ?></td>
                            <td class="align-middle small">
                                <span class="text-primary">D: ₹<?= number_format($r['discount'], 2) ?></span><br>
                                <span class="text-danger">P: ₹<?= number_format($r['penalty_amount'], 2) ?></span>
                            </td>
                            <td class="align-middle"><?= htmlspecialchars($r['staff_name']) ?></td>
                            <td class="align-middle text-center">
                                <span class="badge <?= $statusBadge ?>"><?= $r['status'] ?></span>
                            </td>
                            <td class="align-middle text-center">
                                <?php if (hasRole(['Super Admin', 'Admin', 'Staff'])): ?>
                                    <?php if ($r['status'] === 'Pending'): ?>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-success" onclick="handleApproval(<?= $r['id'] ?>, 'Approved')" title="Approve">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn btn-danger" onclick="handleApproval(<?= $r['id'] ?>, 'Rejected')" title="Reject">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">Processed</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function handleApproval(requestId, action) {
        Swal.fire({
            title: 'Are you sure?',
            text: `Do you want to ${action.toLowerCase()} this collection?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, proceed!',
            confirmButtonColor: action === 'Approved' ? '#28a745' : '#dc3545'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax/approval_action.php', {
                    request_id: requestId,
                    action: action
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
