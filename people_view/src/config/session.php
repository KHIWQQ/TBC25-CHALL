<?php
require_once __DIR__ . '/error_handler.php';


try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        logMessage("Session started successfully");
    }
} catch (Exception $e) {
    logMessage("Session start failed: " . $e->getMessage(), 'ERROR');
    throw new Exception("Session initialization failed");
}

function isLoggedIn() {
    $logged_in = isset($_SESSION['user_id']);
    logMessage("Login check: " . ($logged_in ? 'authenticated' : 'not authenticated'));
    return $logged_in;
}

function requireLogin() {
    if (!isLoggedIn()) {
        logMessage("Access denied - redirecting to login", 'WARNING');
        header('Location: /login.php');
        exit();
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        require_once __DIR__ . '/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT * FROM users WHERE id = ?";
        $stmt = $database->executeQuery($query, [$_SESSION['user_id']]);
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            logMessage("User data retrieved for ID: " . $_SESSION['user_id']);
        } else {
            logMessage("User not found for ID: " . $_SESSION['user_id'], 'WARNING');
        }
        
        return $user;
        
    } catch (Exception $e) {
        logMessage("Failed to get current user: " . $e->getMessage(), 'ERROR');
        return null;
    }
}
?>
