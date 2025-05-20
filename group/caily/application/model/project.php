<?php

class Project extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'projects';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'name' => array(),
            'description' => array(),
            'status' => array(),
            'start_date' => array(),
            'end_date' => array(),
            'created_by' => array('type' => 'int'),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search')),
            'category_id' => array('type' => 'int'),
            'progress' => array('type' => 'int'),
            'estimated_hours' => array('type' => 'int')
        );
        $this->connect();
    }

    function list() {
        $where = '';
        if (isset($_GET['category_id'])) {
            $where = sprintf(" WHERE p.category_id = %d", intval($_GET['category_id']));
        }

        $query = sprintf(
            "SELECT p.*, c.name as category_name, 
            (SELECT COUNT(*) FROM " . DB_PREFIX . "tasks WHERE project_id = p.id) as task_count,
            (SELECT COUNT(*) FROM " . DB_PREFIX . "project_members WHERE project_id = p.id) as member_count
            FROM {$this->table} p 
            LEFT JOIN " . DB_PREFIX . "categories c ON p.category_id = c.id 
            %s
            ORDER BY p.created_at DESC",
            $where
        );
        return $this->fetchAll($query);
    }

    function add() {
        $data = array(
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'status' => $_POST['status'],
            'start_date' => $_POST['start_date'],
            'end_date' => $_POST['end_date'],
            'created_by' => $_POST['created_by'],
            'category_id' => $_POST['category_id'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );
        return $this->query_insert($data);
    }

    function edit($id) {
        $data = array(
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'status' => $_POST['status'],
            'start_date' => $_POST['start_date'],
            'end_date' => $_POST['end_date'],
            'category_id' => $_POST['category_id'],
            'updated_at' => date('Y-m-d H:i:s')
        );
        return $this->query_update($data, ['id' => $id]);
    }

    function delete($id) {
        // Check if project has tasks or members
        $query = sprintf(
            "SELECT COUNT(*) as count FROM " . DB_PREFIX . "tasks WHERE project_id = %d",
            intval($id)
        );
        $tasks = $this->fetchOne($query)['count'];

        $query = sprintf(
            "SELECT COUNT(*) as count FROM " . DB_PREFIX . "project_members WHERE project_id = %d",
            intval($id)
        );
        $members = $this->fetchOne($query)['count'];
        
        if ($tasks > 0 || $members > 0) {
            throw new Exception('このプロジェクトにはタスクやメンバーが存在するため、削除できません。');
        }
        
        return $this->query_delete(['id' => $id]);
    }

    function getMembers($project_id) {
        $query = sprintf(
            "SELECT pm.*, u.name as user_name 
            FROM " . DB_PREFIX . "project_members pm 
            LEFT JOIN " . DB_PREFIX . "users u ON pm.user_id = u.id 
            WHERE pm.project_id = %d 
            ORDER BY pm.created_at DESC",
            intval($project_id)
        );
        return $this->fetchAll($query);
    }

    function getTasks($projectId) {
        $query = sprintf(
            "SELECT t.*, u.name as assigned_to_name, c.name as category_name 
            FROM " . DB_PREFIX . "tasks t 
            LEFT JOIN " . DB_PREFIX . "users u ON t.assigned_to = u.id 
            LEFT JOIN " . DB_PREFIX . "categories c ON t.category_id = c.id 
            WHERE t.project_id = %d 
            ORDER BY t.created_at DESC",
            intval($projectId)
        );
        return $this->fetchAll($query);
    }

    function addMember($project_id, $user_id) {
        $data = array(
            'project_id' => $project_id,
            'user_id' => $user_id,
            'created_at' => date('Y-m-d H:i:s')
        );
        $this->table = DB_PREFIX . 'project_members';
        $result = $this->query_insert($data);
        $this->table = DB_PREFIX . 'projects'; // Reset table back to projects
        return $result;
    }

    function removeMember($project_id, $user_id) {
        $this->table = DB_PREFIX . 'project_members';
        $result = $this->query_delete(['project_id' => $project_id, 'user_id' => $user_id]);
        $this->table = DB_PREFIX . 'projects'; // Reset table back to projects
        return $result;
    }

    function getById($id) {
        $query = sprintf(
            "SELECT p.*, c.name as category_name, 
            (SELECT COUNT(*) FROM " . DB_PREFIX . "tasks WHERE project_id = p.id) as task_count,
            (SELECT COUNT(*) FROM " . DB_PREFIX . "project_members WHERE project_id = p.id) as member_count
            FROM {$this->table} p 
            LEFT JOIN " . DB_PREFIX . "categories c ON p.category_id = c.id 
            WHERE p.id = %d",
            intval($id)
        );
        return $this->fetchOne($query);
    }

    function updateProgress($projectId) {
        $query = sprintf(
            "UPDATE {$this->table} p 
            SET progress = (
                SELECT COALESCE(AVG(progress), 0) 
                FROM " . DB_PREFIX . "tasks 
                WHERE project_id = %d
            ) 
            WHERE id = %d",
            intval($projectId),
            intval($projectId)
        );
        return $this->query($query);
    }

    function category (){
    }
    function task (){
    }
}

?>