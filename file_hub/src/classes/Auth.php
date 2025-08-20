<?php
session_start();

class Auth {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function login($username, $password) {
        $stmt = $this->db->prepare("
            SELECT * FROM users 
            WHERE username = ? AND is_active = 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            return true;
        }
        return false;
    }

    public function logout() {
        session_destroy();
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: /login.php');
            exit;
        }
    }

    public function requireAdmin() {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            http_response_code(403);
            die('Access denied');
        }
    }
}
?>