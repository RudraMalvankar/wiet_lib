<?php
require_once '../../includes/config.php';
require_admin();

$page_title = 'Books List';

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $book_id = (int)$_GET['delete'];
    
    // Check if book has any active issues
    $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM book_issues WHERE book_id = ? AND status = 'issued'");
    $check_stmt->bind_param("i", $book_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $has_active_issues = $check_result->fetch_assoc()['count'] > 0;
    
    if ($has_active_issues) {
        flash_message('danger', 'Cannot delete book. It has active issues.');
    } else {
        // Soft delete
        $delete_stmt = $db->prepare("UPDATE books SET is_active = 0 WHERE book_id = ?");
        $delete_stmt->bind_param("i", $book_id);
        
        if ($delete_stmt->execute()) {
            flash_message('success', 'Book deleted successfully.');
        } else {
            flash_message('danger', 'Error deleting book.');
        }
    }
    
    header('Location: list.php');
    exit();
}

// Pagination and search
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$records_per_page = 20;
$offset = ($page - 1) * $records_per_page;

// Build search query
$where_conditions = ['b.is_active = 1'];
$search_params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(b.title LIKE ? OR b.isbn LIKE ? OR a.author_name LIKE ?)";
    $search_term = "%$search%";
    $search_params = array_merge($search_params, [$search_term, $search_term, $search_term]);
    $param_types .= 'sss';
}

if ($category_filter > 0) {
    $where_conditions[] = "b.category_id = ?";
    $search_params[] = $category_filter;
    $param_types .= 'i';
}

$where_clause = implode(' AND ', $where_conditions);

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
           (SELECT COUNT(*) FROM book_issues WHERE book_id = b.book_id AND status = 'issued') as issued_count
    FROM books b 
    LEFT JOIN authors a ON b.author_id = a.author_id 
    LEFT JOIN categories c ON b.category_id = c.category_id 
    WHERE $where_clause
    ORDER BY b.created_at DESC 
    LIMIT ? OFFSET ?
";

$books_stmt = $db->prepare($books_sql);
$search_params[] = $records_per_page;
$search_params[] = $offset;
$param_types .= 'ii';

if (!empty($search_params)) {
    $books_stmt->bind_param($param_types, ...$search_params);
}
$books_stmt->execute();
$books = $books_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get categories for filter
$categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);

include '../../includes/header.php';
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
            <h1 class="h3 mb-0">Books Management</h1>
            <p class="text-muted">Manage your library book collection</p>
        </div>
        <div>
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>Add New Book
            </a>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Search by title, ISBN, or author..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
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
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>Search
                        </button>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <a href="list.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Books Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-book me-2"></i>Books (<?php echo number_format($total_records); ?> total)
            </h5>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print();">
                    <i class="fas fa-print me-1"></i>Print
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportBooks();">
                    <i class="fas fa-download me-1"></i>Export
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($books)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-book text-muted" style="font-size: 3rem;"></i>
                    <h4 class="text-muted mt-3">No books found</h4>
                    <p class="text-muted">Try adjusting your search criteria or add some books to get started.</p>
                    <a href="add.php" class="btn btn-primary">Add First Book</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Book Details</th>
                                <th>Author</th>
                                <th>Category</th>
                                <th>Copies</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($books as $book): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo $book['book_image'] ? '../../uploads/' . $book['book_image'] : '../../assets/images/default-book.jpg'; ?>" 
                                                 alt="Book Cover" class="me-3" style="width: 50px; height: 70px; object-fit: cover; border-radius: 4px;">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($book['title']); ?></h6>
                                                <small class="text-muted">
                                                    ISBN: <?php echo htmlspecialchars($book['isbn'] ?? 'N/A'); ?><br>
                                                    Publisher: <?php echo htmlspecialchars($book['publisher'] ?? 'N/A'); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($book['author_name'] ?? 'Unknown'); ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo htmlspecialchars($book['category_name'] ?? 'Uncategorized'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div>
                                            <strong>Available:</strong> <?php echo $book['available_copies']; ?><br>
                                            <strong>Total:</strong> <?php echo $book['total_copies']; ?><br>
                                            <strong>Issued:</strong> <?php echo $book['issued_count']; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($book['available_copies'] > 0): ?>
                                            <span class="badge bg-success">Available</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Not Available</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view.php?id=<?php echo $book['book_id']; ?>" 
                                               class="btn btn-sm btn-outline-info" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $book['book_id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($book['issued_count'] == 0): ?>
                                                <a href="?delete=<?php echo $book['book_id']; ?>" 
                                                   class="btn btn-sm btn-outline-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this book?')" 
                                                   title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
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
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>">
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
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>">
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

<script>
function exportBooks() {
    // This would typically export to CSV or Excel
    window.open('export.php?format=csv&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>', '_blank');
}
</script>

<?php include '../../includes/footer.php'; ?>