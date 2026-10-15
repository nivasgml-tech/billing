<?php
// ajax/approval_action.php
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!hasRole(['Super Admin', 'Admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    try {
        $pdo->beginTransaction();

        // Get request details
        $stmt = $pdo->prepare("SELECT * FROM collection_approvals WHERE id = ? AND status = 'Pending'");
        $stmt->execute([$request_id]);
        $req = $stmt->fetch();

        if (!$req) {
            throw new Exception("Request not found or already processed.");
        }

        if ($action === 'Approved') {
            $id = $req['schedule_id'];
            $paid_now = $req['paid_amount'];
            $discount_now = $req['discount'];
            $penalty_now = $req['penalty_amount'];
            $penalty_desc = $req['penalty_description'];

            // Get current status of the installment
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

            $stmt = $pdo->prepare("UPDATE collection_approvals SET status = 'Approved' WHERE id = ?");
            $stmt->execute([$request_id]);

            $message = "Collection approved and recorded.";
        } else {
            $stmt = $pdo->prepare("UPDATE collection_approvals SET status = 'Rejected' WHERE id = ?");
            $stmt->execute([$request_id]);
            $message = "Collection request rejected.";
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => $message]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
