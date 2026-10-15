<?php
require_once 'config/database.php';

// Mock session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 1;
$_SESSION['branch_id'] = 1;
$_SESSION['role'] = 'Super Admin';

$_POST['action'] = 'search_invoices';
$_POST['q'] = ''; // Test empty search

$branch_id = $_SESSION['branch_id'];

// Directly run logic from pos_action.php
require_once 'ajax/pos_action.php';
