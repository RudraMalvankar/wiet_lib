-- Sample Data for WIET-LIB
-- Insert demo data for testing

-- Insert sample students
INSERT INTO students (student_number, username, password, full_name, email, phone, course, year_of_study, department, address) VALUES 
('2024001', 'student', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', 'john.doe@student.wiet.edu', '+91-9876543210', 'Computer Science Engineering', 3, 'Computer Science', '123 Main Street, Pune, Maharashtra'),
('2024002', 'jane_smith', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Smith', 'jane.smith@student.wiet.edu', '+91-9876543211', 'Information Technology', 2, 'Information Technology', '456 Oak Avenue, Pune, Maharashtra'),
('2024003', 'mike_wilson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Michael Wilson', 'mike.wilson@student.wiet.edu', '+91-9876543212', 'Electronics Engineering', 4, 'Electronics', '789 Pine Road, Pune, Maharashtra'),
('2024004', 'sarah_jones', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Jones', 'sarah.jones@student.wiet.edu', '+91-9876543213', 'Mechanical Engineering', 1, 'Mechanical', '321 Elm Street, Pune, Maharashtra'),
('2024005', 'david_brown', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'David Brown', 'david.brown@student.wiet.edu', '+91-9876543214', 'Civil Engineering', 3, 'Civil', '654 Maple Drive, Pune, Maharashtra');

-- Insert sample books
INSERT INTO books (isbn, title, author_id, category_id, publisher, publication_year, edition, pages, language, description, total_copies, available_copies, shelf_location, price) VALUES
('978-0132350884', 'Clean Code: A Handbook of Agile Software Craftsmanship', 1, 1, 'Prentice Hall', 2008, '1st', 464, 'English', 'A comprehensive guide to writing clean, readable, and maintainable code.', 5, 5, 'CS-A1-001', 2500.00),
('978-0262033848', 'Introduction to Algorithms', 2, 1, 'MIT Press', 2009, '3rd', 1312, 'English', 'Comprehensive textbook covering a broad range of algorithms in depth.', 3, 3, 'CS-A1-002', 4500.00),
('978-0201896831', 'The Art of Computer Programming, Volume 1', 3, 1, 'Addison-Wesley', 1997, '3rd', 672, 'English', 'Fundamental algorithms and data structures by Donald Knuth.', 2, 2, 'CS-A1-003', 3800.00),
('978-0072424348', 'Java: The Complete Reference', 4, 1, 'McGraw-Hill', 2020, '11th', 1248, 'English', 'Comprehensive guide to Java programming language.', 4, 4, 'CS-A1-004', 3200.00),
('978-0134494166', 'The C++ Programming Language', 5, 1, 'Addison-Wesley', 2013, '4th', 1376, 'English', 'Definitive guide to C++ by its creator Bjarne Stroustrup.', 3, 3, 'CS-A1-005', 4000.00),
('978-0521880687', 'Linear Algebra and Its Applications', 2, 2, 'Cambridge University Press', 2016, '5th', 544, 'English', 'Introduction to linear algebra with applications.', 6, 6, 'MATH-B1-001', 2800.00),
('978-0073383095', 'Calculus: Early Transcendentals', 2, 2, 'McGraw-Hill', 2019, '8th', 1344, 'English', 'Comprehensive calculus textbook with early transcendentals approach.', 5, 5, 'MATH-B1-002', 3500.00),
('978-0470458365', 'Engineering Mechanics: Statics', 3, 6, 'Wiley', 2016, '14th', 672, 'English', 'Fundamental principles of statics for engineering students.', 4, 4, 'ENG-C1-001', 3000.00),
('978-0134685991', 'Digital Design and Computer Architecture', 4, 1, 'Morgan Kaufmann', 2016, '2nd', 712, 'English', 'Comprehensive introduction to digital design and computer architecture.', 3, 3, 'CS-A1-006', 3600.00),
('978-0321125215', 'Database System Concepts', 5, 1, 'McGraw-Hill', 2019, '7th', 1376, 'English', 'Comprehensive database systems textbook.', 4, 4, 'CS-A1-007', 4200.00);

-- Insert some book issues (for demo)
INSERT INTO book_issues (book_id, student_id, issue_date, due_date, status, issued_by) VALUES
(1, 1, '2024-01-15', '2024-01-29', 'issued', 1),
(2, 1, '2024-01-20', '2024-02-03', 'issued', 1),
(3, 2, '2024-01-18', '2024-02-01', 'issued', 1),
(4, 3, '2024-01-10', '2024-01-24', 'returned', 1),
(5, 4, '2024-01-12', '2024-01-26', 'issued', 1);

-- Update available copies based on issued books
UPDATE books SET available_copies = available_copies - 1 WHERE book_id IN (1, 2, 3, 5);

-- Insert some sample reservations
INSERT INTO reservations (book_id, student_id, reservation_date, status) VALUES
(6, 2, '2024-01-22', 'active'),
(7, 3, '2024-01-21', 'active'),
(8, 5, '2024-01-23', 'active');

-- Insert sample notifications
INSERT INTO notifications (recipient_type, recipient_id, title, message, type) VALUES
('student', 1, 'Welcome to WIET Library', 'Welcome to the WIET Library Management System. You can now search, reserve, and manage your books online.', 'info'),
('student', 1, 'Book Due Reminder', 'Your book "Clean Code" is due on January 29, 2024. Please return or renew it before the due date.', 'warning'),
('student', 2, 'Book Reserved', 'Book "Linear Algebra and Its Applications" has been reserved for you.', 'success'),
('student', 3, 'New Book Added', 'New books have been added to the Computer Science section. Check them out!', 'info'),
('admin', 1, 'System Update', 'Library management system has been updated with new features.', 'info');

-- Insert sample events
INSERT INTO events (title, description, event_date, start_time, end_time, location, organizer_id, max_participants, registration_required, status) VALUES
('Book Reading Session', 'Weekly book reading and discussion session for students.', '2024-02-05', '14:00:00', '16:00:00', 'Library Hall A', 1, 50, 1, 'upcoming'),
('Digital Library Workshop', 'Learn how to effectively use our digital library resources.', '2024-02-10', '10:00:00', '12:00:00', 'Computer Lab 1', 1, 30, 1, 'upcoming'),
('Author Meet & Greet', 'Meet renowned author Dr. Smith and get your books signed.', '2024-02-15', '15:30:00', '17:00:00', 'Main Auditorium', 1, 100, 1, 'upcoming'),
('Research Paper Writing', 'Workshop on writing effective research papers and citations.', '2024-02-20', '09:00:00', '11:00:00', 'Library Conference Room', 1, 25, 1, 'upcoming');

-- Insert sample reading history
INSERT INTO reading_history (student_id, book_id, read_date, rating, review) VALUES
(1, 4, '2024-01-25', 5, 'Excellent book for learning Java. Comprehensive and well-structured.'),
(2, 6, '2024-01-20', 4, 'Good introduction to linear algebra concepts.'),
(3, 8, '2024-01-18', 5, 'Very helpful for understanding engineering mechanics.'),
(4, 1, '2024-01-15', 5, 'Must-read for every programmer. Changed my coding style completely.'),
(5, 2, '2024-01-12', 4, 'Comprehensive but dense. Takes time to digest the concepts.');

-- Update book statistics
UPDATE books b SET 
    available_copies = total_copies - (
        SELECT COUNT(*) 
        FROM book_issues bi 
        WHERE bi.book_id = b.book_id AND bi.status = 'issued'
    );

-- Insert additional system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES 
('library_hours', '09:00 AM - 08:00 PM', 'Library operating hours'),
('holiday_schedule', 'Closed on Sundays and National Holidays', 'Holiday schedule information'),
('renewal_limit', '2', 'Maximum number of renewals allowed per book'),
('reservation_hold_days', '3', 'Number of days to hold reserved books');

-- Create some sample fines
INSERT INTO fines (student_id, issue_id, amount, reason, status) VALUES
(2, 3, 10.00, 'Late return fine', 'pending'),
(4, 4, 6.00, 'Late return fine', 'paid');

COMMIT;