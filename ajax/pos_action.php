<?php
// ajax/pos_action.php
header('Content-Type: application/json');
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!hasRole(['Super Admin', 'Admin', 'Staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
    exit();
}

$branch_id = $_SESSION['branch_id'] ?? 1; // Default to 1 if super admin without specific branch

// Handle typical POST
if (isset($_POST['action'])) {
    $action = sanitize($_POST['action']);

    if ($action === 'get_products') {
        $category_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
        $search = isset($_POST['search']) ? trim($_POST['search']) : '';

        $sql = "SELECT p.*, bs.quantity as stock_qty 
                FROM products p 
                LEFT JOIN branch_stock bs ON p.id = bs.product_id AND bs.branch_id = :branch_id
                WHERE p.status = 1";
        $params = [':branch_id' => $branch_id];

        if ($category_id) {
            $sql .= " AND p.category_id = :cat_id";
            $params[':cat_id'] = $category_id;
        }

        if ($search) {
            $sql .= " AND (p.product_name LIKE :search OR p.barcode LIKE :search)";
            $params[':search'] = "%$search%";
        }

        $sql .= " ORDER BY p.product_name ASC LIMIT 50";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($products);
        exit();
    }

    if ($action === 'scan_barcode') {
        $barcode = trim($_POST['barcode']);
        $stmt = $pdo->prepare("SELECT p.*, bs.quantity as stock_qty 
                FROM products p 
                LEFT JOIN branch_stock bs ON p.id = bs.product_id AND bs.branch_id = ?
                WHERE p.barcode = ? AND p.status = 1");
        $stmt->execute([$branch_id, $barcode]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            echo json_encode(['success' => true, 'product' => $product]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit();
    }

    if ($action === 'get_sale_details') {
        $sale_id = (int)$_POST['sale_id'];
        $stmt = $pdo->prepare("SELECT s.*, c.customer_name FROM sales s LEFT JOIN customers c ON s.customer_id = c.id WHERE s.id = ? AND s.branch_id = ?");
        $stmt->execute([$sale_id, $branch_id]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($sale) {
            $stmtItems = $pdo->prepare("SELECT si.*, p.product_name FROM sales_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?");
            $stmtItems->execute([$sale_id]);
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            // Format items for POS cart
            $cartItems = [];
            foreach ($items as $item) {
                $cartItems[] = [
                    'id' => $item['product_id'],
                    'name' => $item['product_name'],
                    'price' => (float)$item['unit_price'],
                    'qty' => (float)$item['quantity'],
                    'discount_percent' => (float)($item['unit_price'] > 0 ? ($item['discount_amount'] / ($item['unit_price'] * $item['quantity'])) * 100 : 0),
                    'gst_rate' => (float)$item['tax_rate']
                ];
            }

            echo json_encode(['success' => true, 'sale' => $sale, 'items' => $cartItems]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Sale not found']);
        }
        exit();
    }

    if ($action === 'search_invoices') {
        $q = isset($_POST['q']) ? trim($_POST['q']) : '';
        // Show invoices that are NOT fully returned (Partial or other statuses are OK)
        $sql = "SELECT s.id, s.invoice_no, s.grand_total, s.payment_status, c.customer_name 
                FROM sales s 
                LEFT JOIN customers c ON s.customer_id = c.id 
                WHERE s.sale_type = 'Sale Invoice' 
                AND s.payment_status != 'Returned'
                AND s.branch_id = :branch_id";
        $params = [':branch_id' => $branch_id];

        if ($q) {
            $sql .= " AND (s.invoice_no LIKE :q OR c.customer_name LIKE :q)";
            $params[':q'] = "%$q%";
        }

        $sql .= " ORDER BY s.id DESC LIMIT 30";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formatted = [];
        foreach ($results as $res) {
            // Check if there are any returnable items remaining
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM sales_items WHERE sale_id = ? AND (quantity - COALESCE(returned_qty, 0)) > 0.00001");
            $checkStmt->execute([$res['id']]);
            if ((int)$checkStmt->fetchColumn() === 0) continue; // No returnable items left

            $formatted[] = [
                'id'   => $res['id'],
                'text' => $res['invoice_no'] . ' - ' . ($res['customer_name'] ?? 'Walk-in') . ' (₹' . number_format($res['grand_total'], 2) . ')'
            ];
        }

        echo json_encode(['results' => $formatted]);
        exit();
    }
    if ($action === 'get_sale_items') {
        $sale_id = (int)($_POST['sale_id'] ?? 0);
        if (!$sale_id) { echo json_encode(['success' => false, 'message' => 'Invalid sale']); exit(); }

        $stmt = $pdo->prepare("
            SELECT s.id, s.invoice_no, s.sale_date, s.grand_total, s.payment_mode,
                   c.customer_name
            FROM sales s
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.id = ? AND s.payment_status != 'Returned'
        ");
        $stmt->execute([$sale_id]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sale) { echo json_encode(['success' => false, 'message' => 'Invoice not found or already fully returned']); exit(); }

        $stmtI = $pdo->prepare("
            SELECT si.id as item_id, si.product_id, si.quantity,
                   COALESCE(si.returned_qty, 0) as returned_qty,
                   (si.quantity - COALESCE(si.returned_qty, 0)) as returnable_qty,
                   si.unit_price,
                   si.discount_amount, si.tax_rate, si.tax_amount, si.total_amount,
                   p.product_name
            FROM sales_items si
            JOIN products p ON si.product_id = p.id
            WHERE si.sale_id = ?
            HAVING returnable_qty > 0
        ");
        $stmtI->execute([$sale_id]);
        $items = $stmtI->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) {
            echo json_encode(['success' => false, 'message' => 'All items in this invoice have already been returned.']);
            exit();
        }

        echo json_encode(['success' => true, 'sale' => $sale, 'items' => $items]);
        exit();
    }

    if ($action === 'process_partial_sale_return') {
        $sale_id     = (int)($_POST['sale_id'] ?? 0);
        $reason      = sanitize($_POST['return_reason'] ?? '');

        // $.ajax with traditional:true sends item_ids[] → PHP reads as $_POST['item_ids']
        $item_ids    = isset($_POST['item_ids'])    ? (array)$_POST['item_ids']    : [];
        $return_qtys = isset($_POST['return_qtys']) ? (array)$_POST['return_qtys'] : [];

        if (!$sale_id || empty($item_ids)) {
            echo json_encode(['success' => false, 'message' => 'Please select at least one product to return.']);
            exit();
        }

        try {
            $pdo->beginTransaction();

            $stmtSale = $pdo->prepare("SELECT * FROM sales WHERE id = ? AND payment_status != 'Returned'");
            $stmtSale->execute([$sale_id]);
            $sale = $stmtSale->fetch(PDO::FETCH_ASSOC);
            if (!$sale) throw new Exception('Invoice not found or already fully returned.');

            $totalRefund = 0;
            $totalSubtotalRefund = 0;
            $totalTaxRefund = 0;
            $returnedItems = [];

            $stmtItem    = $pdo->prepare("SELECT * FROM sales_items WHERE id = ? AND sale_id = ?");
            $stmtStockUp = $pdo->prepare("UPDATE branch_stock SET quantity = quantity + ? WHERE branch_id = ? AND product_id = ?");

            foreach ($item_ids as $idx => $item_id) {
                $item_id = (int)$item_id;
                $ret_qty = (float)($return_qtys[$idx] ?? 0);
                if ($ret_qty <= 0) continue;

                $stmtItem->execute([$item_id, $sale_id]);
                $item = $stmtItem->fetch(PDO::FETCH_ASSOC);
                if (!$item) continue;

                // MAX returnable = original qty - already returned qty
                $already_returned = (float)($item['returned_qty'] ?? 0);
                $max_returnable   = (float)$item['quantity'] - $already_returned;
                if ($max_returnable <= 0) continue; // Already fully returned

                // Clamp to max returnable
                $ret_qty = min($ret_qty, $max_returnable);

                // Calculate proportional refund based on ORIGINAL values to handle multiple partial returns correctly
                $orig_qty   = (float)$item['quantity'];
                $orig_total = (float)$item['total_amount'];
                $row_tax    = (float)$item['tax_amount'];
                
                // Refund per unit (post-tax/discount)
                $unit_refund = $orig_total / $orig_qty;
                $refund = round($ret_qty * $unit_refund, 2);
                
                // Subtotal part (pre-tax) for reporting
                $unit_subtotal = ($orig_total - $row_tax) / $orig_qty;
                $subtotal_refund = round($ret_qty * $unit_subtotal, 2);
                $tax_refund = $refund - $subtotal_refund;

                $totalRefund += $refund;
                $totalSubtotalRefund += $subtotal_refund;
                $totalTaxRefund += $tax_refund;

                // Update returned_qty in sales_items
                $new_returned = $already_returned + $ret_qty;
                $pdo->prepare("UPDATE sales_items SET returned_qty = ? WHERE id = ?")->execute([$new_returned, $item_id]);

                // Revert stock
                $stmtStockUp->execute([$ret_qty, $sale['branch_id'], $item['product_id']]);

                $returnedItems[] = [
                    'item_id'       => $item_id,
                    'product_id'    => $item['product_id'],
                    'returned_qty'  => $ret_qty,
                    'unit_price'    => $item['unit_price'],
                    'refund_amount' => $refund
                ];
            }

            if (empty($returnedItems)) {
                throw new Exception('No valid items selected for return. Items may have already been fully returned.');
            }

            // Check if all items in this sale are now fully returned
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM sales_items WHERE sale_id = ? AND (quantity - COALESCE(returned_qty, 0)) > 0.00001");
            $stmtCheck->execute([$sale_id]);
            $hasRemaining = (int)$stmtCheck->fetchColumn();

            if ($hasRemaining === 0) {
                // Full return - zero out everything
                $pdo->prepare("UPDATE sales SET payment_status = 'Returned', sub_total = 0, tax_amount = 0, grand_total = 0, paid_amount = 0 WHERE id = ?")
                    ->execute([$sale_id]);
            } else {
                // Partial return - reduce parent totals
                $pdo->prepare("UPDATE sales SET 
                    sub_total = GREATEST(0, sub_total - ?), 
                    tax_amount = GREATEST(0, tax_amount - ?),
                    grand_total = GREATEST(0, grand_total - ?), 
                    paid_amount = GREATEST(0, paid_amount - ?) 
                    WHERE id = ?")
                    ->execute([$totalSubtotalRefund, $totalTaxRefund, $totalRefund, $totalRefund, $sale_id]);
            }

            // Record in sales_returns audit table
            $stmtReturn = $pdo->prepare("
                INSERT INTO sales_returns (sale_id, return_date, return_reason, total_refund, subtotal_refund, tax_refund, returned_by, is_full_return)
                VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?)
            ");
            $stmtReturn->execute([
                $sale_id, $reason, $totalRefund, $totalSubtotalRefund, $totalTaxRefund,
                $_SESSION['user_id'] ?? 0,
                $hasRemaining === 0 ? 1 : 0
            ]);
            $return_id = $pdo->lastInsertId();

            // Record return items
            $stmtReturnItem = $pdo->prepare("
                INSERT INTO sales_return_items (return_id, sale_item_id, product_id, returned_qty, unit_price, refund_amount)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($returnedItems as $ri) {
                $stmtReturnItem->execute([$return_id, $ri['item_id'], $ri['product_id'], $ri['returned_qty'], $ri['unit_price'], $ri['refund_amount']]);
            }

            // Record refund payment out
            if ($totalRefund > 0) {
                $pdo->prepare("INSERT INTO payments (branch_id, transaction_type, reference_id, amount, payment_mode, payment_date, notes) VALUES (?, 'Payment Out', ?, ?, ?, CURDATE(), ?)")
                    ->execute([$sale['branch_id'], $sale_id, $totalRefund, $sale['payment_mode'] ?? 'Cash', 'Sales Return: ' . $reason]);
            }

            $pdo->commit();
            $msg = 'Return processed successfully. Refund: ₹' . number_format($totalRefund, 2);
            if ($hasRemaining === 0) $msg .= ' (Invoice fully returned)';
            echo json_encode(['success' => true, 'message' => $msg]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    if ($action === 'collect_sale_payment') {
        $sale_id = (int)($_POST['sale_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $payment_mode = sanitize($_POST['payment_mode'] ?? 'Cash');

        if (!$sale_id || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid sale or amount']);
            exit();
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT grand_total, paid_amount FROM sales WHERE id = ? AND branch_id = ?");
            $stmt->execute([$sale_id, $branch_id]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale) throw new Exception('Sale not found');

            $new_paid = (float)$sale['paid_amount'] + $amount;
            $status = ($new_paid >= (float)$sale['grand_total'] - 0.01) ? 'Paid' : 'Partial';

            $update = $pdo->prepare("UPDATE sales SET paid_amount = ?, payment_status = ? WHERE id = ?");
            $update->execute([$new_paid, $status, $sale_id]);

            // Record payment
            $stmtPay = $pdo->prepare("INSERT INTO payments (branch_id, transaction_type, reference_id, amount, payment_mode, payment_date, notes) VALUES (?, 'Payment In', ?, ?, ?, CURDATE(), ?)");
            $stmtPay->execute([$branch_id, $sale_id, $amount, $payment_mode, "Sale Payment: Partial balance collection"]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Payment collected successfully']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
}

// Handle Raw JSON input or FormData for save_sale
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data && isset($_POST['action']) && $_POST['action'] === 'save_sale') {
    $data = $_POST;
    if (isset($data['items']) && is_string($data['items'])) {
        $data['items'] = json_decode($data['items'], true);
    }
}

if ($data && isset($data['action']) && $data['action'] === 'save_sale') {
    try {
        $pdo->beginTransaction();

        $customer_id = !empty($data['customer_id']) ? (int)$data['customer_id'] : null;
        $sale_type = $data['sale_type'];
        $is_gst_bill = $data['is_gst_bill'];
        $payment_mode = $data['payment_mode'];
        $paid_amount = (float)$data['paid_amount'];
        $grand_total = (float)$data['grand_total'];
        $sale_id = isset($data['sale_id']) ? (int)$data['sale_id'] : null;

        $payment_status = 'Paid';
        if ($paid_amount == 0) $payment_status = 'Unpaid';
        elseif ($paid_amount < $grand_total) $payment_status = 'Partial';

        if ($sale_id) {
            // EDIT MODE: Revert old stock
            $stmtOldItems = $pdo->prepare("SELECT product_id, quantity FROM sales_items WHERE sale_id = ?");
            $stmtOldItems->execute([$sale_id]);
            $oldItems = $stmtOldItems->fetchAll();

            $stmtOldSale = $pdo->prepare("SELECT sale_type FROM sales WHERE id = ?");
            $stmtOldSale->execute([$sale_id]);
            $oldSaleType = $stmtOldSale->fetchColumn();

            if (in_array($oldSaleType, ['Sale Invoice', 'Delivery Challan'])) {
                foreach ($oldItems as $oi) {
                    $pdo->prepare("UPDATE branch_stock SET quantity = quantity + ? WHERE branch_id = ? AND product_id = ?")
                        ->execute([$oi['quantity'], $branch_id, $oi['product_id']]);
                }
            }

            // Update Sale
            $stmt = $pdo->prepare("
                UPDATE sales SET customer_id = ?, sale_type = ?, is_gst_bill = ?, sub_total = ?, 
                discount_amount = ?, tax_amount = ?, round_off = ?, grand_total = ?, paid_amount = ?, 
                payment_mode = ?, payment_status = ?, emi_scheme_id = ?, emi_amount = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $customer_id,
                $sale_type,
                $is_gst_bill,
                $data['sub_total'],
                $data['discount_amount'],
                $data['tax_amount'],
                $data['round_off'],
                $grand_total,
                $paid_amount,
                $payment_mode,
                $payment_status,
                $data['emi_scheme_id'] ?? null,
                $data['emi_amount'] ?? 0,
                $sale_id
            ]);

            // Delete old items
            $pdo->prepare("DELETE FROM sales_items WHERE sale_id = ?")->execute([$sale_id]);
            $invoice_no = $pdo->query("SELECT invoice_no FROM sales WHERE id = $sale_id")->fetchColumn();
        } else {
            // NEW SALE
            // Generate Invoice Number
            $prefix = isset($settings['invoice_prefix']) ? $settings['invoice_prefix'] : 'INV-';
            $stmt = $pdo->query("SELECT COUNT(id) as total FROM sales");
            $count = $stmt->fetchColumn() + 1;
            $invoice_no = $prefix . date('ym') . sprintf('%04d', $count);

            $stmt = $pdo->prepare("
                INSERT INTO sales (branch_id, user_id, customer_id, invoice_no, sale_date, sale_type, 
                is_gst_bill, sub_total, discount_amount, tax_amount, round_off, grand_total, paid_amount, 
                payment_mode, payment_status, emi_scheme_id, emi_amount)
                VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $branch_id,
                $_SESSION['user_id'],
                $customer_id,
                $invoice_no,
                $sale_type,
                $is_gst_bill,
                $data['sub_total'],
                $data['discount_amount'],
                $data['tax_amount'],
                $data['round_off'],
                $grand_total,
                $paid_amount,
                $payment_mode,
                $payment_status,
                $data['emi_scheme_id'] ?? null,
                $data['emi_amount'] ?? 0
            ]);
            $sale_id = $pdo->lastInsertId();
        }

        // If payment mode is EMI, record it in emi table
        if ($payment_mode === 'EMI') {
            // Get customer details for emi table
            $custStmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
            $custStmt->execute([$customer_id]);
            $customer = $custStmt->fetch();

            $product_names = [];
            foreach ($data['items'] as $item) {
                $product_names[] = $item['name'];
            }
            $product_name_str = implode(', ', $product_names);

            // Calculate loan amount
            $loan_amount = $grand_total - $paid_amount;
            
            // Get scheme details
            $emi_method = '';
            if (!empty($data['emi_scheme_id'])) {
                $schStmt = $pdo->prepare("SELECT scheme_name, repayment_cycle FROM emi_schemes WHERE id = ?");
                $schStmt->execute([$data['emi_scheme_id']]);
                $sch = $schStmt->fetch();
                if ($sch) {
                    $emi_method = $sch['scheme_name'] . ' (' . $sch['repayment_cycle'] . ')';
                }
            }

            // Handle File Uploads
            $emi_photo = null;
            $emi_proof1 = null;
            $emi_proof2 = null;
            $upload_dir = '../uploads/emi/';

            if (isset($_FILES['emi_photo']) && $_FILES['emi_photo']['error'] === UPLOAD_ERR_OK) {
                $emi_photo = time() . '_photo_' . $_FILES['emi_photo']['name'];
                move_uploaded_file($_FILES['emi_photo']['tmp_name'], $upload_dir . $emi_photo);
            }
            if (isset($_FILES['emi_proof1']) && $_FILES['emi_proof1']['error'] === UPLOAD_ERR_OK) {
                $emi_proof1 = time() . '_proof1_' . $_FILES['emi_proof1']['name'];
                move_uploaded_file($_FILES['emi_proof1']['tmp_name'], $upload_dir . $emi_proof1);
            }
            if (isset($_FILES['emi_proof2']) && $_FILES['emi_proof2']['error'] === UPLOAD_ERR_OK) {
                $emi_proof2 = time() . '_proof2_' . $_FILES['emi_proof2']['name'];
                move_uploaded_file($_FILES['emi_proof2']['tmp_name'], $upload_dir . $emi_proof2);
            }

            $emiSql = "INSERT INTO emi (emi_date, customer_name, city, mobile, address, product_name, total_amount, paid_amount, loan_amount, interest, emi_amount, emi_method, branch_id, photo, proof1, proof2) 
                       VALUES (CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $emiStmt = $pdo->prepare($emiSql);
            $emiStmt->execute([
                $customer['customer_name'] ?? 'Walk-in',
                $customer['city'] ?? '',
                $customer['phone'] ?? '',
                $customer['address'] ?? '',
                $product_name_str,
                $grand_total,
                $paid_amount,
                $loan_amount,
                0, // Interest is usually baked into emi_amount or handled by scheme
                $data['emi_amount'],
                $emi_method,
                $branch_id,
                $emi_photo,
                $emi_proof1,
                $emi_proof2
            ]);
            $emi_record_id = $pdo->lastInsertId();

            // Generate EMI Schedule
            if (!empty($data['emi_scheme_id'])) {
                $schDataStmt = $pdo->prepare("SELECT number_of_installments, repayment_cycle FROM emi_schemes WHERE id = ?");
                $schDataStmt->execute([$data['emi_scheme_id']]);
                $scheme = $schDataStmt->fetch();

                if ($scheme) {
                    $installments = $scheme['number_of_installments'];
                    $cycle = $scheme['repayment_cycle'];
                    $emi_amount = $data['emi_amount'];
                    $start_date = date('Y-m-d');

                    for ($i = 1; $i <= $installments; $i++) {
                        // Calculate next due date
                        if ($cycle === 'Weekly') {
                            $due_date = date('Y-m-d', strtotime("+$i week", strtotime($start_date)));
                        } elseif ($cycle === '14 Days') {
                            $weeks = $i * 2;
                            $due_date = date('Y-m-d', strtotime("+$weeks week", strtotime($start_date)));
                        } else { // Monthly
                            $due_date = date('Y-m-d', strtotime("+$i month", strtotime($start_date)));
                        }

                        $schedSql = "INSERT INTO emi_schedule (emi_id, installment_no, due_date, amount, status) 
                                     VALUES (?, ?, ?, ?, 'Pending')";
                        $schedStmt = $pdo->prepare($schedSql);
                        $schedStmt->execute([$emi_record_id, $i, $due_date, $emi_amount]);
                    }
                }
            }
        }

        // Insert Items and Update Stock
        $stmtItem = $pdo->prepare("
            INSERT INTO sales_items (sale_id, product_id, quantity, unit_price, discount_amount, tax_rate, tax_amount, total_amount)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmtStockCheck = $pdo->prepare("SELECT id, quantity FROM branch_stock WHERE branch_id = ? AND product_id = ?");
        $stmtStockUpdate = $pdo->prepare("UPDATE branch_stock SET quantity = quantity - ? WHERE id = ?");
        $stmtStockInsert = $pdo->prepare("INSERT INTO branch_stock (branch_id, product_id, quantity) VALUES (?, ?, ?)");

        foreach ($data['items'] as $item) {
            // Check stock availability for invoices/challans
            if (in_array($sale_type, ['Sale Invoice', 'Delivery Challan'])) {
                $stmtStockCheck->execute([$branch_id, $item['id']]);
                $stockRow = $stmtStockCheck->fetch();
                $available = $stockRow ? (float)$stockRow['quantity'] : 0;
                
                if ($available < (float)$item['qty']) {
                    throw new Exception("Insufficient stock for product: " . $item['name'] . ". Available: " . $available);
                }
            }

            $itemTotal = $item['price'] * $item['qty'];
            $itemDiscount = $itemTotal * ($item['discount_percent'] / 100);
            $taxableValue = $itemTotal - $itemDiscount;

            $itemTax = 0;
            if ($is_gst_bill) {
                $itemTax = $taxableValue * ($item['gst_rate'] / 100);
            }

            $rowTotal = $taxableValue + $itemTax;

            $stmtItem->execute([
                $sale_id,
                $item['id'],
                $item['qty'],
                $item['price'],
                $itemDiscount,
                $is_gst_bill ? $item['gst_rate'] : 0,
                $itemTax,
                $rowTotal
            ]);

            // Only reduce stock if it's an actual sale/delivery, not an estimate/order
            if (in_array($sale_type, ['Sale Invoice', 'Delivery Challan'])) {
                // $stockRow already fetched above
                $stmtStockUpdate->execute([$item['qty'], $stockRow['id']]);
            }
        }

        // Record Payment if any
        if ($paid_amount > 0 && $sale_type !== 'Estimate') {
            // If editing, remove old payment first
            if (isset($data['sale_id'])) {
                $pdo->prepare("DELETE FROM payments WHERE transaction_type = 'Payment In' AND reference_id = ?")->execute([(int)$data['sale_id']]);
            }

            $stmtPay = $pdo->prepare("
                INSERT INTO payments (branch_id, transaction_type, reference_id, amount, payment_mode, payment_date)
                VALUES (?, 'Payment In', ?, ?, ?, CURDATE())
            ");
            $stmtPay->execute([$branch_id, $sale_id, $paid_amount, $payment_mode]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'sale_id' => $sale_id, 'invoice_no' => $invoice_no]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}
