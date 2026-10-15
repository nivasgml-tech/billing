<?php
// ajax/supplier_action.php
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!hasRole(['Super Admin', 'Admin', 'Staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
    exit();
}

$action = sanitize($_POST['action'] ?? '');
$id = (int)($_POST['id'] ?? 0);

$supplier_name = sanitize($_POST['supplier_name'] ?? '');
$phone = sanitize($_POST['phone'] ?? '');
$email = sanitize($_POST['email'] ?? '');
$address = sanitize($_POST['address'] ?? '');
$gst_number = sanitize($_POST['gst_number'] ?? '');
$pincode = sanitize($_POST['pincode'] ?? '');

if ($action === 'add') {
    if (empty($supplier_name)) {
        echo json_encode(['success' => false, 'message' => 'Supplier name is required']);
        exit();
    }

    $branch_id = $_SESSION['branch_id'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO suppliers (supplier_name, phone, email, address, gst_number, pincode, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$supplier_name, $phone, $email, $address, $gst_number, $pincode, $branch_id])) {
        echo json_encode(['success' => true, 'message' => 'Supplier added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add supplier']);
    }
} elseif ($action === 'edit') {
    if (empty($supplier_name) || $id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE suppliers SET supplier_name=?, phone=?, email=?, address=?, gst_number=?, pincode=? WHERE id=?");
    if ($stmt->execute([$supplier_name, $phone, $email, $address, $gst_number, $pincode, $id])) {
        echo json_encode(['success' => true, 'message' => 'Supplier updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update supplier']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
