<?php
// ajax/role_action.php
require_once '../includes/auth.php';
require_once '../config/database.php';

requireRole(['Super Admin', 'Admin']); // Restrict to top level

header('Content-Type: application/json');

echo json_encode(['success' => false, 'message' => 'Role modification is disabled. The system is locked to exactly 3 core roles (Super Admin, Admin, Staff).']);
exit();
