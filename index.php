<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: ' . APP_URL . '/admin/dashboard.php');
    } else {
        header('Location: ' . APP_URL . '/dashboard.php');
    }
} else {
    header('Location: ' . APP_URL . '/auth/login.php');
}
exit;
