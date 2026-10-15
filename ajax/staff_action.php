<?php
// ajax/staff_action.php
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $full_name = sanitize($_POST['full_name']);
        $branch_id = (int)$_POST['branch_id'];
        $role_id = (int)$_POST['role_id'];
        $phone = sanitize($_POST['phone']);
        $email = sanitize($_POST['email']);
        $designation = sanitize($_POST['designation']);
        $salary = (float)$_POST['salary'];
        $join_date = $_POST['join_date'];
        $address = sanitize($_POST['address']);

        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO staffs (full_name, branch_id, role_id, phone, email, designation, salary, join_date, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$full_name, $branch_id, $role_id, $phone, $email, $designation, $salary, $join_date, $address]);
                echo json_encode(['success' => true, 'message' => 'Staff added successfully']);
            } else {
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("UPDATE staffs SET full_name=?, branch_id=?, role_id=?, phone=?, email=?, designation=?, salary=?, join_date=?, address=? WHERE id=?");
                $stmt->execute([$full_name, $branch_id, $role_id, $phone, $email, $designation, $salary, $join_date, $address, $id]);
                echo json_encode(['success' => true, 'message' => 'Staff updated successfully']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } elseif ($action === 'create_login') {
        $username = sanitize($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $full_name = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $role_id = (int)$_POST['role_id'];
        $branch_id = (int)$_POST['branch_id'];

        try {
            // Check if username already exists
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            if ($check->fetch()) {
                throw new Exception("Username or Email already associated with a login account.");
            }

            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role_id, branch_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$username, $password, $full_name, $email, $phone, $role_id, $branch_id]);
            echo json_encode(['success' => true, 'message' => 'Login account created successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $pdo->prepare("DELETE FROM staffs WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
