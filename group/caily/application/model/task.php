<?php

class Task extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'tasks';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'project_id' => array('type' => 'int'),
            'title' => array(),
            'description' => array(),
            'status' => array(),
            'priority' => array(),
            'assigned_to' => array('type' => 'int'),
            'created_by' => array('type' => 'int'),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search')),
            'due_date' => array(),
            'progress' => array('type' => 'int'),
            'category_id' => array('type' => 'int'),
            'estimated_hours' => array('type' => 'float'),
            'actual_hours' => array('type' => 'float')
        );
        $this->connect();
    }

    function list() {
        $query = sprintf(
            "SELECT t.*, p.name as project_name, u.name as assigned_to_name, c.name as category_name 
            FROM {$this->table} t 
            LEFT JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id 
            LEFT JOIN " . DB_PREFIX . "users u ON t.assigned_to = u.id 
            LEFT JOIN " . DB_PREFIX . "categories c ON t.category_id = c.id 
            ORDER BY t.created_at DESC"
        );
        return $this->fetchAll($query);
    }

    function add() {
        $data = array(
            'project_id' => $_POST['project_id'],
            'title' => $_POST['title'],
            'description' => $_POST['description'],
            'status' => $_POST['status'],
            'priority' => $_POST['priority'],
            'assigned_to' => $_POST['assigned_to'],
            'created_by' => $_POST['created_by'],
            'due_date' => $_POST['due_date'],
            'category_id' => $_POST['category_id'],
            'estimated_hours' => $_POST['estimated_hours'],
            'actual_hours' => $_POST['actual_hours'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );
        return $this->insert($data);
    }

    function edit($id) {
        $data = array(
            'project_id' => $_POST['project_id'],
            'title' => $_POST['title'],
            'description' => $_POST['description'],
            'status' => $_POST['status'],
            'priority' => $_POST['priority'],
            'assigned_to' => $_POST['assigned_to'],
            'due_date' => $_POST['due_date'],
            'category_id' => $_POST['category_id'],
            'estimated_hours' => $_POST['estimated_hours'],
            'actual_hours' => $_POST['actual_hours'],
            'updated_at' => date('Y-m-d H:i:s')
        );
        return $this->update($data, ['id' => $id]);
    }

    function delete($id) {
        // Check if task has time entries or comments
        $query = sprintf(
            "SELECT COUNT(*) as count FROM " . DB_PREFIX . "time_entries WHERE task_id = %d",
            intval($id)
        );
        $timeEntries = $this->fetchOne($query)['count'];

        $query = sprintf(
            "SELECT COUNT(*) as count FROM " . DB_PREFIX . "comments WHERE task_id = %d",
            intval($id)
        );
        $comments = $this->fetchOne($query)['count'];
        
        if ($timeEntries > 0 || $comments > 0) {
            throw new Exception('このタスクには時間記録やコメントが存在するため、削除できません。');
        }
        
        return $this->delete(['id' => $id]);
    }

    function getTimeEntries($taskId) {
        $query = sprintf(
            "SELECT te.*, u.name as user_name 
            FROM " . DB_PREFIX . "time_entries te 
            LEFT JOIN " . DB_PREFIX . "users u ON te.user_id = u.id 
            WHERE te.task_id = %d 
            ORDER BY te.start_time DESC",
            intval($taskId)
        );
        return $this->fetchAll($query);
    }

    function getComments($taskId) {
        $query = sprintf(
            "SELECT c.*, u.name as user_name 
            FROM " . DB_PREFIX . "comments c 
            LEFT JOIN " . DB_PREFIX . "users u ON c.user_id = u.id 
            WHERE c.task_id = %d 
            ORDER BY c.created_at DESC",
            intval($taskId)
        );
        return $this->fetchAll($query);
    }

    function addTimeEntry($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->table = DB_PREFIX . 'time_entries';
        $result = $this->insert($data);
        $this->table = DB_PREFIX . 'tasks'; // Reset table back to tasks
        return $result;
    }

    function updateTimeEntry($data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->table = DB_PREFIX . 'time_entries';
        $result = $this->update($data, ['task_id' => $data['task_id']]);
        $this->table = DB_PREFIX . 'tasks'; // Reset table back to tasks
        return $result;
    }

    function addComment($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->table = DB_PREFIX . 'comments';
        $result = $this->insert($data);
        $this->table = DB_PREFIX . 'tasks'; // Reset table back to tasks
        return $result;
    }

    function getById($id) {
        $query = sprintf(
            "SELECT t.*, p.name as project_name, u.name as assigned_to_name, c.name as category_name 
            FROM {$this->table} t 
            LEFT JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id 
            LEFT JOIN " . DB_PREFIX . "users u ON t.assigned_to = u.id 
            LEFT JOIN " . DB_PREFIX . "categories c ON t.category_id = c.id 
            WHERE t.id = %d",
            intval($id)
        );
        return $this->fetchOne($query);
    }
} 