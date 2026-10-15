<?php
// settings.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

$stmt = $pdo->query("SELECT * FROM settings");
$raw_settings = $stmt->fetchAll();
$s = [];
foreach ($raw_settings as $row) {
    $s[$row['setting_key']] = $row['setting_value'];
}
?>

<div class="mt-4 mb-3">
    <h2 class="text-wood"><i class="fas fa-cogs me-2"></i>General Settings</h2>
</div>

<div class="card shadow-sm border-0" style="max-width: 800px;">
    <div class="card-body">
        <form id="settingsForm">
            <input type="hidden" name="action" value="save_settings">

            <h5 class="text-wood border-bottom pb-2 mb-3">Shop Details</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Shop Name</label>
                    <input type="text" class="form-control" name="shop_name" value="<?= htmlspecialchars($s['shop_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Contact Number</label>
                    <input type="text" class="form-control" name="shop_contact" value="<?= htmlspecialchars($s['shop_contact'] ?? '') ?>" required>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label fw-bold">Shop Address</label>
                    <textarea class="form-control" name="shop_address" rows="2" required><?= htmlspecialchars($s['shop_address'] ?? '') ?></textarea>
                </div>
            </div>

            <h5 class="text-wood border-bottom pb-2 mb-3 mt-4">Billing & Financial</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">GSTIN Number</label>
                    <input type="text" class="form-control" name="gst_number" value="<?= htmlspecialchars($s['gst_number'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Financial Year</label>
                    <input type="text" class="form-control" name="financial_year" value="<?= htmlspecialchars($s['financial_year'] ?? '2025-2026') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Invoice Prefix</label>
                    <input type="text" class="form-control" name="invoice_prefix" value="<?= htmlspecialchars($s['invoice_prefix'] ?? 'INV-') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Print Format</label>
                    <select class="form-select" name="print_type">
                        <option value="A4" <?= ($s['print_type'] ?? 'A4') == 'A4' ? 'selected' : '' ?>>A4 Regular</option>
                        <option value="Thermal" <?= ($s['print_type'] ?? '') == 'Thermal' ? 'selected' : '' ?>>Thermal Receipt (80mm)</option>
                    </select>
                </div>
                
<div class="col-12 mb-3">
    <label class="form-label fw-bold">Invoice Footer Messages</label>
    <textarea
        class="form-control"
        name="invoice_footer"
        rows="5"
        placeholder="Thank You for Shopping!&#10;Please Visit Again&#10;Goods once sold cannot be returned"
    ><?= htmlspecialchars($s['invoice_footer'] ?? '') ?></textarea>
    <small class="text-muted">
        Enter each message on a separate line. All lines will appear at the bottom of the invoice.
    </small>
</div>

            </div>

            <div class="mt-4 text-end">
                <button type="submit" class="btn btn-wood btn-lg px-5" id="saveBtn"><i class="fas fa-save me-1"></i> Save Configuration</button>
            </div>
        </form>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#settingsForm').submit(function(e) {
            e.preventDefault();
            $('#saveBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: 'ajax/settings_action.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Saved',
                            text: 'Settings updated successfully. They will reflect on next reload.',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                        $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Configuration');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Server error occurred', 'error');
                    $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Configuration');
                }
            });
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>