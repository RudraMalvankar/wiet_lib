<?php
require_once '../../includes/config.php';
require_admin();

$page_title = 'Issue Book';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_search = sanitize_input($_POST['student_search']);
    $book_search = sanitize_input($_POST['book_search']);
    $loan_period = (int)$_POST['loan_period'];
    
    $errors = [];
    
    // Validate inputs
    if (empty($student_search)) $errors[] = 'Please select a student.';
    if (empty($book_search)) $errors[] = 'Please select a book.';
    if ($loan_period < 1 || $loan_period > 30) $errors[] = 'Loan period must be between 1 and 30 days.';
    
    if (empty($errors)) {
        // Find student
        $student_stmt = $db->prepare("
            SELECT student_id, full_name, student_number 
            FROM students 
            WHERE (student_number = ? OR full_name LIKE ?) AND is_active = 1
        ");
        $student_like = "%$student_search%";
        $student_stmt->bind_param("ss", $student_search, $student_like);
        $student_stmt->execute();
        $student_result = $student_stmt->get_result();
        
        if ($student_result->num_rows === 0) {
            $errors[] = 'Student not found.';
        } else {
            $student = $student_result->fetch_assoc();
            $student_id = $student['student_id'];
            
            // Check if student has reached maximum book limit
            $limit_check = $db->prepare("
                SELECT COUNT(*) as current_books 
                FROM book_issues 
                WHERE student_id = ? AND status = 'issued'
            ");
            $limit_check->bind_param("i", $student_id);
            $limit_check->execute();
            $current_books = $limit_check->get_result()->fetch_assoc()['current_books'];
            
            // Get maximum books limit from settings
            $max_books = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'max_books_per_student'")->fetch_assoc()['setting_value'] ?? 5;
            
            if ($current_books >= $max_books) {
                $errors[] = "Student has reached the maximum limit of $max_books books.";
            }
            
            // Check for pending fines
            $fine_check = $db->prepare("
                SELECT SUM(amount) as total_fines 
                FROM fines 
                WHERE student_id = ? AND status = 'pending'
            ");
            $fine_check->bind_param("i", $student_id);
            $fine_check->execute();
            $pending_fines = $fine_check->get_result()->fetch_assoc()['total_fines'] ?? 0;
            
            if ($pending_fines > 0) {
                $errors[] = "Student has pending fines of " . format_currency($pending_fines) . ". Please clear dues before issuing books.";
            }
        }
        
        if (empty($errors)) {
            // Find book
            $book_stmt = $db->prepare("
                SELECT book_id, title, isbn, available_copies 
                FROM books 
                WHERE (isbn = ? OR title LIKE ?) AND is_active = 1 AND available_copies > 0
            ");
            $book_like = "%$book_search%";
            $book_stmt->bind_param("ss", $book_search, $book_like);
            $book_stmt->execute();
            $book_result = $book_stmt->get_result();
            
            if ($book_result->num_rows === 0) {
                $errors[] = 'Book not found or not available.';
            } else {
                $book = $book_result->fetch_assoc();
                $book_id = $book['book_id'];
                
                // Check if student already has this book
                $duplicate_check = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM book_issues 
                    WHERE student_id = ? AND book_id = ? AND status = 'issued'
                ");
                $duplicate_check->bind_param("ii", $student_id, $book_id);
                $duplicate_check->execute();
                $has_book = $duplicate_check->get_result()->fetch_assoc()['count'] > 0;
                
                if ($has_book) {
                    $errors[] = 'Student already has this book issued.';
                }
            }
        }
        
        if (empty($errors)) {
            // Issue the book
            $issue_date = date('Y-m-d');
            $due_date = date('Y-m-d', strtotime("+$loan_period days"));
            $issued_by = $_SESSION['user_id'];
            
            $db->autocommit(false);
            
            try {
                // Insert book issue record
                $issue_stmt = $db->prepare("
                    INSERT INTO book_issues (book_id, student_id, issue_date, due_date, issued_by) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $issue_stmt->bind_param("iissi", $book_id, $student_id, $issue_date, $due_date, $issued_by);
                $issue_stmt->execute();
                
                // Update available copies
                $update_stmt = $db->prepare("
                    UPDATE books 
                    SET available_copies = available_copies - 1 
                    WHERE book_id = ?
                ");
                $update_stmt->bind_param("i", $book_id);
                $update_stmt->execute();
                
                // Add notification for student
                add_notification('student', $student_id, 'Book Issued', 
                    "Book '{$book['title']}' has been issued to you. Due date: " . format_date($due_date, 'M j, Y'), 
                    'success');
                
                $db->commit();
                $success_message = "Book '{$book['title']}' successfully issued to {$student['full_name']} (Student Number: {$student['student_number']}). Due date: " . format_date($due_date, 'M j, Y');
                
                // Clear form data
                $_POST = [];
                
            } catch (Exception $e) {
                $db->rollback();
                $errors[] = 'Error issuing book. Please try again.';
            }
            
            $db->autocommit(true);
        }
    }
    
    if (!empty($errors)) {
        $error_message = implode('<br>', $errors);
    }
}

// Get default loan period from settings
$default_loan_period = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'loan_period_days'")->fetch_assoc()['setting_value'] ?? 14;

include '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Issue Book</h1>
            <p class="text-muted">Issue books to students</p>
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

    <div class="row">
        <div class="col-lg-8">
            <!-- Issue Book Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-hand-holding me-2"></i>Issue Book Form
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="student_search" class="form-label">Search Student <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="student_search" name="student_search" 
                                       value="<?php echo isset($_POST['student_search']) ? htmlspecialchars($_POST['student_search']) : ''; ?>" 
                                       placeholder="Enter student number or name..." 
                                       required autocomplete="off">
                                <div class="invalid-feedback">Please search and select a student.</div>
                                <div id="student-suggestions" class="dropdown-menu w-100" style="display: none;"></div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="book_search" class="form-label">Search Book <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="book_search" name="book_search" 
                                       value="<?php echo isset($_POST['book_search']) ? htmlspecialchars($_POST['book_search']) : ''; ?>" 
                                       placeholder="Enter ISBN or book title..." 
                                       required autocomplete="off">
                                <div class="invalid-feedback">Please search and select a book.</div>
                                <div id="book-suggestions" class="dropdown-menu w-100" style="display: none;"></div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="loan_period" class="form-label">Loan Period (Days) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="loan_period" name="loan_period" 
                                       value="<?php echo isset($_POST['loan_period']) ? $_POST['loan_period'] : $default_loan_period; ?>" 
                                       min="1" max="30" required>
                                <div class="invalid-feedback">Please enter a valid loan period (1-30 days).</div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Issue Date</label>
                                <input type="text" class="form-control" value="<?php echo date('Y-m-d'); ?>" readonly>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Due Date</label>
                                <input type="text" class="form-control" id="due_date_display" readonly>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-1"></i>Reset Form
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-hand-holding me-1"></i>Issue Book
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Recent Issues -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-clock me-2"></i>Recent Issues Today
                    </h6>
                </div>
                <div class="card-body">
                    <?php
                    $recent_issues = $db->query("
                        SELECT bi.*, b.title, s.full_name, s.student_number
                        FROM book_issues bi
                        JOIN books b ON bi.book_id = b.book_id
                        JOIN students s ON bi.student_id = s.student_id
                        WHERE DATE(bi.created_at) = CURDATE()
                        ORDER BY bi.created_at DESC
                        LIMIT 5
                    ")->fetch_all(MYSQLI_ASSOC);
                    ?>
                    
                    <?php if (empty($recent_issues)): ?>
                        <p class="text-muted text-center">No books issued today.</p>
                    <?php else: ?>
                        <?php foreach ($recent_issues as $issue): ?>
                            <div class="mb-3 p-3 bg-light rounded">
                                <h6 class="mb-1"><?php echo htmlspecialchars($issue['title']); ?></h6>
                                <small class="text-muted">
                                    Issued to: <?php echo htmlspecialchars($issue['full_name']); ?><br>
                                    Student: <?php echo $issue['student_number']; ?><br>
                                    Due: <?php echo format_date($issue['due_date'], 'M j, Y'); ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i>Quick Stats
                    </h6>
                </div>
                <div class="card-body">
                    <?php
                    $stats = [];
                    $stats['issued_today'] = $db->query("SELECT COUNT(*) as count FROM book_issues WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];
                    $stats['total_issued'] = $db->query("SELECT COUNT(*) as count FROM book_issues WHERE status = 'issued'")->fetch_assoc()['count'];
                    $stats['available_books'] = $db->query("SELECT SUM(available_copies) as count FROM books WHERE is_active = 1")->fetch_assoc()['count'] ?? 0;
                    ?>
                    
                    <div class="row text-center">
                        <div class="col-12 mb-3">
                            <div class="bg-primary text-white p-3 rounded">
                                <h4 class="mb-0"><?php echo $stats['issued_today']; ?></h4>
                                <small>Issued Today</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-warning text-white p-2 rounded">
                                <h5 class="mb-0"><?php echo $stats['total_issued']; ?></h5>
                                <small>Currently Issued</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-success text-white p-2 rounded">
                                <h5 class="mb-0"><?php echo $stats['available_books']; ?></h5>
                                <small>Available Books</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-calculate due date
function updateDueDate() {
    const loanPeriod = parseInt(document.getElementById('loan_period').value) || 0;
    const issueDate = new Date();
    const dueDate = new Date(issueDate.getTime() + (loanPeriod * 24 * 60 * 60 * 1000));
    
    document.getElementById('due_date_display').value = dueDate.toISOString().split('T')[0];
}

document.getElementById('loan_period').addEventListener('input', updateDueDate);
window.addEventListener('load', updateDueDate);

// Student search with autocomplete
let studentTimeout;
document.getElementById('student_search').addEventListener('input', function() {
    const query = this.value.trim();
    
    clearTimeout(studentTimeout);
    
    if (query.length >= 2) {
        studentTimeout = setTimeout(() => {
            fetch(`../../api/search_students.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    const suggestions = document.getElementById('student-suggestions');
                    suggestions.innerHTML = '';
                    
                    if (data.success && data.students.length > 0) {
                        data.students.forEach(student => {
                            const item = document.createElement('a');
                            item.className = 'dropdown-item';
                            item.href = '#';
                            item.innerHTML = `
                                <strong>${student.full_name}</strong><br>
                                <small class="text-muted">Student Number: ${student.student_number}</small><br>
                                <small class="text-muted">Course: ${student.course || 'N/A'}</small>
                            `;
                            item.addEventListener('click', (e) => {
                                e.preventDefault();
                                document.getElementById('student_search').value = student.student_number;
                                suggestions.style.display = 'none';
                            });
                            suggestions.appendChild(item);
                        });
                        suggestions.style.display = 'block';
                    } else {
                        suggestions.style.display = 'none';
                    }
                });
        }, 300);
    } else {
        document.getElementById('student-suggestions').style.display = 'none';
    }
});

// Book search with autocomplete
let bookTimeout;
document.getElementById('book_search').addEventListener('input', function() {
    const query = this.value.trim();
    
    clearTimeout(bookTimeout);
    
    if (query.length >= 2) {
        bookTimeout = setTimeout(() => {
            fetch(`../../api/search_books.php?q=${encodeURIComponent(query)}&available_only=1`)
                .then(response => response.json())
                .then(data => {
                    const suggestions = document.getElementById('book-suggestions');
                    suggestions.innerHTML = '';
                    
                    if (data.success && data.books.length > 0) {
                        data.books.forEach(book => {
                            const item = document.createElement('a');
                            item.className = 'dropdown-item';
                            item.href = '#';
                            item.innerHTML = `
                                <strong>${book.title}</strong><br>
                                <small class="text-muted">ISBN: ${book.isbn || 'N/A'}</small><br>
                                <small class="text-muted">Available: ${book.available_copies}/${book.total_copies}</small>
                            `;
                            item.addEventListener('click', (e) => {
                                e.preventDefault();
                                document.getElementById('book_search').value = book.isbn || book.title;
                                suggestions.style.display = 'none';
                            });
                            suggestions.appendChild(item);
                        });
                        suggestions.style.display = 'block';
                    } else {
                        suggestions.style.display = 'none';
                    }
                });
        }, 300);
    } else {
        document.getElementById('book-suggestions').style.display = 'none';
    }
});

// Hide suggestions when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('#student_search') && !e.target.closest('#student-suggestions')) {
        document.getElementById('student-suggestions').style.display = 'none';
    }
    if (!e.target.closest('#book_search') && !e.target.closest('#book-suggestions')) {
        document.getElementById('book-suggestions').style.display = 'none';
    }
});

// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

<?php include '../../includes/footer.php'; ?>