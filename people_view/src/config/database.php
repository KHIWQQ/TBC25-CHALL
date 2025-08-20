<?php
require_once __DIR__ . '/error_handler.php';

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;
    private $max_retries = 3;
    private $retry_delay = 2; 

    public function __construct() {
        
        $this->host = $_ENV['DB_HOST'] ?? 'db';
        $this->db_name = $_ENV['DB_NAME'] ?? 'hr_portal';
        $this->username = $_ENV['DB_USER'] ?? 'hr_user';
        $this->password = $_ENV['DB_PASS'] ?? 'hr_password';
    }

    public function getConnection() {
        if ($this->conn !== null) {
            return $this->conn;
        }

        $attempts = 0;
        while ($attempts < $this->max_retries) {
            try {
                $attempts++;
                logMessage("Attempting database connection (attempt $attempts/{$this->max_retries}) to {$this->host}:{$this->db_name}");
                
                $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ];

                $this->conn = new PDO($dsn, $this->username, $this->password, $options);
                
                
                $this->conn->query("SELECT 1");
                
                logMessage("Database connection successful");
                return $this->conn;
                
            } catch(PDOException $exception) {
                $error_message = "Database connection attempt $attempts failed: " . $exception->getMessage();
                logMessage($error_message, 'ERROR');
                
                if ($attempts >= $this->max_retries) {
                    if (($_ENV['PHP_ENV'] ?? 'development') === 'development') {
                        throw new Exception("Database connection failed after {$this->max_retries} attempts: " . $exception->getMessage());
                    } else {
                        throw new Exception("Database connection failed. Please contact administrator.");
                    }
                }
                
               
                sleep($this->retry_delay);
            }
        }
        
        return null;
    }
    
    public function executeQuery($query, $params = []) {
        try {
            $db = $this->getConnection();
            if (!$db) {
                throw new Exception("No database connection available");
            }
            
            logQuery($query, $params);
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            return $stmt;
            
        } catch(PDOException $exception) {
            $error_message = "Query execution failed: " . $exception->getMessage();
            logMessage($error_message, 'ERROR');
            throw new Exception($error_message);
        }
    }
    
    public function isConnected() {
        try {
            if ($this->conn === null) {
                return false;
            }
            $this->conn->query("SELECT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>
