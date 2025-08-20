<?php
require_once 'config/database.php';
require_once 'config/session.php';

if ($_ENV['PHP_ENV'] !== 'development') {
    http_response_code(404);
    exit('Page not found');
}

$info = [];

$info['php'] = [
    'version' => PHP_VERSION,
    'error_reporting' => error_reporting(),
    'display_errors' => ini_get('display_errors'),
    'log_errors' => ini_get('log_errors'),
    'error_log' => ini_get('error_log'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
];

$info['environment'] = [
    'DB_HOST' => $_ENV['DB_HOST'] ?? 'Not set',
    'DB_NAME' => $_ENV['DB_NAME'] ?? 'Not set',
    'DB_USER' => $_ENV['DB_USER'] ?? 'Not set',
    'PHP_ENV' => $_ENV['PHP_ENV'] ?? 'Not set',
];

try {
    $database = new Database();
    $db = $database->getConnection();
    $info['database'] = [
        'status' => 'Connected',
        'server_info' => $db->getAttribute(PDO::ATTR_SERVER_INFO),
        'server_version' => $db->getAttribute(PDO::ATTR_SERVER_VERSION),
        'client_version' => $db->getAttribute(PDO::ATTR_CLIENT_VERSION),
    ];
    
    $stmt = $db->query("SELECT COUNT(*) as user_count FROM users");
    $result = $stmt->fetch();
    $info['database']['user_count'] = $result['user_count'];
    
} catch (Exception $e) {
    $info['database'] = [
        'status' => 'Failed',
        'error' => $e->getMessage(),
    ];
}

$info['session'] = [
    'session_id' => session_id(),
    'session_status' => session_status(),
    'logged_in' => isLoggedIn(),
    'session_data' => $_SESSION ?? [],
];

$info['server'] = [
    'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'php_sapi' => php_sapi_name(),
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
];

$log_files = [
    'PHP Errors' => '/var/log/apache2/php_errors.log',
    'Application Log' => '/var/log/apache2/app.log',
    'SQL Log' => '/var/log/apache2/sql.log',
    'Apache Error' => '/var/log/apache2/error.log',
    'Apache Access' => '/var/log/apache2/access.log',
];

$logs = [];
foreach ($log_files as $name => $file) {
    if (file_exists($file)) {
        $logs[$name] = [
            'exists' => true,
            'size' => filesize($file),
            'modified' => date('Y-m-d H:i:s', filemtime($file)),
            'tail' => array_slice(file($file), -10), // Last 10 lines
        ];
    } else {
        $logs[$name] = ['exists' => false];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Portal - Debug Information</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .debug-section { margin-bottom: 2rem; }
        .log-content { background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; font-size: 12px; max-height: 200px; overflow-y: auto; }
        pre { white-space: pre-wrap; word-wrap: break-word; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-bug"></i> Debug Information</h1>
                    <div>
                        <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
                        <button onclick="location.reload()" class="btn btn-secondary">Refresh</button>
                    </div>
                </div>

                <!-- PHP Information -->
                <div class="debug-section">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5><i class="fab fa-php"></i> PHP Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($info['php'] as $key => $value): ?>
                                    <div class="col-md-6 mb-2">
                                        <strong><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</strong>
                                        <span class="text-muted"><?php echo htmlspecialchars($value); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Environment Variables -->
                <div class="debug-section">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5><i class="fas fa-cog"></i> Environment Variables</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($info['environment'] as $key => $value): ?>
                                    <div class="col-md-6 mb-2">
                                        <strong><?php echo $key; ?>:</strong>
                                        <span class="text-muted"><?php echo htmlspecialchars($value); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Database Information -->
                <div class="debug-section">
                    <div class="card">
                        <div class="card-header <?php echo $info['database']['status'] === 'Connected' ? 'bg-success' : 'bg-danger'; ?> text-white">
                            <h5><i class="fas fa-database"></i> Database Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($info['database'] as $key => $value): ?>
                                    <div class="col-md-6 mb-2">
                                        <strong><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</strong>
                                        <span class="text-muted"><?php echo htmlspecialchars($value); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Session Information -->
                <div class="debug-section">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5><i class="fas fa-user-clock"></i> Session Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Session ID:</strong> <span class="text-muted"><?php echo $info['session']['session_id']; ?></span><br>
                                    <strong>Status:</strong> <span class="text-muted"><?php echo $info['session']['session_status']; ?></span><br>
                                    <strong>Logged In:</strong> <span class="text-muted"><?php echo $info['session']['logged_in'] ? 'Yes' : 'No'; ?></span>
                                </div>
                                <div class="col-md-6">
                                    <strong>Session Data:</strong>
                                    <pre class="log-content"><?php echo json_encode($info['session']['session_data'], JSON_PRETTY_PRINT); ?></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Server Information -->
                <div class="debug-section">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5><i class="fas fa-server"></i> Server Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($info['server'] as $key => $value): ?>
                                    <div class="col-md-6 mb-2">
                                        <strong><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</strong>
                                        <span class="text-muted"><?php echo htmlspecialchars($value); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Log Files -->
                <div class="debug-section">
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            <h5><i class="fas fa-file-alt"></i> Log Files</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($logs as $name => $log): ?>
                                <div class="mb-3">
                                    <h6><?php echo $name; ?></h6>
                                    <?php if ($log['exists']): ?>
                                        <p class="text-muted mb-1">
                                            Size: <?php echo number_format($log['size']); ?> bytes | 
                                            Modified: <?php echo $log['modified']; ?>
                                        </p>
                                        <div class="log-content">
                                            <?php foreach ($log['tail'] as $line): ?>
                                                <?php echo htmlspecialchars($line); ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted">File does not exist</p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Test Error Button -->
                <div class="debug-section">
                    <div class="card">
                        <div class="card-header bg-danger text-white">
                            <h5><i class="fas fa-exclamation-triangle"></i> Error Testing</h5>
                        </div>
                        <div class="card-body">
                            <p>Use these buttons to test error handling:</p>
                            <a href="?test_error=warning" class="btn btn-warning btn-sm">Trigger Warning</a>
                            <a href="?test_error=notice" class="btn btn-info btn-sm">Trigger Notice</a>
                            <a href="?test_error=exception" class="btn btn-danger btn-sm">Trigger Exception</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    if (isset($_GET['test_error'])) {
        switch ($_GET['test_error']) {
            case 'warning':
                trigger_error("This is a test warning", E_USER_WARNING);
                break;
            case 'notice':
                trigger_error("This is a test notice", E_USER_NOTICE);
                break;
            case 'exception':
                throw new Exception("This is a test exception");
                break;
        }
    }
    ?>
</body>
</html>
