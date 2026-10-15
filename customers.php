<?php
// customers.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

if (hasRole(['Super Admin', 'Staff'])) {
    $stmt = $pdo->query("
        SELECT c.*, 
            COUNT(s.id) as total_invoices,
            COALESCE(SUM(s.grand_total), 0) as total_spent,
            COALESCE(SUM(s.paid_amount), 0) as total_paid
        FROM customers c
        LEFT JOIN sales s ON c.id = s.customer_id
        GROUP BY c.id
        ORDER BY c.id DESC
    ");
    $customers = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT c.*, 
            COUNT(s.id) as total_invoices,
            COALESCE(SUM(s.grand_total), 0) as total_spent,
            COALESCE(SUM(s.paid_amount), 0) as total_paid
        FROM customers c
        LEFT JOIN sales s ON c.id = s.customer_id
        WHERE c.branch_id = ? OR c.branch_id IS NULL
        GROUP BY c.id
        ORDER BY c.id DESC
    ");
    $stmt->execute([$_SESSION['branch_id']]);
    $customers = $stmt->fetchAll();
}
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood fw-bold"><i class="fas fa-users me-2"></i>Customer Directory</h2>
    <button class="btn btn-wood shadow-sm font-weight-bold" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="resetForm()">
        <i class="fas fa-plus-circle me-1"></i> Add New Customer
    </button>
</div>

<div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-4">
        <table class="table table-hover datatable w-100 table-borderless border-bottom">
            <thead class="bg-wood text-white rounded-top">
                <tr>
                    <th class="rounded-start">ID</th>
                    <th>Customer Name</th>
                    <th>Contact Info</th>
                    <th>Location Details</th>
                    <th>Total Invoices</th>
                    <th>Total Spent</th>
                    <th>Pending Dues</th>
                    <th class="rounded-end text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $c):
                    $pending = $c['total_spent'] - $c['total_paid'];
                ?>
                    <tr class="border-bottom">
                        <td class="align-middle fw-bold text-muted">#<?= $c['id'] ?></td>
                        <td class="align-middle">
                            <h6 class="mb-0 fw-bold text-primary"><?= htmlspecialchars($c['customer_name']) ?></h6>
                        </td>
                        <td class="align-middle">
                            <div class="small text-muted">
                                <i class="fas fa-phone-alt me-2"></i><?= htmlspecialchars($c['phone'] ?: 'N/A') ?><br>
                                <i class="fas fa-envelope me-2 mt-1"></i><?= htmlspecialchars($c['email'] ?: 'N/A') ?>
                            </div>
                        </td>
                        <td class="align-middle">
                            <span class="badge bg-light text-dark border mb-1">GST: <?= htmlspecialchars($c['gst_number'] ?: 'Unregistered') ?></span><br>
                            <span class="small text-muted"><i class="fas fa-map-marker-alt me-1"></i>Pincode: <?= htmlspecialchars($c['pincode'] ?: 'N/A') ?></span>
                        </td>
                        <td class="align-middle text-center">
                            <span class="badge bg-info rounded-pill px-3"><?= $c['total_invoices'] ?></span>
                        </td>
                        <td class="align-middle fw-bold text-success">
                            ₹<?= number_format($c['total_spent'], 2) ?>
                        </td>
                        <td class="align-middle">
                            <?php if ($pending > 0): ?>
                                <span class="text-danger fw-bold">₹<?= number_format($pending, 2) ?></span>
                            <?php else: ?>
                                <span class="text-muted"><i class="fas fa-check-circle text-success me-1"></i>Settled</span>
                            <?php endif; ?>
                        </td>
                        <td class="align-middle text-center">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" title="Edit Customer" onclick='editCustomer(<?= json_encode($c) ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger" title="Delete Customer" onclick='deleteCustomer(<?= $c['id'] ?>)'>
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

<!-- Add/Edit Customer Modal -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="customerForm" class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-wood text-white border-0">
                <h5 class="modal-title fw-bold" id="modalTitle"><i class="fas fa-user-plus me-2"></i>Add Customer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="customer_id" value="">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-wood fw-bold">Customer Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-lg bg-white" name="customer_name" id="customer_name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-wood fw-bold">Phone Number</label>
                        <input type="text" class="form-control form-control-lg bg-white" name="phone" id="phone" placeholder="+91 XXXXX XXXXX">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-wood fw-bold">Email Address</label>
                        <input type="email" class="form-control form-control-lg bg-white" name="email" id="email" placeholder="customer@email.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-wood fw-bold">GSTIN Number</label>
                        <input type="text" class="form-control form-control-lg bg-white" name="gst_number" id="gst_number" placeholder="22AAAAA0000A1Z5">
                    </div>

                    <div class="col-12">
                        <label class="form-label text-wood fw-bold">Complete Address</label>
                        <textarea class="form-control bg-white" name="address" id="address" rows="3" placeholder="Flat No, Street, City, State, Pincode"></textarea>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label text-wood fw-bold">Pincode</label>
                        <input type="text" class="form-control form-control-lg bg-white" name="pincode" id="pincode" placeholder="6-digit Pincode">
                    </div>
                </div>

            </div>
            <div class="modal-footer border-0 bg-white">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-wood px-5 fw-bold" id="saveBtn"><i class="fas fa-save me-2"></i>Save Customer</button>
            </div>
        </form>
    </div>
</div>

<script>
    function resetForm() {
        $('#customerForm')[0].reset();
        $('#action').val('add');
        $('#customer_id').val('');
        $('#modalTitle').html('<i class="fas fa-user-plus me-2"></i>Add Customer');
    }

    function editCustomer(c) {
        $('#action').val('edit');
        $('#customer_id').val(c.id);
        $('#customer_name').val(c.customer_name);
        $('#phone').val(c.phone);
        $('#email').val(c.email);
        $('#gst_number').val(c.gst_number);
        $('#pincode').val(c.pincode);
        $('#address').val(c.address);

        $('#modalTitle').html('<i class="fas fa-user-edit me-2"></i>Edit Customer Details');
        $('#customerModal').modal('show');
    }

    function deleteCustomer(id) {
        Swal.fire({
            title: 'Delete Customer?',
            text: "Are you sure you want to delete this customer? This action cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax/customer_action.php', {
                    action: 'delete',
                    id: id
                }, function(res) {
                    if (res.success) {
                        Swal.fire('Deleted', res.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }, 'json');
            }
        });
    }

    $(document).ready(function() {
        $('#customerForm').submit(function(e) {
            e.preventDefault();
            $('#saveBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...');

            $.ajax({
                url: 'ajax/customer_action.php',
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
                        $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save me-2"></i>Save Customer');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Server error occurred', 'error');
                    $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save me-2"></i>Save Customer');
                }
            });
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>