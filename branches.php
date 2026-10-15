<?php
// branches.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

$stmt = $pdo->query("SELECT * FROM branches ORDER BY id");
$branches = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood"><i class="fas fa-code-branch me-2"></i>Branch Management</h2>
    <button class="btn btn-wood" data-bs-toggle="modal" data-bs-target="#branchModal" onclick="resetForm()">
        <i class="fas fa-plus-circle me-1"></i> Add Branch
    </button>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <table class="table table-striped table-hover datatable w-100">
            <thead class="bg-wood text-white">
                <tr>
                    <th>ID</th>
                    <th>Branch Name</th>
                    <th>Address</th>
                    <th>Phone</th>
                    <th>GST Number</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($branches as $b): ?>
                    <tr>
                        <td><?= $b['id'] ?></td>
                        <td><strong><?= htmlspecialchars($b['branch_name']) ?></strong></td>
                        <td><?= htmlspecialchars($b['address']) ?></td>
                        <td><?= htmlspecialchars($b['phone']) ?></td>
                        <td><?= htmlspecialchars($b['gst_number']) ?></td>
                        <td>
                            <?php if ($b['status'] == 1): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm shadow-sm">
                                <button class="btn btn-white border" title="Edit Branch" onclick='editBranch(<?= json_encode($b) ?>)'><i class="fas fa-edit text-primary"></i></button>
                                <button class="btn btn-white border" title="Delete Branch" onclick="deleteBranch(<?= $b['id'] ?>)"><i class="fas fa-trash text-danger"></i></button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="branchModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="branchForm" class="modal-content">
            <div class="modal-header bg-wood text-white">
                <h5 class="modal-title" id="modalTitle">Add Branch</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="branch_id" value="">

                <div class="mb-3">
                    <label class="form-label text-wood fw-bold">Branch Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="branch_name" id="branch_name" required>
                </div>

                <div class="mb-3">
                    <label class="form-label text-wood fw-bold">Address</label>
                    <textarea class="form-control" name="address" id="address" rows="2"></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-wood fw-bold">Phone</label>
                        <input type="text" class="form-control" name="phone" id="phone">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-wood fw-bold">GST Number</label>
                        <input type="text" class="form-control" name="gst_number" id="gst_number">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-wood fw-bold">Status</label>
                    <select class="form-select" name="status" id="status">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
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
        $('#branchForm')[0].reset();
        $('#action').val('add');
        $('#branch_id').val('');
        $('#modalTitle').text('Add Branch');
    }

    function editBranch(b) {
        $('#action').val('edit');
        $('#branch_id').val(b.id);
        $('#branch_name').val(b.branch_name);
        $('#address').val(b.address);
        $('#phone').val(b.phone);
        $('#gst_number').val(b.gst_number);
        $('#status').val(b.status);

        $('#modalTitle').text('Edit Branch');
        $('#branchModal').modal('show');
    }

    $(document).ready(function() {
        $('#branchForm').submit(function(e) {
            e.preventDefault();
            $('#saveBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: 'ajax/branch_action.php',
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
                        $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Changes');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Server error occurred', 'error');
                    $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Changes');
                }
            });
        });
    });

    function deleteBranch(id) {
        Swal.fire({
            title: 'Delete Branch?',
            text: "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, delete!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax/branch_action.php', { action: 'delete', id: id }, function(res) {
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: res.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }, 'json');
            }
        });
    }
</script>

<style>
.btn-white { background: #fff; border: 1px solid #dee2e6; }
.btn-white:hover { background: #f8f9fa; }
</style>

<?php require_once 'includes/footer.php'; ?>