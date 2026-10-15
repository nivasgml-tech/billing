<?php
// units.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

// Fetch Units
$stmt = $pdo->query("SELECT * FROM units ORDER BY id DESC");
$units = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood"><i class="fas fa-balance-scale me-2"></i>Unit Management</h2>
    <button class="btn btn-wood" data-bs-toggle="modal" data-bs-target="#unitModal" onclick="resetForm()">
        <i class="fas fa-plus-circle me-1"></i> Add Unit
    </button>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <table class="table table-striped table-hover datatable w-100">
            <thead class="bg-wood">
                <tr>
                    <th>ID</th>
                    <th>Unit Name</th>
                    <th>Fractional Support</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($units as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><?= htmlspecialchars($u['unit_name']) ?></td>
                        <td>
                            <?= $u['is_fractional'] == 1 ? 'Yes' : 'No' ?>
                        </td>
                        <td>
                            <?php if ($u['status'] == 1): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick='editUnit(<?= json_encode($u) ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteUnit(<?= $u['id'] ?>)">
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
<div class="modal fade" id="unitModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="unitForm" class="modal-content">
            <div class="modal-header bg-wood text-white">
                <h5 class="modal-title" id="modalTitle">Add Unit</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="unit_id" value="">

                <div class="mb-3">
                    <label class="form-label text-wood fw-bold">Unit Name (e.g., PCS, Set) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="unit_name" id="unit_name" required>
                </div>

                <div class="mb-3">
                    <label class="form-label text-wood fw-bold">Allow Decimals (Fractional Support) <span class="text-danger">*</span></label>
                    <select class="form-select" name="is_fractional" id="is_fractional" required>
                        <option value="0">No (Whole numbers only)</option>
                        <option value="1">Yes (Like 1.5 kgs)</option>
                    </select>
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
        $('#unitForm')[0].reset();
        $('#action').val('add');
        $('#unit_id').val('');
        $('#modalTitle').text('Add Unit');
    }

    function editUnit(u) {
        $('#action').val('edit');
        $('#unit_id').val(u.id);
        $('#unit_name').val(u.unit_name);
        $('#is_fractional').val(u.is_fractional);
        $('#status').val(u.status);
        $('#modalTitle').text('Edit Unit');
        $('#unitModal').modal('show');
    }

    function deleteUnit(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "This unit will be permanently deleted!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax/unit_action.php', { action: 'delete', id: id }, function(res) {
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
        $('#unitForm').submit(function(e) {
            e.preventDefault();
            $('#saveBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: 'ajax/unit_action.php',
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