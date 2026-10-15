<?php
// ajax/emi_collection_action.php
require_once '../config/database.php';
require_once '../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action === 'get_schedule') {
        $emi_id = (int)$_GET['emi_id'];
        
        // Fetch schedule with latest approval status if any
        $query = "SELECT es.*, 
                  (SELECT status FROM collection_approvals WHERE schedule_id = es.id ORDER BY id DESC LIMIT 1) as approval_status,
                  (SELECT paid_amount FROM collection_approvals WHERE schedule_id = es.id ORDER BY id DESC LIMIT 1) as approval_amount
                  FROM emi_schedule es 
                  WHERE es.emi_id = ? 
                  ORDER BY es.installment_no ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$emi_id]);
        $schedule = $stmt->fetchAll();

        if (empty($schedule)) {
            echo '<div class="alert alert-info">No schedule found for this EMI.</div>';
            exit;
        }

        $pending_total = 0;
        foreach ($schedule as $s) {
            if ($s['status'] !== 'Paid') {
                $pending_total += ($s['amount'] + $s['penalty_amount'] - $s['paid_amount'] - $s['discount']);
            }
        }

        echo '<div class="d-flex justify-content-between align-items-center mb-3 bg-light p-3 rounded-3">';
        echo '<div><span class="text-muted small">Total Remaining Balance (incl. penalties)</span><h4 class="mb-0 text-danger fw-bold">₹' . number_format($pending_total, 2) . '</h4></div>';
        if ($pending_total > 0) {
            echo '<button class="btn btn-danger shadow-sm" onclick="settleAll(' . $emi_id . ', ' . $pending_total . ')"><i class="fas fa-check-double me-1"></i>Settle All EMIs</button>';
        }
        echo '</div>';

        echo '<div class="table-responsive">';
        echo '<table class="table table-bordered align-middle text-center">';
        echo '<thead class="table-dark"><tr><th>No.</th><th>Due Date</th><th>EMI Amount</th><th>Penalty</th><th>Paid</th><th>Discount</th><th>Balance</th><th>Status</th><th>Action</th></tr></thead>';
        echo '<tbody>';
        foreach ($schedule as $s) {
            $balance = $s['amount'] + $s['penalty_amount'] - $s['paid_amount'] - $s['discount'];
            $statusClass = 'bg-warning text-dark';
            if ($s['status'] === 'Paid') $statusClass = 'bg-success text-white';
            elseif ($s['status'] === 'Overdue') $statusClass = 'bg-danger text-white';
            elseif ($s['status'] === 'Partial') $statusClass = 'bg-info text-white';

            echo '<tr>';
            echo '<td>' . $s['installment_no'] . '</td>';
            echo '<td>' . date('d-m-Y', strtotime($s['due_date'])) . '</td>';
            echo '<td class="fw-bold">₹' . number_format($s['amount'], 2) . '</td>';
            echo '<td class="text-danger">';
            echo '₹' . number_format($s['penalty_amount'], 2);
            if ($s['penalty_description']) {
                echo '<br><small class="text-muted" style="font-size: 0.7rem;">' . htmlspecialchars($s['penalty_description']) . '</small>';
            }
            echo '</td>';
            echo '<td class="text-success">₹' . number_format($s['paid_amount'], 2) . '</td>';
            echo '<td class="text-primary">₹' . number_format($s['discount'], 2) . '</td>';
            echo '<td class="text-danger fw-bold">₹' . number_format($balance, 2) . '</td>';
            echo '<td><span class="badge ' . $statusClass . '">' . $s['status'] . '</span></td>';
            echo '<td>';
            if ($s['status'] !== 'Paid') {
                if ($s['approval_status'] === 'Pending') {
                    echo '<div class="alert alert-warning py-1 px-2 mb-0 small text-center">';
                    echo '<i class="fas fa-clock me-1"></i>Pending Appr.<br>';
                    echo '<strong>₹' . number_format($s['approval_amount'], 2) . '</strong>';
                    echo '</div>';
                } elseif ($s['approval_status'] === 'Rejected') {
                    echo '<div class="alert alert-danger py-1 px-2 mb-1 small text-center">';
                    echo '<i class="fas fa-times-circle me-1"></i>Rejected';
                    echo '</div>';
                    echo '<button class="btn btn-sm btn-success w-100" onclick="collectPayment(' . $s['id'] . ', ' . $balance . ')">Pay Again</button>';
                } else {
                    echo '<button class="btn btn-sm btn-success w-100" onclick="collectPayment(' . $s['id'] . ', ' . $balance . ')">Pay Now</button>';
                }
            } else {
                echo '<div class="small text-muted"><i class="fas fa-check-circle text-success"></i><br>' . date('d-m-Y', strtotime($s['payment_date'])) . '</div>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'pay_installment') {
        $id = (int)$_POST['id'];
        $paid_now = floatval($_POST['paid_amount'] ?? 0);
        $discount_now = floatval($_POST['discount'] ?? 0);
        $penalty_now = floatval($_POST['penalty_amount'] ?? 0);
        $penalty_desc = sanitize($_POST['penalty_description'] ?? '');
        
        try {
            if (!hasRole(['Super Admin', 'Admin'])) {
                $stmt = $pdo->prepare("INSERT INTO collection_approvals (schedule_id, paid_amount, discount, penalty_amount, penalty_description, requested_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$id, $paid_now, $discount_now, $penalty_now, $penalty_desc, $_SESSION['user_id']]);
                echo json_encode(['success' => true, 'message' => 'Collection request sent for admin approval.']);
                exit;
            }

            // Get current status
            $stmt = $pdo->prepare("SELECT amount, paid_amount, discount, penalty_amount, penalty_description FROM emi_schedule WHERE id = ?");
            $stmt->execute([$id]);
            $s = $stmt->fetch();
            
            $new_paid = $s['paid_amount'] + $paid_now;
            $new_discount = $s['discount'] + $discount_now;
            $new_penalty = $s['penalty_amount'] + $penalty_now;
            
            // Final total due for this installment
            $total_due = $s['amount'] + $new_penalty;
            $status = ($new_paid + $new_discount >= $total_due) ? 'Paid' : 'Partial';

            $stmt = $pdo->prepare("UPDATE emi_schedule SET status = ?, paid_amount = ?, discount = ?, penalty_amount = ?, penalty_description = ?, payment_date = CURDATE() WHERE id = ?");
            $stmt->execute([$status, $new_paid, $new_discount, $new_penalty, $penalty_desc ?: $s['penalty_description'], $id]);
            
            echo json_encode(['success' => true, 'message' => 'Payment recorded successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    } elseif ($action === 'settle_all') {
        $emi_id = (int)$_POST['emi_id'];
        $discount = floatval($_POST['discount'] ?? 0);
        
        try {
            if (!hasRole(['Super Admin', 'Admin'])) {
                $stmt = $pdo->prepare("SELECT id, (amount + penalty_amount - paid_amount - discount) as bal FROM emi_schedule WHERE emi_id = ? AND status != 'Paid'");
                $stmt->execute([$emi_id]);
                $pending = $stmt->fetchAll();
                
                $remaining_discount = $discount;
                foreach ($pending as $p) {
                    $give_discount = min($remaining_discount, $p['bal']);
                    $pay = $p['bal'] - $give_discount;
                    
                    $stmt = $pdo->prepare("INSERT INTO collection_approvals (schedule_id, paid_amount, discount, requested_by) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$p['id'], $pay, $give_discount, $_SESSION['user_id']]);
                    $remaining_discount -= $give_discount;
                }
                echo json_encode(['success' => true, 'message' => 'All installments sent for admin approval.']);
                exit;
            }

            $pdo->beginTransaction();
            
            // Mark all pending as paid
            $stmt = $pdo->prepare("SELECT id, (amount + penalty_amount - paid_amount - discount) as bal FROM emi_schedule WHERE emi_id = ? AND status != 'Paid'");
            $stmt->execute([$emi_id]);
            $pending = $stmt->fetchAll();
            
            $remaining_discount = $discount;
            foreach ($pending as $p) {
                $give_discount = min($remaining_discount, $p['bal']);
                $pay = $p['bal'] - $give_discount;
                
                // Set paid_amount to close the installment
                // Logic: amount + penalty_amount - (paid_amount + new_paid_now) - (discount + give_discount) = 0
                // So new_paid_now = amount + penalty_amount - paid_amount - discount - give_discount
                
                $stmt = $pdo->prepare("UPDATE emi_schedule SET status = 'Paid', paid_amount = amount + penalty_amount - (discount + ?), discount = discount + ?, payment_date = CURDATE() WHERE id = ?");
                $stmt->execute([$give_discount, $give_discount, $p['id']]);
                $remaining_discount -= $give_discount;
            }
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'All EMIs settled successfully']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
}
