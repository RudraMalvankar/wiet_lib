<?php
/**
 * Configuration file for WIET-LIB
 * Library Management System ERP
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define base URL and paths
define('BASE_URL', '/wiet_lib');
define('BASE_PATH', dirname(__DIR__));
define('ASSETS_PATH', BASE_PATH . '/assets');
define('UPLOADS_PATH', BASE_PATH . '/uploads');

// Include database configuration
require_once BASE_PATH . '/config/database.php';

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Security functions
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Authentication functions
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit();
    }
}

function require_admin() {
    if (!is_logged_in() || $_SESSION['user_type'] !== 'admin') {
        header('Location: ' . BASE_URL . '/login.php');
        exit();
    }
}

function require_student() {
    if (!is_logged_in() || $_SESSION['user_type'] !== 'student') {
        header('Location: ' . BASE_URL . '/login.php');
        exit();
    }
}

// Utility functions
function redirect($url) {
    header('Location: ' . $url);
    exit();
}

function flash_message($type, $message) {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flash_messages() {
    if (isset($_SESSION['flash'])) {
        $messages = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $messages;
    }
    return [];
}

function format_date($date, $format = 'Y-m-d') {
    return date($format, strtotime($date));
}

function format_currency($amount) {
    return '₹' . number_format($amount, 2);
}

// File upload functions
function upload_file($file, $target_dir, $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf']) {
    $target_dir = rtrim($target_dir, '/') . '/';
    
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $file_name = uniqid() . '.' . $file_extension;
    $target_file = $target_dir . $file_name;
    
    // Check file type
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    // Check file size (5MB max)
    if ($file['size'] > 5000000) {
        return ['success' => false, 'message' => 'File too large'];
    }
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return ['success' => true, 'file_name' => $file_name, 'file_path' => $target_file];
    } else {
        return ['success' => false, 'message' => 'Upload failed'];
    }
}

// Notification functions
function add_notification($recipient_type, $recipient_id, $title, $message, $type = 'info') {
    global $db;
    
    $stmt = $db->prepare("INSERT INTO notifications (recipient_type, recipient_id, title, message, type) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$recipient_type, $recipient_id, $title, $message, $type]);
}

function get_unread_notifications($user_type, $user_id) {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM notifications WHERE recipient_type = ? AND recipient_id = ? AND is_read = 0 ORDER BY created_at DESC");
    $stmt->execute([$user_type, $user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Pagination function
function paginate($total_records, $records_per_page, $current_page) {
    $total_pages = ceil($total_records / $records_per_page);
    $offset = ($current_page - 1) * $records_per_page;
    
    return [
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'offset' => $offset,
        'records_per_page' => $records_per_page
    ];
}
?>