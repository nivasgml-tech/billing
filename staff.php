<?php
// staff.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

$branch_id = $_SESSION['branch_id'];
$isSuperAdminOrStaff = hasRole(['Super Admin', 'Staff']);

$where = $isSuperAdminOrStaff ? "1=1" : "s.branch_id = $branch_id";
$staffs = $pdo->query("SELECT s.*, b.branch_name, r.role_name, u.username 
                       FROM staffs s 
                       LEFT JOIN branches b ON s.branch_id = b.id 
                       LEFT JOIN roles r ON s.role_id = r.id 
                       LEFT JOIN users u ON (s.email = u.email OR s.phone = u.phone)
                       WHERE $where 
                       ORDER BY s.full_name ASC")->fetchAll();

$branches = $pdo->query("SELECT id, branch_name FROM branches WHERE status = 1")->fetchAll();
$roles = $pdo->query("SELECT id, role_name FROM roles")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood fw-bold"><i class="fas fa-id-badge me-2"></i>Staff & HR Management</h2>
    <button class="btn btn-wood shadow-sm" data-bs-toggle="modal" data-bs-target="#staffModal" onclick="resetForm()">
        <i class="fas fa-plus-circle me-1"></i> Add New Staff
    </button>
</div>

<div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover datatable w-100 table-borderless border-bottom">
                <thead class="bg-wood text-white rounded-top">
                    <tr>
                        <th class="rounded-start">Staff Details</th>
                        <th>Designation/Role</th>
                        <th>Contact info</th>
                        <th>Login Access</th>
                        <th>Financials</th>
                        <th>Status</th>
                        <th class="rounded-end text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staffs as $s): ?>
                        <tr class="border-bottom">
                            <td class="align-middle">
                                <div class="fw-bold"><?= htmlspecialchars($s['full_name']) ?></div>
                                <small class="text-muted"><i class="fas fa-building me-1 small"></i><?= htmlspecialchars($s['branch_name'] ?: 'Global') ?></small>
                            </td>
                            <td class="align-middle">
                                <div class="small fw-bold"><?= htmlspecialchars($s['designation'] ?: 'N/A') ?></div>
                                <span class="badge bg-light text-dark border small fw-normal"><?= htmlspecialchars($s['role_name'] ?: 'No Role') ?></span>
                            </td>
                            <td class="align-middle">
                                <div class="small"><i class="fas fa-phone-alt me-1 text-muted"></i> <?= htmlspecialchars($s['phone']) ?></div>
                                <div class="small text-muted"><i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($s['email']) ?></div>
                            </td>
                            <td class="align-middle">
                                <?php if ($s['username']): ?>
                                    <div class="badge bg-success-soft text-success border border-success px-3">
                                        <i class="fas fa-user-check me-1"></i> <?= htmlspecialchars($s['username']) ?>
                                    </div>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-primary py-1 px-3" onclick="createLogin(<?= $s['id'] ?>, '<?= htmlspecialchars($s['full_name']) ?>', '<?= htmlspecialchars($s['email']) ?>', '<?= htmlspecialchars($s['phone']) ?>', <?= $s['role_id'] ?>, <?= $s['branch_id'] ?>)">
                                        <i class="fas fa-key me-1"></i> Create Login
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td class="align-middle">
                                <div class="small fw-bold">₹<?= number_format($s['salary'], 2) ?></div>
                                <div class="small text-muted"><?= $s['join_date'] ? date('d M Y', strtotime($s['join_date'])) : 'N/A' ?></div>
                            </td>
                            <td class="align-middle">
                                <span class="badge <?= $s['status'] ? 'bg-success' : 'bg-danger' ?> rounded-pill">
                                    <?= $s['status'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td class="align-middle text-center">
                                <div class="btn-group btn-group-sm shadow-sm">
                                    <button class="btn btn-white border" title="Edit Staff" onclick='editStaff(<?= json_encode($s) ?>)'><i class="fas fa-edit text-primary"></i></button>
                                    <button class="btn btn-white border" title="Delete Staff" onclick="deleteStaff(<?= $s['id'] ?>)"><i class="fas fa-trash text-danger"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Staff Modal -->
<div class="modal fade" id="staffModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="staffForm" class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-wood text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-user-plus me-2"></i>Staff Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="staff_id">
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" id="full_name" class="form-control shadow-sm" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Branch <span class="text-danger">*</span></label>
                        <select name="branch_id" id="branch_id" class="form-select shadow-sm" required>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['branch_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Phone</label>
                        <input type="text" name="phone" id="phone" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Email</label>
                        <input type="email" name="email" id="email" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Designation</label>
                        <input type="text" name="designation" id="designation" class="form-control shadow-sm" placeholder="e.g. Sales Executive">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">System Role <span class="text-danger">*</span></label>
                        <select name="role_id" id="role_id" class="form-select shadow-sm" required>
                            <option value="">-- Select Role --</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Monthly Salary (₹)</label>
                        <input type="number" step="0.01" name="salary" id="salary" class="form-control shadow-sm" value="0.00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Joining Date</label>
                        <input type="date" name="join_date" id="join_date" class="form-control shadow-sm" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold small">Address</label>
                        <textarea name="address" id="address" class="form-control shadow-sm" rows="2"></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Status</label>
                        <select name="status" id="status" class="form-select shadow-sm">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-white">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-wood px-5 fw-bold" id="saveBtn"><i class="fas fa-save me-2"></i>Save Staff</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetForm() {
    $('#staffForm')[0].reset();
    $('#action').val('add');
    $('#staff_id').val('');
}

function editStaff(s) {
    $('#action').val('edit');
    $('#staff_id').val(s.id);
    $('#full_name').val(s.full_name);
    $('#branch_id').val(s.branch_id);
    $('#phone').val(s.phone);
    $('#email').val(s.email);
    $('#designation').val(s.designation);
    $('#role_id').val(s.role_id);
    $('#salary').val(s.salary);
    $('#join_date').val(s.join_date);
    $('#address').val(s.address);
    $('#status').val(s.status);
    $('#staffModal').modal('show');
}

$(document).ready(function() {
    $('#staffForm').submit(function(e) {
        e.preventDefault();
        $('#saveBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...');
        $.post('ajax/staff_action.php', $(this).serialize(), function(res) {
            if (res.success) {
                Swal.fire({icon: 'success', title: 'Success', text: res.message, timer: 1500, showConfirmButton: false}).then(() => location.reload());
            } else {
                Swal.fire('Error', res.message, 'error');
                $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save me-2"></i>Save Staff');
            }
        }, 'json');
    });
});

function createLogin(id, name, email, phone, role_id, branch_id) {
    Swal.fire({
        title: 'Grant System Access',
        html: `
            <div class="text-start p-2">
                <div class="alert alert-info py-2 small mb-3"><i class="fas fa-info-circle me-1"></i> Creating a login for <strong>${name}</strong>.</div>
                
                <div class="mb-3">
                    <label class="form-label small fw-bold">Username <span class="text-danger">*</span></label>
                    <input type="text" id="swal_username" class="form-control form-control-sm" value="${email ? email.split('@')[0] : 'staff'+id}" placeholder="Enter username">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Temporary Password <span class="text-danger">*</span></label>
                    <input type="text" id="swal_password" class="form-control form-control-sm" value="Welcome@123">
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-user-shield me-2"></i>Create Account',
        confirmButtonColor: '#8B5A2B',
        preConfirm: () => {
            const username = $('#swal_username').val();
            const password = $('#swal_password').val();
            if (!username || !password) {
                Swal.showValidationMessage('Username and Password are required');
                return false;
            }
            return { username, password };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('ajax/staff_action.php', {
                action: 'create_login',
                staff_id: id,
                username: result.value.username,
                password: result.value.password,
                full_name: name,
                email: email,
                phone: phone,
                role_id: role_id,
                branch_id: branch_id
            }, function(res) {
                if (res.success) {
                    Swal.fire('Login Created!', 'Staff can now access the system.', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Access Error', res.message, 'error');
                }
            }, 'json');
        }
    });
}

function deleteStaff(id) {
    Swal.fire({
        title: 'Archive Staff Record?',
        text: "This will remove them from active management.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, delete!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('ajax/staff_action.php', { action: 'delete', id: id }, function(res) {
                if (res.success) location.reload();
            }, 'json');
        }
    });
}
</script>

<style>
.bg-success-soft { background-color: rgba(40, 167, 69, 0.1); }
.btn-xs { padding: 0.25rem 0.5rem; font-size: 0.75rem; border-radius: 0.2rem; }
</style>

<?php require_once 'includes/footer.php'; ?>