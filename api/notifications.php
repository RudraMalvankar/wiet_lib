<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

try {
    $stmt = $db->prepare("
        SELECT notification_id, title, message, type, created_at, is_read
        FROM notifications 
        WHERE recipient_type = ? AND recipient_id = ? AND is_read = 0 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    
    $stmt->bind_param("si", $user_type, $user_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $notifications = [];
    
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    // Get count of unread notifications
    $count_stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE recipient_type = ? AND recipient_id = ? AND is_read = 0
    ");
    
    $count_stmt->bind_param("si", $user_type, $user_id);
    $count_stmt->execute();
    $count = $count_stmt->get_result()->fetch_assoc()['count'];
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'count' => (int)$count
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load notifications'
    ]);
}

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    $notification_id = (int)$_POST['notification_id'];
    
    try {
        $stmt = $db->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE notification_id = ? AND recipient_type = ? AND recipient_id = ?
        ");
        
        $stmt->bind_param("isi", $notification_id, $user_type, $user_id);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read']);
    }
}
?>