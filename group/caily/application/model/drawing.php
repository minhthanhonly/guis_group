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
            'created_by' => array(),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search')),
            'check_date' => array('except' => array('search')),
            'checked_by' => array()
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
            "SELECT d.*, u.realname as created_by_name, c.realname as checked_by_name
            FROM {$this->table} d 
            LEFT JOIN " . DB_PREFIX . "user u ON d.created_by = u.userid 
            LEFT JOIN " . DB_PREFIX . "user c ON d.checked_by = c.userid
            %s
            ORDER BY d.name ASC",
            $where
        );
        
        $drawings = $this->fetchAll($query);
        
        // For each drawing, resolve created_by to names
        foreach ($drawings as &$drawing) {
            $drawing['created_by_names'] = '';
            if (!empty($drawing['created_by'])) {
                $user_ids = array_filter(array_map('trim', explode(',', $drawing['created_by'])));
                if (!empty($user_ids)) {
                    $user_ids_escaped = array_map(function($id) {
                        return "'" . str_replace("'", "''", $id) . "'";
                    }, $user_ids);
                    $user_query = sprintf(
                        "SELECT realname FROM %suser WHERE userid IN (%s)",
                        DB_PREFIX,
                        implode(',', $user_ids_escaped)
                    );
                    $users = $this->fetchAll($user_query);
                    if ($users) {
                        $names = array_column($users, 'realname');
                        $drawing['created_by_names'] = implode(', ', $names);
                    }
                }
            }
        }
        unset($drawing);
        
        return $drawings;
    }

    function add() {
        if(!isset($_POST['project_id']) || !isset($_POST['name'])){
            return [
                'status' => 'error',
                'message' => 'データが不正です'
            ];
        }
        
        $project_id = $_POST['project_id'];
        $name = $_POST['name'];
        
        // Check if file with same name exists and delete it
        $existing_files = $this->getByNameAndProject($name, $project_id);
        $replaced = !empty($existing_files);
        
        if ($replaced) {
            foreach ($existing_files as $existing_file) {
                $this->query_delete(['id' => $existing_file['id']]);
            }
        }
        
        $data = array(
            'project_id' => $project_id,
            'name' => $name,
            'status' => isset($_POST['status']) ? $_POST['status'] : 'draft',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        // Only set created_by if explicitly provided
        if (isset($_POST['created_by'])) {
            $data['created_by'] = $_POST['created_by'];
        }
        
        
        $drawing_id = $this->query_insert($data);
        
        if($drawing_id){
            return [
                'status' => 'success',
                'id' => $drawing_id,
                'replaced' => $replaced
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
        
        // If status is approved or rejected, set check date and checker
        if ($status === 'approved' || $status === 'rejected') {
            $data['check_date'] = date('Y-m-d H:i:s');
            $data['checked_by'] = $_SESSION['userid'];
        }
        
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
        
        // If status is approved or rejected, set check date and checker
        if ($status === 'approved' || $status === 'rejected') {
            $query = sprintf(
                "UPDATE {$this->table} SET status = '%s', updated_at = '%s', check_date = '%s', checked_by = '%s' WHERE id IN (%s)",
                $status,
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s'),
                $_SESSION['userid'],
                $ids_str
            );
        } else {
            $query = sprintf(
                "UPDATE {$this->table} SET status = '%s', updated_at = '%s' WHERE id IN (%s)",
                $status,
                date('Y-m-d H:i:s'),
                $ids_str
            );
        }
        
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
            LEFT JOIN " . DB_PREFIX . "user u ON d.created_by = u.userid 
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
            LEFT JOIN " . DB_PREFIX . "user u ON d.created_by = u.userid 
            WHERE d.project_id = %d 
            ORDER BY d.created_at DESC",
            $project_id
        );
        return $this->fetchAll($query);
    }
    
    function getByNameAndProject($name, $project_id) {
        $query = sprintf(
            "SELECT d.*, u.realname as created_by_name
            FROM {$this->table} d 
            LEFT JOIN " . DB_PREFIX . "user u ON d.created_by = u.userid 
            WHERE d.name = '%s' AND d.project_id = %d",
            $this->quote($name),
            intval($project_id)
        );
        return $this->fetchAll($query);
    }

    function assignUser() {
        $drawing_id = isset($_POST['drawing_id']) ? intval($_POST['drawing_id']) : 0;
        $current_user_id = $_SESSION['userid'];
        
        if (!$drawing_id) {
            return [
                'status' => 'error',
                'message' => 'ファイルIDが指定されていません'
            ];
        }
        
        // Get current drawing to check existing created_by
        $drawing = $this->getById(['id' => $drawing_id]);
        if (!$drawing) {
            return [
                'status' => 'error',
                'message' => 'ファイルが見つかりません'
            ];
        }
        
        // Get current user info
        $user_query = sprintf(
            "SELECT realname FROM " . DB_PREFIX . "user WHERE userid = %d",
            $current_user_id
        );
        $user = $this->fetchOne($user_query);
        
        if (!$user) {
            return [
                'status' => 'error',
                'message' => 'ユーザー情報が見つかりません'
            ];
        }
        
        $current_user_name = $user['realname'];
        
        // Handle existing created_by
        $existing_created_by = $drawing['created_by'];
        
        if (empty($existing_created_by)) {
            // First assignment
            $new_created_by = $current_user_id;
        } else {
            // Check if user is already assigned
            $existing_user_ids = explode(',', $existing_created_by);
            if (in_array($current_user_id, $existing_user_ids)) {
                return [
                    'status' => 'error',
                    'message' => '既に割り当てられています'
                ];
            }
            
            // Add new user to existing list
            $new_created_by = $existing_created_by . ',' . $current_user_id;
        }
        
        // Update the drawing
        $data = array(
            'created_by' => $new_created_by,
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        $result = $this->query_update($data, ['id' => $drawing_id]);
        
        if ($result) {
            return [
                'status' => 'success',
                'message' => '割り当てが完了しました'
            ];
        }
        
        return [
            'status' => 'error',
            'message' => '割り当てに失敗しました'
        ];
    }

    function unassignUser() {
        $drawing_id = isset($_POST['drawing_id']) ? intval($_POST['drawing_id']) : 0;
        $current_user_id = $_SESSION['userid'];
        
        if (!$drawing_id) {
            return [
                'status' => 'error',
                'message' => 'ファイルIDが指定されていません'
            ];
        }
        
        // Get current drawing to check existing created_by
        $drawing = $this->getById(['id' => $drawing_id]);
        if (!$drawing) {
            return [
                'status' => 'error',
                'message' => 'ファイルが見つかりません'
            ];
        }
        
        $existing_created_by = $drawing['created_by'];
        
        if (empty($existing_created_by)) {
            return [
                'status' => 'error',
                'message' => '割り当てられていません'
            ];
        }
        
        // Remove current user from the list
        $existing_user_ids = array_filter(array_map('trim', explode(',', $existing_created_by)));
        $new_user_ids = array_filter($existing_user_ids, function($id) use ($current_user_id) {
            return $id !== $current_user_id;
        });
        
        // Check if user was in the list
        if (count($new_user_ids) === count($existing_user_ids)) {
            return [
                'status' => 'error',
                'message' => '割り当てられていません'
            ];
        }
        
        // Update the drawing
        $new_created_by = implode(',', $new_user_ids);
        $data = array(
            'created_by' => $new_created_by,
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        $result = $this->query_update($data, ['id' => $drawing_id]);
        
        if ($result) {
            return [
                'status' => 'success',
                'message' => '割り当てを解除しました'
            ];
        }
        
        return [
            'status' => 'error',
            'message' => '割り当て解除に失敗しました'
        ];
    }

    function bulkAssignUser() {
        $ids = json_decode($_POST['ids'], true);
        $current_user_id = $_SESSION['userid'];
        
        if (empty($ids) || !is_array($ids)) {
            return [
                'status' => 'error',
                'message' => 'IDが指定されていません'
            ];
        }
        
        $success_count = 0;
        $already_assigned_count = 0;
        
        foreach ($ids as $drawing_id) {
            $drawing = $this->getById(['id' => intval($drawing_id)]);
            if (!$drawing) continue;
            
            $existing_created_by = $drawing['created_by'];
            
            if (empty($existing_created_by)) {
                // First assignment
                $new_created_by = $current_user_id;
            } else {
                // Check if user is already assigned
                $existing_user_ids = explode(',', $existing_created_by);
                if (in_array($current_user_id, $existing_user_ids)) {
                    $already_assigned_count++;
                    continue;
                }
                
                // Add new user to existing list
                $new_created_by = $existing_created_by . ',' . $current_user_id;
            }
            
            // Update the drawing
            $data = array(
                'created_by' => $new_created_by,
                'updated_at' => date('Y-m-d H:i:s')
            );
            
            $result = $this->query_update($data, ['id' => intval($drawing_id)]);
            if ($result) {
                $success_count++;
            }
        }
        
        if ($success_count > 0) {
            $message = $success_count . '件の割り当てが完了しました';
            if ($already_assigned_count > 0) {
                $message .= '（' . $already_assigned_count . '件は既に割り当て済み）';
            }
            return [
                'status' => 'success',
                'message' => $message
            ];
        }
        
        return [
            'status' => 'error',
            'message' => '割り当てに失敗しました'
        ];
    }

    function bulkUnassignUser() {
        $ids = json_decode($_POST['ids'], true);
        $current_user_id = $_SESSION['userid'];
        
        if (empty($ids) || !is_array($ids)) {
            return [
                'status' => 'error',
                'message' => 'IDが指定されていません'
            ];
        }
        
        $success_count = 0;
        $not_assigned_count = 0;
        
        foreach ($ids as $drawing_id) {
            $drawing = $this->getById(['id' => intval($drawing_id)]);
            if (!$drawing) continue;
            
            $existing_created_by = $drawing['created_by'];
            
            if (empty($existing_created_by)) {
                $not_assigned_count++;
                continue;
            }
            
            // Remove current user from the list
            $existing_user_ids = array_filter(array_map('trim', explode(',', $existing_created_by)));
            $new_user_ids = array_filter($existing_user_ids, function($id) use ($current_user_id) {
                return $id !== $current_user_id;
            });
            
            // Check if user was in the list
            if (count($new_user_ids) === count($existing_user_ids)) {
                $not_assigned_count++;
                continue;
            }
            
            // Update the drawing
            $new_created_by = implode(',', $new_user_ids);
            $data = array(
                'created_by' => $new_created_by,
                'updated_at' => date('Y-m-d H:i:s')
            );
            
            $result = $this->query_update($data, ['id' => intval($drawing_id)]);
            if ($result) {
                $success_count++;
            }
        }
        
        if ($success_count > 0) {
            $message = $success_count . '件の割り当て解除が完了しました';
            if ($not_assigned_count > 0) {
                $message .= '（' . $not_assigned_count . '件は割り当てられていませんでした）';
            }
            return [
                'status' => 'success',
                'message' => $message
            ];
        }
        
        return [
            'status' => 'error',
            'message' => '割り当て解除に失敗しました'
        ];
    }
} 