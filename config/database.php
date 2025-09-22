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
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4";
            $this->connection = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function closeConnection() {
        $this->connection = null;
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
        return $this->connection->lastInsertId();
    }
    
    // Execute and return single result
    public function fetchRow($sql, $params = []) {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    // Execute and return all results
    public function fetchAll($sql, $params = []) {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    // Execute statement and return affected rows
    public function execute($sql, $params = []) {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}

// Create global database instance
$db = new Database();
?>