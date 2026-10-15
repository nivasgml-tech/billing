<?php
// ajax/purchase_action.php
header('Content-Type: application/json');
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!hasRole(['Super Admin', 'Admin', 'Staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
    exit();
}

$action = sanitize($_POST['action'] ?? '');
$branch_id = $_SESSION['branch_id'] ?? 1;

if ($action === 'get_product_by_barcode') {
    $barcode = sanitize($_POST['barcode'] ?? '');
    if (empty($barcode)) {
        echo json_encode(['success' => false, 'message' => 'Barcode is required']);
        exit();
    }
    $stmt = $pdo->prepare("SELECT id, product_name, barcode, purchase_price, gst_rate FROM products WHERE barcode = ? AND status = 1 LIMIT 1");
    $stmt->execute([$barcode]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($product) {
        echo json_encode(['success' => true, 'product' => $product]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Product not found for barcode: ' . htmlspecialchars($barcode)]);
    }
    exit();
}

if ($action === 'search_purchases') {
    $q = sanitize($_POST['q'] ?? '');
    $sql = "SELECT p.id, p.bill_no, p.grand_total, p.payment_status, s.supplier_name 
            FROM purchases p
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            WHERE p.payment_status != 'Returned' 
            AND p.branch_id = :branch_id";
    $params = [':branch_id' => $branch_id];

    if (!empty($q)) {
        $sql .= " AND (p.bill_no LIKE :q OR s.supplier_name LIKE :q)";
        $params[':q'] = "%$q%";
    }

    $sql .= " ORDER BY p.id DESC LIMIT 30";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted = [];
    foreach ($results as $r) {
        // Filter out bills where all items have been fully returned
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_items WHERE purchase_id = ? AND (quantity - COALESCE(returned_qty, 0)) > 0.00001");
        $checkStmt->execute([$r['id']]);
        if ((int)$checkStmt->fetchColumn() === 0) continue;

        $formatted[] = [
            'id'   => $r['id'],
            'text' => $r['bill_no'] . " - " . $r['supplier_name'] . " (₹" . number_format($r['grand_total'], 2) . ")"
        ];
    }
    echo json_encode(['results' => $formatted]);
    exit();
}

if ($action === 'get_purchase_details') {
    $id = (int)($_POST['id'] ?? 0);

    // Fetch main purchase info
    $stmt = $pdo->prepare("
        SELECT p.*, s.supplier_name, b.branch_name 
        FROM purchases p 
        LEFT JOIN suppliers s ON p.supplier_id = s.id 
        LEFT JOIN branches b ON p.branch_id = b.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($purchase) {
        // Fetch items
        $stmtItems = $pdo->prepare("
            SELECT pi.*, pr.product_name 
            FROM purchase_items pi 
            LEFT JOIN products pr ON pi.product_id = pr.id 
            WHERE pi.purchase_id = ?
        ");
        $stmtItems->execute([$id]);
        $purchase['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $purchase]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Purchase not found']);
    }
    exit();
}

if ($action === 'get_purchase_items') {
    $purchase_id = (int)($_POST['purchase_id'] ?? 0);
    if (!$purchase_id) { echo json_encode(['success' => false, 'message' => 'Invalid purchase']); exit(); }

    $stmt = $pdo->prepare("
        SELECT p.id, p.bill_no, p.purchase_date, p.grand_total, p.paid_amount,
               s.supplier_name
        FROM purchases p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        WHERE p.id = ? AND p.payment_status != 'Returned'
    ");
    $stmt->execute([$purchase_id]);
    $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$purchase) { echo json_encode(['success' => false, 'message' => 'Bill not found or already fully returned']); exit(); }

    $stmtI = $pdo->prepare("
        SELECT pi.id as item_id, pi.product_id, pi.quantity,
               COALESCE(pi.returned_qty, 0) as returned_qty,
               (pi.quantity - COALESCE(pi.returned_qty, 0)) as returnable_qty,
               pi.unit_price,
               pi.tax_rate, pi.tax_amount, pi.total_amount,
               pr.product_name
        FROM purchase_items pi
        JOIN products pr ON pi.product_id = pr.id
        WHERE pi.purchase_id = ?
        HAVING returnable_qty > 0
    ");
    $stmtI->execute([$purchase_id]);
    $items = $stmtI->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'All items in this bill have already been returned.']);
        exit();
    }

    echo json_encode(['success' => true, 'purchase' => $purchase, 'items' => $items]);
    exit();
}

if ($action === 'process_partial_purchase_return') {
    $purchase_id = (int)($_POST['purchase_id'] ?? 0);
    $reason      = sanitize($_POST['return_reason'] ?? '');

    // $.ajax with traditional:true sends item_ids[] → PHP reads as $_POST['item_ids']
    $item_ids    = isset($_POST['item_ids'])    ? (array)$_POST['item_ids']    : [];
    $return_qtys = isset($_POST['return_qtys']) ? (array)$_POST['return_qtys'] : [];

    if (!$purchase_id || empty($item_ids)) {
        echo json_encode(['success' => false, 'message' => 'Please select at least one product to return.']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        $stmtPur = $pdo->prepare("SELECT * FROM purchases WHERE id = ? AND payment_status != 'Returned'");
        $stmtPur->execute([$purchase_id]);
        $purchase = $stmtPur->fetch(PDO::FETCH_ASSOC);
        if (!$purchase) throw new Exception('Bill not found or already fully returned.');

        $totalRefund = 0;
        $totalSubtotalRefund = 0;
        $totalTaxRefund = 0;
        $returnedItems = [];
        $stmtItem    = $pdo->prepare("SELECT * FROM purchase_items WHERE id = ? AND purchase_id = ?");
        $stmtStockDn = $pdo->prepare("UPDATE branch_stock SET quantity = quantity - ? WHERE branch_id = ? AND product_id = ?");

        foreach ($item_ids as $idx => $item_id) {
            $item_id = (int)$item_id;
            $ret_qty = (float)($return_qtys[$idx] ?? 0);
            if ($ret_qty <= 0) continue;

            $stmtItem->execute([$item_id, $purchase_id]);
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
            
            // Refund per unit (post-tax)
            $unit_refund = $orig_total / $orig_qty;
            $refund = round($ret_qty * $unit_refund, 2);
            
            // Subtotal part (pre-tax) for reporting
            $unit_subtotal = ($orig_total - $row_tax) / $orig_qty;
            $subtotal_refund = round($ret_qty * $unit_subtotal, 2);
            $tax_refund = $refund - $subtotal_refund;

            $totalRefund += $refund;
            $totalSubtotalRefund += $subtotal_refund;
            $totalTaxRefund += $tax_refund;

            // Update returned_qty in purchase_items
            $new_returned = $already_returned + $ret_qty;
            $pdo->prepare("UPDATE purchase_items SET returned_qty = ? WHERE id = ?")->execute([$new_returned, $item_id]);

            // Deduct stock (returning goods back to supplier)
            $stmtStockDn->execute([$ret_qty, $purchase['branch_id'], $item['product_id']]);

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

        // Check if all items in this purchase are now fully returned
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM purchase_items WHERE purchase_id = ? AND (quantity - COALESCE(returned_qty, 0)) > 0.00001");
        $stmtCheck->execute([$purchase_id]);
        $hasRemaining = (int)$stmtCheck->fetchColumn();

        if ($hasRemaining === 0) {
            // Full return - zero out everything
            $pdo->prepare("UPDATE purchases SET payment_status = 'Returned', sub_total = 0, tax_amount = 0, grand_total = 0, paid_amount = 0 WHERE id = ?")
                ->execute([$purchase_id]);
        } else {
            // Partial return - reduce parent totals
            $pdo->prepare("UPDATE purchases SET 
                sub_total = GREATEST(0, sub_total - ?), 
                tax_amount = GREATEST(0, tax_amount - ?),
                grand_total = GREATEST(0, grand_total - ?), 
                paid_amount = GREATEST(0, paid_amount - ?) 
                WHERE id = ?")
                ->execute([$totalSubtotalRefund, $totalTaxRefund, $totalRefund, $totalRefund, $purchase_id]);
        }

        // Record in purchase_returns audit table
        $stmtReturn = $pdo->prepare("
            INSERT INTO purchase_returns (purchase_id, return_date, return_reason, total_refund, subtotal_refund, tax_refund, returned_by, is_full_return)
            VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?)
        ");
        $stmtReturn->execute([
            $purchase_id, $reason, $totalRefund, $totalSubtotalRefund, $totalTaxRefund,
            $_SESSION['user_id'] ?? 0,
            $hasRemaining === 0 ? 1 : 0
        ]);
        $return_id = $pdo->lastInsertId();

        // Record return items
        $stmtReturnItem = $pdo->prepare("
            INSERT INTO purchase_return_items (return_id, purchase_item_id, product_id, returned_qty, unit_price, refund_amount)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($returnedItems as $ri) {
            $stmtReturnItem->execute([$return_id, $ri['item_id'], $ri['product_id'], $ri['returned_qty'], $ri['unit_price'], $ri['refund_amount']]);
        }

        // Record refund payment in (from supplier)
        if ($totalRefund > 0) {
            $pdo->prepare("INSERT INTO payments (branch_id, transaction_type, reference_id, amount, payment_mode, payment_date, notes) VALUES (?, 'Payment In', ?, ?, 'Cash', CURDATE(), ?)")
                ->execute([$purchase['branch_id'], $purchase_id, $totalRefund, 'Purchase Return from Supplier: ' . $reason]);
        }

        $pdo->commit();
        $msg = 'Return processed. Refund from supplier: ₹' . number_format($totalRefund, 2);
        if ($hasRemaining === 0) $msg .= ' (Bill fully returned)';
        echo json_encode(['success' => true, 'message' => $msg]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

if ($action === 'save_purchase') {
    try {
        $pdo->beginTransaction();

        $supplier_id = (int)$_POST['supplier_id'];
        $bill_no = sanitize($_POST['bill_no']);
        $purchase_date = sanitize($_POST['purchase_date']);

        $sub_total = (float)$_POST['sub_total'];
        $discount_amount = (float)$_POST['discount_amount'];
        $tax_amount = (float)$_POST['tax_amount'];
        $grand_total = (float)$_POST['grand_total'];

        $payment_status = sanitize($_POST['payment_status']);
        $paid_amount = (float)($_POST['paid_amount'] ?? 0);
        $payment_mode = sanitize($_POST['payment_mode'] ?? 'Cash');

        // Validation
        if ($supplier_id <= 0 || empty($bill_no) || empty($_POST['product_id'])) {
            throw new Exception("Missing required fields.");
        }

        // Insert Purchase
        $stmt = $pdo->prepare("
            INSERT INTO purchases (branch_id, user_id, supplier_id, bill_no, purchase_date, 
            sub_total, discount_amount, tax_amount, grand_total, paid_amount, payment_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $branch_id,
            $_SESSION['user_id'],
            $supplier_id,
            $bill_no,
            $purchase_date,
            $sub_total,
            $discount_amount,
            $tax_amount,
            $grand_total,
            $paid_amount,
            $payment_status
        ]);

        $purchase_id = $pdo->lastInsertId();

        // Process Items
        $product_ids = $_POST['product_id'];
        $prices = $_POST['unit_price'];
        $qtys = $_POST['qty'];
        $tax_rates = $_POST['tax_rate'];
        $tax_amts = $_POST['tax_amt'];
        $total_amts = $_POST['total_amt'];

        $stmtItem = $pdo->prepare("
            INSERT INTO purchase_items (purchase_id, product_id, quantity, unit_price, tax_rate, tax_amount, total_amount)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmtStockCheck = $pdo->prepare("SELECT id FROM branch_stock WHERE branch_id = ? AND product_id = ?");
        $stmtStockUpdate = $pdo->prepare("UPDATE branch_stock SET quantity = quantity + ? WHERE id = ?");
        $stmtStockInsert = $pdo->prepare("INSERT INTO branch_stock (branch_id, product_id, quantity) VALUES (?, ?, ?)");

        for ($i = 0; $i < count($product_ids); $i++) {
            $pid = $product_ids[$i];
            $qty = (float)$qtys[$i];

            if (empty($pid) || $qty <= 0) continue;

            // Check if $pid is a new product name (not numeric)
            if (!is_numeric($pid)) {
                $productName = sanitize($pid);

                // CHECK IF PRODUCT ALREADY EXISTS BY NAME
                $stmtCheck = $pdo->prepare("SELECT id FROM products WHERE product_name = ? LIMIT 1");
                $stmtCheck->execute([$productName]);
                $existing = $stmtCheck->fetchColumn();

                if ($existing) {
                    $pid = $existing;
                } else {
                    // DYNAMICALLY GET DEFAULT CATEGORY AND UNIT
                    $default_cat = $pdo->query("SELECT id FROM categories WHERE status=1 LIMIT 1")->fetchColumn();
                    $default_unit = $pdo->query("SELECT id FROM units WHERE status=1 LIMIT 1")->fetchColumn();

                    if (!$default_cat || !$default_unit) {
                        // Fallback in case table is empty or all inactive
                        $default_cat = 1;
                        $default_unit = 1;
                    }

                    $stmtNewProd = $pdo->prepare("INSERT INTO products (product_name, category_id, unit_id, quantity_type, gst_rate, purchase_price, sale_price, status) VALUES (?, ?, ?, 'PCS', 0, ?, ?, 1)");
                    $stmtNewProd->execute([$productName, $default_cat, $default_unit, (float)$prices[$i], (float)$prices[$i]]);
                    $pid = $pdo->lastInsertId();
                }
            } else {
                $pid = (int)$pid;
            }

            $stmtItem->execute([
                $purchase_id,
                $pid,
                $qty,
                (float)$prices[$i],
                (float)$tax_rates[$i],
                (float)$tax_amts[$i],
                (float)$total_amts[$i]
            ]);

            // Increase Stock
            $stmtStockCheck->execute([$branch_id, $pid]);
            if ($row = $stmtStockCheck->fetch()) {
                $stmtStockUpdate->execute([$qty, $row['id']]);
            } else {
                $stmtStockInsert->execute([$branch_id, $pid, $qty]);
            }
        }

        // Process Payment Out
        if ($paid_amount > 0) {
            $stmtPay = $pdo->prepare("
                INSERT INTO payments (branch_id, transaction_type, reference_id, amount, payment_mode, payment_date)
                VALUES (?, 'Payment Out', ?, ?, ?, ?)
            ");
            $stmtPay->execute([$branch_id, $purchase_id, $paid_amount, $payment_mode, $purchase_date]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Purchase Bill saved successfully!', 'purchase_id' => $purchase_id]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} elseif ($action === 'add_purchase_payment') {
    try {
        $pdo->beginTransaction();
        $purchase_id = (int)$_POST['purchase_id'];
        $amount_paid = (float)$_POST['amount_paid'];
        $payment_mode = sanitize($_POST['payment_mode'] ?? 'Cash');
        $payment_date = sanitize($_POST['payment_date'] ?? date('Y-m-d'));

        if ($purchase_id <= 0 || $amount_paid <= 0) {
            throw new Exception("Invalid payment details.");
        }

        // Get current bill info
        $stmt = $pdo->prepare("SELECT grand_total, paid_amount, branch_id FROM purchases WHERE id = ?");
        $stmt->execute([$purchase_id]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$purchase) throw new Exception("Purchase bill not found.");

        $new_paid_total = $purchase['paid_amount'] + $amount_paid;
        $balance = $purchase['grand_total'] - $new_paid_total;

        if ($balance < -0.01) {
            throw new Exception("Payment amount exceeds the remaining balance (Balance: ₹" . number_format($purchase['grand_total'] - $purchase['paid_amount'], 2) . ")");
        }

        $status = ($balance <= 0.01) ? 'Paid' : 'Partial';

        // Update Purchase
        $stmtUpdate = $pdo->prepare("UPDATE purchases SET paid_amount = ?, payment_status = ? WHERE id = ?");
        $stmtUpdate->execute([$new_paid_total, $status, $purchase_id]);

        // Record Payment
        $stmtPay = $pdo->prepare("INSERT INTO payments (branch_id, transaction_type, reference_id, amount, payment_mode, payment_date, notes) VALUES (?, 'Payment Out', ?, ?, ?, ?, ?)");
        $stmtPay->execute([$purchase['branch_id'], $purchase_id, $amount_paid, $payment_mode, $payment_date, 'Balance payment for Purchase Bill']);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Payment recorded successfully!']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
