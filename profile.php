<?php
// profile.php
require_once 'includes/header.php';

// Fetch current user details
$stmt = $pdo->prepare("
    SELECT u.*, r.role_name, b.branch_name 
    FROM users u 
    LEFT JOIN roles r ON u.role_id = r.id 
    LEFT JOIN branches b ON u.branch_id = b.id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

?>

<div class="row mt-4">
    <div class="col-12 mb-3">
        <h2 class="text-wood fw-bold"><i class="fas fa-user-circle me-2"></i>My Profile</h2>
    </div>

    <!-- User Info Card -->
    <div class="col-lg-4 col-md-5 mb-4">
        <div class="card shadow-sm border-0 rounded-4 text-center h-100">
            <div class="card-body p-5 d-flex flex-column align-items-center justify-content-center">
                <div class="rounded-circle mb-3 d-flex align-items-center justify-content-center text-white"
                    style="width: 120px; height: 120px; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-glow) 100%); font-size: 3rem; box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);">
                    <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                </div>
                <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($user['full_name']) ?></h4>
                <p class="text-muted mb-2">@<?= htmlspecialchars($user['username']) ?></p>
                <span class="badge bg-primary rounded-pill px-3 py-2 mb-3 fs-6"><?= htmlspecialchars($user['role_name'] ?? 'No Role') ?></span>

                <hr class="w-100 text-muted my-3">

                <div class="d-flex justify-content-between w-100 px-3 text-start">
                    <span class="text-muted"><i class="fas fa-code-branch me-2"></i>Branch:</span>
                    <strong class="text-dark"><?= $user['branch_name'] ? htmlspecialchars($user['branch_name']) : 'Global (All Branches)' ?></strong>
                </div>
                <div class="d-flex justify-content-between w-100 px-3 text-start mt-2">
                    <span class="text-muted"><i class="fas fa-calendar-alt me-2"></i>Joined:</span>
                    <strong class="text-dark"><?= date('M d, Y', strtotime($user['created_at'])) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Settings Forms -->
    <div class="col-lg-8 col-md-7">
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                <h5 class="fw-bold text-wood"><i class="fas fa-user-edit me-2"></i>Update Personal Details</h5>
            </div>
            <div class="card-body p-4">
                <form id="profileForm">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label text-muted fw-bold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-lg bg-light" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted fw-bold">Email Address</label>
                            <input type="email" class="form-control form-control-lg bg-light" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted fw-bold">Phone Number</label>
                            <input type="text" class="form-control form-control-lg bg-light" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-wood px-4 fw-bold" id="btnUpdateProfile"><i class="fas fa-save me-2"></i>Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                <h5 class="fw-bold text-wood"><i class="fas fa-key me-2"></i>Change Password</h5>
            </div>
            <div class="card-body p-4">
                <form id="passwordForm">
                    <input type="hidden" name="action" value="update_password">

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label text-muted fw-bold">Current Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control bg-light" name="current_password" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted fw-bold">New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control bg-light" name="new_password" required minlength="6">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted fw-bold">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control bg-light" name="confirm_password" required minlength="6">
                        </div>
                    </div>
                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-dark px-4 fw-bold" id="btnUpdatePassword"><i class="fas fa-lock me-2"></i>Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#profileForm').submit(function(e) {
            e.preventDefault();
            $('#btnUpdateProfile').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...');

            $.post('ajax/profile_action.php', $(this).serialize(), function(res) {
                $('#btnUpdateProfile').prop('disabled', false).html('<i class="fas fa-save me-2"></i>Save Changes');
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Profile Updated',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => location.reload()); // Reload to update navbar state
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json').fail(function() {
                $('#btnUpdateProfile').prop('disabled', false).html('<i class="fas fa-save me-2"></i>Save Changes');
                Swal.fire('Error', 'Server error', 'error');
            });
        });

        $('#passwordForm').submit(function(e) {
            e.preventDefault();

            let newPwd = $('input[name="new_password"]').val();
            let confPwd = $('input[name="confirm_password"]').val();

            if (newPwd !== confPwd) {
                Swal.fire('Error', 'New password and confirm password do not match!', 'error');
                return;
            }

            $('#btnUpdatePassword').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Updating...');

            $.post('ajax/profile_action.php', $(this).serialize(), function(res) {
                $('#btnUpdatePassword').prop('disabled', false).html('<i class="fas fa-lock me-2"></i>Update Password');
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Password Changed',
                        text: res.message
                    });
                    $('#passwordForm')[0].reset();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json').fail(function() {
                $('#btnUpdatePassword').prop('disabled', false).html('<i class="fas fa-lock me-2"></i>Update Password');
                Swal.fire('Error', 'Server error', 'error');
            });
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>