# WIET-LIB - Library Management System ERP

A comprehensive Library Management System ERP (Enterprise Resource Planning) built with HTML, CSS, JavaScript, PHP, and MySQL. This system is designed for educational institutions to manage their library operations efficiently.

## 🚀 Features

### Admin Module
- **Dashboard**: Real-time analytics and statistics
- **Book Management**: Complete CRUD operations for books, authors, and categories
- **Student Management**: Comprehensive student records management
- **Circulation Management**: Book issue/return with fine calculation
- **Event Management**: Library events and activities management
- **Notifications System**: Real-time notifications for students and staff
- **Reports & Analytics**: Comprehensive reporting system

### Student Module
- **Student Dashboard**: Personalized dashboard with current books and recommendations
- **Book Search**: Advanced search with filters and sorting
- **My Books**: Current issued books with due dates
- **Reading History**: Complete reading history with ratings and reviews
- **Book Reservations**: Reserve books that are currently unavailable
- **Digital ID Card**: Digital student library ID with QR code
- **Profile Management**: Update personal information and preferences

### Core Features
- **Responsive Design**: Mobile-friendly interface using Bootstrap 5
- **Security**: Role-based access control with secure authentication
- **Real-time Notifications**: Live notification system
- **File Upload**: Book cover images and student photos
- **Advanced Search**: Multi-criteria search functionality
- **Fine Management**: Automatic fine calculation for overdue books
- **Reservation System**: Book reservation with automatic notifications

## 🛠️ Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript (jQuery), Bootstrap 5
- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Icons**: Font Awesome 6
- **Additional Libraries**: 
  - Bootstrap 5.1.3
  - jQuery 3.6.0
  - Font Awesome 6.0.0

## 📋 Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Web browser (Chrome, Firefox, Safari, Edge)

## 🔧 Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/RudraMalvankar/wiet_lib.git
   cd wiet_lib
   ```

2. **Set up the database**
   - Create a MySQL database named `wiet_lib_db`
   - Import the database schema:
     ```bash
     mysql -u username -p wiet_lib_db < database/schema.sql
     ```
   - Import sample data (optional):
     ```bash
     mysql -u username -p wiet_lib_db < database/sample_data.sql
     ```

3. **Configure database connection**
   - Edit `config/database.php`
   - Update database credentials:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'your_username');
     define('DB_PASS', 'your_password');
     define('DB_NAME', 'wiet_lib_db');
     ```

4. **Set up file permissions**
   ```bash
   chmod 755 uploads/
   chmod 755 uploads/books/
   chmod 755 uploads/profiles/
   ```

5. **Configure web server**
   - Point document root to the project directory
   - Ensure mod_rewrite is enabled (for Apache)

## 🎯 Usage

### Default Login Credentials

**Admin/Librarian:**
- Username: `admin`
- Password: `password`

**Student:**
- Username: `student`
- Password: `password`

### Admin Functions

1. **Book Management**
   - Add new books with ISBN, author, category, and cover images
   - Edit existing book details
   - Manage authors and categories
   - Track book availability and location

2. **Student Management**
   - Add new student accounts
   - Update student information
   - View student reading history and current issues

3. **Circulation**
   - Issue books to students
   - Process book returns
   - Calculate and manage fines
   - Handle overdue books

4. **Events & Notifications**
   - Create library events
   - Send notifications to students
   - Manage event registrations

### Student Functions

1. **Book Discovery**
   - Search books by title, author, ISBN, or keywords
   - Filter by categories and sort results
   - View detailed book information

2. **Account Management**
   - View current issued books and due dates
   - Check reading history and reviews
   - Manage book reservations
   - Update profile information

3. **Digital Services**
   - Access digital ID card
   - Receive real-time notifications
   - Participate in library events

## 📁 Project Structure

```
wiet_lib/
├── admin/                  # Admin module
│   ├── books/             # Book management
│   ├── students/          # Student management
│   ├── circulation/       # Issue/return management
│   └── dashboard.php      # Admin dashboard
├── student/               # Student module
│   ├── dashboard.php      # Student dashboard
│   ├── search.php         # Book search
│   ├── my-books.php       # Current books
│   ├── history.php        # Reading history
│   └── digital-id.php     # Digital ID card
├── api/                   # API endpoints
│   ├── search_books.php   # Book search API
│   ├── search_students.php # Student search API
│   ├── notifications.php  # Notifications API
│   └── reservations.php   # Reservations API
├── assets/                # Static assets
│   ├── css/               # Stylesheets
│   ├── js/                # JavaScript files
│   └── images/            # Images and icons
├── config/                # Configuration files
│   └── database.php       # Database configuration
├── database/              # Database files
│   ├── schema.sql         # Database schema
│   └── sample_data.sql    # Sample data
├── includes/              # PHP includes
│   ├── config.php         # Main configuration
│   ├── header.php         # Header template
│   └── footer.php         # Footer template
├── uploads/               # File uploads
│   ├── books/             # Book cover images
│   └── profiles/          # Profile pictures
├── index.php              # Main entry point
├── login.php              # Login page
└── logout.php             # Logout handler
```

## 🔒 Security Features

- **Password Hashing**: All passwords are hashed using PHP's password_hash()
- **SQL Injection Prevention**: Prepared statements for all database queries
- **XSS Protection**: Input sanitization and output escaping
- **CSRF Protection**: Token-based CSRF protection for forms
- **Session Management**: Secure session handling
- **Role-based Access**: Separate access levels for admin and students

## 🎨 UI/UX Features

- **Responsive Design**: Works on desktop, tablet, and mobile devices
- **Modern Interface**: Clean and intuitive user interface
- **Real-time Updates**: Live notifications and status updates
- **Dark/Light Theme**: Consistent color scheme throughout
- **Accessibility**: WCAG compliant design elements
- **Fast Loading**: Optimized assets and efficient database queries

## 📊 Database Schema

The system uses a comprehensive database schema with the following main tables:

- **admins**: Administrator accounts
- **students**: Student accounts and profiles
- **books**: Book catalog with metadata
- **authors**: Author information
- **categories**: Book categories
- **book_issues**: Circulation records
- **reservations**: Book reservations
- **notifications**: System notifications
- **events**: Library events
- **fines**: Fine management
- **reading_history**: Student reading records
- **system_settings**: Configuration settings

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📝 License

This project is developed for educational purposes as part of academic coursework.

## 👨‍💻 Developer

**Rudra Malvankar**
- GitHub: [@RudraMalvankar](https://github.com/RudraMalvankar)

## 🏫 Institution

**Walchand Institute of Engineering and Technology (WIET)**
- Department: Computer Engineering
- Course: Library Management System Project

## 📞 Support

For support and queries, please contact:
- Email: library@wiet.edu
- Phone: +91-1234567890

---

**Note**: This is an academic project developed for learning purposes. For production use, additional security measures and testing are recommended.