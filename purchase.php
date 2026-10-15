<?php
// purchase.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY supplier_name")->fetchAll();
$products = $pdo->query("SELECT id, product_name, barcode, purchase_price, gst_rate FROM products WHERE status = 1 ORDER BY product_name")->fetchAll();
?>

<div class="mt-4 mb-3">
    <h2 class="text-wood"><i class="fas fa-truck-loading me-2"></i>New Purchase Entry</h2>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body bg-light">
        <form id="purchaseForm">
            <input type="hidden" name="action" value="save_purchase">

            <div class="row mb-3">
                <div class="col-md-3 mb-2">
                    <label class="form-label fw-bold text-wood">Supplier <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <select class="form-select select2" name="supplier_id" id="supplier_id" required>
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['supplier_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <a href="suppliers.php" class="btn btn-outline-secondary" title="Add Supplier"><i class="fas fa-plus"></i></a>
                    </div>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label fw-bold text-wood">Bill Number <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="bill_no" required>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label fw-bold text-wood">Purchase Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="purchase_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label fw-bold text-wood">Purchase Type</label>
                    <select class="form-select" name="purchase_type" id="purchase_type" onchange="togglePurchaseType()">
                        <option value="GST">GST Purchase</option>
                        <option value="Non-GST">Non-GST</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label fw-bold text-wood">Branch</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($_SESSION['branch_name'] ?? 'Main Branch') ?>" readonly>
                </div>
            </div>

            <!-- Barcode Scanner Input -->
            <div class="card border-0 mb-2">
                <div class="card-body py-2 px-3" style="background: linear-gradient(135deg,#f8f3ee,#fff8f2); border: 1.5px dashed #b5895a; border-radius: 8px;">
                    <div class="row align-items-end g-2">
                        <div class="col-auto">
                            <label class="form-label fw-bold text-wood mb-1" style="font-size:0.85rem;"><i class="fas fa-barcode me-1"></i> Scan / Enter Barcode</label>
                        </div>
                        <div class="col">
                            <div class="input-group">
                                <span class="input-group-text bg-wood text-white px-3"><i class="fas fa-qrcode"></i></span>
                                <input type="text" id="barcodeInput" class="form-control form-control" placeholder="Scan barcode or type and press Enter..." autocomplete="off" style="font-size:1rem; letter-spacing:1px; font-family:monospace;">
                                <button type="button" class="btn btn-wood" id="barcodeAddBtn"><i class="fas fa-plus-circle me-1"></i> Add Product</button>
                            </div>
                            <div id="barcodeMsg" class="mt-1" style="font-size:0.8rem; min-height:18px;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 mb-3">
                <div class="card-header bg-wood text-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Purchase Items</h6>
                    <button type="button" class="btn btn-sm btn-light" id="addRowBtn"><i class="fas fa-plus-circle"></i> Add Row</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered mb-0" id="purchaseTable">
                        <thead class="bg-light text-center">
                            <tr>
                                <th width="35%">Product</th>
                                <th width="15%">Unit Price</th>
                                <th width="10%">Qty</th>
                                <th width="10%" class="tax-col">Tax %</th>
                                <th width="10%" class="tax-col">Tax Amt</th>
                                <th width="15%">Total Amt</th>
                                <th width="5%"></th>
                            </tr>
                        </thead>
                        <tbody id="purchaseBody">
                            <!-- Rows -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="5" class="text-end text-muted footer-colspan">Sub Total</th>
                                <th><input type="text" class="form-control text-end fw-bold" id="sub_total" name="sub_total" readonly value="0.00"></th>
                                <th></th>
                            </tr>
                            <tr>
                                <th colspan="5" class="text-end text-muted footer-colspan">Discount (-)</th>
                                <th><input type="number" step="0.01" class="form-control text-end" id="discount_amount" name="discount_amount" value="0.00" onkeyup="calculateGrandTotal()"></th>
                                <th></th>
                            </tr>
                            <tr class="tax-col">
                                <th colspan="5" class="text-end text-muted footer-colspan">Total Tax (+)</th>
                                <th><input type="text" class="form-control text-end fw-bold text-primary" id="total_tax" name="tax_amount" readonly value="0.00"></th>
                                <th></th>
                            </tr>
                            <tr>
                                <th colspan="5" class="text-end fs-5 text-wood footer-colspan">Grand Total</th>
                                <th><input type="text" class="form-control text-end fs-5 fw-bold text-success" id="grand_total" name="grand_total" readonly value="0.00"></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Payment -->
            <div class="row mt-3 bg-white p-3 rounded border">
                <div class="col-md-3 mb-2">
                    <label class="form-label text-wood fw-bold">Payment Status</label>
                    <select class="form-select" name="payment_status" id="payment_status" onchange="togglePaymentBox()">
                        <option value="Paid">Fully Paid</option>
                        <option value="Partial">Partial Payment</option>
                        <option value="Unpaid">Unpaid / Credit</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2 payment-box">
                    <label class="form-label text-wood fw-bold">Paid Amount</label>
                    <input type="number" step="0.01" class="form-control text-end fw-bold" name="paid_amount" id="paid_amount" value="0.00">
                </div>
                <div class="col-md-3 mb-2 payment-box">
                    <label class="form-label text-wood fw-bold">Payment Mode</label>
                    <select class="form-select" name="payment_mode">
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="UPI">UPI</option>
                        <option value="Cheque">Cheque</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-wood w-100 btn-lg" id="saveBtn"><i class="fas fa-save me-1"></i> Save Purchase Bill</button>
                </div>
            </div>

        </form>
    </div>
</div>

<style>
    .non-gst-mode .tax-col {
        display: none !important;
    }
</style>
<script>
    const products = <?= json_encode($products) ?>;
    let rowCount = 0;

    function productOptions(selectedId) {
        let opts = '';
        products.forEach(p => {
            let sel = (selectedId && p.id == selectedId) ? 'selected' : '';
            opts += `<option value="${p.id}" data-price="${p.purchase_price}" data-tax="${p.gst_rate}" ${sel}>${p.product_name}</option>`;
        });
        return opts;
    }

    // addRow(productData) — if productData passed, pre-fill row from barcode scan
    function addRow(productData) {
        if (!Array.isArray(products)) {
            console.error("Products data not loaded");
            return;
        }

        rowCount++;
        let selectedId = productData ? productData.id : null;
        let tr = `
    <tr id="row_${rowCount}">
        <td>
            <select class="form-control" name="product_id[]" id="product_${rowCount}" required onchange="fetchProductDetails(${rowCount})">
                <option></option>
                ${productOptions(selectedId)}
            </select>
        </td>
        <td><input type="number" step="0.01" class="form-control text-end" name="unit_price[]" id="price_${rowCount}" value="0.00" onkeyup="calcRow(${rowCount})" required></td>
        <td><input type="number" step="0.01" class="form-control text-center text-primary fw-bold" name="qty[]" id="qty_${rowCount}" value="1" onkeyup="calcRow(${rowCount})" required></td>
        <td class="tax-col"><input type="number" step="0.01" class="form-control text-center" name="tax_rate[]" id="taxrate_${rowCount}" value="0" onkeyup="calcRow(${rowCount})" readonly></td>
        <td class="tax-col"><input type="text" class="form-control text-end" name="tax_amt[]" id="taxamt_${rowCount}" value="0.00" readonly></td>
        <td><input type="text" class="form-control text-end fw-bold" name="total_amt[]" id="total_${rowCount}" value="0.00" readonly></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="$('#row_${rowCount}').remove(); calculateGrandTotal();"><i class="fas fa-times"></i></button></td>
    </tr>`;

        $('#purchaseBody').append(tr);

        // Select2 init
        setTimeout(() => {
            $(`#product_${rowCount}`).select2({
                theme: 'bootstrap-5',
                tags: true,
                placeholder: "Select or Type New Product...",
                width: '100%',
                allowClear: true,
                dropdownParent: $('#purchaseTable')
            });

            // If barcode product data supplied, pre-fill price & tax then calc
            if (productData) {
                $(`#price_${rowCount}`).val(parseFloat(productData.purchase_price).toFixed(2));
                $(`#taxrate_${rowCount}`).val(productData.gst_rate);
                calcRow(rowCount);
                // Focus quantity field so user can quickly update
                $(`#qty_${rowCount}`).select();
            }
        }, 60);
    }

    // Scan barcode → AJAX → add row
    function scanBarcode() {
        let barcode = $('#barcodeInput').val().trim();
        if (!barcode) {
            showBarcodeMsg('Please enter a barcode.', 'text-warning');
            return;
        }

        $('#barcodeAddBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        $('#barcodeMsg').text('').removeClass();

        $.ajax({
            url: 'ajax/purchase_action.php',
            type: 'POST',
            data: { action: 'get_product_by_barcode', barcode: barcode },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    // Check if same barcode product row already exists → just add qty+1
                    let existingRow = findExistingProductRow(res.product.id);
                    if (existingRow) {
                        let qtyField = $(`#qty_${existingRow}`);
                        qtyField.val((parseFloat(qtyField.val()) || 0) + 1);
                        calcRow(existingRow);
                        qtyField.closest('tr').addClass('table-warning');
                        setTimeout(() => qtyField.closest('tr').removeClass('table-warning'), 800);
                        showBarcodeMsg(`✔ Qty updated for: ${res.product.product_name}`, 'text-success fw-bold');
                    } else {
                        addRow(res.product);
                        showBarcodeMsg(`✔ Added: ${res.product.product_name} (Barcode: ${res.product.barcode})`, 'text-success fw-bold');
                    }
                    $('#barcodeInput').val('').focus();
                } else {
                    showBarcodeMsg('✘ ' + res.message, 'text-danger fw-bold');
                    $('#barcodeInput').select();
                }
                $('#barcodeAddBtn').prop('disabled', false).html('<i class="fas fa-plus-circle me-1"></i> Add Product');
            },
            error: function() {
                showBarcodeMsg('✘ Server error. Try again.', 'text-danger fw-bold');
                $('#barcodeAddBtn').prop('disabled', false).html('<i class="fas fa-plus-circle me-1"></i> Add Product');
            }
        });
    }

    function findExistingProductRow(productId) {
        let found = null;
        $('select[name="product_id[]"]').each(function() {
            if ($(this).val() == productId) {
                found = $(this).attr('id').replace('product_', '');
                return false; // break
            }
        });
        return found;
    }

    function showBarcodeMsg(msg, cssClass) {
        $('#barcodeMsg').text(msg).removeClass().addClass(cssClass);
    }

    function fetchProductDetails(rId) {
        let selected = $(`#product_${rId} option:selected`);
        let price = selected.data('price');
        let tax = selected.data('tax');

        if (price) $(`#price_${rId}`).val(price);
        if (tax) $(`#taxrate_${rId}`).val(tax);

        calcRow(rId);
    }

    function calcRow(rId) {
        let isNonGST = $('#purchase_type').val() === 'Non-GST';
        let price = parseFloat($(`#price_${rId}`).val()) || 0;
        let qty = parseFloat($(`#qty_${rId}`).val()) || 0;
        let taxRate = isNonGST ? 0 : (parseFloat($(`#taxrate_${rId}`).val()) || 0);

        let sub = price * qty;
        let taxAmt = sub * (taxRate / 100);
        let total = sub + taxAmt;

        $(`#taxamt_${rId}`).val(taxAmt.toFixed(2));
        $(`#total_${rId}`).val(total.toFixed(2));

        calculateGrandTotal();
    }

    function calculateGrandTotal() {
        let subTotal = 0;
        let totalTax = 0;

        $('input[name="qty[]"]').each(function(index) {
            let price = parseFloat($($('input[name="unit_price[]"]')[index]).val()) || 0;
            let qty = parseFloat($(this).val()) || 0;
            let tax = parseFloat($($('input[name="tax_amt[]"]')[index]).val()) || 0;

            subTotal += (price * qty);
            totalTax += tax;
        });

        let discount = parseFloat($('#discount_amount').val()) || 0;

        $('#sub_total').val(subTotal.toFixed(2));
        $('#total_tax').val(totalTax.toFixed(2));

        let grandTotal = subTotal + totalTax - discount;
        $('#grand_total').val(grandTotal.toFixed(2));

        if ($('#payment_status').val() === 'Paid') {
            $('#paid_amount').val(grandTotal.toFixed(2));
        }
    }

    function togglePaymentBox() {
        let status = $('#payment_status').val();
        if (status === 'Unpaid') {
            $('.payment-box').hide();
            $('#paid_amount').val(0);
        } else {
            $('.payment-box').show();
            if (status === 'Paid') {
                $('#paid_amount').val($('#grand_total').val());
                $('#paid_amount').prop('readonly', true);
            } else {
                $('#paid_amount').prop('readonly', false);
            }
        }
    }

    function togglePurchaseType() {
        let isNonGST = $('#purchase_type').val() === 'Non-GST';
        if (isNonGST) {
            $('#purchaseTable').addClass('non-gst-mode');
            $('.footer-colspan').attr('colspan', 3);
        } else {
            $('#purchaseTable').removeClass('non-gst-mode');
            $('.footer-colspan').attr('colspan', 5);
        }
        
        // Recalculate all rows to update tax
        $('input[name="qty[]"]').each(function() {
            let idAttr = $(this).attr('id');
            if(idAttr) {
                let id = idAttr.split('_')[1];
                calcRow(id);
            }
        });
    }
    $(document).ready(function() {
        $('#addRowBtn').click(function() {
            addRow();
        });

        // Barcode scan events
        $('#barcodeAddBtn').click(function() {
            scanBarcode();
        });
        $('#barcodeInput').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                scanBarcode();
            }
        });

        // Initialize first row after listeners are set
        addRow();
        togglePurchaseType();

        $('#purchaseForm').submit(function(e) {
            e.preventDefault();

            // Check if any product rows exist
            let rows = $('#purchaseBody tr');
            if (rows.length === 0) {
                Swal.fire('Error', 'Please add at least one product', 'error');
                return;
            }

            // Check that at least one row has a product selected
            let hasProduct = false;
            $('select[name="product_id[]"]').each(function() {
                if ($(this).val()) { hasProduct = true; return false; }
            });
            if (!hasProduct) {
                Swal.fire('Error', 'Please select a product in each row', 'error');
                return;
            }

            $('#saveBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

            // Build data manually - $.serialize() misses Select2 hidden values
            let formData = {
                action:          'save_purchase',
                supplier_id:     $('#supplier_id').val(),
                bill_no:         $('input[name="bill_no"]').val(),
                purchase_date:   $('input[name="purchase_date"]').val(),
                sub_total:       $('#sub_total').val(),
                discount_amount: $('#discount_amount').val(),
                tax_amount:      $('#total_tax').val(),
                grand_total:     $('#grand_total').val(),
                payment_status:  $('#payment_status').val(),
                paid_amount:     $('#paid_amount').val(),
                payment_mode:    $('select[name="payment_mode"]').val(),
                'product_id[]':  [],
                'unit_price[]':  [],
                'qty[]':         [],
                'tax_rate[]':    [],
                'tax_amt[]':     [],
                'total_amt[]':   []
            };

            $('#purchaseBody tr').each(function() {
                let pid   = $(this).find('select[name="product_id[]"]').val();
                let price = $(this).find('input[name="unit_price[]"]').val();
                let qty   = $(this).find('input[name="qty[]"]').val();
                let taxR  = $(this).find('input[name="tax_rate[]"]').val();
                let taxA  = $(this).find('input[name="tax_amt[]"]').val();
                let total = $(this).find('input[name="total_amt[]"]').val();

                if (!pid || !qty || parseFloat(qty) <= 0) return; // skip empty rows

                formData['product_id[]'].push(pid);
                formData['unit_price[]'].push(price || '0');
                formData['qty[]'].push(qty);
                formData['tax_rate[]'].push(taxR || '0');
                formData['tax_amt[]'].push(taxA || '0');
                formData['total_amt[]'].push(total || '0');
            });

            if (formData['product_id[]'].length === 0) {
                $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Purchase Bill');
                Swal.fire('Error', 'Please add at least one valid product row', 'error');
                return;
            }

            $.ajax({
                url: 'ajax/purchase_action.php',
                type: 'POST',
                data: formData,
                traditional: true,
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        Swal.fire({
                            title: 'Success',
                            text: res.message,
                            icon: 'success',
                            showCancelButton: true,
                            confirmButtonText: '<i class="fas fa-print"></i> Print Barcodes',
                            cancelButtonText: 'Go to Purchases',
                            confirmButtonColor: '#b5895a',
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.open('print_barcode.php?purchase_id=' + res.purchase_id, '_blank');
                            }
                            window.location.href = 'purchases.php';
                        });
                    } else {
                        Swal.fire('Error', res.message, 'error');
                        $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Purchase Bill');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Server error occurred', 'error');
                    $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Purchase Bill');
                }
            });
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>