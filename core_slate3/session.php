<?php
session_start();


// if (!isset($_SESSION['username'])) {
//     header("Location: login.php");
//     exit;
// }

function normalizeRoleName($role) {
    $role = strtolower(trim((string) $role));
    $role = str_replace('_', ' ', $role);
    $role = preg_replace('/\s+/', ' ', $role);
    return $role;
}

// Require user to be logged in
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

// Require a specific role
function requireRole($role) {
    if (
        !isset($_SESSION['user_id']) ||
        !isset($_SESSION['role']) ||
        normalizeRoleName($_SESSION['role']) !== normalizeRoleName($role)
    ) {
        header("Location: login.php");
        exit();
    }
}

// Optional: allow multiple roles
function requireRoles(array $roles) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header("Location: login.php");
        exit();
    }

    $currentRole = normalizeRoleName($_SESSION['role']);
    $normalizedRoles = array_map('normalizeRoleName', $roles);

    if (!in_array($currentRole, $normalizedRoles, true)) {
        header("Location: login.php");
        exit();
    }
}
?>
