<?php
require_once '../includes/config.php';
require_student();

$page_title = 'Search Books';

// Search parameters
$query = isset($_GET['q']) ? sanitize_input($_GET['q']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$sort_by = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'title';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$records_per_page = 12;
$offset = ($page - 1) * $records_per_page;

// Get categories for filter
$categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);

// Build search query
$where_conditions = ['b.is_active = 1'];
$search_params = [];
$param_types = '';

if (!empty($query)) {
    $where_conditions[] = "(b.title LIKE ? OR b.isbn LIKE ? OR a.author_name LIKE ? OR b.description LIKE ?)";
    $search_term = "%$query%";
    $search_params = array_merge($search_params, [$search_term, $search_term, $search_term, $search_term]);
    $param_types .= 'ssss';
}

if ($category_filter > 0) {
    $where_conditions[] = "b.category_id = ?";
    $search_params[] = $category_filter;
    $param_types .= 'i';
}

$where_clause = implode(' AND ', $where_conditions);

// Determine sort order
$order_clause = 'b.title ASC';
switch ($sort_by) {
    case 'author':
        $order_clause = 'a.author_name ASC';
        break;
    case 'newest':
        $order_clause = 'b.created_at DESC';
        break;
    case 'popular':
        $order_clause = 'issue_count DESC, b.title ASC';
        break;
    default:
        $order_clause = 'b.title ASC';
}

// Count total records
$count_sql = "
    SELECT COUNT(*) as total 
    FROM books b 
    LEFT JOIN authors a ON b.author_id = a.author_id 
    LEFT JOIN categories c ON b.category_id = c.category_id 
    WHERE $where_clause
";

$count_stmt = $db->prepare($count_sql);
if (!empty($search_params)) {
    $count_stmt->bind_param($param_types, ...$search_params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get books
$books_sql = "
    SELECT b.*, a.author_name, c.category_name,
           (SELECT COUNT(*) FROM book_issues WHERE book_id = b.book_id) as issue_count,
           (SELECT COUNT(*) FROM reservations WHERE book_id = b.book_id AND student_id = ? AND status = 'active') as is_reserved
    FROM books b 
    LEFT JOIN authors a ON b.author_id = a.author_id 
    LEFT JOIN categories c ON b.category_id = c.category_id 
    WHERE $where_clause
    ORDER BY $order_clause
    LIMIT ? OFFSET ?
";

$student_id = $_SESSION['user_id'];
$books_params = [$student_id];
$books_param_types = 'i';
$books_params = array_merge($books_params, $search_params);
$books_param_types .= $param_types;
$books_params[] = $records_per_page;
$books_params[] = $offset;
$books_param_types .= 'ii';

$books_stmt = $db->prepare($books_sql);
if (!empty($books_params)) {
    $books_stmt->bind_param($books_param_types, ...$books_params);
}
$books_stmt->execute();
$books = $books_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Search Books</h1>
            <p class="text-muted">Discover and reserve books from our collection</p>
        </div>
        <div>
            <span class="text-muted">
                <?php echo number_format($total_records); ?> book(s) found
            </span>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-5">
                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="form-control search-input" name="q" 
                               placeholder="Search by title, author, ISBN, or description..." 
                               value="<?php echo htmlspecialchars($query); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>" 
                                    <?php echo $category_filter == $category['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="sort">
                        <option value="title" <?php echo $sort_by === 'title' ? 'selected' : ''; ?>>Sort by Title</option>
                        <option value="author" <?php echo $sort_by === 'author' ? 'selected' : ''; ?>>Sort by Author</option>
                        <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="popular" <?php echo $sort_by === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>Search
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Books Grid -->
    <?php if (empty($books)): ?>
        <div class="text-center py-5">
            <i class="fas fa-book text-muted" style="font-size: 4rem;"></i>
            <h4 class="text-muted mt-3">No books found</h4>
            <p class="text-muted">
                <?php if (!empty($query) || $category_filter > 0): ?>
                    Try adjusting your search criteria or browse all books.
                    <br><a href="search.php" class="btn btn-primary mt-2">Browse All Books</a>
                <?php else: ?>
                    The library collection is currently empty.
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <div class="row" id="books-grid">
            <?php foreach ($books as $book): ?>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-4">
                    <div class="card book-card h-100">
                        <div class="position-relative">
                            <img src="<?php echo $book['book_image'] ? '../uploads/' . $book['book_image'] : '../assets/images/default-book.jpg'; ?>" 
                                 class="card-img-top book-image" alt="<?php echo htmlspecialchars($book['title']); ?>">
                            <span class="book-status <?php echo $book['available_copies'] > 0 ? 'available' : 'unavailable'; ?>">
                                <?php echo $book['available_copies'] > 0 ? 'Available' : 'Not Available'; ?>
                            </span>
                            <?php if ($book['is_reserved'] > 0): ?>
                                <span class="badge bg-warning position-absolute top-0 start-0 m-2">Reserved</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h6 class="card-title" title="<?php echo htmlspecialchars($book['title']); ?>">
                                <?php echo htmlspecialchars(strlen($book['title']) > 50 ? substr($book['title'], 0, 47) . '...' : $book['title']); ?>
                            </h6>
                            <p class="card-text flex-grow-1">
                                <small class="text-muted">by <?php echo htmlspecialchars($book['author_name'] ?? 'Unknown'); ?></small><br>
                                <small class="text-muted"><?php echo htmlspecialchars($book['category_name'] ?? 'Uncategorized'); ?></small><br>
                                <small class="text-muted">Available: <?php echo $book['available_copies']; ?>/<?php echo $book['total_copies']; ?></small>
                            </p>
                            <div class="mt-auto">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-info btn-sm view-book" data-id="<?php echo $book['book_id']; ?>">
                                        <i class="fas fa-eye me-1"></i>View Details
                                    </button>
                                    <?php if ($book['available_copies'] > 0): ?>
                                        <?php if ($book['is_reserved'] > 0): ?>
                                            <button class="btn btn-warning btn-sm" disabled>
                                                <i class="fas fa-bookmark me-1"></i>Already Reserved
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-success btn-sm reserve-book" data-id="<?php echo $book['book_id']; ?>">
                                                <i class="fas fa-bookmark me-1"></i>Reserve
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm" disabled>
                                            <i class="fas fa-times me-1"></i>Not Available
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&q=<?php echo urlencode($query); ?>&category=<?php echo $category_filter; ?>&sort=<?php echo $sort_by; ?>">
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
                            <a class="page-link" href="?page=<?php echo $i; ?>&q=<?php echo urlencode($query); ?>&category=<?php echo $category_filter; ?>&sort=<?php echo $sort_by; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&q=<?php echo urlencode($query); ?>&category=<?php echo $category_filter; ?>&sort=<?php echo $sort_by; ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Book Details Modal -->
<div class="modal fade" id="bookDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Book Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="bookDetailsContent">
                <!-- Book details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
// Book reservation
$(document).on('click', '.reserve-book', function() {
    const bookId = $(this).data('id');
    const button = $(this);
    
    if (confirm('Are you sure you want to reserve this book?')) {
        $.ajax({
            url: '../api/reservations.php',
            method: 'POST',
            data: {
                action: 'reserve',
                book_id: bookId
            },
            dataType: 'json',
            beforeSend: function() {
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Reserving...');
            },
            success: function(response) {
                if (response.success) {
                    button.removeClass('btn-success').addClass('btn-warning')
                          .html('<i class="fas fa-bookmark me-1"></i>Already Reserved')
                          .prop('disabled', true);
                    showAlert('success', 'Book reserved successfully!');
                } else {
                    showAlert('danger', response.message || 'Failed to reserve book');
                    button.prop('disabled', false).html('<i class="fas fa-bookmark me-1"></i>Reserve');
                }
            },
            error: function() {
                showAlert('danger', 'Failed to reserve book. Please try again.');
                button.prop('disabled', false).html('<i class="fas fa-bookmark me-1"></i>Reserve');
            }
        });
    }
});

// View book details
$(document).on('click', '.view-book', function() {
    const bookId = $(this).data('id');
    
    $.ajax({
        url: '../api/book_details.php',
        method: 'GET',
        data: { book_id: bookId },
        dataType: 'json',
        beforeSend: function() {
            $('#bookDetailsContent').html('<div class="text-center"><div class="spinner"></div></div>');
            $('#bookDetailsModal').modal('show');
        },
        success: function(response) {
            if (response.success) {
                $('#bookDetailsContent').html(response.html);
            } else {
                $('#bookDetailsContent').html('<div class="alert alert-danger">Failed to load book details.</div>');
            }
        },
        error: function() {
            $('#bookDetailsContent').html('<div class="alert alert-danger">Failed to load book details.</div>');
        }
    });
});

function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    $('main').prepend(alertHtml);
    
    // Auto-hide after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
}
</script>

<?php include '../includes/footer.php'; ?>