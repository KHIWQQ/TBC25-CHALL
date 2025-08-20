<?php
require_once 'config/database.php';
require_once 'config/session.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';
$debug_info = [];

if ($_POST) {
    try {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $full_name = trim($_POST['full_name'] ?? '');
        
        logMessage("Registration attempt for username: $username, email: $email");
        

        if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($full_name)) {
            throw new Exception('All fields are required');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address');
        }
        
        if (strlen($password) < 6) {
            throw new Exception('Password must be at least 6 characters long');
        }
        
        if ($password !== $confirm_password) {
            throw new Exception('Passwords do not match');
        }
        
        if (strlen($username) < 3) {
            throw new Exception('Username must be at least 3 characters long');
        }
        
        $database = new Database();
        $db = $database->getConnection();
        

        $check_query = "SELECT id FROM users WHERE username = ? OR email = ?";
        $check_stmt = $database->executeQuery($check_query, [$username, $email]);
        
        if ($check_stmt->fetch()) {
            throw new Exception('Username or email already exists');
        }
        

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        

        $insert_query = "INSERT INTO users (username, email, password, full_name, created_at) VALUES (?, ?, ?, ?, NOW())";
        $insert_stmt = $database->executeQuery($insert_query, [$username, $email, $hashed_password, $full_name]);
        
        logMessage("Registration successful for user: $username");
        $success = 'Registration successful! You can now login with your credentials.';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        logMessage("Registration error: " . $error, 'ERROR');
        
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
    <title>HR Portal - Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .register-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .register-body {
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
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .btn-login-link {
            background: #6c757d;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
        }
        .btn-login-link:hover {
            background: #5a6268;
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
            <div class="col-md-6 col-lg-5">
                <div class="register-card">
                    <div class="register-header">
                        <i class="fas fa-user-plus fa-3x mb-3"></i>
                        <h3>HR Portal</h3>
                        <p class="mb-0">Create your account</p>
                    </div>
                    <div class="register-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
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
                                <label class="form-label">Full Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" name="full_name" 
                                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-at"></i></span>
                                    <input type="text" class="form-control" name="username" 
                                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" name="password" required>
                                </div>
                                <small class="text-muted">Password must be at least 6 characters long</small>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-register w-100 mb-3">
                                <i class="fas fa-user-plus"></i> Create Account
                            </button>
                            
                            <a href="login.php" class="btn btn-secondary btn-login-link w-100">
                                <i class="fas fa-sign-in-alt"></i> Back to Login
                            </a>
                        </form>
                        
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
