<?php
// reminders.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

$branch_id = $_SESSION['branch_id'];
$isSuperAdminOrStaff = hasRole(['Super Admin', 'Staff']);

$where = $isSuperAdminOrStaff ? "1=1" : "branch_id = $branch_id";
$reminders = $pdo->query("SELECT * FROM reminders WHERE $where ORDER BY remind_date ASC, priority DESC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood"><i class="fas fa-bell me-2"></i>Reminders & Tasks</h2>
    <button class="btn btn-wood" data-bs-toggle="modal" data-bs-target="#addReminderModal">
        <i class="fas fa-plus-circle me-1"></i> Add Reminder
    </button>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <table class="table table-hover datatable w-100">
                    <thead class="bg-wood text-white">
                        <tr>
                            <th>Date</th>
                            <th>Title</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reminders as $r): 
                            $pClass = 'bg-info';
                            if ($r['priority'] == 'High') $pClass = 'bg-danger';
                            if ($r['priority'] == 'Medium') $pClass = 'bg-warning text-dark';
                        ?>
                            <tr>
                                <td><?= date('d M Y', strtotime($r['remind_date'])) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($r['title']) ?></strong>
                                    <?php if ($r['description']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($r['description']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= $pClass ?>"><?= $r['priority'] ?></span></td>
                                <td>
                                    <span class="badge <?= $r['status'] == 'Completed' ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $r['status'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <?php if ($r['status'] == 'Pending'): ?>
                                            <button class="btn btn-sm btn-outline-success" onclick="updateStatus(<?= $r['id'] ?>, 'Completed')">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="updateStatus(<?= $r['id'] ?>, 'Pending')">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteReminder(<?= $r['id'] ?>)">
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
    </div>
</div>

<!-- Add Reminder Modal -->
<div class="modal fade" id="addReminderModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="reminderForm" class="modal-content">
            <div class="modal-header bg-wood text-white">
                <h5 class="modal-title">Add New Reminder</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="mb-3">
                    <label class="form-label fw-bold">Title</label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. Call customer for payment">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Description</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Remind Date</label>
                        <input type="date" name="remind_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-wood">Save Reminder</button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#reminderForm').submit(function(e) {
        e.preventDefault();
        $.post('ajax/reminder_action.php', $(this).serialize(), function(res) {
            if (res.success) {
                Swal.fire('Success', res.message, 'success').then(() => location.reload());
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    });
});

function updateStatus(id, status) {
    $.post('ajax/reminder_action.php', { action: 'update_status', id: id, status: status }, function(res) {
        if (res.success) {
            location.reload();
        }
    }, 'json');
}

function deleteReminder(id) {
    Swal.fire({
        title: 'Delete Reminder?',
        text: "This action cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('ajax/reminder_action.php', { action: 'delete', id: id }, function(res) {
                if (res.success) {
                    location.reload();
                }
            }, 'json');
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
