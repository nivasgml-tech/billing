<?php
// add_emi.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$emi = null;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM emi WHERE id = ?");
    $stmt->execute([$id]);
    $emi = $stmt->fetch();
}

$schemes = $pdo->query("SELECT * FROM emi_schemes WHERE status = 1 ORDER BY scheme_name")->fetchAll();
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-wood fw-bold">
            <i class="fas <?= $id ? 'fa-edit' : 'fa-plus-circle' ?> me-2"></i>
            <?= $id ? 'Edit EMI Record' : 'Add New EMI Record' ?>
        </h2>
        <a href="emi.php" class="btn btn-outline-secondary px-4 shadow-sm">
            <i class="fas fa-arrow-left me-2"></i> Back to List
        </a>
    </div>

    <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
        <div class="card-header bg-wood text-white p-3 border-0">
            <h5 class="mb-0 fw-bold"><i class="fas fa-info-circle me-2"></i> EMI Details Information</h5>
        </div>
        <div class="card-body p-4 bg-light">
            <form id="emiForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?= $id ? 'edit' : 'add' ?>">
                <input type="hidden" name="id" value="<?= $id ?>">

                <div class="row g-4">
                    <!-- Fetch from POS UI -->
                    <?php if (!$id): // Only show on Add New ?>
                    <div class="col-12">
                        <div class="bg-white p-3 border rounded shadow-sm mb-2">
                            <div class="row align-items-end g-2">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-primary"><i class="fas fa-search me-1"></i>Fetch from POS Sale</label>
                                    <input type="text" class="form-control border-primary" id="search_invoice_no" placeholder="Enter Invoice Number (e.g. INV-001)">
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-primary w-100 fw-bold" id="fetchSaleBtn">Fetch Sale</button>
                                </div>
                                <div class="col-md-6 pt-2 pt-md-0 d-flex align-items-end">
                                    <small class="text-muted"><i class="fas fa-info-circle me-1"></i> Enter a POS invoice number to auto-fill Customer, Product, and Amount details.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Basic Info -->
                    <div class="col-md-3">
                        <label class="form-label fw-bold">EMI Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="emi_date" id="emi_date" 
                               value="<?= $emi ? $emi['emi_date'] : date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">First EMI Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="first_emi_date" id="first_emi_date" 
                               value="<?= $emi ? $emi['first_emi_date'] : date('Y-m-d', strtotime('+1 month')) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Manual Bill No</label>
                        <input type="text" class="form-control" name="manual_bill_no" id="manual_bill_no" 
                               value="<?= $emi ? htmlspecialchars($emi['manual_bill_no']) : '' ?>" 
                               placeholder="Bill Number">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Mobile</label>
                        <input type="text" class="form-control" name="mobile" id="mobile" 
                               value="<?= $emi ? htmlspecialchars($emi['mobile']) : '' ?>" 
                               placeholder="Contact Number">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Customer Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="customer_name" id="customer_name" 
                               value="<?= $emi ? htmlspecialchars($emi['customer_name']) : '' ?>" 
                               placeholder="Full Name" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">City</label>
                        <input type="text" class="form-control" name="city" id="city" 
                               value="<?= $emi ? htmlspecialchars($emi['city']) : '' ?>" 
                               placeholder="City">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">EMI Method</label>
                        <select class="form-select" name="emi_method" id="emi_method">
                            <option value="Monthly" <?= ($emi && $emi['emi_method'] == 'Monthly') ? 'selected' : '' ?>>Monthly</option>
                            <option value="Weekly" <?= ($emi && $emi['emi_method'] == 'Weekly') ? 'selected' : '' ?>>Weekly</option>
                            <option value="14 Days" <?= ($emi && $emi['emi_method'] == '14 Days') ? 'selected' : '' ?>>14 Days</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">Address</label>
                        <textarea class="form-control" name="address" id="address" rows="2" 
                                  placeholder="Full Address"><?= $emi ? htmlspecialchars($emi['address']) : '' ?></textarea>
                    </div>

                    <!-- Product & Center Info -->
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-light" name="product_name" id="product_name" 
                               value="<?= $emi ? htmlspecialchars($emi['product_name']) : '' ?>" 
                               placeholder="Product Name (Fetched from POS)" required readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Center Name</label>
                        <input type="text" class="form-control" name="center_name" id="center_name" 
                               value="<?= $emi ? htmlspecialchars($emi['center_name']) : '' ?>" 
                               placeholder="Center Name">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Is Center?</label>
                        <select class="form-select" name="is_center" id="is_center">
                            <option value="No" <?= ($emi && $emi['is_center'] == 'No') ? 'selected' : '' ?>>No</option>
                            <option value="Yes" <?= ($emi && $emi['is_center'] == 'Yes') ? 'selected' : '' ?>>Yes</option>
                        </select>
                    </div>
                    <div class="col-md-4" style="display:none;">
                        <label class="form-label fw-bold">Scheme</label>
                        <select class="form-select" name="scheme" id="scheme">
                            <option value="">Select Scheme</option>
                            <?php foreach ($schemes as $s): ?>
                                <option value="<?= htmlspecialchars($s['scheme_name']) ?>" 
                                        <?= ($emi && $emi['scheme'] == $s['scheme_name']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['scheme_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 mt-3" id="fetched_items_container" style="display: none;">
                        <h6 class="fw-bold text-primary mb-2">Purchased Items Details</h6>
                        <div class="table-responsive border rounded">
                            <table class="table table-bordered table-sm table-striped mb-0">
                                <thead class="table-primary">
                                    <tr>
                                        <th>Product Name</th>
                                        <th class="text-center" width="15%">Quantity</th>
                                        <th class="text-end" width="20%">Amount (₹)</th>
                                    </tr>
                                </thead>
                                <tbody id="fetched_items_body">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="fw-bold mb-0 text-wood"><i class="fas fa-calculator me-2"></i> Financial Calculations</h5>

                    <!-- Financials -->
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Int. Type</label>
                        <select class="form-select" name="interest_type" id="interest_type">
                            <option value="Percentage" <?= ($emi && $emi['interest_type'] == 'Percentage') ? 'selected' : '' ?>>Percentage (%)</option>
                            <option value="Fixed" <?= ($emi && $emi['interest_type'] == 'Fixed') ? 'selected' : '' ?>>Fixed (₹)</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold" id="interest_label">Interest (%)</label>
                        <input type="number" step="0.01" class="form-control" name="interest" id="interest" 
                               value="<?= $emi ? $emi['interest'] : '0.00' ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Interest Amount</label>
                        <input type="number" step="0.01" class="form-control" name="interest_amount" id="interest_amount" 
                               value="<?= $emi ? $emi['interest_amount'] : '0.00' ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Product Amount</label>
                        <input type="number" step="0.01" class="form-control calc-emi" name="product_amount" id="product_amount" 
                               value="<?= $emi ? $emi['product_amount'] : '0.00' ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold text-success">Advance Amount</label>
                        <input type="number" step="0.01" class="form-control calc-emi border-success" name="advance_amount" id="advance_amount" 
                               value="<?= $emi ? $emi['advance_amount'] : '0.00' ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold text-wood">Total Amount</label>
                        <input type="number" step="0.01" class="form-control bg-white fw-bold calc-emi" name="total_amount" id="total_amount" 
                               value="<?= $emi ? $emi['total_amount'] : '0.00' ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold text-danger">Loan Amount</label>
                        <input type="number" step="0.01" class="form-control bg-white fw-bold text-danger" name="loan_amount" id="loan_amount" 
                               value="<?= $emi ? $emi['loan_amount'] : '0.00' ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Tenure (Installments)</label>
                        <input type="number" class="form-control calc-emi" name="tenure" id="tenure" 
                               value="<?= $emi ? $emi['tenure'] : '1' ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold text-primary">EMI Amount</label>
                        <input type="number" step="0.01" class="form-control fw-bold text-primary border-primary" name="emi_amount" id="emi_amount" 
                               value="<?= $emi ? $emi['emi_amount'] : '0.00' ?>">
                    </div>

                    <hr class="my-4">
                    <h5 class="fw-bold mb-0 text-wood"><i class="fas fa-file-upload me-2"></i> Document Proofs</h5>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Customer Photo <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="photo" id="photo_input" accept="image/*" <?= (!$emi) ? 'required' : '' ?>>
                        <?php if ($emi && $emi['photo']): ?>
                            <small class="text-success"><i class="fas fa-check-circle me-1"></i> Current: <?= $emi['photo'] ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Cheque Leaf</label>
                        <input type="file" class="form-control" name="cheque_leaf" id="cheque_leaf_input" accept="image/*,application/pdf">
                        <?php if ($emi && $emi['cheque_leaf']): ?>
                            <small class="text-success"><i class="fas fa-check-circle me-1"></i> Current: <?= $emi['cheque_leaf'] ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Aadhar Card <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="aadhar" id="aadhar_input" accept="image/*,application/pdf" <?= (!$emi) ? 'required' : '' ?>>
                        <?php if ($emi && $emi['aadhar']): ?>
                            <small class="text-success"><i class="fas fa-check-circle me-1"></i> Current: <?= $emi['aadhar'] ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">PAN Card <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="pan" id="pan_input" accept="image/*,application/pdf" <?= (!$emi) ? 'required' : '' ?>>
                        <?php if ($emi && $emi['pan']): ?>
                            <small class="text-success"><i class="fas fa-check-circle me-1"></i> Current: <?= $emi['pan'] ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row mt-5">
                    <div class="col-12 text-center">
                        <button type="submit" class="btn btn-wood px-5 py-2 fw-bold shadow" id="saveBtn">
                            <i class="fas fa-save me-2"></i> <?= $id ? 'Update EMI Record' : 'Save EMI Record' ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const schemesData = <?= json_encode($schemes) ?>;

    $(document).ready(function() {
        $('#fetchSaleBtn').click(function() {
            const invoice = $('#search_invoice_no').val().trim();
            if (!invoice) {
                Swal.fire('Error', 'Please enter an invoice number', 'warning');
                return;
            }
            
            const btn = $(this);
            const originalText = btn.html();
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            $.post('ajax/emi_action.php', {
                action: 'fetch_pos_invoice',
                invoice_no: invoice
            }, function(res) {
                btn.prop('disabled', false).html(originalText);
                if (res.success) {
                    const data = res.data;
                    $('#manual_bill_no').val(invoice);
                    $('#customer_name').val(data.customer_name);
                    $('#mobile').val(data.mobile);
                    $('#city').val(data.city);
                    $('#address').val(data.address);
                    
                    // Product Selection
                    if (data.product_name) {
                        $('#product_name').val(data.product_name);
                    }
                    
                    if (data.items && data.items.length > 0) {
                        let rows = '';
                        data.items.forEach(function(item) {
                            rows += `<tr>
                                <td>${item.product_name}</td>
                                <td class="text-center">${parseFloat(item.quantity).toFixed(2)}</td>
                                <td class="text-end">${parseFloat(item.total_amount).toFixed(2)}</td>
                            </tr>`;
                        });
                        $('#fetched_items_body').html(rows);
                        $('#fetched_items_container').show();
                    } else {
                        $('#fetched_items_container').hide();
                    }
                    
                    $('#total_amount').val(parseFloat(data.grand_total).toFixed(2));
                    $('#advance_amount').val('0.00');
                    $('#product_amount').val(parseFloat(data.grand_total).toFixed(2));
                    
                    $('.calc-emi').first().trigger('input');
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Sale Found',
                        text: 'Details populated successfully!',
                        timer: 1500,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire('Not Found', res.message, 'error');
                }
            }, 'json').fail(function() {
                btn.prop('disabled', false).html(originalText);
                Swal.fire('Error', 'Server communication failed', 'error');
            });
        });

        $('#emi_date, #emi_method').on('change', function() {
            if ($('#emi_date').val()) {
                const emiDate = new Date($('#emi_date').val());
                const method = $('#emi_method').val();
                let firstEmiDate = new Date(emiDate);

                if (method === 'Weekly') {
                    firstEmiDate.setDate(emiDate.getDate() + 7);
                } else if (method === '14 Days') {
                    firstEmiDate.setDate(emiDate.getDate() + 14);
                } else { // Monthly
                    firstEmiDate.setMonth(emiDate.getMonth() + 1);
                }

                // Format to YYYY-MM-DD
                const yyyy = firstEmiDate.getFullYear();
                const mm = String(firstEmiDate.getMonth() + 1).padStart(2, '0');
                const dd = String(firstEmiDate.getDate()).padStart(2, '0');
                $('#first_emi_date').val(`${yyyy}-${mm}-${dd}`);
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
        }).trigger('change');

        $('.calc-emi, #interest, #interest_amount').on('input', function() {
            const triggerId = $(this).attr('id');
            let total_amt = parseFloat($('#total_amount').val()) || 0;
            
            if (triggerId === 'product_amount') {
                total_amt = parseFloat($('#product_amount').val()) || 0;
                $('#total_amount').val(total_amt.toFixed(2));
            } else if (triggerId === 'total_amount') {
                $('#product_amount').val(total_amt.toFixed(2));
            }

            let advance = parseFloat($('#advance_amount').val()) || 0;
            if (advance > total_amt) {
                advance = total_amt;
                $('#advance_amount').val(advance.toFixed(2));
                // Optional: alert user
            }
            
            // Calculate Interest
            const type = $('#interest_type').val();
            let interestAmt = 0;
            if (type === 'Percentage') {
                const interestPer = parseFloat($('#interest').val()) || 0;
                interestAmt = total_amt * (interestPer / 100);
                $('#interest_amount').val(interestAmt.toFixed(2));
            } else {
                interestAmt = parseFloat($('#interest_amount').val()) || 0;
                const interestPer = total_amt > 0 ? (interestAmt / total_amt) * 100 : 0;
                $('#interest').val(interestPer.toFixed(2));
            }

            const loan_total = total_amt + interestAmt;
            $('#loan_amount').val(loan_total.toFixed(2));

            const tenure = parseInt($('#tenure').val()) || 1;
            const emiAmt = (loan_total - advance) / tenure;
            $('#emi_amount').val(emiAmt.toFixed(2));
        });

        $('#emiForm').submit(function(e) {
            e.preventDefault();
            const btn = $('#saveBtn');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...');

            const emiAmt = parseFloat($('#emi_amount').val()) || 0;
            if (emiAmt < 0) {
                Swal.fire('Error', 'EMI amount cannot be negative. Please check Advance Amount.', 'error');
                btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i>Save EMI Record');
                return;
            }

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
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = 'emi.php';
                        });
                    } else {
                        Swal.fire('Error', res.message, 'error');
                        btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i>Save EMI Record');
                    }
                },
                error: function(xhr) {
                    Swal.fire('Error', 'Server error occurred. Please check console.', 'error');
                    console.log(xhr.responseText);
                    btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i>Save EMI Record');
                }
            });
        });

        // Trigger initial calculation if editing
        <?php if ($emi): ?>
        $('.calc-emi').first().trigger('input');
        <?php endif; ?>
    });
</script>

<?php require_once 'includes/footer.php'; ?>
