<?php
require_once 'classes/Auth.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->logout();
header('Location: /');
exit;
?>