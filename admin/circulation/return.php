<?php
require_once '../../includes/config.php';
require_admin();

$page_title = 'Return Book';

$success_message = '';
$error_message = '';

// Handle return action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'return') {
    $issue_id = (int)$_POST['issue_id'];
    $fine_amount = (float)$_POST['fine_amount'];
    $condition_notes = sanitize_input($_POST['condition_notes']);
    
    $errors = [];
    
    if ($issue_id <= 0) $errors[] = 'Invalid issue record.';
    if ($fine_amount < 0) $errors[] = 'Fine amount cannot be negative.';
    
    if (empty($errors)) {
        $db->autocommit(false);
        
        try {
            // Get issue details
            $issue_stmt = $db->prepare("
                SELECT bi.*, b.title, s.full_name, s.student_number 
                FROM book_issues bi
                JOIN books b ON bi.book_id = b.book_id
                JOIN students s ON bi.student_id = s.student_id
                WHERE bi.issue_id = ? AND bi.status = 'issued'
            ");
            $issue_stmt->bind_param("i", $issue_id);
            $issue_stmt->execute();
            $issue = $issue_stmt->get_result()->fetch_assoc();
            
            if (!$issue) {
                $errors[] = 'Issue record not found or already returned.';
            } else {
                // Update book issue record
                $return_stmt = $db->prepare("
                    UPDATE book_issues 
                    SET status = 'returned', return_date = CURDATE(), returned_to = ?, notes = ? 
                    WHERE issue_id = ?
                ");
                $returned_to = $_SESSION['user_id'];
                $return_stmt->bind_param("isi", $returned_to, $condition_notes, $issue_id);
                $return_stmt->execute();
                
                // Update book available copies
                $update_book_stmt = $db->prepare("
                    UPDATE books 
                    SET available_copies = available_copies + 1 
                    WHERE book_id = ?
                ");
                $update_book_stmt->bind_param("i", $issue['book_id']);
                $update_book_stmt->execute();
                
                // Add fine if applicable
                if ($fine_amount > 0) {
                    $fine_stmt = $db->prepare("
                        INSERT INTO fines (student_id, issue_id, amount, reason) 
                        VALUES (?, ?, ?, 'Late return fine')
                    ");
                    $fine_stmt->bind_param("iid", $issue['student_id'], $issue_id, $fine_amount);
                    $fine_stmt->execute();
                    
                    // Add notification about fine
                    add_notification('student', $issue['student_id'], 'Fine Added', 
                        "A fine of " . format_currency($fine_amount) . " has been added for late return of '{$issue['title']}'.", 
                        'warning');
                }
                
                // Add notification for successful return
                add_notification('student', $issue['student_id'], 'Book Returned', 
                    "Book '{$issue['title']}' has been successfully returned.", 
                    'success');
                
                $db->commit();
                $success_message = "Book '{$issue['title']}' successfully returned by {$issue['full_name']} (Student Number: {$issue['student_number']}).";
                
                if ($fine_amount > 0) {
                    $success_message .= " Fine of " . format_currency($fine_amount) . " has been added.";
                }
            }
            
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = 'Error processing return. Please try again.';
        }
        
        $db->autocommit(true);
    }
    
    if (!empty($errors)) {
        $error_message = implode('<br>', $errors);
    }
}

// Get specific issue if issue_id is provided
$selected_issue = null;
if (isset($_GET['issue_id']) && is_numeric($_GET['issue_id'])) {
    $issue_id = (int)$_GET['issue_id'];
    $issue_stmt = $db->prepare("
        SELECT bi.*, b.title, b.isbn, s.full_name, s.student_number,
               DATEDIFF(CURDATE(), bi.due_date) as days_overdue
        FROM book_issues bi
        JOIN books b ON bi.book_id = b.book_id
        JOIN students s ON bi.student_id = s.student_id
        WHERE bi.issue_id = ? AND bi.status = 'issued'
    ");
    $issue_stmt->bind_param("i", $issue_id);
    $issue_stmt->execute();
    $selected_issue = $issue_stmt->get_result()->fetch_assoc();
}

// Get issued books for search
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 15;
$offset = ($page - 1) * $records_per_page;

$where_clause = "bi.status = 'issued'";
$search_params = [];
$param_types = '';

if (!empty($search)) {
    $where_clause .= " AND (s.student_number LIKE ? OR s.full_name LIKE ? OR b.title LIKE ? OR b.isbn LIKE ?)";
    $search_term = "%$search%";
    $search_params = [$search_term, $search_term, $search_term, $search_term];
    $param_types = 'ssss';
}

// Count total records
$count_sql = "
    SELECT COUNT(*) as total 
    FROM book_issues bi
    JOIN books b ON bi.book_id = b.book_id
    JOIN students s ON bi.student_id = s.student_id
    WHERE $where_clause
";

$count_stmt = $db->prepare($count_sql);
if (!empty($search_params)) {
    $count_stmt->bind_param($param_types, ...$search_params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get issued books
$issues_sql = "
    SELECT bi.*, b.title, b.isbn, s.full_name, s.student_number,
           DATEDIFF(CURDATE(), bi.due_date) as days_overdue
    FROM book_issues bi
    JOIN books b ON bi.book_id = b.book_id
    JOIN students s ON bi.student_id = s.student_id
    WHERE $where_clause
    ORDER BY bi.due_date ASC, bi.issue_date ASC
    LIMIT ? OFFSET ?
";

$issues_stmt = $db->prepare($issues_sql);
$search_params[] = $records_per_page;
$search_params[] = $offset;
$param_types .= 'ii';

if (!empty($search_params)) {
    $issues_stmt->bind_param($param_types, ...$search_params);
}
$issues_stmt->execute();
$issued_books = $issues_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get fine per day from settings
$fine_per_day = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'fine_per_day'")->fetch_assoc()['setting_value'] ?? 2.00;

include '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Return Book</h1>
            <p class="text-muted">Process book returns and manage fines</p>
        </div>
        <div>
            <a href="../dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Return Form (if specific issue selected) -->
    <?php if ($selected_issue): ?>
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-undo me-2"></i>Return Book
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h6>Book Details:</h6>
                        <p><strong>Title:</strong> <?php echo htmlspecialchars($selected_issue['title']); ?></p>
                        <p><strong>ISBN:</strong> <?php echo htmlspecialchars($selected_issue['isbn']); ?></p>
                        
                        <h6>Student Details:</h6>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($selected_issue['full_name']); ?></p>
                        <p><strong>Student Number:</strong> <?php echo $selected_issue['student_number']; ?></p>
                        
                        <h6>Issue Details:</h6>
                        <p><strong>Issue Date:</strong> <?php echo format_date($selected_issue['issue_date'], 'M j, Y'); ?></p>
                        <p><strong>Due Date:</strong> <?php echo format_date($selected_issue['due_date'], 'M j, Y'); ?></p>
                        
                        <?php if ($selected_issue['days_overdue'] > 0): ?>
                            <div class="alert alert-warning">
                                <strong>Overdue:</strong> This book is <?php echo $selected_issue['days_overdue']; ?> day(s) overdue.
                                <br><strong>Suggested Fine:</strong> <?php echo format_currency($selected_issue['days_overdue'] * $fine_per_day); ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <strong>On Time:</strong> This book is being returned on time.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-4">
                        <form method="POST">
                            <input type="hidden" name="action" value="return">
                            <input type="hidden" name="issue_id" value="<?php echo $selected_issue['issue_id']; ?>">
                            
                            <div class="mb-3">
                                <label for="fine_amount" class="form-label">Fine Amount (₹)</label>
                                <input type="number" class="form-control" id="fine_amount" name="fine_amount" 
                                       value="<?php echo $selected_issue['days_overdue'] > 0 ? ($selected_issue['days_overdue'] * $fine_per_day) : '0'; ?>" 
                                       step="0.01" min="0">
                            </div>
                            
                            <div class="mb-3">
                                <label for="condition_notes" class="form-label">Condition Notes</label>
                                <textarea class="form-control" id="condition_notes" name="condition_notes" rows="3" 
                                          placeholder="Note any damage or issues with the returned book..."></textarea>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check me-1"></i>Process Return
                                </button>
                                <a href="return.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Search and Issued Books List -->
    <div class="card">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>Currently Issued Books (<?php echo number_format($total_records); ?>)
                    </h5>
                </div>
                <div class="col-md-6">
                    <form method="GET" class="d-flex">
                        <input type="text" class="form-control me-2" name="search" 
                               placeholder="Search by student, book title, or ISBN..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if (!empty($search)): ?>
                            <a href="return.php" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($issued_books)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-book-open text-muted" style="font-size: 3rem;"></i>
                    <h4 class="text-muted mt-3">No issued books found</h4>
                    <p class="text-muted">
                        <?php if (!empty($search)): ?>
                            Try adjusting your search criteria.
                        <?php else: ?>
                            All books have been returned.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Book</th>
                                <th>Issue Date</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($issued_books as $issue): ?>
                                <tr class="<?php echo $issue['days_overdue'] > 0 ? 'table-warning' : ''; ?>">
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($issue['full_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo $issue['student_number']; ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($issue['title']); ?></strong>
                                            <br><small class="text-muted">ISBN: <?php echo htmlspecialchars($issue['isbn']); ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo format_date($issue['issue_date'], 'M j, Y'); ?></td>
                                    <td>
                                        <?php echo format_date($issue['due_date'], 'M j, Y'); ?>
                                        <?php if ($issue['days_overdue'] > 0): ?>
                                            <br><span class="badge bg-danger">
                                                <?php echo $issue['days_overdue']; ?> day(s) overdue
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($issue['days_overdue'] > 0): ?>
                                            <span class="badge bg-danger">Overdue</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">On Time</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="return.php?issue_id=<?php echo $issue['issue_id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-undo me-1"></i>Return
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>