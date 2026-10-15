<?php
// ajax/emi_action.php
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add' || $action === 'edit') {
            $emi_date = $_POST['emi_date'] ?? date('Y-m-d');
            $first_emi_date = $_POST['first_emi_date'] ?? $emi_date;
            $manual_bill_no = sanitize($_POST['manual_bill_no'] ?? '');
            $customer_name = sanitize($_POST['customer_name'] ?? '');
            $city = sanitize($_POST['city'] ?? '');
            $mobile = sanitize($_POST['mobile'] ?? '');
            $address = sanitize($_POST['address'] ?? '');
            $product_name = sanitize($_POST['product_name'] ?? '');
            $product_amount = floatval($_POST['product_amount'] ?? 0);
            $total_amount = floatval($_POST['total_amount'] ?? 0);
            $advance_amount = floatval($_POST['advance_amount'] ?? 0);
            $paid_amount = $advance_amount;
            $loan_amount = floatval($_POST['loan_amount'] ?? 0);
            $interest = floatval($_POST['interest'] ?? 0);
            $interest_amount = floatval($_POST['interest_amount'] ?? 0);
            $emi_amount = floatval($_POST['emi_amount'] ?? 0);
            $tenure = intval($_POST['tenure'] ?? 1);
            $emi_method = sanitize($_POST['emi_method'] ?? '');
            $center_name = sanitize($_POST['center_name'] ?? '');
            $is_center = sanitize($_POST['is_center'] ?? 'No');
            $scheme = sanitize($_POST['scheme'] ?? '');
            $interest_type = sanitize($_POST['interest_type'] ?? 'Percentage');
            $branch_id = $_SESSION['branch_id'] ?? null;

            if (empty($customer_name)) {
                echo json_encode(['success' => false, 'message' => 'Customer name is required']);
                exit;
            }

            // Handle File Uploads
            $upload_dir = '../uploads/emi/';
            $photo = '';
            $cheque_leaf = '';
            $aadhar = '';
            $pan = '';

            $id = $_POST['id'] ?? 0;
            if ($action === 'edit' && $id > 0) {
                $stmt = $pdo->prepare("SELECT photo, cheque_leaf, aadhar, pan FROM emi WHERE id = ?");
                $stmt->execute([$id]);
                $existing = $stmt->fetch();
                if ($existing) {
                    $photo = $existing['photo'];
                    $cheque_leaf = $existing['cheque_leaf'];
                    $aadhar = $existing['aadhar'];
                    $pan = $existing['pan'];
                }
            }

            foreach (['photo', 'cheque_leaf', 'aadhar', 'pan'] as $file_key) {
                if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION);
                    $filename = $file_key . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $upload_dir . $filename)) {
                        // Delete old file if exists
                        if ($action === 'edit' && !empty($$file_key) && file_exists($upload_dir . $$file_key)) {
                            @unlink($upload_dir . $$file_key);
                        }
                        $$file_key = $filename;
                    }
                }
            }

            if ($action === 'add') {
                // Check if EMI already exists for this invoice
                if (!empty($manual_bill_no)) {
                    $checkStmt = $pdo->prepare("SELECT id FROM emi WHERE manual_bill_no = ?");
                    $checkStmt->execute([$manual_bill_no]);
                    if ($checkStmt->fetch()) {
                        echo json_encode(['success' => false, 'message' => 'An EMI record already exists for this invoice number (' . $manual_bill_no . ')']);
                        exit;
                    }
                }

                $sql = "INSERT INTO emi (emi_date, first_emi_date, manual_bill_no, customer_name, city, mobile, address, product_name, product_amount, total_amount, paid_amount, loan_amount, interest, interest_amount, advance_amount, emi_amount, tenure, emi_method, center_name, is_center, scheme, interest_type, photo, cheque_leaf, aadhar, pan, branch_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$emi_date, $first_emi_date, $manual_bill_no, $customer_name, $city, $mobile, $address, $product_name, $product_amount, $total_amount, $paid_amount, $loan_amount, $interest, $interest_amount, $advance_amount, $emi_amount, $tenure, $emi_method, $center_name, $is_center, $scheme, $interest_type, $photo, $cheque_leaf, $aadhar, $pan, $branch_id]);
                $emi_id = $pdo->lastInsertId();

                // Generate EMI Schedule
                $cycle = $emi_method;
                for ($i = 1; $i <= $tenure; $i++) {
                    $offset = $i - 1;
                    if (strpos($cycle, 'Weekly') !== false) {
                        $due_date = date('Y-m-d', strtotime("+$offset week", strtotime($first_emi_date)));
                    } elseif (strpos($cycle, '14 Days') !== false) {
                        $weeks = $offset * 2;
                        $due_date = date('Y-m-d', strtotime("+$weeks week", strtotime($first_emi_date)));
                    } else { // Monthly
                        $due_date = date('Y-m-d', strtotime("+$offset month", strtotime($first_emi_date)));
                    }

                    $schedSql = "INSERT INTO emi_schedule (emi_id, installment_no, due_date, amount, status) 
                                 VALUES (?, ?, ?, ?, 'Pending')";
                    $schedStmt = $pdo->prepare($schedSql);
                    $schedStmt->execute([$emi_id, $i, $due_date, $emi_amount]);
                }

                // Update POS sale if linked
                if (!empty($manual_bill_no)) {
                    $stmtSale = $pdo->prepare("UPDATE sales SET paid_amount = grand_total, payment_status = 'Paid', payment_mode = 'EMI', emi_amount = ? WHERE invoice_no = ?");
                    $stmtSale->execute([$loan_amount, $manual_bill_no]);
                }

                echo json_encode(['success' => true, 'message' => 'EMI record added and schedule generated successfully']);
            } else {
                // Check if another EMI record already uses this manual_bill_no
                if (!empty($manual_bill_no)) {
                    $checkStmt = $pdo->prepare("SELECT id FROM emi WHERE manual_bill_no = ? AND id != ?");
                    $checkStmt->execute([$manual_bill_no, $id]);
                    if ($checkStmt->fetch()) {
                        echo json_encode(['success' => false, 'message' => 'Another EMI record already exists for this invoice number (' . $manual_bill_no . ')']);
                        exit;
                    }
                }

                $sql = "UPDATE emi SET emi_date = ?, first_emi_date = ?, manual_bill_no = ?, customer_name = ?, city = ?, mobile = ?, address = ?, product_name = ?, product_amount = ?, total_amount = ?, paid_amount = ?, loan_amount = ?, interest = ?, interest_amount = ?, advance_amount = ?, emi_amount = ?, tenure = ?, emi_method = ?, center_name = ?, is_center = ?, scheme = ?, interest_type = ?, photo = ?, cheque_leaf = ?, aadhar = ?, pan = ? 
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$emi_date, $first_emi_date, $manual_bill_no, $customer_name, $city, $mobile, $address, $product_name, $product_amount, $total_amount, $paid_amount, $loan_amount, $interest, $interest_amount, $advance_amount, $emi_amount, $tenure, $emi_method, $center_name, $is_center, $scheme, $interest_type, $photo, $cheque_leaf, $aadhar, $pan, $id]);
                echo json_encode(['success' => true, 'message' => 'EMI record updated successfully']);
            }
        } elseif ($action === 'delete') {
            $id = $_POST['id'] ?? 0;
            $stmt = $pdo->prepare("DELETE FROM emi WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'EMI record deleted successfully']);
        } elseif ($action === 'fetch') {
            $id = $_POST['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM emi WHERE id = ?");
            $stmt->execute([$id]);
            $emi = $stmt->fetch();
            if ($emi) {
                echo json_encode(['success' => true, 'data' => $emi]);
            } else {
                echo json_encode(['success' => false, 'message' => 'EMI record not found']);
            }
        } elseif ($action === 'fetch_pos_invoice') {
            $invoice_no = sanitize($_POST['invoice_no'] ?? '');
            if (empty($invoice_no)) {
                echo json_encode(['success' => false, 'message' => 'Please enter an invoice number']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT s.*, c.customer_name, c.phone, c.address, c.pincode 
                                   FROM sales s 
                                   LEFT JOIN customers c ON s.customer_id = c.id 
                                   WHERE s.invoice_no = ?");
            $stmt->execute([$invoice_no]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($sale) {
                // Fetch products for this sale
                $stmt_items = $pdo->prepare("SELECT p.product_name, si.quantity, si.total_amount 
                                             FROM sales_items si 
                                             JOIN products p ON si.product_id = p.id 
                                             WHERE si.sale_id = ?");
                $stmt_items->execute([$sale['id']]);
                $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                
                $product_names_arr = [];
                foreach ($items as $item) {
                    $product_names_arr[] = $item['product_name'];
                }
                $product_names_combined = implode(' + ', $product_names_arr);

                echo json_encode([
                    'success' => true, 
                    'data' => [
                        'customer_name' => $sale['customer_name'],
                        'mobile' => $sale['phone'],
                        'address' => $sale['address'],
                        'city' => '', // City is not stored in the customers table
                        'grand_total' => $sale['grand_total'],
                        'paid_amount' => $sale['paid_amount'],
                        'product_name' => $product_names_combined,
                        'items' => $items
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Sale not found for this invoice number']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
