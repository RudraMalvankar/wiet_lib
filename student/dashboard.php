<?php
require_once '../includes/config.php';
require_student();

$page_title = 'Student Dashboard';
$student_id = $_SESSION['user_id'];

// Get student statistics
$stats = [];

// Currently issued books
$result = $db->query("SELECT COUNT(*) as count FROM book_issues WHERE student_id = $student_id AND status = 'issued'");
$stats['current_books'] = $result->fetch_assoc()['count'];

// Total books read (returned)
$result = $db->query("SELECT COUNT(*) as count FROM book_issues WHERE student_id = $student_id AND status = 'returned'");
$stats['books_read'] = $result->fetch_assoc()['count'];

// Active reservations
$result = $db->query("SELECT COUNT(*) as count FROM reservations WHERE student_id = $student_id AND status = 'active'");
$stats['reservations'] = $result->fetch_assoc()['count'];

// Pending fines
$result = $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM fines WHERE student_id = $student_id AND status = 'pending'");
$stats['pending_fines'] = $result->fetch_assoc()['total'];

// Current issued books with details
$current_books = $db->query("
    SELECT bi.*, b.title, b.isbn, b.book_image, a.author_name, c.category_name,
           DATEDIFF(bi.due_date, CURDATE()) as days_remaining
    FROM book_issues bi
    JOIN books b ON bi.book_id = b.book_id
    LEFT JOIN authors a ON b.author_id = a.author_id
    LEFT JOIN categories c ON b.category_id = c.category_id
    WHERE bi.student_id = $student_id AND bi.status = 'issued'
    ORDER BY bi.due_date ASC
")->fetch_all(MYSQLI_ASSOC);

// Recommended books (books from same categories as previously read)
$recommended_books = $db->query("
    SELECT DISTINCT b.*, a.author_name, c.category_name
    FROM books b
    LEFT JOIN authors a ON b.author_id = a.author_id
    LEFT JOIN categories c ON b.category_id = c.category_id
    WHERE b.category_id IN (
        SELECT DISTINCT b2.category_id 
        FROM book_issues bi2 
        JOIN books b2 ON bi2.book_id = b2.book_id 
        WHERE bi2.student_id = $student_id
    )
    AND b.book_id NOT IN (
        SELECT book_id FROM book_issues WHERE student_id = $student_id
    )
    AND b.available_copies > 0 AND b.is_active = 1
    ORDER BY RAND()
    LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

// Recent activities
$recent_activities = $db->query("
    SELECT 'issue' as activity_type, bi.created_at, b.title, bi.issue_date as activity_date
    FROM book_issues bi
    JOIN books b ON bi.book_id = b.book_id
    WHERE bi.student_id = $student_id
    
    UNION ALL
    
    SELECT 'return' as activity_type, bi.updated_at as created_at, b.title, bi.return_date as activity_date
    FROM book_issues bi
    JOIN books b ON bi.book_id = b.book_id
    WHERE bi.student_id = $student_id AND bi.status = 'returned'
    
    ORDER BY created_at DESC
    LIMIT 10
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
            <h1 class="h3 mb-0">My Dashboard</h1>
            <p class="text-muted">Welcome back, <?php echo $_SESSION['full_name']; ?>!</p>
        </div>
        <div>
            <a href="digital-id.php" class="btn btn-primary">
                <i class="fas fa-id-card me-1"></i>View Digital ID
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card dashboard-card">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-book-reader"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['current_books']; ?></div>
                    <div class="stat-label">Current Books</div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card dashboard-card success">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['books_read']; ?></div>
                    <div class="stat-label">Books Read</div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card dashboard-card warning">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-bookmark"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['reservations']; ?></div>
                    <div class="stat-label">Reservations</div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card dashboard-card <?php echo $stats['pending_fines'] > 0 ? 'danger' : ''; ?>">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                    <div class="stat-number"><?php echo format_currency($stats['pending_fines']); ?></div>
                    <div class="stat-label">Pending Fines</div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($stats['pending_fines'] > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Notice:</strong> You have pending fines of <strong><?php echo format_currency($stats['pending_fines']); ?></strong>. 
                Please contact the library to clear your dues.
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Current Books -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-book-open me-2"></i>My Current Books
                    </h5>
                    <a href="search.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-search me-1"></i>Find More Books
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($current_books)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-book-open text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-3">You don't have any books currently issued.</p>
                            <a href="search.php" class="btn btn-primary">Browse Books</a>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($current_books as $book): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card book-card h-100">
                                        <div class="row g-0">
                                            <div class="col-4">
                                                <img src="<?php echo $book['book_image'] ? 'uploads/' . $book['book_image'] : '../assets/images/default-book.jpg'; ?>" 
                                                     class="img-fluid h-100 book-image" alt="<?php echo htmlspecialchars($book['title']); ?>">
                                            </div>
                                            <div class="col-8">
                                                <div class="card-body">
                                                    <h6 class="card-title"><?php echo htmlspecialchars($book['title']); ?></h6>
                                                    <p class="card-text">
                                                        <small class="text-muted">by <?php echo htmlspecialchars($book['author_name']); ?></small><br>
                                                        <small class="text-muted">Category: <?php echo htmlspecialchars($book['category_name']); ?></small>
                                                    </p>
                                                    <div class="mt-2">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="badge <?php echo $book['days_remaining'] < 0 ? 'bg-danger' : ($book['days_remaining'] <= 3 ? 'bg-warning' : 'bg-success'); ?>">
                                                                <?php 
                                                                if ($book['days_remaining'] < 0) {
                                                                    echo abs($book['days_remaining']) . ' days overdue';
                                                                } elseif ($book['days_remaining'] == 0) {
                                                                    echo 'Due today';
                                                                } else {
                                                                    echo $book['days_remaining'] . ' days left';
                                                                }
                                                                ?>
                                                            </span>
                                                        </div>
                                                        <small class="text-muted">
                                                            Due: <?php echo format_date($book['due_date'], 'M j, Y'); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>Recent Activities
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_activities)): ?>
                        <p class="text-muted text-center py-4">No recent activities.</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="timeline-item">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <i class="fas <?php echo $activity['activity_type'] === 'issue' ? 'fa-download text-primary' : 'fa-upload text-success'; ?>"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-1">
                                                <?php echo $activity['activity_type'] === 'issue' ? 'Issued' : 'Returned'; ?>: 
                                                <?php echo htmlspecialchars($activity['title']); ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?php echo format_date($activity['activity_date'], 'M j, Y'); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recommended Books -->
    <?php if (!empty($recommended_books)): ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-thumbs-up me-2"></i>Recommended for You
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($recommended_books as $book): ?>
                            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                                <div class="card book-card h-100">
                                    <div class="position-relative">
                                        <img src="<?php echo $book['book_image'] ? 'uploads/' . $book['book_image'] : '../assets/images/default-book.jpg'; ?>" 
                                             class="card-img-top book-image" alt="<?php echo htmlspecialchars($book['title']); ?>">
                                        <span class="book-status available">Available</span>
                                    </div>
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($book['title']); ?></h6>
                                        <p class="card-text">
                                            <small class="text-muted">by <?php echo htmlspecialchars($book['author_name']); ?></small><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($book['category_name']); ?></small>
                                        </p>
                                        <button class="btn btn-primary btn-sm w-100 reserve-book" data-id="<?php echo $book['book_id']; ?>">
                                            Reserve
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="row mt-4">
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
                            <a href="search.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-search me-2"></i>Search Books
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="my-books.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-book-reader me-2"></i>My Books
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="history.php" class="btn btn-outline-warning w-100">
                                <i class="fas fa-history me-2"></i>Reading History
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="profile.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-user me-2"></i>My Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>