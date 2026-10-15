<?php
// emi.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

if (hasRole(['Super Admin', 'Staff'])) {
    $stmt = $pdo->query("SELECT * FROM emi ORDER BY emi_date DESC, id DESC");
    $emis = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT * FROM emi WHERE branch_id = ? ORDER BY emi_date DESC, id DESC");
    $stmt->execute([$_SESSION['branch_id']]);
    $emis = $stmt->fetchAll();
}
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood fw-bold"><i class="fas fa-hand-holding-usd me-2"></i>EMI Management</h2>
    <a href="add_emi.php" class="btn btn-wood shadow-sm">
        <i class="fas fa-plus-circle me-1"></i> Add New EMI
    </a>
</div>

<div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover datatable w-100 table-borderless border-bottom">
                <thead class="bg-wood text-white rounded-top">
                    <tr>
                        <th class="rounded-start">Date</th>
                        <th>Customer Details</th>
                        <th>Product</th>
                        <th>Financials</th>
                        <th>EMI Info</th>
                        <th>Documents</th>
                        <th class="rounded-end text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($emis as $e): ?>
                        <tr class="border-bottom">
                            <td class="align-middle">
                                <span class="fw-bold"><?= date('d-m-Y', strtotime($e['emi_date'])) ?></span>
                            </td>
                            <td class="align-middle">
                                <h6 class="mb-0 fw-bold"><?= htmlspecialchars($e['customer_name']) ?></h6>
                                <small class="text-muted">
                                    <i class="fas fa-file-invoice me-1"></i> Bill: <?= htmlspecialchars($e['manual_bill_no'] ?? 'N/A') ?><br>
                                    <i class="fas fa-phone-alt me-1"></i> <?= htmlspecialchars($e['mobile']) ?><br>
                                    <i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($e['city']) ?>
                                </small>
                            </td>
                            <td class="align-middle">
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($e['product_name']) ?></span>
                            </td>
                            <td class="align-middle">
                                <div class="small">
                                    Product: ₹<?= number_format($e['product_amount'], 2) ?><br>
                                    Base: <span class="fw-bold text-dark">₹<?= number_format($e['total_amount'], 2) ?></span><br>
                                    Paid: <span class="text-success">₹<?= number_format($e['paid_amount'], 2) ?></span><br>
                                    Total: <span class="text-danger fw-bold">₹<?= number_format($e['loan_amount'], 2) ?></span>
                                </div>
                            </td>
                            <td class="align-middle">
                                <div class="small">
                                    EMI: <span class="fw-bold text-primary">₹<?= number_format($e['emi_amount'], 2) ?></span><br>
                                    Int: <?= number_format($e['interest'], 2) ?>% (₹<?= number_format($e['interest_amount'], 2) ?>)<br>
                                    Scheme: <?= htmlspecialchars($e['scheme'] ?? 'N/A') ?><br>
                                    Method: <?= htmlspecialchars($e['emi_method']) ?>
                                </div>
                            </td>
                            <td class="align-middle">
                                <div class="d-flex gap-2">
                                    <?php if ($e['photo']): ?>
                                        <a href="uploads/emi/<?= $e['photo'] ?>" target="_blank" class="btn btn-sm btn-outline-info" title="Photo">
                                            <i class="fas fa-user-circle"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($e['cheque_leaf']): ?>
                                        <a href="uploads/emi/<?= $e['cheque_leaf'] ?>" target="_blank" class="btn btn-sm btn-outline-info" title="Document Proof Cheque Leaf">
                                            <i class="fas fa-money-check"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($e['aadhar']): ?>
                                        <a href="uploads/emi/<?= $e['aadhar'] ?>" target="_blank" class="btn btn-sm btn-outline-info" title="Aadhar">
                                            <i class="fas fa-id-card"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($e['pan']): ?>
                                        <a href="uploads/emi/<?= $e['pan'] ?>" target="_blank" class="btn btn-sm btn-outline-info" title="PAN">
                                            <i class="fas fa-id-badge"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!$e['photo'] && !$e['cheque_leaf'] && !$e['aadhar'] && !$e['pan']): ?>
                                        <span class="text-muted small">None</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="align-middle text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="emi_statement.php?id=<?= $e['id'] ?>" class="btn btn-outline-info" title="Statement" target="_blank">
                                        <i class="fas fa-file-alt"></i>
                                    </a>
                                    <a href="add_emi.php?id=<?= $e['id'] ?>" class="btn btn-outline-primary" title="Edit EMI">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn btn-outline-danger" title="Delete EMI" onclick='deleteEMI(<?= $e['id'] ?>)'>
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

<script>
    function deleteEMI(id) {
        Swal.fire({
            title: 'Delete EMI?',
            text: "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax/emi_action.php', {
                    action: 'delete',
                    id: id
                }, function(res) {
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
        $('#product_name').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Search or Type Product Name...',
            allowClear: true,
            tags: true,
            minimumInputLength: 0,
            ajax: {
                url: 'ajax/emi_action.php',
                type: 'POST',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        action: 'search_products',
                        q: params.term || ''
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.results
                    };
                },
                cache: true
            }
        });

        $('#product_name').on('select2:select', function(e) {
            const data = e.params.data;
            if (data.price) {
                $('#total_amount').val(data.price).trigger('input');
            }
        });

        $('#scheme').on('change', function() {
            const schemeName = $(this).val();
            const scheme = schemesData.find(s => s.scheme_name === schemeName);
            if (scheme) {
                $('#interest').val(scheme.interest_rate);
                $('#emi_method').val(scheme.repayment_cycle);
                $('#tenure').val(scheme.number_of_installments);
                $('.calc-emi').first().trigger('input');
            }
        });

        $('#interest_type').on('change', function() {
            const type = $(this).val();
            if (type === 'Percentage') {
                $('#interest_label').text('Interest (%)');
                $('#interest').prop('readonly', false);
                $('#interest_amount').prop('readonly', true);
            } else {
                $('#interest_label').text('Interest Value');
                $('#interest').prop('readonly', true);
                $('#interest_amount').prop('readonly', false);
            }
        });

        $('.calc-emi, #interest, #interest_amount').on('input', function() {
            const total = parseFloat($('#total_amount').val()) || 0;
            const advance = parseFloat($('#advance_amount').val()) || 0;
            const loan = total - advance;
            $('#loan_amount').val(loan.toFixed(2));

            const type = $('#interest_type').val();
            let interestAmt = 0;
            if (type === 'Percentage') {
                const interestPer = parseFloat($('#interest').val()) || 0;
                interestAmt = loan * (interestPer / 100);
                $('#interest_amount').val(interestAmt.toFixed(2));
            } else {
                interestAmt = parseFloat($('#interest_amount').val()) || 0;
                const interestPer = loan > 0 ? (interestAmt / loan) * 100 : 0;
                $('#interest').val(interestPer.toFixed(2));
            }

            const tenure = parseInt($('#tenure').val()) || 1;
            const emiAmt = (loan + interestAmt) / tenure;
            $('#emi_amount').val(emiAmt.toFixed(2));
        });

        $('#emiForm').submit(function(e) {
            e.preventDefault();
            $('#saveBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...');

            const formData = new FormData(this);

            $.ajax({
                url: 'ajax/emi_action.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
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
                        $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save me-2"></i>Save EMI');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Server error occurred', 'error');
                    $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save me-2"></i>Save EMI');
                }
            });
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>
