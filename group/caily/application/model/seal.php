<?php

class Seal extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'seals';
        // Add integer fields that should not be quoted
        $this->donotquote = array_merge($this->donotquote, array(
            'file_size', 'is_active'
        ));
        $this->schema = array(
            'id' => array('except' => array('search')),
            'name' => array(),
            'type' => array(),
            'owner_name' => array(),
            'owner_id' => array(),
            'description' => array(),
            'image_path' => array(),
            'file_name' => array(),
            'file_size' => array(),
            'mime_type' => array(),
            'is_active' => array(),
            'created_by' => array(),
            'updated_by' => array(),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search'))
        );
        $this->connect();
    }

    function list() {
        $query = sprintf(
            "SELECT s.*, 
            CASE 
                WHEN s.type = 'employee' AND s.owner_id IS NOT NULL THEN u.realname
                ELSE s.owner_name 
            END as display_owner_name
            FROM {$this->table} s
            LEFT JOIN %s u ON s.owner_id = u.userid
            ORDER BY s.type ASC, s.name ASC", 
            DB_PREFIX . 'user'
        );
        return $this->fetchAll($query);
    }

    function add() {
        // Debug: Log received data
        error_log("Seal add() - POST data: " . print_r($_POST, true));
        
        $data = array(
            'name' => $_POST['name'],
            'type' => $_POST['type'],
            'owner_id' => !empty($_POST['owner_id']) ? $_POST['owner_id'] : null,
            'description' => $_POST['description'],
            'is_active' => (isset($_POST['is_active']) && $_POST['is_active'] === '1') ? 1 : 0,
            'created_by' => $_SESSION['userid'] ?? null,
            'updated_by' => $_SESSION['userid'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        // Debug: Log data to be inserted
        error_log("Seal add() - Data to insert: " . print_r($data, true));
        
        // Handle file upload if present
        if (isset($_FILES['seal_image']) && $_FILES['seal_image']['error'] === UPLOAD_ERR_OK) {
            $upload_result = $this->upload_file();
            $data['image_path'] = $upload_result['image_path'];
            $data['file_name'] = $upload_result['file_name'];
            $data['file_size'] = $upload_result['file_size'];
            $data['mime_type'] = $upload_result['mime_type'];
        }
        
        $result = $this->query_insert($data);
        error_log("Seal add() - Insert result: " . print_r($result, true));
        return $result;
    }

    function edit() {
        $id = $_GET['id'];
        
        // Debug: Log received data
        error_log("Seal edit() - POST data: " . print_r($_POST, true));
        error_log("Seal edit() - ID: " . $id);
        
        $data = array(
            'name' => $_POST['name'],
            'type' => $_POST['type'],
            'owner_id' => !empty($_POST['owner_id']) ? $_POST['owner_id'] : null,
            'description' => $_POST['description'],
            'is_active' => (isset($_POST['is_active']) && $_POST['is_active'] === '1') ? 1 : 0,
            'updated_by' => $_SESSION['userid'] ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        // Debug: Log data to be updated
        error_log("Seal edit() - Data to update: " . print_r($data, true));
        
        // Handle file upload if present
        if (isset($_FILES['seal_image']) && $_FILES['seal_image']['error'] === UPLOAD_ERR_OK) {
            // Delete old image if exists
            $old_seal = $this->get();
            if ($old_seal && !empty($old_seal['image_path'])) {
                $old_file_path = $_SERVER['DOCUMENT_ROOT'] . $old_seal['image_path'];
                if (file_exists($old_file_path)) {
                    unlink($old_file_path);
                }
            }
            
            $upload_result = $this->upload_file();
            $data['image_path'] = $upload_result['image_path'];
            $data['file_name'] = $upload_result['file_name'];
            $data['file_size'] = $upload_result['file_size'];
            $data['mime_type'] = $upload_result['mime_type'];
        }
        
        $result = $this->query_update($data, ['id' => $id]);
        error_log("Seal edit() - Update result: " . print_r($result, true));
        return $result;
    }

    function delete() {
        $id = $_GET['id'];
        
        // Get seal info to delete file
        $seal = $this->get();
        if ($seal && !empty($seal['image_path'])) {
            $file_path = $_SERVER['DOCUMENT_ROOT'] . $seal['image_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        $query = sprintf("DELETE FROM {$this->table} WHERE id = %d", intval($id));
        return $this->query($query);
    }

    function get() {
        $id = $_GET['id'];
        $query = sprintf(
            "SELECT s.*, 
            CASE 
                WHEN s.type = 'employee' AND s.owner_id IS NOT NULL THEN u.realname
                ELSE s.owner_name 
            END as display_owner_name
            FROM {$this->table} s
            LEFT JOIN %s u ON s.owner_id = u.userid
            WHERE s.id = %d",
            DB_PREFIX . 'user',
            intval($id)
        );
        return $this->fetchOne($query);
    }

    function get_employees() {
        $query = sprintf(
            "SELECT userid, realname FROM %s WHERE is_suspend = 0 OR is_suspend IS NULL OR is_suspend = '' ORDER BY id ASC",
            DB_PREFIX . 'user'
        );
        return $this->fetchAll($query);
    }

    function upload_file() {
        $upload_dir = 'assets/upload/seals/';
        $full_upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/' . $upload_dir;
        
        // Create directory if it doesn't exist
        if (!is_dir($full_upload_dir)) {
            if (!mkdir($full_upload_dir, 0755, true)) {
                throw new Exception('ディレクトリの作成に失敗しました: ' . $full_upload_dir);
            }
        }
        
        if (!isset($_FILES['seal_image'])) {
            throw new Exception('ファイルがアップロードされていません。');
        }
        
        if ($_FILES['seal_image']['error'] !== UPLOAD_ERR_OK) {
            $error_messages = array(
                UPLOAD_ERR_INI_SIZE => 'ファイルサイズが大きすぎます。',
                UPLOAD_ERR_FORM_SIZE => 'フォームのファイルサイズ制限を超えています。',
                UPLOAD_ERR_PARTIAL => 'ファイルが部分的にしかアップロードされていません。',
                UPLOAD_ERR_NO_FILE => 'ファイルがアップロードされていません。',
                UPLOAD_ERR_NO_TMP_DIR => '一時ディレクトリが見つかりません。',
                UPLOAD_ERR_CANT_WRITE => 'ディスクへの書き込みに失敗しました。',
                UPLOAD_ERR_EXTENSION => 'PHP拡張機能によってアップロードが停止されました。'
            );
            $error_message = isset($error_messages[$_FILES['seal_image']['error']]) 
                ? $error_messages[$_FILES['seal_image']['error']] 
                : 'アップロードエラー: ' . $_FILES['seal_image']['error'];
            throw new Exception($error_message);
        }
        
        $file = $_FILES['seal_image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp'];
        
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('対応していないファイル形式です。JPEG, PNG, GIF, BMPのみ対応しています。');
        }
        
        $max_size = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $max_size) {
            throw new Exception('ファイルサイズが大きすぎます。5MB以下にしてください。');
        }
        
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
        $file_path = $full_upload_dir . $new_filename;
        
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception('ファイルの保存に失敗しました。パス: ' . $file_path);
        }
        
        return array(
            'image_path' => '/' . $upload_dir . $new_filename,
            'file_name' => $file['name'],
            'file_size' => $file['size'],
            'mime_type' => $file['type']
        );
    }
}
