<?php



$environment = $_ENV['PHP_ENV'] ?? 'development';

if ($environment === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}


function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $error_types = [
        E_ERROR => 'Fatal Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];

    $error_type = $error_types[$errno] ?? 'Unknown Error';
    $timestamp = date('Y-m-d H:i:s');
    
 
    $log_message = "[$timestamp] $error_type: $errstr in $errfile on line $errline" . PHP_EOL;
    
  
    $log_dir = dirname('/var/log/apache2/php_errors.log');
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    error_log($log_message, 3, '/var/log/apache2/php_errors.log');
    

    if (($_ENV['PHP_ENV'] ?? 'development') === 'development') {
        echo "<div style='background: #ffebee; border: 1px solid #f44336; padding: 10px; margin: 10px; border-radius: 5px; font-family: Arial, sans-serif;'>";
        echo "<strong style='color: #f44336;'>$error_type:</strong> " . htmlspecialchars($errstr) . "<br>";
        echo "<strong>File:</strong> " . htmlspecialchars($errfile) . "<br>";
        echo "<strong>Line:</strong> $errline<br>";
        echo "<strong>Time:</strong> $timestamp";
        echo "</div>";
    }
    

    return true;
}


function customExceptionHandler($exception) {
    $timestamp = date('Y-m-d H:i:s');
    $message = $exception->getMessage();
    $file = $exception->getFile();
    $line = $exception->getLine();
    $trace = $exception->getTraceAsString();
    

    $log_message = "[$timestamp] Uncaught Exception: $message in $file on line $line" . PHP_EOL;
    $log_message .= "Stack trace:" . PHP_EOL . $trace . PHP_EOL;
    

    $log_dir = dirname('/var/log/apache2/php_errors.log');
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    error_log($log_message, 3, '/var/log/apache2/php_errors.log');
    

    if (($_ENV['PHP_ENV'] ?? 'development') === 'development') {
        echo "<div style='background: #ffebee; border: 1px solid #f44336; padding: 15px; margin: 10px; border-radius: 5px; font-family: Arial, sans-serif;'>";
        echo "<h3 style='color: #f44336; margin-top: 0;'>Uncaught Exception</h3>";
        echo "<strong>Message:</strong> " . htmlspecialchars($message) . "<br>";
        echo "<strong>File:</strong> " . htmlspecialchars($file) . "<br>";
        echo "<strong>Line:</strong> $line<br>";
        echo "<strong>Time:</strong> $timestamp<br>";
        echo "<details style='margin-top: 10px;'>";
        echo "<summary style='cursor: pointer; color: #f44336;'><strong>Stack Trace</strong></summary>";
        echo "<pre style='background: #f5f5f5; padding: 10px; margin-top: 5px; overflow-x: auto; white-space: pre-wrap;'>" . htmlspecialchars($trace) . "</pre>";
        echo "</details>";
        echo "</div>";
    } else {
        http_response_code(500);
        echo "<!DOCTYPE html><html><head><title>Application Error</title></head><body>";
        echo "<h1>Application Error</h1>";
        echo "<p>An error occurred. Please contact the administrator.</p>";
        echo "</body></html>";
    }
}


set_error_handler('customErrorHandler');
set_exception_handler('customExceptionHandler');


function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] [$level] $message" . PHP_EOL;
    

    $log_dir = dirname('/var/log/apache2/app.log');
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    error_log($log_message, 3, '/var/log/apache2/app.log');
}


function logQuery($query, $params = []) {
    if (($_ENV['PHP_ENV'] ?? 'development') === 'development') {
        $timestamp = date('Y-m-d H:i:s');
        $params_str = empty($params) ? '' : ' | Params: ' . json_encode($params);
        $log_message = "[$timestamp] [SQL] $query$params_str" . PHP_EOL;
        

        $log_dir = dirname('/var/log/apache2/sql.log');
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        error_log($log_message, 3, '/var/log/apache2/sql.log');
    }
}


register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] Fatal Error: {$error['message']} in {$error['file']} on line {$error['line']}" . PHP_EOL;
        

        $log_dir = dirname('/var/log/apache2/php_errors.log');
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        error_log($log_message, 3, '/var/log/apache2/php_errors.log');
    }
});


logMessage("Error handler initialized successfully");
?>
