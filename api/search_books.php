<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$query = isset($_GET['q']) ? sanitize_input($_GET['q']) : '';
$available_only = isset($_GET['available_only']) && $_GET['available_only'] == '1';

if (strlen($query) < 2) {
    echo json_encode(['success' => false, 'message' => 'Query too short']);
    exit();
}

try {
    $where_conditions = ['b.is_active = 1'];
    $params = [];
    $param_types = '';
    
    // Add search condition
    $where_conditions[] = "(b.title LIKE ? OR b.isbn LIKE ? OR a.author_name LIKE ?)";
    $search_term = "%$query%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $param_types .= 'sss';
    
    // Add availability condition
    if ($available_only) {
        $where_conditions[] = "b.available_copies > 0";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $stmt = $db->prepare("
        SELECT b.book_id, b.isbn, b.title, b.available_copies, b.total_copies, b.book_image,
               a.author_name, c.category_name
        FROM books b 
        LEFT JOIN authors a ON b.author_id = a.author_id 
        LEFT JOIN categories c ON b.category_id = c.category_id 
        WHERE $where_clause
        ORDER BY b.title 
        LIMIT 10
    ");
    
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $books = [];
    
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'books' => $books
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Search failed'
    ]);
}
?>