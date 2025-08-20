<?php

require_once '../config/database.php'; 
header('Content-Type: application/json');

$username = $_GET['username'] ?? $_POST['username'] ?? null;

if (!$username) {
    http_response_code(400);
    echo json_encode(['error' => 'Username is required']);
    exit;
}


try {

    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id, username FROM users WHERE username = ? ";
    $stmt = $database->executeQuery($query, [$username]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($userData) {
        echo json_encode($userData);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}

?>
