<?php
// users.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

$stmt = $pdo->query("
    SELECT u.*, r.role_name, b.branch_name 
    FROM users u 
    LEFT JOIN roles r ON u.role_id = r.id 
    LEFT JOIN branches b ON u.branch_id = b.id 
    ORDER BY u.id DESC
");
$users = $stmt->fetchAll();

$roles = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();
$branches = $pdo->query("SELECT * FROM branches WHERE status = 1 ORDER BY branch_name")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood fw-bold"><i class="fas fa-id-badge me-2"></i>Staff Management</h2>
    <button class="btn btn-wood shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetForm()">
        <i class="fas fa-plus-circle me-1"></i> Add New Staff
    </button>
</div>

<div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover datatable w-100 table-borderless border-bottom">
                <thead class="bg-wood text-white rounded-top">
                    <tr>
                        <th class="rounded-start">Staff Profile</th>
                        <th>Credentials</th>
                        <th>System Role</th>
                        <th>Branch Access</th>
                        <th>Financials</th>
                        <th>Status</th>
                        <th class="rounded-end text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr class="border-bottom">
                            <td class="align-middle">
                                <div class="d-flex align-items-center">
                                    <div class="bg-light rounded-circle p-2 me-3 text-wood">
                                        <i class="fas fa-user-circle fa-2x"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($u['full_name']) ?></div>
                                        <small class="text-muted"><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($u['email'] ?: 'No Email') ?></small>
                                    </div>
                                </div>
                            </td>
                            <td class="align-middle">
                                <div class="fw-bold text-primary">@<?= htmlspecialchars($u['username']) ?></div>
                                <small class="text-muted"><i class="fas fa-phone-alt me-1"></i><?= htmlspecialchars($u['phone'] ?: 'N/A') ?></small>
                            </td>
                            <td class="align-middle">
                                <span class="badge bg-light text-dark border px-3 fw-normal"><?= htmlspecialchars($u['role_name']) ?></span>
                            </td>
                            <td class="align-middle">
                                <div class="small fw-semibold"><?= htmlspecialchars($u['branch_name'] ?? 'All Branches') ?></div>
                                <small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?= $u['branch_id'] ? 'Restricted' : 'Full Access' ?></small>
                            </td>
                            <td class="align-middle">
                                <div class="fw-bold text-dark">₹<?= number_format($u['salary'] ?? 0, 2) ?></div>
                                <small class="text-muted text-uppercase" style="font-size: 0.65rem;">Monthly Salary</small>
                            </td>
                            <td class="align-middle">
                                <?php if ($u['status'] == 1): ?>
                                    <span class="badge bg-success rounded-pill px-3">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger rounded-pill px-3">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="align-middle text-center">
                                <div class="btn-group btn-group-sm shadow-sm">
                                    <button class="btn btn-white border" title="Edit Staff" onclick='editUser(<?= json_encode($u) ?>)'><i class="fas fa-edit text-primary"></i></button>
                                    <button class="btn btn-white border" title="Delete Staff" onclick="deleteUser(<?= $u['id'] ?>)"><i class="fas fa-trash text-danger"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modern User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="userForm" class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-wood text-white border-0">
                <h5 class="modal-title fw-bold" id="modalTitle"><i class="fas fa-user-plus me-2"></i>Add Staff</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="user_id" value="">

                <div class="row g-3">
                    <!-- Basic Info -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control shadow-sm" name="full_name" id="full_name" required placeholder="e.g. John Doe">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Branch Access <span class="text-danger">*</span></label>
                        <select class="form-select shadow-sm" name="branch_id" id="branch_id">
                            <option value="">-- All Branches (Global) --</option>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['branch_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Contact Info -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Phone Number</label>
                        <input type="text" class="form-control shadow-sm" name="phone" id="phone" placeholder="e.g. +91 9876543210">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Email Address</label>
                        <input type="email" class="form-control shadow-sm" name="email" id="email" placeholder="e.g. john@example.com">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Monthly Salary (₹)</label>
                        <input type="number" step="0.01" class="form-control shadow-sm" name="salary" id="salary" value="0.00">
                    </div>

                    <!-- Account Credentials -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Username <span class="text-danger">*</span></label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-at text-muted"></i></span>
                            <input type="text" class="form-control border-start-0" name="username" id="username" required placeholder="unique_username">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Password <span class="text-danger" id="pwdStar">*</span></label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-lock text-muted"></i></span>
                            <input type="password" class="form-control border-start-0" name="password" id="password" placeholder="••••••••">
                        </div>
                        <small class="text-muted extra-small" id="pwdHelp">Required for new accounts.</small>
                    </div>

                    <!-- System Access -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">System Role <span class="text-danger">*</span></label>
                        <select class="form-select shadow-sm" name="role_id" id="role_id" required onchange="toggleBranch()">
                            <option value="">-- Select Role --</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Account Status</label>
                        <select class="form-select shadow-sm" name="status" id="status">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-white p-3">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-wood px-5 fw-bold" id="saveBtn"><i class="fas fa-save me-2"></i>Save Staff</button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleBranch() {
        let roleName = $("#role_id option:selected").text().trim();
        if (roleName === 'Super Admin') {
            $('#branch_id').val('').prop('disabled', true);
            $('#branch_id').addClass('bg-light');
        } else {
            $('#branch_id').prop('disabled', false);
            $('#branch_id').removeClass('bg-light');
        }
    }

    function resetForm() {
        $('#userForm')[0].reset();
        $('#action').val('add');
        $('#user_id').val('');
        $('#modalTitle').html('<i class="fas fa-user-plus me-2"></i>Add New Staff');
        $('#password').prop('required', true);
        $('#pwdStar').show();
        $('#pwdHelp').show().text('Required for new accounts.');
        $('#salary').val('0.00');
        toggleBranch();
    }

    function editUser(u) {
        $('#action').val('edit');
        $('#user_id').val(u.id);
        $('#full_name').val(u.full_name);
        $('#username').val(u.username);
        $('#email').val(u.email);
        $('#phone').val(u.phone);
        $('#salary').val(u.salary);
        $('#role_id').val(u.role_id);
        $('#branch_id').val(u.branch_id);
        $('#status').val(u.status);

        $('#password').prop('required', false);
        $('#pwdStar').hide();
        $('#pwdHelp').show().text('Leave blank to keep current password.');
        toggleBranch();

        $('#modalTitle').html('<i class="fas fa-user-edit me-2"></i>Edit Staff Access');
        $('#userModal').modal('show');
    }

    $(document).ready(function() {
        $('#userForm').submit(function(e) {
            e.preventDefault();
            $('#saveBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...');

            let formData = $(this).serialize();
            if ($('#branch_id').is(':disabled')) {
                formData += '&branch_id=';
            }

            $.ajax({
                url: 'ajax/user_action.php',
                type: 'POST',
                data: formData,
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
                        $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save me-2"></i>Save Staff');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Server error occurred', 'error');
                    $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save me-2"></i>Save Staff');
                }
            });
        });
    });

    function deleteUser(id) {
        Swal.fire({
            title: 'Delete Staff Account?',
            text: "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, delete!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax/user_action.php', { action: 'delete', id: id }, function(res) {
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
.extra-small { font-size: 0.75rem; }
.btn-white { background: #fff; border: 1px solid #dee2e6; }
.btn-white:hover { background: #f8f9fa; }
</style>

<?php require_once 'includes/footer.php'; ?>