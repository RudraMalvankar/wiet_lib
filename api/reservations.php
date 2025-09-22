<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in as student
if (!is_logged_in() || $_SESSION['user_type'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$student_id = $_SESSION['user_id'];

// Handle reservation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reserve') {
    $book_id = (int)$_POST['book_id'];
    
    try {
        // Check if book exists and is available
        $book_stmt = $db->prepare("
            SELECT book_id, title, available_copies 
            FROM books 
            WHERE book_id = ? AND is_active = 1
        ");
        $book_stmt->bind_param("i", $book_id);
        $book_stmt->execute();
        $book = $book_stmt->get_result()->fetch_assoc();
        
        if (!$book) {
            echo json_encode(['success' => false, 'message' => 'Book not found']);
            exit();
        }
        
        if ($book['available_copies'] <= 0) {
            echo json_encode(['success' => false, 'message' => 'Book is not available']);
            exit();
        }
        
        // Check if student already has this book reserved
        $existing_stmt = $db->prepare("
            SELECT reservation_id 
            FROM reservations 
            WHERE book_id = ? AND student_id = ? AND status = 'active'
        ");
        $existing_stmt->bind_param("ii", $book_id, $student_id);
        $existing_stmt->execute();
        
        if ($existing_stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'You have already reserved this book']);
            exit();
        }
        
        // Check if student already has this book issued
        $issued_stmt = $db->prepare("
            SELECT issue_id 
            FROM book_issues 
            WHERE book_id = ? AND student_id = ? AND status = 'issued'
        ");
        $issued_stmt->bind_param("ii", $book_id, $student_id);
        $issued_stmt->execute();
        
        if ($issued_stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'You already have this book issued']);
            exit();
        }
        
        // Create reservation
        $reserve_stmt = $db->prepare("
            INSERT INTO reservations (book_id, student_id, reservation_date) 
            VALUES (?, ?, CURDATE())
        ");
        $reserve_stmt->bind_param("ii", $book_id, $student_id);
        
        if ($reserve_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Book reserved successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to reserve book']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'An error occurred while reserving the book']);
    }
    
    exit();
}

// Handle cancel reservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $reservation_id = (int)$_POST['reservation_id'];
    
    try {
        $cancel_stmt = $db->prepare("
            UPDATE reservations 
            SET status = 'cancelled' 
            WHERE reservation_id = ? AND student_id = ? AND status = 'active'
        ");
        $cancel_stmt->bind_param("ii", $reservation_id, $student_id);
        
        if ($cancel_stmt->execute() && $cancel_stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Reservation cancelled successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Reservation not found or already cancelled']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'An error occurred while cancelling the reservation']);
    }
    
    exit();
}

// Get student's reservations
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $db->prepare("
            SELECT r.*, b.title, b.isbn, a.author_name, c.category_name
            FROM reservations r
            JOIN books b ON r.book_id = b.book_id
            LEFT JOIN authors a ON b.author_id = a.author_id
            LEFT JOIN categories c ON b.category_id = c.category_id
            WHERE r.student_id = ? AND r.status = 'active'
            ORDER BY r.reservation_date DESC
        ");
        
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $reservations = [];
        
        while ($row = $result->fetch_assoc()) {
            $reservations[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'reservations' => $reservations
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to load reservations'
        ]);
    }
}
?>