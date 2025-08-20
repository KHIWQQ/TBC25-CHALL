<?php
require_once 'classes/Auth.php';
require_once 'classes/FileManager.php';
require_once 'classes/Logger.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->requireAdmin();

$fileManager = new FileManager();
$logger = new Logger($db);

$files = $fileManager->getFilesList(100);
$logs = $logger->getActivityLogs(50);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Secure File Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/">🔒 Secure File Hub - Admin</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="/">Home</a>
                <a class="nav-link" href="/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <ul class="nav nav-tabs" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="files-tab" data-bs-toggle="tab" data-bs-target="#files" type="button">Files</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button">Activity Logs</button>
            </li>
        </ul>

        <div class="tab-content" id="adminTabsContent">
            <div class="tab-pane fade show active" id="files" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <h5>Uploaded Files</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Filename</th>
                                        <th>Size</th>
                                        <th>Uploaded</th>
                                        <th>Expires</th>
                                        <th>Downloads</th>
                                        <th>Uploaded By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($files as $file): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($file['original_filename']) ?></td>
                                        <td><?= number_format($file['file_size'] / 1024, 2) ?> KB</td>
                                        <td><?= date('Y-m-d H:i', strtotime($file['upload_date'])) ?></td>
                                        <td><?= $file['expiry_date'] ? date('Y-m-d H:i', strtotime($file['expiry_date'])) : 'Never' ?></td>
                                        <td><?= $file['download_count'] ?><?= $file['max_downloads'] ? '/' . $file['max_downloads'] : '' ?></td>
                                        <td><?= htmlspecialchars($file['uploaded_by'] ?? 'Anonymous') ?></td>
                                        <td>
                                            <a href="/download.php?token=<?= $file['share_token'] ?>" class="btn btn-sm btn-primary">Download</a>
                                            <button class="btn btn-sm btn-danger" onclick="deleteFile(<?= $file['id'] ?>)">Delete</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="logs" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <h5>Activity Logs</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Action</th>
                                        <th>File</th>
                                        <th>IP Address</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?= date('Y-m-d H:i:s', strtotime($log['timestamp'])) ?></td>
                                        <td><span class="badge bg-<?= $log['action'] === 'upload' ? 'success' : ($log['action'] === 'download' ? 'primary' : 'danger') ?>"><?= $log['action'] ?></span></td>
                                        <td><?= htmlspecialchars($log['original_filename'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($log['ip_address']) ?></td>
                                        <td><?= htmlspecialchars(json_encode(json_decode($log['details']), JSON_PRETTY_PRINT)) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteFile(fileId) {
            alert("Still in development, not implemented yet.");
    </script>
</body>
</html>
