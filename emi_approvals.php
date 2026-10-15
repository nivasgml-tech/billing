<?php
// emi_approvals.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

$query = "SELECT ca.*, es.installment_no, e.customer_name, e.product_name, u.username as staff_name 
          FROM collection_approvals ca
          JOIN emi_schedule es ON ca.schedule_id = es.id
          JOIN emi e ON es.emi_id = e.id
          JOIN users u ON ca.requested_by = u.id
          WHERE ca.status = 'Pending'
          ORDER BY ca.created_at DESC";
$requests = $pdo->query($query)->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood fw-bold"><i class="fas fa-check-circle me-2"></i>Collection Approvals</h2>
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
                        <th>Amount</th>
                        <th>Discount</th>
                        <th>Penalty</th>
                        <th>Staff</th>
                        <th class="rounded-end text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $r): ?>
                        <tr class="border-bottom">
                            <td class="align-middle"><?= date('d-m-Y H:i', strtotime($r['created_at'])) ?></td>
                            <td class="align-middle"><?= htmlspecialchars($r['customer_name']) ?></td>
                            <td class="align-middle"><?= htmlspecialchars($r['product_name']) ?></td>
                            <td class="align-middle"><?= $r['installment_no'] ?></td>
                            <td class="align-middle fw-bold text-success">₹<?= number_format($r['paid_amount'], 2) ?></td>
                            <td class="align-middle text-primary">₹<?= number_format($r['discount'], 2) ?></td>
                            <td class="align-middle text-danger">₹<?= number_format($r['penalty_amount'], 2) ?></td>
                            <td class="align-middle"><?= htmlspecialchars($r['staff_name']) ?></td>
                            <td class="align-middle text-center">
                                <button class="btn btn-sm btn-success me-1" onclick="handleApproval(<?= $r['id'] ?>, 'Approved')">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="handleApproval(<?= $r['id'] ?>, 'Rejected')">
                                    <i class="fas fa-times"></i> Reject
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
