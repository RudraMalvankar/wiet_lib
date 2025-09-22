<?php
require_once '../includes/config.php';
require_student();

$page_title = 'Digital ID Card';
$student_id = $_SESSION['user_id'];

// Get student details
$student_stmt = $db->prepare("
    SELECT * FROM students WHERE student_id = ? AND is_active = 1
");
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();

if (!$student) {
    header('Location: ../login.php');
    exit();
}

// Get student statistics
$stats = [];

// Books currently issued
$stats['current_books'] = $db->query("
    SELECT COUNT(*) as count 
    FROM book_issues 
    WHERE student_id = $student_id AND status = 'issued'
")->fetch_assoc()['count'];

// Total books read
$stats['total_read'] = $db->query("
    SELECT COUNT(*) as count 
    FROM book_issues 
    WHERE student_id = $student_id AND status = 'returned'
")->fetch_assoc()['count'];

// Active reservations
$stats['reservations'] = $db->query("
    SELECT COUNT(*) as count 
    FROM reservations 
    WHERE student_id = $student_id AND status = 'active'
")->fetch_assoc()['count'];

// Member since
$member_since = date('M Y', strtotime($student['created_at']));

include '../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Digital ID Card</h1>
            <p class="text-muted">Your digital library identification</p>
        </div>
        <div>
            <button onclick="printCard()" class="btn btn-primary">
                <i class="fas fa-print me-1"></i>Print ID Card
            </button>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <!-- Digital ID Card -->
            <div class="digital-id-card" id="id-card">
                <div class="row">
                    <div class="col-4 text-center">
                        <img src="<?php echo $student['profile_image'] ? '../uploads/' . $student['profile_image'] : '../assets/images/default-avatar.png'; ?>" 
                             alt="Student Photo" class="student-photo mb-3">
                        <div class="qr-code">
                            <!-- QR Code would be generated here -->
                            <div class="bg-white text-dark p-2 rounded">
                                <small>QR Code</small><br>
                                <div style="width: 60px; height: 60px; background: #000; margin: 0 auto;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-8">
                        <div class="card-header-text">
                            <h4 class="text-white mb-1">WIET LIBRARY</h4>
                            <p class="text-white-50 mb-3">Student ID Card</p>
                        </div>
                        
                        <div class="student-info">
                            <h3 class="text-white mb-3"><?php echo htmlspecialchars($student['full_name']); ?></h3>
                            
                            <div class="info-row mb-2">
                                <strong>Student Number:</strong>
                                <span class="float-end"><?php echo $student['student_number']; ?></span>
                            </div>
                            
                            <div class="info-row mb-2">
                                <strong>Course:</strong>
                                <span class="float-end"><?php echo htmlspecialchars($student['course'] ?? 'N/A'); ?></span>
                            </div>
                            
                            <div class="info-row mb-2">
                                <strong>Year:</strong>
                                <span class="float-end"><?php echo $student['year_of_study'] ?? 'N/A'; ?></span>
                            </div>
                            
                            <div class="info-row mb-2">
                                <strong>Department:</strong>
                                <span class="float-end"><?php echo htmlspecialchars($student['department'] ?? 'N/A'); ?></span>
                            </div>
                            
                            <div class="info-row mb-2">
                                <strong>Member Since:</strong>
                                <span class="float-end"><?php echo $member_since; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr class="my-3" style="border-color: rgba(255,255,255,0.3);">
                
                <div class="row text-center">
                    <div class="col-4">
                        <div class="stat-item">
                            <h4 class="text-white mb-0"><?php echo $stats['current_books']; ?></h4>
                            <small class="text-white-50">Current Books</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-item">
                            <h4 class="text-white mb-0"><?php echo $stats['total_read']; ?></h4>
                            <small class="text-white-50">Books Read</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-item">
                            <h4 class="text-white mb-0"><?php echo $stats['reservations']; ?></h4>
                            <small class="text-white-50">Reservations</small>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <small class="text-white-50">
                        Valid through Academic Year <?php echo date('Y') . '-' . (date('Y') + 1); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Information -->
    <div class="row mt-5">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>ID Card Information
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            This digital ID card serves as your library identification
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Present this card when borrowing books or accessing library services
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Keep your profile information updated for accurate records
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check text-success me-2"></i>
                            Report any lost or damaged physical ID cards immediately
                        </li>
                        <li class="mb-0">
                            <i class="fas fa-check text-success me-2"></i>
                            Digital ID is valid only with proper authentication
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-book me-2"></i>Library Privileges
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get library settings
                    $settings = [];
                    $settings_result = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('max_books_per_student', 'loan_period_days', 'fine_per_day')");
                    while ($row = $settings_result->fetch_assoc()) {
                        $settings[$row['setting_key']] = $row['setting_value'];
                    }
                    ?>
                    
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="privilege-item">
                                <h3 class="text-primary"><?php echo $settings['max_books_per_student'] ?? '5'; ?></h3>
                                <small class="text-muted">Maximum Books</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="privilege-item">
                                <h3 class="text-primary"><?php echo $settings['loan_period_days'] ?? '14'; ?></h3>
                                <small class="text-muted">Loan Days</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="privilege-item">
                                <h3 class="text-primary"><?php echo format_currency($settings['fine_per_day'] ?? '2.00'); ?></h3>
                                <small class="text-muted">Fine per Day</small>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6>Access Rights:</h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success me-2"></i>Book borrowing and returns</li>
                        <li><i class="fas fa-check text-success me-2"></i>Digital catalog search</li>
                        <li><i class="fas fa-check text-success me-2"></i>Book reservations</li>
                        <li><i class="fas fa-check text-success me-2"></i>Reading history access</li>
                        <li><i class="fas fa-check text-success me-2"></i>Library events participation</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Information -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-address-book me-2"></i>Contact Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Email:</strong><br>
                            <?php echo htmlspecialchars($student['email']); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Phone:</strong><br>
                            <?php echo htmlspecialchars($student['phone'] ?? 'Not provided'); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Address:</strong><br>
                            <?php echo htmlspecialchars($student['address'] ?? 'Not provided'); ?>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="profile.php" class="btn btn-outline-primary">
                            <i class="fas fa-edit me-1"></i>Update Information
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #id-card, #id-card * {
        visibility: visible;
    }
    #id-card {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    .container-fluid {
        padding: 0;
    }
    .digital-id-card {
        page-break-inside: avoid;
        margin: 0;
        transform: scale(1.2);
        transform-origin: top left;
    }
}

.privilege-item {
    padding: 1rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.info-row {
    border-bottom: 1px solid rgba(255,255,255,0.2);
    padding-bottom: 0.5rem;
}
</style>

<script>
function printCard() {
    window.print();
}

// Add animation to the card
$(document).ready(function() {
    $('#id-card').hide().fadeIn(1000);
});
</script>

<?php include '../includes/footer.php'; ?>