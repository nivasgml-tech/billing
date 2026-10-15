<?php
// ajax/reminder_action.php
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $remind_date = $_POST['remind_date'];
        $priority = $_POST['priority'];
        $branch_id = $_SESSION['branch_id'];

        try {
            $stmt = $pdo->prepare("INSERT INTO reminders (title, description, remind_date, priority, branch_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $remind_date, $priority, $branch_id]);
            echo json_encode(['success' => true, 'message' => 'Reminder added successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } elseif ($action === 'update_status') {
        $id = (int)$_POST['id'];
        $status = $_POST['status'];

        try {
            $stmt = $pdo->prepare("UPDATE reminders SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM reminders WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
