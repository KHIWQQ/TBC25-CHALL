<?php
require_once 'config/database.php';
require_once 'classes/Logger.php';

class FileManager {
    private $db;
    private $logger;
    private $upload_dir = 'uploads/';

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->logger = new Logger($this->db);
        
        if (!is_dir($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
    }


    private function random_password($length = 8) {
        $hexdigits = '0123456789abcdef';
        $char = $hexdigits[array_rand(str_split($hexdigits))];
        $result = implode('', array_fill(0, $length, $char));
        return $result;
    }
    public function uploadFile($file, $password = null, $expiry_hours = null, $max_downloads = null, $uploaded_by = null) {
        try {
   
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload error: ' . $file['error']);
            }

    
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 
                            'text/plain', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime_type, $allowed_types)) {
                throw new Exception('File type not allowed');
            }

         
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $secure_filename = bin2hex(random_bytes(16)) . '.' . $file_extension;
            $share_token = bin2hex(random_bytes(32));
            $file_path = $this->upload_dir . $secure_filename;

      
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                throw new Exception('Failed to move uploaded file');
            }

   
            $expiry_date = null;
            if ($expiry_hours) {
                $expiry_date = date('Y-m-d H:i:s', time() + ($expiry_hours * 3600));
            }


            if (!$password) {
                $password =  base64_encode($this->random_password(10));
            }


            $password_hash = $password ? password_hash($password, PASSWORD_DEFAULT) : null;


            $stmt = $this->db->prepare("
                INSERT INTO files (filename, original_filename, file_path, file_size, 
                                 mime_type, expiry_date, password_hash, password, max_downloads, 
                                 share_token, uploaded_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $secure_filename,
                $file['name'],
                $file_path,
                $file['size'],
                $mime_type,
                $expiry_date,
                $password_hash,
                $password,
                $max_downloads,
                $share_token,
                $uploaded_by
            ]);

            $file_id = $this->db->lastInsertId();


            $this->logger->log($file_id, 'upload', [
                'filename' => $file['name'],
                'size' => $file['size']
            ]);

            return [
                'success' => true,
                'file_id' => $file_id,
                'password' => $password,
                'share_token' => $share_token,
                'max_downloads' => $max_downloads,
                'share_url' => '/download.php?token=' . $share_token
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getFile($token, $password = null) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM files 
                WHERE share_token = ? AND is_active = 1
            ");
            $stmt->execute([$token]);
            $file = $stmt->fetch();

 

            if (!$file) {
                throw new Exception('File not found');
            }

  
            if ($file['expiry_date'] && strtotime($file['expiry_date']) < time()) {
                throw new Exception('File has expired');
            }

            if ($file['max_downloads'] && $file['max_downloads'] > 0  && $file['download_count'] >= $file['max_downloads']) {
                throw new Exception('Download limit exceeded');
            }


            if ($file['password'] && !$password) {
                return ['success' => false, 'error' => 'Please provide a password to download this file'];
            }

            
            if ($file['password'] && strcmp($password, $file['password']) !== 0) {
                throw new Exception('Invalid password');
            }



            return ['success' => true, 'file' => $file];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function downloadFile($token, $password = null) {
        $result = $this->getFile($token, $password);
        
        if (!$result['success']) {
            return $result;
        }

        $file = $result['file'];



        if (!file_exists($file['file_path'])) {
            return ['success' => false, 'error' => 'File not found on disk'];
        }

        $stmt = $this->db->prepare("
            UPDATE files SET download_count = download_count + 1 
            WHERE id = ?
        ");
        $stmt->execute([$file['id']]);

        $this->logger->log($file['id'], 'download', [
            'filename' => $file['original_filename']
        ]);

        return [
            'success' => true,
            'file_path' => $file['file_path'],
            'filename' => $file['original_filename'],
            'mime_type' => $file['mime_type']
        ];
    }

    public function getFilesList($limit = 50, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT id, original_filename, file_size, upload_date, expiry_date, 
                   download_count, max_downloads, uploaded_by, is_active,
                   share_token
            FROM files 
            ORDER BY upload_date DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }

    public function deleteFile($file_id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM files WHERE id = ?");
            $stmt->execute([$file_id]);
            $file = $stmt->fetch();

            if (!$file) {
                throw new Exception('File not found');
            }

            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }

            $stmt = $this->db->prepare("DELETE FROM files WHERE id = ?");
            $stmt->execute([$file_id]);

            $this->logger->log($file_id, 'delete', [
                'filename' => $file['original_filename']
            ]);

            return ['success' => true];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>
