<?php
// ajax/customer_action.php
require_once '../includes/auth.php';
require_once '../config/database.php';

requireRole(['Super Admin', 'Admin', 'Staff']);

header('Content-Type: application/json');

$action = sanitize($_POST['action'] ?? '');
$id = (int)($_POST['id'] ?? 0);

$customer_name = sanitize($_POST['customer_name'] ?? '');
$phone = sanitize($_POST['phone'] ?? '');
$email = sanitize($_POST['email'] ?? '');
$address = sanitize($_POST['address'] ?? '');
$gst_number = sanitize($_POST['gst_number'] ?? '');
$pincode = sanitize($_POST['pincode'] ?? '');

if ($action === 'add') {
    if (empty($customer_name)) {
        echo json_encode(['success' => false, 'message' => 'Customer Name is required']);
        exit();
    }

    if (!empty($phone)) {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'A customer with this mobile number already exists.']);
            exit();
        }
    }

    $branch_id = $_SESSION['branch_id'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO customers (customer_name, phone, email, address, gst_number, pincode, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$customer_name, $phone, $email, $address, $gst_number, $pincode, $branch_id])) {
        // Return ID for POS quick add
        $new_id = $pdo->lastInsertId();
        echo json_encode([
            'success' => true,
            'message' => 'Customer added successfully',
            'customer' => [
                'id' => $new_id,
                'customer_name' => $customer_name,
                'phone' => $phone
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add customer']);
    }
} elseif ($action === 'edit') {
    if (empty($customer_name) || $id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        exit();
    }

    if (!empty($phone)) {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ? AND id != ?");
        $stmt->execute([$phone, $id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Another customer with this mobile number already exists.']);
            exit();
        }
    }

    $stmt = $pdo->prepare("UPDATE customers SET customer_name=?, phone=?, email=?, address=?, gst_number=?, pincode=? WHERE id=?");
    if ($stmt->execute([$customer_name, $phone, $email, $address, $gst_number, $pincode, $id])) {
        echo json_encode(['success' => true, 'message' => 'Customer updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update customer']);
    }
} elseif ($action === 'delete') {
    // Check if customer has any sales before deleting
    $stmt = $pdo->prepare("SELECT count(*) FROM sales WHERE customer_id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete customer as they have associated sales records.']);
        exit();
    }

    $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
    if ($stmt->execute([$id])) {
        echo json_encode(['success' => true, 'message' => 'Customer deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete customer']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
