<?php
/**
 * Database Configuration for WIET-LIB
 * Library Management System ERP
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'wiet_lib_db');

// Database connection class
class Database {
    private $host = DB_HOST;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $database = DB_NAME;
    private $connection;
    
    public function __construct() {
        $this->connect();
    }
    
    private function connect() {
        try {
            $this->connection = new mysqli($this->host, $this->username, $this->password, $this->database);
            
            if ($this->connection->connect_error) {
                throw new Exception("Connection failed: " . $this->connection->connect_error);
            }
            
            $this->connection->set_charset("utf8");
        } catch (Exception $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function closeConnection() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
    
    // Execute query
    public function query($sql) {
        return $this->connection->query($sql);
    }
    
    // Prepared statement
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
    
    // Get last inserted ID
    public function lastInsertId() {
        return $this->connection->insert_id;
    }
    
    // Escape string
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }
}

// Create global database instance
$db = new Database();
?>