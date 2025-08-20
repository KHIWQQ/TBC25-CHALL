<?php
require_once 'classes/FileManager.php';
require_once 'classes/Auth.php';
require_once 'config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$fileManager = new FileManager();

$password = $_POST['password'] ?? null;
$expiry_hours = $_POST['expiry_hours'] ?? null;
$max_downloads = -1;
if (!empty($_POST['max_downloads'])) {
    $max_downloads = (int)$_POST['max_downloads'];
}
$uploaded_by = $auth->isLoggedIn() ? $_SESSION['username'] : 'anonymous';

$result = $fileManager->uploadFile(
    $_FILES['file'], 
    $password, 
    $expiry_hours, 
    $max_downloads, 
    $uploaded_by
);

echo json_encode($result);
?>
