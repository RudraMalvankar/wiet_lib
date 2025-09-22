#!/bin/bash

# WIET-LIB Installation Script
# Library Management System ERP

echo "=================================================="
echo "WIET-LIB Library Management System Installation"
echo "=================================================="

# Check if MySQL is running
if ! pgrep -x "mysqld" > /dev/null; then
    echo "⚠️  MySQL is not running. Please start MySQL service first."
    echo "   - Ubuntu/Debian: sudo service mysql start"
    echo "   - CentOS/RHEL: sudo systemctl start mysqld"
    echo "   - macOS: brew services start mysql"
    echo "   - Windows: Start MySQL service from Services panel"
    exit 1
fi

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_VERSION;")
echo "📋 PHP Version: $PHP_VERSION"

if php -r "exit(version_compare(PHP_VERSION, '7.4.0', '<') ? 1 : 0);"; then
    echo "❌ PHP 7.4 or higher is required. Current version: $PHP_VERSION"
    exit 1
fi

echo "✅ PHP version check passed"

# Check required PHP extensions
echo "📋 Checking PHP extensions..."
REQUIRED_EXTENSIONS=("pdo" "pdo_mysql" "json" "mbstring" "fileinfo")

for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if php -m | grep -q "$ext"; then
        echo "✅ $ext extension found"
    else
        echo "❌ $ext extension is missing"
        echo "   Please install php-$ext package"
        exit 1
    fi
done

# Create necessary directories
echo "📁 Creating necessary directories..."
mkdir -p uploads/books
mkdir -p uploads/profiles
mkdir -p uploads/documents

# Set appropriate permissions
echo "🔐 Setting file permissions..."
chmod 755 uploads/
chmod 755 uploads/books/
chmod 755 uploads/profiles/
chmod 755 uploads/documents/

# Database setup
echo "📊 Setting up database..."
read -p "Enter MySQL username [root]: " DB_USER
DB_USER=${DB_USER:-root}

read -s -p "Enter MySQL password: " DB_PASS
echo

read -p "Enter database name [wiet_lib_db]: " DB_NAME
DB_NAME=${DB_NAME:-wiet_lib_db}

# Create database
echo "🔧 Creating database '$DB_NAME'..."
mysql -u "$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME;" 2>/dev/null

if [ $? -eq 0 ]; then
    echo "✅ Database created successfully"
else
    echo "❌ Failed to create database. Please check your credentials."
    exit 1
fi

# Import schema
echo "📥 Importing database schema..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/schema.sql 2>/dev/null

if [ $? -eq 0 ]; then
    echo "✅ Database schema imported successfully"
else
    echo "❌ Failed to import database schema"
    exit 1
fi

# Import sample data
read -p "Do you want to import sample data? (y/N): " IMPORT_SAMPLE
if [[ $IMPORT_SAMPLE =~ ^[Yy]$ ]]; then
    echo "📥 Importing sample data..."
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/sample_data.sql 2>/dev/null
    
    if [ $? -eq 0 ]; then
        echo "✅ Sample data imported successfully"
    else
        echo "⚠️  Failed to import sample data (optional)"
    fi
fi

# Update database configuration
echo "⚙️  Updating database configuration..."
cat > config/database.php << EOF
<?php
/**
 * Database Configuration for WIET-LIB
 * Library Management System ERP
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', '$DB_USER');
define('DB_PASS', '$DB_PASS');
define('DB_NAME', '$DB_NAME');

// Database connection class
class Database {
    private \$host = DB_HOST;
    private \$username = DB_USER;
    private \$password = DB_PASS;
    private \$database = DB_NAME;
    private \$connection;
    
    public function __construct() {
        \$this->connect();
    }
    
    private function connect() {
        try {
            \$dsn = "mysql:host={\$this->host};dbname={\$this->database};charset=utf8mb4";
            \$this->connection = new PDO(\$dsn, \$this->username, \$this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException \$e) {
            die("Database connection failed: " . \$e->getMessage());
        }
    }
    
    public function getConnection() {
        return \$this->connection;
    }
    
    public function closeConnection() {
        \$this->connection = null;
    }
    
    // Execute query
    public function query(\$sql) {
        return \$this->connection->query(\$sql);
    }
    
    // Prepared statement
    public function prepare(\$sql) {
        return \$this->connection->prepare(\$sql);
    }
    
    // Get last inserted ID
    public function lastInsertId() {
        return \$this->connection->lastInsertId();
    }
    
    // Execute and return single result
    public function fetchRow(\$sql, \$params = []) {
        \$stmt = \$this->prepare(\$sql);
        \$stmt->execute(\$params);
        return \$stmt->fetch();
    }
    
    // Execute and return all results
    public function fetchAll(\$sql, \$params = []) {
        \$stmt = \$this->prepare(\$sql);
        \$stmt->execute(\$params);
        return \$stmt->fetchAll();
    }
    
    // Execute statement and return affected rows
    public function execute(\$sql, \$params = []) {
        \$stmt = \$this->prepare(\$sql);
        \$stmt->execute(\$params);
        return \$stmt->rowCount();
    }
}

// Create global database instance
\$db = new Database();
?>
EOF

echo "✅ Database configuration updated"

# Create .htaccess for Apache (if Apache is detected)
if command -v apache2 &> /dev/null || command -v httpd &> /dev/null; then
    echo "🌐 Creating .htaccess for Apache..."
    cat > .htaccess << EOF
# WIET-LIB .htaccess Configuration

# Enable mod_rewrite
RewriteEngine On

# Security headers
Header always set X-Frame-Options DENY
Header always set X-Content-Type-Options nosniff
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"

# Disable directory browsing
Options -Indexes

# Protect sensitive files
<FilesMatch "\.(sql|md|json|lock)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Protect config and includes directories
<Directory "config">
    Order Allow,Deny
    Deny from all
</Directory>

<Directory "includes">
    Order Allow,Deny
    Deny from all
</Directory>

# Custom error pages
ErrorDocument 404 /wiet_lib/404.php
ErrorDocument 403 /wiet_lib/403.php

# Enable compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>
EOF
    echo "✅ .htaccess created for Apache"
fi

echo ""
echo "🎉 Installation completed successfully!"
echo ""
echo "=================================================="
echo "📋 INSTALLATION SUMMARY"
echo "=================================================="
echo "✅ Database: $DB_NAME"
echo "✅ Upload directories created with proper permissions"
echo "✅ Configuration files updated"
echo ""
echo "🔑 DEFAULT LOGIN CREDENTIALS:"
echo "Admin/Librarian:"
echo "   Username: admin"
echo "   Password: password"
echo ""
echo "Student:"
echo "   Username: student" 
echo "   Password: password"
echo ""
echo "🌐 NEXT STEPS:"
echo "1. Configure your web server to point to this directory"
echo "2. Access the system via: http://your-domain/wiet_lib/"
echo "3. Login with the default credentials above"
echo "4. Change default passwords after first login"
echo "5. Configure system settings as needed"
echo ""
echo "📚 For detailed documentation, see README.md"
echo "=================================================="