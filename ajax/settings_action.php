<?php
// ajax/settings_action.php
require_once '../includes/auth.php';
require_once '../config/database.php';

if (!hasRole(['Super Admin', 'Admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
    exit();
}

$action = sanitize($_POST['action'] ?? '');

if ($action === 'save_settings') {
    try {
        $pdo->beginTransaction();

        $stmtUpdate = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmtInsert = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
        $stmtCheck = $pdo->prepare("SELECT id FROM settings WHERE setting_key = ?");

        $fields = ['shop_name', 'shop_contact', 'shop_address', 'gst_number', 'financial_year', 'invoice_prefix', 'print_type', 'invoice_footer'];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $val = sanitize($_POST[$field]);
                $stmtCheck->execute([$field]);
                if ($stmtCheck->fetch()) {
                    $stmtUpdate->execute([$val, $field]);
                } else {
                    $stmtInsert->execute([$field, $val]);
                }
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
