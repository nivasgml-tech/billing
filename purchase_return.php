<?php
// purchase_return.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

// Fetch Returned Purchases from purchase_returns history table
$stmtReturned = $pdo->query("
    SELECT pr.id as return_id, pr.return_date, pr.return_reason, pr.total_refund, pr.is_full_return,
           p.bill_no, p.grand_total, p.payment_status,
           s.supplier_name,
           u.full_name as returned_by
    FROM purchase_returns pr
    JOIN purchases p ON pr.purchase_id = p.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    LEFT JOIN users u ON pr.returned_by = u.id
    ORDER BY pr.id DESC
");
$returnedPurchases = $stmtReturned->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood"><i class="fas fa-undo-alt me-2"></i>Purchase Returns</h2>
    <button class="btn btn-wood" data-bs-toggle="modal" data-bs-target="#returnModal">
        <i class="fas fa-plus-circle me-1"></i> Process New Return
    </button>
</div>

<?= $msg ?? '' ?>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <h5 class="card-title border-bottom pb-2">Return History</h5>
        <table class="table table-striped table-hover datatable w-100 table-sm">
            <thead class="bg-wood text-white">
                <tr>
                    <th>Return Date</th>
                    <th>Bill No</th>
                    <th>Supplier</th>
                    <th>Reason</th>
                    <th>Refund (₹)</th>
                    <th>Type</th>
                    <th>By</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($returnedPurchases as $r): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($r['return_date'])) ?></td>
                        <td><strong><?= htmlspecialchars($r['bill_no']) ?></strong></td>
                        <td><?= htmlspecialchars($r['supplier_name']) ?></td>
                        <td><?= htmlspecialchars($r['return_reason']) ?></td>
                        <td class="fw-bold text-end"><?= number_format($r['total_refund'], 2) ?></td>
                        <td>
                            <?php if ($r['is_full_return']): ?>
                                <span class="badge bg-danger">Full Return</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Partial</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($r['returned_by'] ?? 'System') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Return Modal -->
<div class="modal fade" id="returnModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-undo-alt me-2"></i>Process Purchase Return</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <!-- Step 1: Bill Selection -->
                <div id="prStep1">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Purchase Bill to Return <span class="text-danger">*</span></label>
                        <select class="form-select w-100" id="pr_purchase_id">
                            <option value="">-- Search Bill No or Supplier --</option>
                        </select>
                    </div>
                    <div class="d-grid">
                        <button type="button" class="btn btn-outline-danger" id="prLoadItemsBtn">
                            <i class="fas fa-list me-1"></i> Load Products from Bill
                        </button>
                    </div>
                </div>

                <!-- Step 2: Product Checklist -->
                <div id="prStep2" style="display:none;">
                    <div class="alert alert-info small py-2 mb-2" id="prBillInfo"></div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="text-danger"><i class="fas fa-boxes me-1"></i>Select Products to Return</strong>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="prBackBtn">
                            <i class="fas fa-arrow-left me-1"></i> Change Bill
                        </button>
                    </div>
                    <div class="table-responsive mb-3" style="max-height:320px; overflow-y:auto;">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light text-center">
                                <tr>
                                    <th width="5%"><input type="checkbox" id="prSelectAll" title="Select All"></th>
                                    <th width="35%">Product</th>
                                    <th width="15%" class="text-center">Orig Qty</th>
                                    <th width="20%" class="text-center">Return Qty</th>
                                    <th width="15%" class="text-end">Unit Price</th>
                                    <th width="15%" class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody id="prItemsBody"></tbody>
                        </table>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason for Return <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="pr_return_reason" rows="2" placeholder="Enter return reason..." required></textarea>
                    </div>
                    <div class="alert alert-warning small py-2">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Selected products' stock will be deducted and a refund (Payment In from Supplier) voucher will be created automatically.
                    </div>
                    <div class="d-grid">
                        <button type="button" class="btn btn-danger" id="prConfirmBtn">
                            <i class="fas fa-undo-alt me-1"></i> Confirm Return
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        let prPurchaseId = null;
        let prItems = [];

        // Init Select2
        $('#returnModal').on('shown.bs.modal', function() {
            $('#pr_purchase_id').select2({
                theme: 'bootstrap-5',
                width: '100%',
                dropdownParent: $('#returnModal'),
                placeholder: '-- Search Bill No or Supplier --',
                allowClear: true,
                ajax: {
                    url: 'ajax/purchase_action.php',
                    type: 'POST',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) { return { action: 'search_purchases', q: params.term }; },
                    processResults: function(data) { return { results: data.results }; },
                    cache: true
                }
            });
        });

        // Reset on close
        $('#returnModal').on('hidden.bs.modal', function() {
            if ($('#pr_purchase_id').data('select2')) $('#pr_purchase_id').select2('destroy');
            $('#prStep1').show();
            $('#prStep2').hide();
            $('#prItemsBody').html('');
            $('#pr_return_reason').val('');
            prPurchaseId = null; prItems = [];
        });

        // Load items
        $('#prLoadItemsBtn').click(function() {
            prPurchaseId = $('#pr_purchase_id').val();
            if (!prPurchaseId) { Swal.fire('Warning', 'Please select a purchase bill first.', 'warning'); return; }
            let btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Loading...');
            $.post('ajax/purchase_action.php', { action: 'get_purchase_items', purchase_id: prPurchaseId }, function(res) {
                $(btn).prop('disabled', false).html('<i class="fas fa-list me-1"></i> Load Products from Bill');
                if (!res.success) { Swal.fire('Error', res.message, 'error'); return; }
                prItems = res.items;
                let p = res.purchase;
                $('#prBillInfo').html(
                    `<b>Bill No:</b> ${p.bill_no} &nbsp;|&nbsp; <b>Supplier:</b> ${p.supplier_name} &nbsp;|&nbsp; <b>Date:</b> ${p.purchase_date} &nbsp;|&nbsp; <b>Total:</b> ₹${parseFloat(p.grand_total).toFixed(2)}`
                );
                let rows = '';
                prItems.forEach((item, i) => {
                    const origQty    = Math.floor(item.quantity);
                    const retQty     = Math.floor(item.returned_qty || 0);
                    const returnableQty = Math.floor(item.returnable_qty || (origQty - retQty));
                    const alreadyBadge = retQty > 0
                        ? `<small class="text-muted ms-1">(${retQty} already returned)</small>`
                        : '';
                    rows += `<tr>
                        <td class="text-center"><input type="checkbox" class="pr-chk" data-idx="${i}" checked></td>
                        <td>${item.product_name}${alreadyBadge}</td>
                        <td class="text-center">${origQty}</td>
                        <td class="text-center">
                            <input type="number" class="form-control form-control-sm text-center pr-ret-qty"
                                data-idx="${i}" data-max="${returnableQty}"
                                value="${returnableQty}" min="1" max="${returnableQty}" step="1"
                                style="width:80px;margin:auto;">
                        </td>
                        <td class="text-end">₹${parseFloat(item.unit_price).toFixed(2)}</td>
                        <td class="text-end fw-bold">₹${parseFloat(item.total_amount).toFixed(2)}</td>
                    </tr>`;
                });
                $('#prItemsBody').html(rows);
                $('#prStep1').hide();
                $('#prStep2').show();
            }, 'json');
        });

        // Select all
        $('#prSelectAll').on('change', function() {
            $('.pr-chk').prop('checked', $(this).is(':checked'));
        });

        // Back
        $('#prBackBtn').click(function() {
            $('#prStep2').hide();
            $('#prStep1').show();
        });

        // Confirm Return
        $('#prConfirmBtn').click(function() {
            let reason = $('#pr_return_reason').val().trim();
            if (!reason) { Swal.fire('Warning', 'Please enter a return reason.', 'warning'); return; }

            let selectedItemIds = [], selectedQtys = [];
            $('.pr-chk:checked').each(function() {
                let idx = $(this).data('idx');
                let qty = parseInt($(`.pr-ret-qty[data-idx="${idx}"]`).val()) || 0;
                let max = parseInt($(`.pr-ret-qty[data-idx="${idx}"]`).data('max'));
                if (qty <= 0) return;
                if (qty > max) qty = max;
                selectedItemIds.push(prItems[idx].item_id);
                selectedQtys.push(qty);
            });

            if (selectedItemIds.length === 0) {
                Swal.fire('Warning', 'Please select at least one product with a valid return quantity.', 'warning');
                return;
            }

            Swal.fire({
                title: 'Confirm Return?',
                text: `${selectedItemIds.length} product(s) will be returned to supplier and stock deducted.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, Process Return'
            }).then(result => {
                if (!result.isConfirmed) return;
                let btn = $('#prConfirmBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Processing...');
                $.ajax({
                    url: 'ajax/purchase_action.php',
                    type: 'POST',
                    dataType: 'json',
                    traditional: true,
                    data: {
                        action:        'process_partial_purchase_return',
                        purchase_id:   prPurchaseId,
                        return_reason: reason,
                        'item_ids[]':  selectedItemIds,
                        'return_qtys[]': selectedQtys
                    },
                    success: function(res) {
                        $(btn).prop('disabled', false).html('<i class="fas fa-undo-alt me-1"></i> Confirm Return');
                        if (res.success) {
                            Swal.fire('Success', res.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    },
                    error: function() {
                        $(btn).prop('disabled', false).html('<i class="fas fa-undo-alt me-1"></i> Confirm Return');
                        Swal.fire('Error', 'Server error. Please try again.', 'error');
                    }
                });
            });
        });
    });
</script>

<!-- View Detail Modal -->
<div class="modal fade" id="viewDetailModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Purchase Return Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-6">
                        <p class="mb-1"><strong>Bill No:</strong> <span id="v_bill_no"></span></p>
                        <p class="mb-1"><strong>Date:</strong> <span id="v_date"></span></p>
                        <p class="mb-1"><strong>Supplier:</strong> <span id="v_supplier"></span></p>
                    </div>
                    <div class="col-6 text-end">
                        <p class="mb-1"><strong>Branch:</strong> <span id="v_branch"></span></p>
                        <p class="mb-1"><strong>Total Amount:</strong> <span id="v_total"></span></p>
                        <p class="mb-1"><strong>Status:</strong> <span id="v_status"></span></p>
                    </div>
                </div>
                <table class="table table-bordered table-sm">
                    <thead class="bg-light">
                        <tr>
                            <th>Product</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Price</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody id="v_items"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    function viewPurchase(id) {
        $.post('ajax/purchase_action.php', {
            action: 'get_purchase_details',
            id: id
        }, function(res) {
            if (res.success) {
                let p = res.data;
                $('#v_bill_no').text(p.bill_no);
                $('#v_date').text(p.purchase_date);
                $('#v_supplier').text(p.supplier_name);
                $('#v_branch').text(p.branch_name);
                $('#v_total').text('₹' + p.grand_total);
                $('#v_status').html(`<span class="badge bg-danger">${p.payment_status}</span>`);
                let itemsHtml = '';
                p.items.forEach(item => {
                    itemsHtml += `<tr>
                        <td>${item.product_name}</td>
                        <td class="text-center">${item.quantity}</td>
                        <td class="text-end">${item.unit_price}</td>
                        <td class="text-end">${item.total_amount}</td>
                    </tr>`;
                });
                $('#v_items').html(itemsHtml);
                $('#viewDetailModal').modal('show');
            } else {
                alert(res.message);
            }
        }, 'json');
    }
</script>

<?php require_once 'includes/footer.php'; ?>