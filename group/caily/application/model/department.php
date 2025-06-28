<?php

class Department extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'departments';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'name' => array(),
            'description' => array(),
            'can_project' => array(),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search')),
            'is_active' => array()
        );
        $this->connect();
    }

    function list() {
        $query = sprintf(
            "SELECT c.*, 
            (SELECT COUNT(*) FROM " . DB_PREFIX . "projects WHERE department_id = c.id) as project_count,
            (SELECT COUNT(*) FROM " . DB_PREFIX . "user_department WHERE department_id = c.id) as num_employees
            FROM {$this->table} c
            WHERE c.is_active = 1
            ORDER BY c.id ASC"
        );
        return $this->fetchAll($query);
    }

    function listByUser() {
        $query = sprintf( 
            "SELECT DISTINCT c.*
            FROM {$this->table} c
            JOIN " . DB_PREFIX . "user_department ud ON c.id = ud.department_id
            WHERE ud.userid = '%s' AND c.can_project = 1
            ORDER BY c.id ASC",
            $_SESSION['userid']
        );
        return $this->fetchAll($query);
    }

    function list_department() {
        $query = sprintf(
            "SELECT c.*
            FROM {$this->table} c
            WHERE c.is_active = 1
            ORDER BY c.id ASC"
        );
        return $this->fetchAll($query);
    }

    function add() {
        $data = array(
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'can_project' => $_POST['can_project'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );
        $department_id = $this->query_insert($data);
        
        // Add department members
        if (isset($_POST['members']) && is_array($_POST['members'])) {
            foreach ($_POST['members'] as $user_id) {
                $member_data = array(
                    'department_id' => $department_id,
                    'userid' => $user_id,
                    'project_manager' => isset($_POST['project_manager'][$user_id]) && $_POST['project_manager'][$user_id] == 'true' ? 1 : 0,
                    'project_director' => isset($_POST['project_director'][$user_id]) && $_POST['project_director'][$user_id] == 'true' ? 1 : 0,
                    'project_add' => isset($_POST['project_add'][$user_id]) && $_POST['project_add'][$user_id] == 'true' ? 1 : 0,
                    'project_edit' => isset($_POST['project_edit'][$user_id]) && $_POST['project_edit'][$user_id] == 'true' ? 1 : 0,
                    'project_delete' => isset($_POST['project_delete'][$user_id]) && $_POST['project_delete'][$user_id] == 'true' ? 1 : 0,
                    'project_comment' => isset($_POST['project_comment'][$user_id]) && $_POST['project_comment'][$user_id] == 'true' ? 1 : 0,
                    'task_view' => isset($_POST['task_view'][$user_id]) && $_POST['task_view'][$user_id] == 'true' ? 1 : 0,
                    'task_add' => isset($_POST['task_add'][$user_id]) && $_POST['task_add'][$user_id] == 'true' ? 1 : 0,
                    'task_edit' => isset($_POST['task_edit'][$user_id]) && $_POST['task_edit'][$user_id] == 'true' ? 1 : 0,
                    'task_delete' => isset($_POST['task_delete'][$user_id]) && $_POST['task_delete'][$user_id] == 'true' ? 1 : 0
                );
                $this->query_insert($member_data, DB_PREFIX . 'user_department');
            }
        }
        
        return $department_id;
    }

    function edit() {
        $id = $_GET['id'];
        $data = array(
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'can_project' => $_POST['can_project'],
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        // Update department info
        $this->query_update($data, ['id' => $id]);
        
        // Update department members
        if (isset($_POST['members']) && is_array($_POST['members'])) {
            // First delete existing members
            $this->query("DELETE FROM " . DB_PREFIX . "user_department WHERE department_id = " . intval($id));
            
            // Then add new members
            foreach ($_POST['members'] as $user_id) {
                $member_data = array(
                    'department_id' => $id,
                    'userid' => $user_id,
                    'project_manager' => isset($_POST['project_manager'][$user_id]) && $_POST['project_manager'][$user_id] == 'true' ? 1 : 0,
                    'project_director' => isset($_POST['project_director'][$user_id]) && $_POST['project_director'][$user_id] == 'true' ? 1 : 0,
                    'project_add' => isset($_POST['project_add'][$user_id]) && $_POST['project_add'][$user_id] == 'true' ? 1 : 0,
                    'project_edit' => isset($_POST['project_edit'][$user_id]) && $_POST['project_edit'][$user_id] == 'true' ? 1 : 0,
                    'project_delete' => isset($_POST['project_delete'][$user_id]) && $_POST['project_delete'][$user_id] == 'true' ? 1 : 0,
                    'project_comment' => isset($_POST['project_comment'][$user_id]) && $_POST['project_comment'][$user_id] == 'true' ? 1 : 0,
                    'task_view' => isset($_POST['task_view'][$user_id]) && $_POST['task_view'][$user_id] == 'true' ? 1 : 0,
                    'task_add' => isset($_POST['task_add'][$user_id]) && $_POST['task_add'][$user_id] == 'true' ? 1 : 0,
                    'task_edit' => isset($_POST['task_edit'][$user_id]) && $_POST['task_edit'][$user_id] == 'true' ? 1 : 0,
                    'task_delete' => isset($_POST['task_delete'][$user_id]) && $_POST['task_delete'][$user_id] == 'true' ? 1 : 0
                );
                $this->query_insert($member_data, DB_PREFIX . 'user_department');
            }
        }
        
        return true;
    }

    function delete() {
        $id = $_GET['id'];
        // Check if category is in use
        $query = sprintf(
            "SELECT COUNT(*) as count FROM " . DB_PREFIX . "projects WHERE department_id = %d",
            intval($id)
        );
        $result = $this->fetchOne($query);
        
        if ($result['count'] > 0) {
            throw new Exception('この部署は使用中のため削除できません。');
        }
        $query = sprintf("DELETE FROM groupware_user_department WHERE department_id = %d", intval($id));
        $this->query($query);
        return $this->query_update(['is_active' => 0], ['id' => $id]);
    }

    function get() {
        $id = $_GET['id'];
        $query = sprintf(
            "SELECT c.*, 
            (SELECT COUNT(*) FROM " . DB_PREFIX . "projects WHERE department_id = c.id) as project_count
            FROM {$this->table} c
            WHERE c.id = %d",
            intval($id)
        );
        $department = $this->fetchOne($query);
        
        // Get department members with their permissions
        if ($department) {
            $query = sprintf(
                "SELECT ud.userid, u.realname as user_name,
                ud.project_manager, ud.project_director, ud.project_add, ud.project_edit, ud.project_delete, ud.project_comment,
                ud.task_view, ud.task_add, ud.task_edit, ud.task_delete
                FROM " . DB_PREFIX . "user_department ud
                LEFT JOIN " . DB_PREFIX . "user u ON u.userid = ud.userid
                WHERE ud.department_id = %d AND (u.is_suspend = 0 OR u.is_suspend IS NULL)",
                intval($id)
            );
            $department['members'] = $this->fetchAll($query);
        }
        
        return $department;
    }

    function get_users() {
        $department_id = $_GET['department_id'];
        $query = sprintf(
            "SELECT u.id, u.realname as user_name, u.userid as user_id
            FROM " . DB_PREFIX . "user u
            JOIN " . DB_PREFIX . "user_department ud ON u.userid = ud.userid
            WHERE ud.department_id = %d
            AND (u.is_suspend = 0 OR u.is_suspend IS NULL)
            ORDER BY u.userid ASC",
            intval($department_id)
        );
        return $this->fetchAll($query);
    }

    function get_user_permissions() {
        $userid = $_GET['userid'];
        $query = sprintf(
            "SELECT ud.*, d.name as department_name, d.id as department_id
            FROM " . DB_PREFIX . "user_department ud
            JOIN " . DB_PREFIX . "departments d ON ud.department_id = d.id
            WHERE ud.userid = %d",
            intval($userid)
        );
        return $this->fetchAll($query);
    }

    function get_user_permission_by_department() {
        $userid = $_SESSION['userid'];
        $department_id = $_GET['department_id'];
        $query = sprintf(
            "SELECT ud.*
            FROM " . DB_PREFIX . "user_department ud
            WHERE ud.userid = '%s' AND ud.department_id = %d",
            $userid,
            intval($department_id)
        );
        return $this->fetchOne($query);
    }

    // --- Custom Fields Management ---
    function saveCustomFields() {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        // Lưu vào 1 bảng riêng: department_custom_fields (id, department_id, fields)
        foreach ($data['sets'] as $set) {
            $department_id = intval($set['department_id']);
            $name = $set['name'];
            $fields = json_encode($set['fields'], JSON_UNESCAPED_UNICODE);
            // Kiểm tra tồn tại
            $exists = $this->fetchOne("SELECT id FROM " . "department_custom_fields WHERE department_id = $department_id");
            if ($exists) {
                $this->query_update(['fields' => $fields, 'name' => $name], ['department_id' => $department_id], 'department_custom_fields');
            } else {
                $this->query_insert(['department_id' => $department_id, 'fields' => $fields, 'name' => $name], 'department_custom_fields');
            }
        }
        return ['success' => true];
    }

    function getCustomFields() {
        $rows = $this->fetchAll("SELECT * FROM " . "department_custom_fields");
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'department_id' => $row['department_id'],
                'fields' => json_decode($row['fields'], true)
            ];
        }
        return $result;
    }
} 