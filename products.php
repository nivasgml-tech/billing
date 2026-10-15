<?php
// products.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

// Fetch Categories and Units
$categories = $pdo->query("SELECT * FROM categories WHERE status=1")->fetchAll();
$units = $pdo->query("SELECT * FROM units WHERE status=1")->fetchAll();

// Fetch Products
$stmt = $pdo->query("
    SELECT p.*, c.category_name, u.unit_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    LEFT JOIN units u ON p.unit_id = u.id 
    ORDER BY p.id DESC
");
$products = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h2 class="text-wood"><i class="fas fa-boxes me-2"></i>Product Management</h2>
    <button class="btn btn-wood" data-bs-toggle="modal" data-bs-target="#productModal" onclick="resetForm()">
        <i class="fas fa-plus-circle me-1"></i> Add Product
    </button>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <table class="table table-striped table-hover datatable w-100">
            <thead class="bg-wood text-white">
                <tr>
                    <th>Image</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Price (₹)</th>
                    <th>Stock Alert</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td>
                            <?php if ($p['image']): ?>
                                <img src="<?= htmlspecialchars($p['image']) ?>" width="50" height="50" class="rounded object-fit-cover" alt="img">
                            <?php else: ?>
                                <img src="uploads/products/default.png" width="50" height="50" class="rounded object-fit-cover" alt="img">
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($p['product_name']) ?></strong><br>
                            <small class="text-muted">Barcode: <?= htmlspecialchars($p['barcode'] ?? 'N/A') ?></small>
                        </td>
                        <td><?= htmlspecialchars($p['category_name']) ?></td>
                        <td>
                            <small class="text-muted d-block">Pur: ₹<?= number_format($p['purchase_price'], 2) ?></small>
                            <strong class="text-success">Sale: ₹<?= number_format($p['sale_price'], 2) ?></strong>
                        </td>
                        <td><?= $p['min_stock_alert'] ?> <?= htmlspecialchars($p['unit_name']) ?></td>
                        <td>
                            <?php if ($p['status'] == 1): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-info" onclick='viewProduct(<?= json_encode($p) ?>)' title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-outline-primary" onclick='editProduct(<?= json_encode($p) ?>)' title="Edit Product">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="productModal">
    <div class="modal-dialog modal-lg">
        <form id="productForm" class="modal-content" enctype="multipart/form-data">
            <div class="modal-header bg-wood text-white">
                <h5 class="modal-title" id="modalTitle">Add Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="product_id" value="">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-wood fw-bold">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="product_name" id="product_name" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-wood fw-bold">Barcode</label>
                        <input type="text" class="form-control" name="barcode" id="barcode" placeholder="Leave empty to auto-generate">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-wood fw-bold">Category <span class="text-danger">*</span></label>
                        <select class="form-select select2 w-100" name="category_id" id="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-wood fw-bold">Unit <span class="text-danger">*</span></label>
                        <select class="form-select select2 w-100" name="unit_id" id="unit_id" required>
                            <option value="">Select Unit</option>
                            <?php foreach ($units as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['unit_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-wood fw-bold">Quantity Type</label>
                        <input type="text" class="form-control" name="quantity_type" id="quantity_type" placeholder="e.g., Single, Set of 4">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-wood fw-bold">HSN Code</label>
                        <input type="text" class="form-control" name="hsn_code" id="hsn_code">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-wood fw-bold">GST Rate (%)</label>
                        <input type="number" step="0.01" class="form-control" name="gst_rate" id="gst_rate" value="0.00">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-wood fw-bold">Purchase Price <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" name="purchase_price" id="purchase_price" required value="0.00">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label text-wood fw-bold">Sale Price <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" name="sale_price" id="sale_price" required value="0.00">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-wood fw-bold">Min Stock Alert</label>
                        <input type="number" class="form-control" name="min_stock_alert" id="min_stock_alert" value="0">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-wood fw-bold">Status</label>
                        <select class="form-select" name="status" id="status">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label text-wood fw-bold">Product Image</label>
                        <input type="file" class="form-control" name="image" id="image" accept="image/*">
                        <small class="text-muted" id="current_image_text"></small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-wood" id="saveBtn"><i class="fas fa-save me-1"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewProductModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Product Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4 text-center mb-3">
                        <img id="v_image" src="" class="img-fluid rounded shadow-sm border" style="max-height: 250px; width: 100%; object-fit: contain;" alt="Product Image">
                    </div>
                    <div class="col-md-8">
                        <table class="table table-bordered table-sm">
                            <tr>
                                <th class="bg-light w-25">Product Name</th>
                                <td id="v_name" class="fw-bold"></td>
                            </tr>
                            <tr>
                                <th class="bg-light">Barcode</th>
                                <td id="v_barcode"></td>
                            </tr>
                            <tr>
                                <th class="bg-light">Category</th>
                                <td id="v_category"></td>
                            </tr>
                            <tr>
                                <th class="bg-light">Quantity Type</th>
                                <td id="v_qty_type"></td>
                            </tr>
                            <tr>
                                <th class="bg-light">HSN Code</th>
                                <td id="v_hsn"></td>
                            </tr>
                            <tr>
                                <th class="bg-light">GST Rate</th>
                                <td id="v_gst"></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 fw-bold text-wood">Pricing Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <td>Purchase Price:</td>
                                <td class="text-end fw-bold" id="v_purchase_price"></td>
                            </tr>
                            <tr>
                                <td>Sale Price:</td>
                                <td class="text-end fw-bold text-success" id="v_sale_price"></td>
                            </tr>
                            <tr>
                                <td>Min Stock Alert:</td>
                                <td class="text-end fw-bold text-danger" id="v_min_stock"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 fw-bold text-wood">Branch Stock Details</h6>
                        <div id="branchStockList" class="small">
                            <!-- Branch stock loaded via AJAX -->
                            <div class="text-center p-2"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    function resetForm() {
        $('#productForm')[0].reset();
        $('#action').val('add');
        $('#product_id').val('');
        $('#modalTitle').text('Add Product');
        $('#category_id').val('').trigger('change');
        $('#unit_id').val('').trigger('change');
        $('#current_image_text').text('');
    }

    function viewProduct(p) {
        // Basic Details
        $('#v_name').text(p.product_name);
        $('#v_barcode').text(p.barcode || 'N/A');
        $('#v_category').text(p.category_name);
        $('#v_qty_type').text(p.quantity_type);
        $('#v_hsn').text(p.hsn_code || 'N/A');
        $('#v_gst').text(p.gst_rate + '%');
        $('#v_purchase_price').text('₹' + parseFloat(p.purchase_price).toLocaleString('en-IN', {
            minimumFractionDigits: 2
        }));
        $('#v_sale_price').text('₹' + parseFloat(p.sale_price).toLocaleString('en-IN', {
            minimumFractionDigits: 2
        }));
        $('#v_min_stock').text(p.min_stock_alert + ' ' + p.unit_name);

        // Image
        let img = p.image ? p.image : 'uploads/products/default.png';
        $('#v_image').attr('src', img);

        // Fetch Branch Stock
        $('#branchStockList').html('<div class="text-center p-2"><i class="fas fa-spinner fa-spin"></i> Loading...</div>');
        $.post('ajax/product_action.php', {
            action: 'get_branch_stock',
            id: p.id
        }, function(res) {
            let html = '<table class="table table-sm table-bordered"><thead><tr class="bg-light"><th>Branch</th><th class="text-center">Stock</th></tr></thead><tbody>';
            if (res.length === 0) {
                html += '<tr><td colspan="2" class="text-center">No branch data found</td></tr>';
            } else {
                res.forEach(b => {
                    let color = b.stock_qty <= p.min_stock_alert ? 'text-danger fw-bold' : '';
                    html += `<tr><td>${b.branch_name}</td><td class="text-center ${color}">${b.stock_qty}</td></tr>`;
                });
            }
            html += '</tbody></table>';
            $('#branchStockList').html(html);
        }, 'json');

        $('#viewProductModal').modal('show');
    }

    function editProduct(p) {
        $('#action').val('edit');
        $('#product_id').val(p.id);
        $('#product_name').val(p.product_name);
        $('#barcode').val(p.barcode);
        $('#category_id').val(p.category_id).trigger('change');
        $('#unit_id').val(p.unit_id).trigger('change');
        $('#quantity_type').val(p.quantity_type);
        $('#hsn_code').val(p.hsn_code);
        $('#gst_rate').val(p.gst_rate);
        $('#purchase_price').val(p.purchase_price);
        $('#sale_price').val(p.sale_price);
        $('#min_stock_alert').val(p.min_stock_alert);
        $('#status').val(p.status);

        if (p.image) {
            $('#current_image_text').html('<a href="' + p.image + '" target="_blank">View Current Image</a>');
        } else {
            $('#current_image_text').text('');
        }

        $('#modalTitle').text('Edit Product');
        $('#productModal').modal('show');
    }

    $(document).ready(function() {
        // Initialize Select2 for Product Modal with correct dropdownParent
        $('#productModal').on('shown.bs.modal', function() {
            $('.select2', this).select2({
                theme: 'bootstrap-5',
                width: '100%',
                dropdownParent: $('#productModal')
            });
        });

        $('#productForm').submit(function(e) {
            e.preventDefault();
            $('#saveBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

            let formData = new FormData(this);

            $.ajax({
                url: 'ajax/product_action.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function(res) {
                    $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Changes');
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: res.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                },
                error: function() {
                    $('#saveBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Changes');
                    Swal.fire('Error', 'Something went wrong', 'error');
                }
            });
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>