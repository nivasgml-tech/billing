<?php
// suppliers.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

if (hasRole(['Super Admin', 'Staff'])) {
    $stmt = $pdo->query("SELECT * FROM suppliers ORDER BY id DESC");
    $suppliers = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE branch_id = ? OR branch_id IS NULL ORDER BY id DESC");
    $stmt->execute([$_SESSION['branch_id']]);
    $suppliers = $stmt->fetchAll();
}
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood"><i class="fas fa-users-cog me-2"></i>Supplier Management</h2>
    <button class="btn btn-wood" data-bs-toggle="modal" data-bs-target="#supplierModal" onclick="resetForm()">
        <i class="fas fa-plus-circle me-1"></i> Add Supplier
    </button>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <table class="table table-striped table-hover datatable w-100">
            <thead class="bg-wood">
                <tr>
                    <th>ID</th>
                    <th>Supplier Name</th>
                    <th>Phone</th>
                    <th>GST Number</th>
                    <th>Pincode</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suppliers as $s): ?>
                    <tr>
                        <td><?= $s['id'] ?></td>
                        <td><strong><?= htmlspecialchars($s['supplier_name']) ?></strong></td>
                        <td><?= htmlspecialchars($s['phone'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($s['gst_number'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($s['pincode'] ?? 'N/A') ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick='editSupplier(<?= json_encode($s) ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="supplierModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="supplierForm" class="modal-content">
            <div class="modal-header bg-wood text-white">
                <h5 class="modal-title" id="modalTitle">Add Supplier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="supplier_id" value="">

                <div class="mb-3">
                    <label class="form-label text-wood fw-bold">Supplier Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="supplier_name" id="supplier_name" required>
                </div>

                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label text-wood fw-bold">Phone</label>
                        <input type="text" class="form-control" name="phone" id="phone">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label text-wood fw-bold">Email</label>
                        <input type="email" class="form-control" name="email" id="email">
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 mb-3">
                        <label class="form-label text-wood fw-bold">GST Number</label>
                        <input type="text" class="form-control" name="gst_number" id="gst_number">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-wood fw-bold">Address</label>
                    <textarea class="form-control" name="address" id="address" rows="2"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label text-wood fw-bold">Pincode</label>
                    <input type="text" class="form-control" name="pincode" id="pincode" placeholder="6-digit Pincode">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-wood" id="saveBtn"><i class="fas fa-save me-1"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    function resetForm() {
        $('#supplierForm')[0].reset();
        $('#action').val('add');
        $('#supplier_id').val('');
        $('#modalTitle').text('Add Supplier');
    }

    function editSupplier(s) {
        $('#action').val('edit');
        $('#supplier_id').val(s.id);
        $('#supplier_name').val(s.supplier_name);
        $('#phone').val(s.phone);
        $('#email').val(s.email);
        $('#address').val(s.address);
        $('#gst_number').val(s.gst_number);
        $('#pincode').val(s.pincode);

        $('#modalTitle').text('Edit Supplier');
        $('#supplierModal').modal('show');
    }

    $(document).ready(function() {
        $('#supplierForm').submit(function(e) {
            e.preventDefault();
            $('#saveBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: 'ajax/supplier_action.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(res) {
                    $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Changes');
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: res.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                },
                error: function() {
                    $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Changes');
                    Swal.fire('Error', 'Something went wrong', 'error');
                }
            });
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>