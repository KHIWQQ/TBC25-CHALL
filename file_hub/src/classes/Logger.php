<?php
class Logger {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function log($file_id, $action, $details = []) {
        $stmt = $this->db->prepare("
            INSERT INTO activity_logs (file_id, action, ip_address, user_agent, details) 
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $file_id,
            $action,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            json_encode($details)
        ]);
    }

    public function getActivityLogs($limit = 100, $file_id = null) {
        $sql = "
            SELECT al.*, f.original_filename 
            FROM activity_logs al 
            LEFT JOIN files f ON al.file_id = f.id 
        ";
        
        $params = [];
        if ($file_id) {
            $sql .= " WHERE al.file_id = ?";
            $params[] = $file_id;
        }
        
        $sql .= " ORDER BY al.timestamp DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
?>