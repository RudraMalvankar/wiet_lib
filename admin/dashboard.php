<?php
require_once '../includes/config.php';
require_admin();

$page_title = 'Admin Dashboard';

// Get dashboard statistics
$stats = [];

// Total books
$result = $db->query("SELECT COUNT(*) as total FROM books WHERE is_active = 1");
$stats['total_books'] = $result->fetch_assoc()['total'];

// Available books
$result = $db->query("SELECT SUM(available_copies) as available FROM books WHERE is_active = 1");
$stats['available_books'] = $result->fetch_assoc()['available'] ?? 0;

// Total students
$result = $db->query("SELECT COUNT(*) as total FROM students WHERE is_active = 1");
$stats['total_students'] = $result->fetch_assoc()['total'];

// Issued books
$result = $db->query("SELECT COUNT(*) as issued FROM book_issues WHERE status = 'issued'");
$stats['issued_books'] = $result->fetch_assoc()['issued'];

// Overdue books
$result = $db->query("SELECT COUNT(*) as overdue FROM book_issues WHERE status = 'issued' AND due_date < CURDATE()");
$stats['overdue_books'] = $result->fetch_assoc()['overdue'];

// Recent activities
$recent_issues = $db->query("
    SELECT bi.*, b.title, b.isbn, s.full_name as student_name, s.student_number 
    FROM book_issues bi 
    JOIN books b ON bi.book_id = b.book_id 
    JOIN students s ON bi.student_id = s.student_id 
    ORDER BY bi.created_at DESC 
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Popular books
$popular_books = $db->query("
    SELECT b.title, b.isbn, COUNT(bi.issue_id) as issue_count,
           a.author_name, c.category_name
    FROM books b 
    LEFT JOIN book_issues bi ON b.book_id = bi.book_id 
    LEFT JOIN authors a ON b.author_id = a.author_id
    LEFT JOIN categories c ON b.category_id = c.category_id
    WHERE b.is_active = 1
    GROUP BY b.book_id 
    ORDER BY issue_count DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="container-fluid">
    <!-- Flash Messages -->
    <?php $flash_messages = get_flash_messages(); ?>
    <?php if (!empty($flash_messages)): ?>
        <div class="alert-container">
            <?php foreach ($flash_messages as $message): ?>
                <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Admin Dashboard</h1>
            <p class="text-muted">Welcome back, <?php echo $_SESSION['full_name']; ?>!</p>
        </div>
        <div>
            <span class="text-muted">
                <i class="fas fa-clock me-1"></i>
                <?php echo date('l, F j, Y - g:i A'); ?>
            </span>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card dashboard-card">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['total_books']); ?></div>
                    <div class="stat-label">Total Books</div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card dashboard-card success">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['available_books']); ?></div>
                    <div class="stat-label">Available Books</div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card dashboard-card">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['total_students']); ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card dashboard-card warning">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-hand-holding"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($stats['issued_books']); ?></div>
                    <div class="stat-label">Issued Books</div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($stats['overdue_books'] > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Attention:</strong> There are <strong><?php echo $stats['overdue_books']; ?></strong> overdue books that need immediate attention.
                <a href="circulation/overdue.php" class="btn btn-warning btn-sm ms-2">View Overdue Books</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Recent Activities -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-clock me-2"></i>Recent Book Issues
                    </h5>
                    <a href="circulation/issue.php" class="btn btn-primary btn-sm">Issue New Book</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_issues)): ?>
                        <p class="text-muted text-center py-4">No recent book issues found.</p>
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
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_issues as $issue): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($issue['student_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo $issue['student_number']; ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($issue['title']); ?></strong>
                                                    <br><small class="text-muted">ISBN: <?php echo $issue['isbn']; ?></small>
                                                </div>
                                            </td>
                                            <td><?php echo format_date($issue['issue_date'], 'M j, Y'); ?></td>
                                            <td>
                                                <?php
                                                $due_date = new DateTime($issue['due_date']);
                                                $today = new DateTime();
                                                $is_overdue = $due_date < $today;
                                                ?>
                                                <span class="<?php echo $is_overdue ? 'text-danger fw-bold' : ''; ?>">
                                                    <?php echo format_date($issue['due_date'], 'M j, Y'); ?>
                                                    <?php if ($is_overdue): ?>
                                                        <i class="fas fa-exclamation-triangle ms-1"></i>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($issue['status'] === 'issued' && $is_overdue): ?>
                                                    <span class="badge bg-danger">Overdue</span>
                                                <?php elseif ($issue['status'] === 'issued'): ?>
                                                    <span class="badge bg-success">Issued</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?php echo ucfirst($issue['status']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($issue['status'] === 'issued'): ?>
                                                    <a href="circulation/return.php?issue_id=<?php echo $issue['issue_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">Return</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Popular Books -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-star me-2"></i>Popular Books
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($popular_books)): ?>
                        <p class="text-muted text-center py-4">No data available.</p>
                    <?php else: ?>
                        <?php foreach ($popular_books as $index => $book): ?>
                            <div class="d-flex align-items-center mb-3 <?php echo $index < count($popular_books) - 1 ? 'border-bottom pb-3' : ''; ?>">
                                <div class="flex-shrink-0">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width: 30px; height: 30px; font-size: 14px; font-weight: bold;">
                                        <?php echo $index + 1; ?>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($book['title']); ?></h6>
                                    <small class="text-muted">
                                        by <?php echo htmlspecialchars($book['author_name']); ?>
                                    </small>
                                    <div class="small text-muted">
                                        Issues: <span class="fw-bold"><?php echo $book['issue_count']; ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt me-2"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="books/add.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-plus-circle me-2"></i>Add New Book
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="students/add.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-user-plus me-2"></i>Add Student
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="circulation/issue.php" class="btn btn-outline-warning w-100">
                                <i class="fas fa-hand-holding me-2"></i>Issue Book
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="circulation/return.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-undo me-2"></i>Return Book
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>