<?php
// sales_return.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

// Fetch Returned Sales from sales_returns history table (rich data)
$stmtReturned = $pdo->query("
    SELECT sr.id as return_id, sr.return_date, sr.return_reason, sr.total_refund, sr.is_full_return,
           s.invoice_no, s.grand_total, s.payment_status,
           c.customer_name,
           u.full_name as returned_by
    FROM sales_returns sr
    JOIN sales s ON sr.sale_id = s.id
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN users u ON sr.returned_by = u.id
    ORDER BY sr.id DESC
");
$returnedSales = $stmtReturned->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood"><i class="fas fa-undo me-2"></i>Sales Returns</h2>
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
                    <th>Invoice No</th>
                    <th>Customer</th>
                    <th>Reason</th>
                    <th>Refund (₹)</th>
                    <th>Type</th>
                    <th>By</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($returnedSales as $r): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($r['return_date'])) ?></td>
                        <td><strong><?= htmlspecialchars($r['invoice_no']) ?></strong></td>
                        <td><?= htmlspecialchars($r['customer_name'] ?? 'Walk-in') ?></td>
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
                <h5 class="modal-title"><i class="fas fa-undo me-2"></i>Process Sale Return</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <!-- Step 1: Invoice Selection -->
                <div id="srStep1">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Invoice to Return <span class="text-danger">*</span></label>
                        <select class="form-select select2-invoice w-100" id="sr_sale_id">
                            <option value="">-- Search Invoice No or Customer --</option>
                        </select>
                    </div>
                    <div class="d-grid">
                        <button type="button" class="btn btn-outline-danger" id="srLoadItemsBtn">
                            <i class="fas fa-list me-1"></i> Load Products from Invoice
                        </button>
                    </div>
                </div>

                <!-- Step 2: Product Checklist (hidden until invoice loaded) -->
                <div id="srStep2" style="display:none;">
                    <div class="alert alert-info small py-2 mb-2" id="srInvoiceInfo"></div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="text-danger"><i class="fas fa-boxes me-1"></i>Select Products to Return</strong>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="srBackBtn">
                            <i class="fas fa-arrow-left me-1"></i> Change Invoice
                        </button>
                    </div>
                    <div class="table-responsive mb-3" style="max-height:320px; overflow-y:auto;">
                        <table class="table table-bordered table-sm">
                            <thead class="table-light text-center">
                                <tr>
                                    <th width="5%"><input type="checkbox" id="srSelectAll" title="Select All"></th>
                                    <th width="35%">Product</th>
                                    <th width="15%" class="text-center">Orig Qty</th>
                                    <th width="20%" class="text-center">Return Qty</th>
                                    <th width="15%" class="text-end">Unit Price</th>
                                    <th width="15%" class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody id="srItemsBody"></tbody>
                        </table>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason for Return <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="sr_return_reason" rows="2" placeholder="Enter return reason..." required></textarea>
                    </div>
                    <div class="alert alert-warning small py-2">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Selected products' stock will be restored. A refund (Payment Out) voucher will be created automatically.
                    </div>
                    <div class="d-grid">
                        <button type="button" class="btn btn-danger" id="srConfirmBtn">
                            <i class="fas fa-undo me-1"></i> Confirm Return
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        let srSaleId = null;
        let srItems  = [];

        // Init Select2 for invoice search on modal show
        $('#returnModal').on('shown.bs.modal', function() {
            $('#sr_sale_id').select2({
                theme: 'bootstrap-5',
                width: '100%',
                dropdownParent: $('#returnModal'),
                placeholder: '-- Search Invoice No or Customer --',
                allowClear: true,
                ajax: {
                    url: 'ajax/pos_action.php',
                    type: 'POST',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) { return { action: 'search_invoices', q: params.term }; },
                    processResults: function(data) { return { results: data.results }; },
                    cache: true
                }
            });
        });

        // Destroy on hide & reset
        $('#returnModal').on('hidden.bs.modal', function() {
            if ($('#sr_sale_id').data('select2')) $('#sr_sale_id').select2('destroy');
            $('#srStep1').show();
            $('#srStep2').hide();
            $('#srItemsBody').html('');
            $('#sr_return_reason').val('');
            srSaleId = null; srItems = [];
        });

        // Load items
        $('#srLoadItemsBtn').click(function() {
            srSaleId = $('#sr_sale_id').val();
            if (!srSaleId) { Swal.fire('Warning', 'Please select an invoice first.', 'warning'); return; }
            let btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Loading...');
            $.post('ajax/pos_action.php', { action: 'get_sale_items', sale_id: srSaleId }, function(res) {
                $(btn).prop('disabled', false).html('<i class="fas fa-list me-1"></i> Load Products from Invoice');
                if (!res.success) { Swal.fire('Error', res.message, 'error'); return; }
                srItems = res.items;
                let s = res.sale;
                $('#srInvoiceInfo').html(
                    `<b>Invoice:</b> ${s.invoice_no} &nbsp;|&nbsp; <b>Customer:</b> ${s.customer_name || 'Walk-in'} &nbsp;|&nbsp; <b>Date:</b> ${s.sale_date} &nbsp;|&nbsp; <b>Total:</b> ₹${parseFloat(s.grand_total).toFixed(2)}`
                );
                let rows = '';
                srItems.forEach((item, i) => {
                    const origQty    = Math.floor(item.quantity);
                    const retQty     = Math.floor(item.returned_qty || 0);
                    const returnableQty = Math.floor(item.returnable_qty || (origQty - retQty));
                    const alreadyBadge = retQty > 0
                        ? `<small class="text-muted ms-1">(${retQty} already returned)</small>`
                        : '';
                    rows += `<tr>
                        <td class="text-center"><input type="checkbox" class="sr-chk" data-idx="${i}" checked></td>
                        <td>${item.product_name}${alreadyBadge}</td>
                        <td class="text-center">${origQty}</td>
                        <td class="text-center">
                            <input type="number" class="form-control form-control-sm text-center sr-ret-qty"
                                data-idx="${i}" data-max="${returnableQty}"
                                value="${returnableQty}" min="1" max="${returnableQty}" step="1"
                                style="width:80px;margin:auto;">
                        </td>
                        <td class="text-end">₹${parseFloat(item.unit_price).toFixed(2)}</td>
                        <td class="text-end fw-bold">₹${parseFloat(item.total_amount).toFixed(2)}</td>
                    </tr>`;
                });
                $('#srItemsBody').html(rows);
                $('#srStep1').hide();
                $('#srStep2').show();
            }, 'json');
        });

        // Select all
        $('#srSelectAll').on('change', function() {
            $('.sr-chk').prop('checked', $(this).is(':checked'));
        });

        // Back button
        $('#srBackBtn').click(function() {
            $('#srStep2').hide();
            $('#srStep1').show();
        });

        // Confirm Return
        $('#srConfirmBtn').click(function() {
            let reason = $('#sr_return_reason').val().trim();
            if (!reason) { Swal.fire('Warning', 'Please enter a return reason.', 'warning'); return; }

            let selectedItemIds = [], selectedQtys = [];
            $('.sr-chk:checked').each(function() {
                let idx = $(this).data('idx');
                let qty = parseInt($(`.sr-ret-qty[data-idx="${idx}"]`).val()) || 0;
                let max = parseInt($(`.sr-ret-qty[data-idx="${idx}"]`).data('max'));
                if (qty <= 0) { return; }
                if (qty > max) { qty = max; }
                selectedItemIds.push(srItems[idx].item_id);
                selectedQtys.push(qty);
            });

            if (selectedItemIds.length === 0) {
                Swal.fire('Warning', 'Please select at least one product with a valid return quantity.', 'warning');
                return;
            }

            Swal.fire({
                title: 'Confirm Return?',
                text: `${selectedItemIds.length} product(s) will be returned and stock restored.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, Process Return'
            }).then(result => {
                if (!result.isConfirmed) return;
                let btn = $('#srConfirmBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Processing...');
                $.ajax({
                    url: 'ajax/pos_action.php',
                    type: 'POST',
                    dataType: 'json',
                    traditional: true,
                    data: {
                        action:        'process_partial_sale_return',
                        sale_id:       srSaleId,
                        return_reason: reason,
                        'item_ids[]':  selectedItemIds,
                        'return_qtys[]': selectedQtys
                    },
                    success: function(res) {
                        $(btn).prop('disabled', false).html('<i class="fas fa-undo me-1"></i> Confirm Return');
                        if (res.success) {
                            Swal.fire('Success', res.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    },
                    error: function() {
                        $(btn).prop('disabled', false).html('<i class="fas fa-undo me-1"></i> Confirm Return');
                        Swal.fire('Error', 'Server error. Please try again.', 'error');
                    }
                });
            });
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>
