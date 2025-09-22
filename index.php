<?php
require_once 'includes/config.php';

// Redirect to login if not logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

// Redirect based on user type
if ($_SESSION['user_type'] === 'admin') {
    header('Location: admin/dashboard.php');
} else {
    header('Location: student/dashboard.php');
}
exit();
?>