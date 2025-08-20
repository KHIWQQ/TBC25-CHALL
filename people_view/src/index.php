<?php

error_reporting(-1);


ini_set('error_reporting', E_ALL);
require_once 'config/error_handler.php';
require_once 'config/session.php';

try {
    if (isLoggedIn()) {
        header('Location: dashboard.php');
    } else {
        header('Location: login.php');
    }
    exit();
} catch (Exception $e) {
    logMessage("Index page error: " . $e->getMessage(), 'ERROR');
    
    if (($_ENV['PHP_ENV'] ?? 'development') === 'development') {
        echo "<h1>Application Error</h1>";
        echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><a href='debug.php'>View Debug Information</a></p>";
    } else {
        echo "<h1>Application Error</h1>";
        echo "<p>Please contact the administrator.</p>";
    }
}
?>
