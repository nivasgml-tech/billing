<?php
require_once 'config/database.php';
try {
    $pdo->exec("ALTER TABLE emi ADD COLUMN first_emi_date DATE AFTER emi_date");
    echo "Column first_emi_date added successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
