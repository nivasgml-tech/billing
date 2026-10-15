<?php
// pos.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

$categories = $pdo->query("SELECT * FROM categories WHERE status=1")->fetchAll();
$all_products = $pdo->query("SELECT * FROM products WHERE status=1 ORDER BY product_name")->fetchAll();

if (hasRole(['Super Admin', 'Staff'])) {
    $customers = $pdo->query("SELECT * FROM customers ORDER BY customer_name")->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE branch_id = ? OR branch_id IS NULL ORDER BY customer_name");
    $stmt->execute([$_SESSION['branch_id']]);
    $customers = $stmt->fetchAll();
}

$edit_id = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
?>

<style>
    body {
        background-color: var(--bg-color) !important;
        background-image: none !important;
        /* Cleaner background for POS */
    }

    .pos-cart-table th {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted);
        border-bottom: 2px solid rgba(0, 0, 0, 0.05) !important;
    }

    .pos-cart-table td {
        vertical-align: middle;
        font-size: 0.95rem;
        font-weight: 500;
        border-bottom: 1px solid rgba(0, 0, 0, 0.03);
    }

    .item-qty-input {
        width: 70px;
        text-align: center;
        border-radius: 8px;
        font-weight: 700;
        background-color: rgba(79, 70, 229, 0.05);
        border: 1px solid rgba(79, 70, 229, 0.2);
        color: var(--primary);
    }

    .product-card {
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(255, 255, 255, 0.8);
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(10px);
    }

    .product-card:hover {
        transform: translateY(-5px) scale(1.02);
        border-color: var(--primary);
        box-shadow: 0 15px 25px -5px rgba(79, 70, 229, 0.15);
    }

    .product-img-wrapper {
        height: 110px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #ffffff;
        border-bottom: 1px solid rgba(0, 0, 0, 0.03);
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
        padding: 10px;
    }

    .product-img-wrapper img {
        max-height: 90px;
        max-width: 100%;
        object-fit: contain;
        filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
        transition: transform 0.3s ease;
    }

    .product-card:hover .product-img-wrapper img {
        transform: scale(1.1);
    }

    .pos-panel {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(20px);
        border-radius: 24px;
        border: 1px solid rgba(255, 255, 255, 0.6);
        box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.08);
    }

    .pos-header {
        background: var(--sidebar-bg);
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        border-top-left-radius: 24px !important;
        border-top-right-radius: 24px !important;
        padding: 15px 25px;
        border-bottom: none;
    }

    /* Custom Scrollbar for POS */
    ::-webkit-scrollbar {
        width: 6px;
    }

    ::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.02);
    }

    ::-webkit-scrollbar-thumb {
        background: rgba(79, 70, 229, 0.3);
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: rgba(79, 70, 229, 0.6);
    }

    /* Fullscreen POS Styles */
    .fullscreen-active #sidebar-wrapper,
    .fullscreen-active .navbar {
        display: none !important;
    }

    .fullscreen-active #page-content-wrapper {
        padding: 0 !important;
        margin: 0 !important;
    }

    .fullscreen-active .container-fluid {
        max-width: 100% !important;
        padding: 5px !important;
    }

    /* Input styles for cart to prevent text cutoff */
    .pos-cart-table input[type="number"]::-webkit-outer-spin-button,
    .pos-cart-table input[type="number"]::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .pos-cart-table input[type="number"] {
        -moz-appearance: textfield;
        appearance: textfield;
    }

    .pos-input {
        border-radius: 8px;
        padding: 4px 2px !important;
        text-align: center !important;
        min-width: 50px;
    }

    .disc-group {
        display: flex;
        gap: 3px;
        align-items: center;
        justify-content: center;
    }
    .disc-group input {
        width: 58px !important;
        min-width: 48px !important;
        font-size: 0.78rem !important;
        padding: 3px 2px !important;
    }
    .disc-sep {
        font-size: 0.7rem;
        color: #aaa;
        font-weight: bold;
        line-height: 1;
    }

    
/* Mobile-friendly camera barcode scanner */
#cameraScannerPanel {
    width: 100%;
    overflow: hidden;
}

#cameraScannerPanel .card-body {
    padding: 8px;
}

#cameraReader {
    width: 100% !important;
    min-height: 240px;
    overflow: hidden;
    background: #111;
    border-radius: 10px;
}

#cameraReader video {
    display: block !important;
    width: 100% !important;
    max-width: 100% !important;
    max-height: 65vh !important;
    height: auto !important;
    object-fit: contain !important;
    margin: 0 auto !important;
}

@media (max-width: 576px) {
    #cameraScannerPanel .card-header {
        padding: 10px;
    }

    #cameraReader {
        min-height: 280px;
    }

    #cameraReader video {
        max-height: 65vh !important;
    }
}


/* Full-screen POS barcode scanner */
#cameraScannerPanel {
    position: fixed;
    inset: 0;
    z-index: 99999;
    width: 100%;
    height: 100%;
    height: 100dvh;
    overflow: hidden;
    background: #000;
    color: #fff;
}

#cameraReader {
    position: absolute;
    inset: 0;
    width: 100% !important;
    height: 100% !important;
    background: #000;
    overflow: hidden;
}

#cameraReader > div {
    width: 100% !important;
    height: 100% !important;
}

#cameraReader video {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
}

/* Scanner header */
.camera-topbar {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    z-index: 20;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: max(16px, env(safe-area-inset-top)) 16px 16px;
    background: linear-gradient(#000c, transparent);
}

.camera-topbar strong {
    font-size: 18px;
}

.camera-subtitle {
    margin-top: 4px;
    font-size: 12px;
    color: #ddd;
}

.camera-close-btn {
    flex-shrink: 0;
    padding: 10px 14px;
    border: 1px solid #fff8;
    border-radius: 8px;
    background: #252525;
    color: #fff;
    font-size: 14px;
}

/* Wide barcode scanning guide */
.camera-scan-guide {
    position: absolute;
    z-index: 15;
    top: 50%;
    left: 5%;
    width: 90%;
height: 200px;
    transform: translateY(-50%);
    border: 2px solid #ffffffaa;
    border-radius: 10px;
    pointer-events: none;
    overflow: hidden;
    box-shadow: 0 0 0 9999px #0002;
}

/* Red line moves from left to right */
.camera-laser-line {
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    width: 3px;
    background: #ff2638;
    box-shadow: 0 0 10px 3px #ff2638aa;
    animation: cameraLaserSweep 1.5s ease-in-out infinite alternate;
}

@keyframes cameraLaserSweep {
    from { left: 2%; }
    to   { left: calc(98% - 3px); }
}

/* Scanner footer */
.camera-bottombar {
    position: absolute;
    z-index: 20;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 22px 16px max(24px, env(safe-area-inset-bottom));
    background: linear-gradient(transparent, #000d);
    text-align: center;
    font-size: 13px;
    color: #fff;
}

@media (max-width: 576px) {
    .camera-scan-guide {
        height: 135px;
    }
}

</style>

<div class="row mt-2 g-3">
    <!-- Left Panel: Cart & Billing -->
    <div class="col-lg-7 col-md-12">
        <div class="card pos-panel h-100 mb-0">
            <div class="pos-header text-dark d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold tracking-wide"><i class="fas fa-shopping-cart me-2 text-primary"></i><?= $edit_id ? 'Edit Invoice' : 'Point of Sale' ?></h5>
                <?php if ($edit_id): ?>
                    <span class="badge bg-warning text-dark"><i class="fas fa-edit me-1"></i> EDIT MODE</span>
                <?php endif; ?>
                <div class="d-flex align-items-center">
                    <button class="btn btn-sm btn-outline-secondary me-3" id="fullScreenBtn" onclick="toggleFullScreen()" title="Full Screen Mode">
                        <i class="fas fa-expand"></i>
                    </button>
                    <div>
                        <div class="form-check form-switch d-inline-block text-dark me-2">
                            <input class="form-check-input" type="checkbox" id="gstToggle" checked style="cursor: pointer;">
                            <label class="form-check-label fw-bold" for="gstToggle" style="cursor: pointer;">GST Bill</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body p-3 d-flex flex-column">

                <!-- Customer Selection -->
                <div class="row mb-2">
                    <div class="col-sm-8">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <select class="form-select select2" id="customer_id">
                                <option value="">Walk-in Customer</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['customer_name']) ?> (<?= htmlspecialchars($c['phone']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-outline-secondary" type="button" title="Add Customer" data-bs-toggle="modal" data-bs-target="#posCustomerModal" onclick="$('#posCustomerForm')[0].reset();"><i class="fas fa-plus"></i></button>
                        </div>
                        <script>
                            $(document).ready(function() {
                                $('#customer_id').select2({ theme: 'bootstrap-5', width: 'resolve' });
                            });
                        </script>
                    </div>
                    <div class="col-sm-4">
                        <select class="form-select form-select-sm" id="sale_type">
                            <option value="Sale Invoice">Sale Invoice</option>
                            <option value="Estimate">Estimate</option>
                            <option value="Sale Order">Sale Order</option>
                        </select>
                    </div>
                </div>





                <!-- Cart Table -->
                <div class="table-responsive flex-grow-1" style="height: 350px; overflow-y: auto;">
                    <table class="table table-bordered pos-cart-table table-sm" id="cartTable">
                        <thead class="bg-light text-center">
                            <tr>
                                <th width="28%">Item</th>
                                <th width="13%">Price</th>
                                <th width="10%">Qty</th>
                                <th width="20%" class="text-center" style="font-size:0.78rem;">Discount <span style="color:#b5895a;">₹&nbsp;/&nbsp;%</span></th>
                                <th width="8%">Tax %</th>
                                <th width="13%">Total</th>
                                <th width="5%"><i class="fas fa-trash"></i></th>
                            </tr>
                        </thead>
                        <tbody id="cartBody">
                            <!-- Items go here -->
                        </tbody>
                    </table>
                </div>

                <!-- Summary & Payment -->
                <div class="bg-light p-2 rounded border mt-2">
                    <div class="row">
                        <div class="col-md-6 border-end">
                            <div class="row mb-1">
                                <div class="col-6 text-muted">Sub Total:</div>
                                <div class="col-6 text-end fw-bold" id="lbl_subtotal">0.00</div>
                            </div>
                            <div class="row mb-1">
                                <div class="col-6 text-muted">Discount:</div>
                                <div class="col-6 text-end fw-bold text-danger" id="lbl_discount">0.00</div>
                            </div>
                            <div class="row mb-1">
                                <div class="col-6 text-muted">Tax Amount:</div>
                                <div class="col-6 text-end fw-bold text-primary" id="lbl_tax">0.00</div>
                            </div>
                            <div class="row">
                                <div class="col-6 text-muted">Round Off:</div>
                                <div class="col-6 text-end fw-bold" id="lbl_roundoff">0.00</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h2 class="text-end text-success mb-2 font-weight-bold">₹<span id="lbl_grandtotal">0.00</span></h2>

                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text">Pay Mode</span>
                                <select class="form-select" id="payment_mode">
                                    <option value="Cash">Cash</option>
                                    <option value="UPI">UPI</option>
                                    <option value="Card">Card</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                </select>
                            </div>

                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text">Paid Amt</span>
                                <input type="number" class="form-control text-end fs-6 fw-bold text-success" id="paid_amount" value="0.00">
                            </div>



                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="row mt-2">
                    <div class="col-6 d-grid">
                        <button class="btn btn-danger" id="cancelSaleBtn"><i class="fas fa-times me-1"></i> Cancel</button>
                    </div>
                    <div class="col-6 d-grid">
                        <button class="btn btn-success fw-bold p-2" id="saveSaleBtn"><i class="fas fa-check-circle me-1"></i> Pay & Print</button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Right Panel: Product Grid -->
    <div class="col-lg-5 col-md-12">
        <div class="card pos-panel h-100 mb-0">
            <div class="pos-header bg-white d-flex align-items-center" style="background: rgba(255,255,255,0.9); border-bottom: 1px solid rgba(0,0,0,0.05);">
                <i class="fas fa-boxes text-primary me-2 fs-5"></i>
                <select class="form-select form-select-sm border-0 bg-transparent fw-bold text-dark flex-grow-1 ms-2 shadow-none" id="catFilter" style="font-size: 1.05rem;">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="card-body p-3" style="background: rgba(248, 250, 252, 0.5);">
               
<div class="mb-3">
    <div class="input-group shadow-sm rounded-3 overflow-hidden">
        <span class="input-group-text bg-white border-0 text-wood">
            <i class="fas fa-barcode"></i>
        </span>

        <input
            type="text"
            class="form-control border-0"
            id="productSearch"
            placeholder="Scan Barcode or Filter Grid..."
            autocomplete="off"
        >

        <button
            class="btn btn-outline-primary"
            type="button"
            id="openCameraScanner"
            title="Scan barcode using camera"
        >
            <i class="fas fa-camera"></i>
        </button>
    </div>
</div>


<!-- Full-screen Camera Scanner -->
<div id="cameraScannerPanel" style="display:none;">

    <div id="cameraReader"></div>

    <div class="camera-topbar">
        <div>
            <strong>Barcode Scanner</strong>
            <div class="camera-subtitle">Barcode-ஐ frame-க்குள் வைக்கவும்</div>
        </div>

        <button type="button" id="closeCameraScanner"
                class="camera-close-btn">
            ✕ Close
        </button>
    </div>

    <div class="camera-scan-guide">
        <div class="camera-laser-line"></div>
    </div>

    <div class="camera-bottombar">
        <span>Barcode கண்டறியப்பட்டதும் தானாக scan ஆகும்</span>
    </div>

</div>


                <div class="row g-3" id="productGrid" style="height: 600px; overflow-y: auto; overflow-x: hidden; padding-right: 5px;">
                    <!-- Products loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Customer Modal for POS -->
<div class="modal fade" id="posCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="posCustomerForm" class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-user-plus me-2"></i>Quick Add Customer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <input type="hidden" name="action" value="add">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Customer Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="customer_name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Phone Number</label>
                        <input type="text" class="form-control" name="phone" placeholder="+91 XXXXX XXXXX">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Email Address</label>
                        <input type="email" class="form-control" name="email" placeholder="customer@email.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">GSTIN Number</label>
                        <input type="text" class="form-control" name="gst_number" placeholder="22AAAAA0000A1Z5">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Complete Address</label>
                        <textarea class="form-control" name="address" rows="2" placeholder="Flat No, Street, City, State, Pincode"></textarea>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Pincode</label>
                        <input type="text" class="form-control" name="pincode" placeholder="6-digit Pincode">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-white">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-5 fw-bold" id="savePosCustomerBtn"><i class="fas fa-save me-2"></i>Save Customer</button>
            </div>
        </form>
    </div>
</div>

<script>
    let cart = [];
    let isGstBill = true;

    // Load products Grid
    function loadProducts(categoryId = '', searchQuery = '') {
        $.post('ajax/pos_action.php', {
            action: 'get_products',
            category_id: categoryId,
            search: searchQuery
        }, function(res) {
            let html = '';
            if (res.length === 0) {
                html = '<div class="col-12 text-center text-muted p-4">No products found</div>';
            } else {
                res.forEach(p => {
                    let img = p.image ? p.image : 'uploads/products/default.png';
                        let stockQty = parseFloat(p.stock_qty) || 0;
                    let outOfStock = stockQty <= 0;
                    let grayscale = outOfStock ? 'filter: grayscale(1); opacity: 0.7;' : '';
                    let badge = outOfStock ? '<div class="badge bg-danger position-absolute top-0 end-0 m-1">Out of Stock</div>' : '';

                    html += `
                <div class="col-4 col-sm-3 col-md-4 col-lg-4">
                    <div class="card product-card h-100 text-center shadow-sm position-relative" style="${grayscale}" onclick='addToCart(${JSON.stringify(p)})'>
                        ${badge}
                        <div class="product-img-wrapper">
                            <img src="${img}" alt="${p.product_name}">
                        </div>
                        <div class="card-body p-1 d-flex flex-column justify-content-between">
                            <div class="small fw-bold lh-sm text-truncate" title="${p.product_name}">${p.product_name}</div>
                            <div class="text-success fw-bold mt-1">₹${parseFloat(p.sale_price).toFixed(2)}</div>
                            <div class="text-muted" style="font-size:0.75rem;">Stk: ${stockQty}</div>
                        </div>
                    </div>
                </div>`;
                });
            }
            $('#productGrid').html(html);
        }, 'json');
    }

    // Add to Cart
    function addToCart(product) {
        let existingIndex = cart.findIndex(i => i.id === product.id);
        let stockAvailable = parseFloat(product.stock_qty) || 0;
        
        if (existingIndex > -1) {
            let newQty = cart[existingIndex].qty + 1;
            if (newQty > stockAvailable) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Out of Stock',
                    text: 'Cannot add more. Only ' + stockAvailable + ' units available in stock.',
                    timer: 2000,
                    showConfirmButton: false
                });
                return;
            }
            cart[existingIndex].qty = newQty;
        } else {
            if (stockAvailable <= 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Out of Stock',
                    text: 'This product is currently out of stock.',
                    timer: 2000,
                    showConfirmButton: false
                });
                return;
            }
            cart.push({
                id: product.id,
                name: product.product_name,
                price: parseFloat(product.sale_price),
                qty: 1,
                discount_amount: 0,
                discount_percent: 0,
                gst_rate: parseFloat(product.gst_rate),
                stock_available: stockAvailable // Store for quick check during qty change
            });
        }
        renderCart();
    }

    // Remove from Cart
    function removeFromCart(index) {
        cart.splice(index, 1);
        renderCart();
    }

    // Update Cart Item
    function updateCartItem(index, field, value) {
        let val = parseFloat(value);
        if (isNaN(val) || val < 0) val = 0;

        if (field === 'qty') {
            let stockAvailable = cart[index].stock_available || 0;
            if (val > stockAvailable) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Stock Limit',
                    text: 'Only ' + stockAvailable + ' units available in stock. Resetting to max.',
                    timer: 2000,
                    showConfirmButton: false
                });
                val = stockAvailable;
            }
        }

        cart[index][field] = val;

        // Sync discount fields
        let item = cart[index];
        let itemTotal = item.price * item.qty;

        if (field === 'discount_amount') {
            // Derive percent from amount
            cart[index].discount_percent = (itemTotal > 0) ? parseFloat(((val / itemTotal) * 100).toFixed(4)) : 0;
        } else if (field === 'discount_percent') {
            // Derive amount from percent
            cart[index].discount_amount = parseFloat((itemTotal * (val / 100)).toFixed(2));
        }

        renderCart();
    }

    // Render Cart
    function renderCart() {
        let html = '';
        let subtotal = 0;
        let totalTax = 0;
        let totalDiscount = 0;

        cart.forEach((item, index) => {
            // Ensure defaults
            if (item.discount_amount === undefined) item.discount_amount = 0;
            if (item.discount_percent === undefined) item.discount_percent = 0;

            let itemTotal = item.price * item.qty;

            // Use discount_amount as the source of truth (synced from percent too)
            let itemDiscount = item.discount_amount;

            // Clamp discount to itemTotal
            if (itemDiscount > itemTotal) itemDiscount = itemTotal;

            let taxableValue = itemTotal - itemDiscount;

            let itemTax = 0;
            if (isGstBill) {
                itemTax = taxableValue * (item.gst_rate / 100);
            }

            let rowTotal = taxableValue + itemTax;

            subtotal += itemTotal;
            totalDiscount += itemDiscount;
            totalTax += itemTax;

            let dispAmt  = item.discount_amount  > 0 ? item.discount_amount.toFixed(2)  : '';
            let dispPct  = item.discount_percent > 0 ? item.discount_percent.toFixed(2) : '';

            html += `
        <tr>
            <td class="text-wrap"><strong>${item.name}</strong></td>
            <td><input type="number" class="form-control form-control-sm pos-input mx-auto" value="${item.price}" onchange="updateCartItem(${index}, 'price', this.value)"></td>
            <td><input type="number" class="form-control form-control-sm pos-input item-qty-input mx-auto" value="${item.qty}" min="1" onchange="updateCartItem(${index}, 'qty', this.value)"></td>
            <td>
                <div class="disc-group">
                    <input type="number" class="form-control form-control-sm pos-input"
                        placeholder="₹" value="${dispAmt}" min="0"
                        title="Discount Amount (₹)"
                        onchange="updateCartItem(${index}, 'discount_amount', this.value)">
                    <span class="disc-sep">/</span>
                    <input type="number" class="form-control form-control-sm pos-input"
                        placeholder="%" value="${dispPct}" min="0" max="100"
                        title="Discount Percentage (%)"
                        onchange="updateCartItem(${index}, 'discount_percent', this.value)">
                </div>
            </td>
            <td class="text-center">${isGstBill ? item.gst_rate+'%' : '0%'}</td>
            <td class="text-end fw-bold">${rowTotal.toFixed(2)}</td>
            <td class="text-center"><button class="btn btn-sm btn-danger px-2 py-0" onclick="removeFromCart(${index})"><i class="fas fa-times"></i></button></td>
        </tr>`;
        });

        if (cart.length === 0) {
            html = '<tr><td colspan="7" class="text-center text-muted py-4">Cart is empty</td></tr>';
        }

        $('#cartBody').html(html);

        let grandTotalOrig = subtotal - totalDiscount + totalTax;
        let grandTotalRounded = Math.round(grandTotalOrig);
        let roundOff = grandTotalRounded - grandTotalOrig;

        $('#lbl_subtotal').text(subtotal.toFixed(2));
        $('#lbl_discount').text(totalDiscount.toFixed(2));
        $('#lbl_tax').text(totalTax.toFixed(2));
        $('#lbl_roundoff').text(roundOff.toFixed(2));
        $('#lbl_grandtotal').text(grandTotalRounded.toFixed(2));

        // Auto fill paid amount
        if ($('#payment_mode').val() !== 'Split') {
            $('#paid_amount').val(grandTotalRounded.toFixed(2));
        }
    }

    function toggleFullScreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                alert(`Error attempting to enable full-screen mode: ${err.message} (${err.name})`);
            });
            $('body').addClass('fullscreen-active');
            $('#fullScreenBtn').html('<i class="fas fa-compress"></i>');
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
                $('body').removeClass('fullscreen-active');
                $('#fullScreenBtn').html('<i class="fas fa-expand"></i>');
            }
        }
    }

    // Listen for Escape key or manual exit
    document.addEventListener('fullscreenchange', (event) => {
        if (!document.fullscreenElement) {
            $('body').removeClass('fullscreen-active');
            $('#fullScreenBtn').html('<i class="fas fa-expand"></i>');
        }
    });

    let currentSaleId = <?= $edit_id ?>;

    $(document).ready(function() {


        loadProducts();

        $('#posCustomerForm').submit(function(e) {
            e.preventDefault();
            let btn = $('#savePosCustomerBtn');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...');

            $.ajax({
                url: 'ajax/customer_action.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        let c = res.customer;
                        let phoneDisplay = c.phone ? ' (' + c.phone + ')' : '';
                        let newOption = new Option(c.customer_name + phoneDisplay, c.id, true, true);
                        $('#customer_id').append(newOption).trigger('change');
                        $('#posCustomerModal').modal('hide');
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: res.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Server error occurred', 'error');
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i>Save Customer');
                }
            });
        });

        if (currentSaleId > 0) {
            $.post('ajax/pos_action.php', {
                action: 'get_sale_details',
                sale_id: currentSaleId
            }, function(res) {
                if (res.success) {
                    cart = res.items;
                    $('#customer_id').val(res.sale.customer_id).trigger('change');
                    $('#sale_type').val(res.sale.sale_type);
                    $('#payment_mode').val(res.sale.payment_mode);
                    $('#paid_amount').val(res.sale.paid_amount);
                    $('#gstToggle').prop('checked', res.sale.is_gst_bill == 1);
                    isGstBill = res.sale.is_gst_bill == 1;

                    renderCart();

                    $('#saveSaleBtn').html('<i class="fas fa-save me-1"></i> Update Invoice');
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json');
        }

        $('#catFilter').change(function() {
            loadProducts($(this).val(), $('#productSearch').val());
        });

        $('#productSearch').on('keyup', function() {
            loadProducts($('#catFilter').val(), $(this).val());
        });

        // Barcode Scanner listener on search input
       
        // Shared barcode lookup for hardware and camera scanners
        function processScannedBarcode(barcode) {
            barcode = String(barcode || '').trim();

            if (!barcode) return;

            $.post('ajax/pos_action.php', {
                action: 'scan_barcode',
                barcode: barcode
            }, function(res) {
                if (res.success) {
                    addToCart(res.product);
                    $('#productSearch').val('').trigger('focus');
                } else {
                    Swal.fire('Not Found', 'Product barcode not found', 'warning');
                }
            }, 'json');
        }

        // Hardware barcode scanner: scanner usually sends Enter
        $('#productSearch').off('keypress').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                processScannedBarcode($(this).val());
            }
        });

   

        
        
/* Full-screen, optimized barcode scanner */
let cameraScanner = null;
let cameraScanLocked = false;

$('#openCameraScanner').on('click', async function () {

    if (typeof Html5Qrcode === 'undefined') {
        Swal.fire(
            'Scanner Error',
            'Barcode scanner library could not load. Please check your internet connection.',
            'error'
        );
        return;
    }

    $('#cameraScannerPanel').show();

    if (!cameraScanner) {
        cameraScanner = new Html5Qrcode('cameraReader', {
            formatsToSupport: [
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.UPC_A,
                Html5QrcodeSupportedFormats.UPC_E,
                Html5QrcodeSupportedFormats.CODE_128,
                Html5QrcodeSupportedFormats.CODE_39,
                Html5QrcodeSupportedFormats.ITF,
                Html5QrcodeSupportedFormats.CODABAR
            ],
            verbose: false
        });
    }

    try {
        const cameras = await Html5Qrcode.getCameras();

        if (!cameras || cameras.length === 0) {
            throw new Error('No camera found on this device.');
        }

        const backCamera =
            cameras.find(c => /back|rear|environment/i.test(c.label))
            || cameras[cameras.length - 1];

        cameraScanLocked = false;

        await cameraScanner.start(
            backCamera.id,
            {
                fps: 15,
                disableFlip: true,
                qrbox: function (viewfinderWidth, viewfinderHeight) {
    return {
        width: Math.floor(viewfinderWidth * 0.95),
        height: Math.min(
            220,
            Math.floor(viewfinderHeight * 0.50)
        )
    };
}
            },
            function (decodedText) {

                if (cameraScanLocked) return;

                cameraScanLocked = true;

                cameraScanner.stop()
                    .catch(function (err) {
                        console.warn('Scanner stop:', err);
                    })
                    .finally(function () {
                        $('#cameraScannerPanel').hide();

                        // Use existing POS barcode processing.
                        processScannedBarcode(decodedText);
                    });

            },
            function () {
                // No barcode in this frame; continue scanning.
            }
        );

    } catch (err) {
        console.error('Barcode scanner error:', err);

        $('#cameraScannerPanel').hide();
        cameraScanLocked = false;

        Swal.fire(
            'Camera Error',
            err.message ||
                'Unable to start camera. Check permission and HTTPS.',
            'error'
        );
    }
});


/* Close scanner and return to POS */
$('#closeCameraScanner').on('click', async function () {

    if (cameraScanner && cameraScanner.isScanning) {
        try {
            await cameraScanner.stop();
        } catch (err) {
            console.warn('Scanner stop:', err);
        }
    }

    $('#cameraScannerPanel').hide();
    cameraScanLocked = false;

    $('#productSearch').trigger('focus');
});


        $('#gstToggle').change(function() {
            isGstBill = $(this).is(':checked');
            renderCart();
        });

        $('#sale_type').change(function() {
            // Placeholder for future sale type logic
        });

        $('#payment_mode').change(function() {
            if ($('#sale_type').val() !== 'EMI') {
                renderCart();
            }
        });



        $('#cancelSaleBtn').click(function() {
            if (cart.length > 0) {
                Swal.fire({
                    title: 'Clear Cart?',
                    text: "All items will be removed!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, clear it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        cart = [];
                        renderCart();
                    }
                });
            }
        });

        // Save Sale javascript
$('#saveSaleBtn').click(function() {
    
    if (cart.length === 0) {
        Swal.fire('Empty Cart', 'Please add products to cart', 'warning');
        return;
    }

    let formData = new FormData();
    formData.append('action', 'save_sale');
    formData.append('customer_id', $('#customer_id').val());
    formData.append('sale_type', $('#sale_type').val());
    formData.append('is_gst_bill', isGstBill ? 1 : 0);
    formData.append('payment_mode', $('#payment_mode').val());
    formData.append('paid_amount', $('#paid_amount').val());
    formData.append('sub_total', $('#lbl_subtotal').text());
    formData.append('discount_amount', $('#lbl_discount').text());
    formData.append('tax_amount', $('#lbl_tax').text());
    formData.append('round_off', $('#lbl_roundoff').text());
    formData.append('grand_total', $('#lbl_grandtotal').text());
    formData.append('items', JSON.stringify(cart));
    formData.append('sale_id', currentSaleId);
    formData.append('emi_scheme_id', '');
    formData.append('emi_amount', 0);

    // Open a blank tab now to reduce popup-blocking risk.
    let printWindow = null;

    $('#saveSaleBtn')
        .prop('disabled', true)
        .html('<i class="fas fa-spinner fa-spin"></i> Processing...');

    $.ajax({
        url: 'ajax/pos_action.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',

        success: function(res) {
            if (!res.success) {
                Swal.fire('Error', res.message, 'error');
                $('#saveSaleBtn')
                    .prop('disabled', false)
                    .html('<i class="fas fa-check-circle me-1"></i> Pay & Print');
                return;
            }

            // Editing an existing sale: preserve the original edit workflow.
            if (currentSaleId > 0) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Sale updated successfully. Invoice: ' + res.invoice_no,
                    confirmButtonText: 'OK'
                }).then(function() {
                    window.location.href = 'sales.php';
                });
                return;
            }

            Swal.fire({
                icon: 'success',
                title: 'Sale Saved Successfully!',
                text: 'Invoice: ' + res.invoice_no,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-print"></i> Print',
                cancelButtonText: 'New Sale',
                allowOutsideClick: false
            }).then(function(result) {

                if (result.isConfirmed) {
                    // Open thermal invoice only when Print is selected.
                    const url = 'print_invoice.php?id='
                        + encodeURIComponent(res.sale_id)
                        + '&type=thermal&autoprint=1';

                    const win = window.open(url, '_blank');

                    if (!win) {
                        Swal.fire(
                            'Popup Blocked',
                            'Please allow popups for this site, then open the saved invoice and print it.',
                            'warning'
                        );
                    }
                }

                // Both choices start a new bill.
                cart = [];
                $('#customer_id').val('').trigger('change');
                $('#productSearch').val('');
                $('#sale_type').val('Sale Invoice');
                $('#payment_mode').val('Cash').trigger('change');
                $('#paid_amount').val('0');
                renderCart();

                $('#saveSaleBtn')
                    .prop('disabled', false)
                    .html('<i class="fas fa-check-circle me-1"></i> Pay & Print');

                $('#productSearch').trigger('focus');
            });
        },

        error: function() {
            Swal.fire('Error', 'Failed to communicate with server', 'error');

            $('#saveSaleBtn')
                .prop('disabled', false)
                .html('<i class="fas fa-check-circle me-1"></i> Pay & Print');
        }
    });
});

    });
</script>


<script src="https://unpkg.com/html5-qrcode"></script>


<?php require_once 'includes/footer.php'; ?>