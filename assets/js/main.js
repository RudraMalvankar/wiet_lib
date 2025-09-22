// WIET-LIB Main JavaScript File

$(document).ready(function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Load notifications
    loadNotifications();

    // Auto-refresh notifications every 30 seconds
    setInterval(loadNotifications, 30000);

    // Form validation
    validateForms();

    // Search functionality
    initializeSearch();

    // DataTables initialization
    initializeDataTables();

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
});

// Load notifications
function loadNotifications() {
    $.ajax({
        url: BASE_URL + '/api/notifications.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                updateNotificationDropdown(response.notifications);
                updateNotificationCount(response.count);
            }
        },
        error: function() {
            console.log('Error loading notifications');
        }
    });
}

// Update notification dropdown
function updateNotificationDropdown(notifications) {
    const notificationList = $('#notification-list');
    notificationList.empty();

    if (notifications.length === 0) {
        notificationList.append('<li><span class="dropdown-item-text">No new notifications</span></li>');
    } else {
        notifications.forEach(function(notification) {
            const notificationItem = `
                <li class="notification-item ${notification.is_read ? '' : 'unread'}" data-id="${notification.notification_id}">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <h6 class="mb-1">${notification.title}</h6>
                            <p class="mb-1 small">${notification.message}</p>
                            <small class="text-muted">${formatDate(notification.created_at)}</small>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary mark-read" data-id="${notification.notification_id}">
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                </li>
            `;
            notificationList.append(notificationItem);
        });
    }
}

// Update notification count
function updateNotificationCount(count) {
    const badge = $('#notification-count');
    badge.text(count);
    badge.toggle(count > 0);
}

// Mark notification as read
$(document).on('click', '.mark-read', function() {
    const notificationId = $(this).data('id');
    
    $.ajax({
        url: BASE_URL + '/api/notifications.php',
        method: 'POST',
        data: {
            action: 'mark_read',
            notification_id: notificationId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                loadNotifications();
            }
        }
    });
});

// Form validation
function validateForms() {
    // Bootstrap form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Custom validations
    $('#student_number').on('input', function() {
        validateStudentNumber($(this).val());
    });

    $('#isbn').on('input', function() {
        validateISBN($(this).val());
    });

    $('#email').on('input', function() {
        validateEmail($(this).val());
    });
}

// Validate student number
function validateStudentNumber(studentNumber) {
    const pattern = /^[0-9]{8,12}$/;
    const isValid = pattern.test(studentNumber);
    
    const input = $('#student_number');
    const feedback = input.siblings('.invalid-feedback');
    
    if (isValid) {
        input.removeClass('is-invalid').addClass('is-valid');
    } else {
        input.removeClass('is-valid').addClass('is-invalid');
        feedback.text('Student number must be 8-12 digits');
    }
    
    return isValid;
}

// Validate ISBN
function validateISBN(isbn) {
    const pattern = /^(?:ISBN(?:-1[03])?:? )?(?=[0-9X]{10}$|(?=(?:[0-9]+[- ]){3})[- 0-9X]{13}$|97[89][0-9]{10}$|(?=(?:[0-9]+[- ]){4})[- 0-9]{17}$)(?:97[89][- ]?)?[0-9]{1,5}[- ]?[0-9]+[- ]?[0-9]+[- ]?[0-9X]$/;
    const isValid = pattern.test(isbn.replace(/[^0-9X]/gi, ''));
    
    const input = $('#isbn');
    const feedback = input.siblings('.invalid-feedback');
    
    if (isValid || isbn === '') {
        input.removeClass('is-invalid').addClass('is-valid');
    } else {
        input.removeClass('is-valid').addClass('is-invalid');
        feedback.text('Please enter a valid ISBN');
    }
    
    return isValid;
}

// Validate email
function validateEmail(email) {
    const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const isValid = pattern.test(email);
    
    const input = $('#email');
    const feedback = input.siblings('.invalid-feedback');
    
    if (isValid) {
        input.removeClass('is-invalid').addClass('is-valid');
    } else {
        input.removeClass('is-valid').addClass('is-invalid');
        feedback.text('Please enter a valid email address');
    }
    
    return isValid;
}

// Initialize search functionality
function initializeSearch() {
    const searchInput = $('.search-input');
    let searchTimeout;

    searchInput.on('input', function() {
        const query = $(this).val();
        const searchType = $(this).data('search-type') || 'books';
        
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            if (query.length >= 2) {
                performSearch(query, searchType);
            } else {
                clearSearchResults();
            }
        }, 300);
    });
}

// Perform search
function performSearch(query, type) {
    $.ajax({
        url: BASE_URL + '/api/search.php',
        method: 'GET',
        data: {
            q: query,
            type: type
        },
        dataType: 'json',
        beforeSend: function() {
            showSearchLoading();
        },
        success: function(response) {
            hideSearchLoading();
            if (response.success) {
                displaySearchResults(response.results, type);
            } else {
                showSearchError(response.message);
            }
        },
        error: function() {
            hideSearchLoading();
            showSearchError('Search failed. Please try again.');
        }
    });
}

// Display search results
function displaySearchResults(results, type) {
    const resultsContainer = $('#search-results');
    resultsContainer.empty();

    if (results.length === 0) {
        resultsContainer.append('<p class="text-muted">No results found.</p>');
        return;
    }

    results.forEach(function(item) {
        let resultHtml = '';
        
        if (type === 'books') {
            resultHtml = `
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card book-card h-100">
                        <div class="position-relative">
                            <img src="${item.book_image || BASE_URL + '/assets/images/default-book.jpg'}" 
                                 class="card-img-top book-image" alt="${item.title}">
                            <span class="book-status ${item.available_copies > 0 ? 'available' : 'unavailable'}">
                                ${item.available_copies > 0 ? 'Available' : 'Not Available'}
                            </span>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">${item.title}</h5>
                            <p class="card-text">
                                <small class="text-muted">by ${item.author_name}</small><br>
                                <small class="text-muted">Category: ${item.category_name}</small><br>
                                <small class="text-muted">Available: ${item.available_copies}/${item.total_copies}</small>
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <button class="btn btn-primary btn-sm view-book" data-id="${item.book_id}">
                                    View Details
                                </button>
                                ${item.available_copies > 0 ? 
                                    '<button class="btn btn-success btn-sm reserve-book" data-id="' + item.book_id + '">Reserve</button>' : 
                                    '<button class="btn btn-secondary btn-sm" disabled>Not Available</button>'
                                }
                            </div>
                        </div>
                    </div>
                </div>
            `;
        } else if (type === 'students') {
            resultHtml = `
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">${item.full_name}</h5>
                            <p class="card-text">
                                <small class="text-muted">Student Number: ${item.student_number}</small><br>
                                <small class="text-muted">Course: ${item.course}</small><br>
                                <small class="text-muted">Year: ${item.year_of_study}</small>
                            </p>
                            <button class="btn btn-primary btn-sm view-student" data-id="${item.student_id}">
                                View Profile
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }
        
        resultsContainer.append(resultHtml);
    });
}

// Show search loading
function showSearchLoading() {
    $('#search-results').html('<div class="text-center"><div class="spinner"></div></div>');
}

// Hide search loading
function hideSearchLoading() {
    // Loading will be replaced by results
}

// Clear search results
function clearSearchResults() {
    $('#search-results').empty();
}

// Show search error
function showSearchError(message) {
    $('#search-results').html(`<div class="alert alert-danger">${message}</div>`);
}

// Initialize DataTables
function initializeDataTables() {
    if ($.fn.DataTable && $('.data-table').length) {
        $('.data-table').DataTable({
            responsive: true,
            pageLength: 25,
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            },
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
        });
    }
}

// Book reservation
$(document).on('click', '.reserve-book', function() {
    const bookId = $(this).data('id');
    
    if (confirm('Are you sure you want to reserve this book?')) {
        $.ajax({
            url: BASE_URL + '/api/reservations.php',
            method: 'POST',
            data: {
                action: 'reserve',
                book_id: bookId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', 'Book reserved successfully!');
                    loadNotifications();
                } else {
                    showAlert('danger', response.message);
                }
            },
            error: function() {
                showAlert('danger', 'Failed to reserve book. Please try again.');
            }
        });
    }
});

// Show alert
function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    if ($('.alert-container').length) {
        $('.alert-container').prepend(alertHtml);
    } else {
        $('main').prepend(alertHtml);
    }
    
    // Auto-hide after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    const seconds = Math.floor(diff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);
    
    if (days > 0) {
        return `${days} day${days > 1 ? 's' : ''} ago`;
    } else if (hours > 0) {
        return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    } else if (minutes > 0) {
        return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
    } else {
        return 'Just now';
    }
}

// Confirm delete
function confirmDelete(message = 'Are you sure you want to delete this item?') {
    return confirm(message);
}

// Print functionality
function printElement(elementId) {
    const printContents = document.getElementById(elementId).innerHTML;
    const originalContents = document.body.innerHTML;
    
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
    location.reload();
}

// Export to CSV
function exportToCSV(data, filename) {
    const csv = convertToCSV(data);
    downloadCSV(csv, filename);
}

function convertToCSV(objArray) {
    const array = typeof objArray !== 'object' ? JSON.parse(objArray) : objArray;
    let str = '';
    
    for (let i = 0; i < array.length; i++) {
        let line = '';
        for (let index in array[i]) {
            if (line !== '') line += ',';
            line += array[i][index];
        }
        str += line + '\r\n';
    }
    
    return str;
}

function downloadCSV(csv, filename) {
    const csvFile = new Blob([csv], { type: 'text/csv' });
    const downloadLink = document.createElement('a');
    
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Global variables
const BASE_URL = '/wiet_lib';