<?php
require_once 'classes/Auth.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure File Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/">🔒 Secure File Hub</a>
            <div class="navbar-nav ms-auto">
                <?php if ($auth->isLoggedIn()): ?>
                    <span class="navbar-text me-3">Welcome, <?= htmlspecialchars($_SESSION['username']) ?></span>
                    <?php if ($auth->isAdmin()): ?>
                        <a class="nav-link" href="/admin.php">Admin Panel</a>
                    <?php endif; ?>
                    <a class="nav-link" href="/logout.php">Logout</a>
                <?php else: ?>
                    <a class="nav-link" href="/login.php">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h3>Upload File</h3>
                    </div>
                    <div class="card-body">
                        <form id="uploadForm" enctype="multipart/form-data" method="post" action="upload.php">
                            <div class="mb-3">
                                <label for="file" class="form-label">Select File</label>
                                <input type="file" class="form-control" id="file" name="file" required>
                                <div class="form-text">Max file size: 100MB. Allowed types: Images, PDF, Documents</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password (Optional) </label>
                                <input type="password" class="form-control" id="password" name="password">
                                <div class="form-text">Leave empty for random password generation</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="expiry_hours" class="form-label">Expires in (hours)</label>
                                    <select class="form-select" id="expiry_hours" name="expiry_hours">
                                        <option value="">Never</option>
                                        <option value="1">1 hour</option>
                                        <option value="24">24 hours</option>
                                        <option value="168">1 week</option>
                                        <option value="720">1 month</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="max_downloads" class="form-label">Max Downloads</label>
                                    <input type="number" class="form-control" id="max_downloads" name="max_downloads" min="1">
                                    <div class="form-text">Leave empty for unlimited</div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">Upload File</button>
                            </div>
                        </form>
                        
                        <div id="uploadResult" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>