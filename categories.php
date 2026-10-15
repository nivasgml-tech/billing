<?php
// categories.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']); // Only authorized roles can access

// Fetch Categories
$stmt = $pdo->query("SELECT * FROM categories ORDER BY id DESC");
$categories = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood"><i class="fas fa-tags me-2"></i>Category Management</h2>
    <button class="btn btn-wood" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="resetForm()">
        <i class="fas fa-plus-circle me-1"></i> Add Category
    </button>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <table class="table table-striped table-hover datatable w-100">
            <thead class="bg-wood">
                <tr>
                    <th>ID</th>
                    <th>Category Name</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td><?= $cat['id'] ?></td>
                        <td><?= htmlspecialchars($cat['category_name']) ?></td>
                        <td>
                            <?php if ($cat['status'] == 1): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick='editCategory(<?= json_encode($cat) ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteCategory(<?= $cat['id'] ?>)">
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
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="categoryForm" class="modal-content">
            <div class="modal-header bg-wood text-white">
                <h5 class="modal-title" id="modalTitle">Add Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="cat_id" value="">

                <div class="mb-3">
                    <label class="form-label text-wood fw-bold">Category Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="category_name" id="category_name" required>
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
        $('#categoryForm')[0].reset();
        $('#action').val('add');
        $('#cat_id').val('');
        $('#modalTitle').text('Add Category');
    }

    function editCategory(cat) {
        $('#action').val('edit');
        $('#cat_id').val(cat.id);
        $('#category_name').val(cat.category_name);
        $('#status').val(cat.status);
        $('#modalTitle').text('Edit Category');
        $('#categoryModal').modal('show');
    }

    function deleteCategory(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "This category will be permanently deleted!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax/category_action.php', { action: 'delete', id: id }, function(res) {
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
        $('#categoryForm').submit(function(e) {
            e.preventDefault();
            $('#saveBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: 'ajax/category_action.php',
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