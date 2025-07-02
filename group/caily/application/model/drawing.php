<?php

class Drawing extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'project_drawings';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'project_id' => array('type' => 'int'),
            'name' => array(),
            'status' => array(),
            'file_path' => array(),
            'created_by' => array('type' => 'int'),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search'))
        );
        $this->connect();
    }

    function list($params = null) {
        $whereArr = [];
        
        // Handle both direct parameters and params array from API
        if (is_array($params)) {
            if (isset($params['project_id'])) {
                $whereArr[] = sprintf("d.project_id = %d", intval($params['project_id']));
            }
        } else {
            if (isset($_GET['project_id'])) {
                $whereArr[] = sprintf("d.project_id = %d", intval($_GET['project_id']));
            }
        }
        
        $where = !empty($whereArr) ? "WHERE " . implode(" AND ", $whereArr) : "";
        
        $query = sprintf(
            "SELECT d.*, u.realname as created_by_name
            FROM {$this->table} d 
            LEFT JOIN " . DB_PREFIX . "user u ON d.created_by = u.id 
            %s
            ORDER BY d.created_at DESC",
            $where
        );
        
        return $this->fetchAll($query);
    }

    function add() {
        $data = array(
            'project_id' => $_POST['project_id'],
            'name' => $_POST['name'],
            'status' => isset($_POST['status']) ? $_POST['status'] : 'draft',
            'file_path' => '', // Always empty since we only save filename
            'created_by' => $_SESSION['user_id'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        $drawing_id = $this->query_insert($data);
        
        if($drawing_id){
            return [
                'status' => 'success',
                'id' => $drawing_id
            ];
        }
        
        return [
            'status' => 'error'
        ];
    }

    function edit() {
        $id = $_POST['id'];
        
        $data = array(
            'name' => $_POST['name'],
            'status' => isset($_POST['status']) ? $_POST['status'] : 'draft',
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        $result = $this->query_update($data, ['id' => $id]);
        
        if($result){
            return [
                'status' => 'success'
            ];
        }
        return [
            'status' => 'error'
        ];
    }

    function delete() {
        $id = isset($_POST['id']) ? $_POST['id'] : 0;
        if(!$id){
            return [
                'status' => 'error',
                'message' => 'ファイルIDが指定されていません'
            ];
        }
        
        $result = $this->query_delete(['id' => $id]);
        
        if($result){
            return [
                'status' => 'success'
            ];
        }
        return [
            'status' => 'error'
        ];
    }

    function updateStatus() {
        $id = $_POST['id'];
        $status = $_POST['status'];
        $data = array(
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        $result = $this->query_update($data, ['id' => $id]);
        
        return $result;
    }

    function bulkUpdateStatus() {
        $ids = json_decode($_POST['ids'], true);
        $status = $_POST['status'];
        
        if (empty($ids) || !is_array($ids)) {
            return [
                'status' => 'error',
                'message' => 'IDが指定されていません'
            ];
        }
        
        $ids_str = implode(',', array_map('intval', $ids));
        $query = sprintf(
            "UPDATE {$this->table} SET status = '%s', updated_at = '%s' WHERE id IN (%s)",
            $status,
            date('Y-m-d H:i:s'),
            $ids_str
        );
        
        $result = $this->query($query);
        
        if($result){
            return [
                'status' => 'success'
            ];
        }
        return [
            'status' => 'error'
        ];
    }

    function bulkDelete() {
        $ids = json_decode($_POST['ids'], true);
        
        if (empty($ids) || !is_array($ids)) {
            return [
                'status' => 'error',
                'message' => 'IDが指定されていません'
            ];
        }
        
        $ids_str = implode(',', array_map('intval', $ids));
        $query = sprintf("DELETE FROM {$this->table} WHERE id IN (%s)", $ids_str);
        
        $result = $this->query($query);
        
        if($result){
            return [
                'status' => 'success'
            ];
        }
        return [
            'status' => 'error'
        ];
    }

    function getById($params = null) {
        // Handle both direct ID parameter and params array from API
        if (is_array($params)) {
            $id = isset($params['id']) ? $params['id'] : 0;
        } else {
            $id = $params;
        }

        $query = sprintf(
            "SELECT d.*, u.realname as created_by_name 
            FROM {$this->table} d 
            LEFT JOIN " . DB_PREFIX . "user u ON d.created_by = u.id 
            WHERE d.id = %d",
            intval($id)
        );
        return $this->fetchOne($query);
    }

    function getByProject($params = null) {
        // Handle both direct project_id parameter and params array from API
        if (is_array($params)) {
            $project_id = isset($params['project_id']) ? intval($params['project_id']) : 0;
        } else {
            $project_id = intval($params);
        }
        
        if (!$project_id) {
            return [];
        }
        
        $query = sprintf(
            "SELECT d.*, u.realname as created_by_name
            FROM {$this->table} d 
            LEFT JOIN " . DB_PREFIX . "user u ON d.created_by = u.id 
            WHERE d.project_id = %d 
            ORDER BY d.created_at DESC",
            $project_id
        );
        return $this->fetchAll($query);
    }
} 