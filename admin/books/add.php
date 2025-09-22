<?php
require_once '../../includes/config.php';
require_admin();

$page_title = 'Add New Book';

// Get categories and authors for dropdown
$categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);
$authors = $db->query("SELECT * FROM authors ORDER BY author_name")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $title = sanitize_input($_POST['title']);
    $isbn = sanitize_input($_POST['isbn']);
    $author_id = !empty($_POST['author_id']) ? (int)$_POST['author_id'] : null;
    $new_author = sanitize_input($_POST['new_author']);
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $publisher = sanitize_input($_POST['publisher']);
    $publication_year = !empty($_POST['publication_year']) ? (int)$_POST['publication_year'] : null;
    $edition = sanitize_input($_POST['edition']);
    $pages = !empty($_POST['pages']) ? (int)$_POST['pages'] : null;
    $language = sanitize_input($_POST['language']);
    $description = sanitize_input($_POST['description']);
    $total_copies = (int)$_POST['total_copies'];
    $shelf_location = sanitize_input($_POST['shelf_location']);
    $price = !empty($_POST['price']) ? (float)$_POST['price'] : null;
    
    $errors = [];
    
    // Validation
    if (empty($title)) $errors[] = 'Title is required.';
    if (empty($total_copies) || $total_copies < 1) $errors[] = 'Total copies must be at least 1.';
    if (!empty($isbn)) {
        // Check if ISBN already exists
        $isbn_check = $db->prepare("SELECT book_id FROM books WHERE isbn = ? AND is_active = 1");
        $isbn_check->bind_param("s", $isbn);
        $isbn_check->execute();
        if ($isbn_check->get_result()->num_rows > 0) {
            $errors[] = 'A book with this ISBN already exists.';
        }
    }
    
    // Handle new author
    if (empty($author_id) && !empty($new_author)) {
        $author_stmt = $db->prepare("INSERT INTO authors (author_name) VALUES (?)");
        $author_stmt->bind_param("s", $new_author);
        if ($author_stmt->execute()) {
            $author_id = $db->lastInsertId();
        } else {
            $errors[] = 'Error creating new author.';
        }
    }
    
    // Handle file upload
    $book_image = null;
    if (isset($_FILES['book_image']) && $_FILES['book_image']['error'] === UPLOAD_ERR_OK) {
        $upload_result = upload_file($_FILES['book_image'], UPLOADS_PATH . '/books', ['jpg', 'jpeg', 'png', 'gif']);
        if ($upload_result['success']) {
            $book_image = 'books/' . $upload_result['file_name'];
        } else {
            $errors[] = 'Error uploading book image: ' . $upload_result['message'];
        }
    }
    
    if (empty($errors)) {
        // Insert book
        $stmt = $db->prepare("
            INSERT INTO books (isbn, title, author_id, category_id, publisher, publication_year, 
                              edition, pages, language, description, book_image, total_copies, 
                              available_copies, shelf_location, price) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $available_copies = $total_copies; // Initially all copies are available
        
        $stmt->bind_param("ssiisisssssiiss", 
            $isbn, $title, $author_id, $category_id, $publisher, $publication_year,
            $edition, $pages, $language, $description, $book_image, $total_copies,
            $available_copies, $shelf_location, $price
        );
        
        if ($stmt->execute()) {
            flash_message('success', 'Book added successfully!');
            header('Location: list.php');
            exit();
        } else {
            $errors[] = 'Error adding book to database.';
        }
    }
}

include '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Add New Book</h1>
            <p class="text-muted">Add a new book to the library collection</p>
        </div>
        <div>
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Books
            </a>
        </div>
    </div>

    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Add Book Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-book me-2"></i>Book Information
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" 
                                       required>
                                <div class="invalid-feedback">Please provide a book title.</div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="isbn" class="form-label">ISBN</label>
                                <input type="text" class="form-control" id="isbn" name="isbn" 
                                       value="<?php echo isset($_POST['isbn']) ? htmlspecialchars($_POST['isbn']) : ''; ?>" 
                                       placeholder="978-0-123456-78-9">
                                <div class="invalid-feedback">Please provide a valid ISBN.</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="author_id" class="form-label">Author</label>
                                <select class="form-select" id="author_id" name="author_id">
                                    <option value="">Select existing author</option>
                                    <?php foreach ($authors as $author): ?>
                                        <option value="<?php echo $author['author_id']; ?>" 
                                                <?php echo (isset($_POST['author_id']) && $_POST['author_id'] == $author['author_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($author['author_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="new_author" class="form-label">Or Add New Author</label>
                                <input type="text" class="form-control" id="new_author" name="new_author" 
                                       value="<?php echo isset($_POST['new_author']) ? htmlspecialchars($_POST['new_author']) : ''; ?>" 
                                       placeholder="Enter new author name">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="category_id" class="form-label">Category</label>
                                <select class="form-select" id="category_id" name="category_id">
                                    <option value="">Select category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['category_id']; ?>" 
                                                <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="publisher" class="form-label">Publisher</label>
                                <input type="text" class="form-control" id="publisher" name="publisher" 
                                       value="<?php echo isset($_POST['publisher']) ? htmlspecialchars($_POST['publisher']) : ''; ?>">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="publication_year" class="form-label">Publication Year</label>
                                <input type="number" class="form-control" id="publication_year" name="publication_year" 
                                       value="<?php echo isset($_POST['publication_year']) ? $_POST['publication_year'] : ''; ?>" 
                                       min="1900" max="<?php echo date('Y') + 1; ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="edition" class="form-label">Edition</label>
                                <input type="text" class="form-control" id="edition" name="edition" 
                                       value="<?php echo isset($_POST['edition']) ? htmlspecialchars($_POST['edition']) : ''; ?>" 
                                       placeholder="1st, 2nd, etc.">
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="pages" class="form-label">Pages</label>
                                <input type="number" class="form-control" id="pages" name="pages" 
                                       value="<?php echo isset($_POST['pages']) ? $_POST['pages'] : ''; ?>" 
                                       min="1">
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="language" class="form-label">Language</label>
                                <input type="text" class="form-control" id="language" name="language" 
                                       value="<?php echo isset($_POST['language']) ? htmlspecialchars($_POST['language']) : 'English'; ?>">
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label for="price" class="form-label">Price (₹)</label>
                                <input type="number" class="form-control" id="price" name="price" 
                                       value="<?php echo isset($_POST['price']) ? $_POST['price'] : ''; ?>" 
                                       step="0.01" min="0">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="total_copies" class="form-label">Total Copies <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="total_copies" name="total_copies" 
                                       value="<?php echo isset($_POST['total_copies']) ? $_POST['total_copies'] : '1'; ?>" 
                                       min="1" required>
                                <div class="invalid-feedback">Please provide the number of copies.</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="shelf_location" class="form-label">Shelf Location</label>
                                <input type="text" class="form-control" id="shelf_location" name="shelf_location" 
                                       value="<?php echo isset($_POST['shelf_location']) ? htmlspecialchars($_POST['shelf_location']) : ''; ?>" 
                                       placeholder="e.g., A1-B2, Section C">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4" 
                                      placeholder="Brief description of the book..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label for="book_image" class="form-label">Book Cover Image</label>
                            <input type="file" class="form-control" id="book_image" name="book_image" 
                                   accept="image/*">
                            <div class="form-text">Upload book cover image (JPG, PNG, GIF - Max 5MB)</div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="list.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Add Book
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>Guidelines
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Ensure the title is accurate and complete
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            ISBN should be in standard format (if available)
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Select appropriate category for easy discovery
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Add new authors if not in the existing list
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Specify exact shelf location for easy retrieval
                        </li>
                        <li class="mb-0">
                            <i class="fas fa-check text-success me-2"></i>
                            Upload clear book cover image for better identification
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
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

// Clear author selection when typing new author
document.getElementById('new_author').addEventListener('input', function() {
    if (this.value.trim() !== '') {
        document.getElementById('author_id').value = '';
    }
});

document.getElementById('author_id').addEventListener('change', function() {
    if (this.value !== '') {
        document.getElementById('new_author').value = '';
    }
});
</script>

<?php include '../../includes/footer.php'; ?>