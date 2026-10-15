<?php
// includes/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Global functions for authorization
function hasRole($roles)
{
    if (!isset($_SESSION['role_name'])) return false;
    if (is_array($roles)) {
        return in_array($_SESSION['role_name'], $roles);
    }
    return $_SESSION['role_name'] === $roles;
}

function requireRole($roles)
{
    if (!hasRole($roles)) {
        echo "<script>alert('Access Denied'); window.location.href='index.php';</script>";
        exit();
    }
}
