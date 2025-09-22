<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$query = isset($_GET['q']) ? sanitize_input($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode(['success' => false, 'message' => 'Query too short']);
    exit();
}

try {
    $stmt = $db->prepare("
        SELECT student_id, student_number, full_name, email, course, year_of_study 
        FROM students 
        WHERE (student_number LIKE ? OR full_name LIKE ? OR email LIKE ?) 
        AND is_active = 1 
        ORDER BY full_name 
        LIMIT 10
    ");
    
    $search_term = "%$query%";
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $students = [];
    
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'students' => $students
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Search failed'
    ]);
}
?>