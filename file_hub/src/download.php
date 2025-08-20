<?php
require_once 'classes/FileManager.php';

$token = $_GET['token'] ?? '';
$password = $_POST['password'] ?? $_GET['password'] ?? '';

if (!$token) {
    http_response_code(400);
    die('Invalid token');
}

$fileManager = new FileManager();

if (!$password) {
    $result = $fileManager->getFile($token);
    if ($result['success'] && $result['file']['password_hash']) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Password Required</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body>
            <div class="container mt-5">
                <div class="row justify-content-center">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5>Password Required</h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Enter Password</label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Access File</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

$result = $fileManager->downloadFile($token, $password);

if (!$result['success']) {
    http_response_code(404);
    die('Error: ' . $result['error']);
}

header('Content-Type: ' . $result['mime_type']);
header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
header('Content-Length: ' . filesize($result['file_path']));
header('Cache-Control: no-cache, must-revalidate');

readfile($result['file_path']);
?>
