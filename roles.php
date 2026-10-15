<?php
// roles.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

$roles = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood fw-bold"><i class="fas fa-user-tag me-2"></i>System Roles</h2>
</div>

<div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-4">
        <table class="table table-hover datatable w-100 table-borderless border-bottom">
            <thead class="bg-wood text-white rounded-top">
                <tr>
                    <th class="rounded-start" width="20%">ID</th>
                    <th class="rounded-end" width="80%">Role Name</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roles as $r): ?>
                    <tr class="border-bottom">
                        <td class="align-middle fw-bold text-muted">#<?= $r['id'] ?></td>
                        <td class="align-middle">
                            <h6 class="mb-0 fw-bold text-primary"><?= htmlspecialchars($r['role_name']) ?></h6>
                            <?php if (in_array($r['role_name'], ['Super Admin', 'Admin', 'Staff'])): ?>
                                <span class="badge bg-danger rounded-pill mt-1" style="font-size: 0.70rem;">System Protected</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Role modifications have been disabled. System strictly uses 3 roles.
</script>

<?php require_once 'includes/footer.php'; ?>