<?php
require_once 'config/database.php';
require_once 'config/session.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$debug_info = [];

if ($_POST) {
    try {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        logMessage("Login attempt for username: $username");
        
        if (empty($username) || empty($password)) {
            throw new Exception('Username and password are required');
        }
        
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT id, username, password, full_name FROM users WHERE username = ? OR email = ?";
        $stmt = $database->executeQuery($query, [$username, $username]);
        
        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            logMessage("User found in database: " . $user['username']);
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                
                logMessage("Login successful for user: " . $user['username']);
                header('Location: dashboard.php');
                exit();
            } else {
                logMessage("Password verification failed for user: " . $user['username'], 'WARNING');
                throw new Exception('Invalid username or password');
            }
        } else {
            logMessage("User not found: $username", 'WARNING');
            throw new Exception('Invalid username or password');
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        logMessage("Login error: " . $error, 'ERROR');
        
        if ($_ENV['PHP_ENV'] === 'development') {
            $debug_info[] = "Error: " . $e->getMessage();
            $debug_info[] = "File: " . $e->getFile();
            $debug_info[] = "Line: " . $e->getLine();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Portal - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .login-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
            font-family: monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-card">
                    <div class="login-header">
                        <i class="fas fa-building fa-3x mb-3"></i>
                        <h3>HR Portal</h3>
                        <p class="mb-0">Welcome back!</p>
                    </div>
                    <div class="login-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($debug_info) && $_ENV['PHP_ENV'] === 'development'): ?>
                            <div class="debug-info">
                                <strong>Debug Information:</strong><br>
                                <?php foreach ($debug_info as $info): ?>
                                    <?php echo htmlspecialchars($info); ?><br>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Username or Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" name="username" 
                                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" name="password" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-login w-100">
                                <i class="fas fa-sign-in-alt"></i> Sign In
                            </button>
                        </form>
                        
                        <div class="mt-4 text-center">
                            <small class="text-muted">
                                Manage your employees and their benefits.
                            </small>
                        </div>
                        
                        <div class="mt-3 text-center">
                            <p class="text-muted mb-2">Don't have an account?</p>
                            <a href="register.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-user-plus"></i> Register
                            </a>
                        </div>
                        
                        <?php if ($_ENV['PHP_ENV'] === 'development'): ?>
                            <div class="mt-3 text-center">
                                <small class="badge bg-warning text-dark">
                                    Development Mode - Detailed errors enabled
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>