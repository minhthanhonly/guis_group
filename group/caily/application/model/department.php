<?php

class Department extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'departments';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'name' => array(),
            'description' => array(),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search'))
        );
        $this->connect();
    }

    function list() {
        $query = sprintf(
            "SELECT c.*, 
            (SELECT COUNT(*) FROM " . DB_PREFIX . "projects WHERE department_id = c.id) as project_count
            FROM {$this->table} c
            ORDER BY c.name ASC"
        );
        return $this->fetchAll($query);
    }

    function add() {
        $data = array(
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );
        return $this->query_insert($data);
    }

    function edit() {
        $id = $_GET['id'];
        $data = array(
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'updated_at' => date('Y-m-d H:i:s')
        );
        return $this->query_update($data, ['id' => $id]);
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

        $query = sprintf("DELETE FROM {$this->table} WHERE id = %d", intval($id));
        return $this->query($query);
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
        return $this->fetchOne($query);
    }
} 