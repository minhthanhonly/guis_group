<?php

class Branch extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'branches';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'name' => array(),
            'name_kana' => array(),
            'registration_number' => array(),
            'company_name' => array(),
            'postal_code' => array(),
            'address1' => array(),
            'address2' => array(),
            'tel' => array(),
            'fax' => array(),
            'email' => array(),
            'type' => array(),
            'description' => array(),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search'))
        );
        $this->connect();
    }

    function list() {
        $query = sprintf(
            "SELECT c.*,
            (SELECT COUNT(*) FROM %s WHERE branch_id = c.id) as num_employees
            FROM {$this->table} c
            ORDER BY id ASC", 
            DB_PREFIX . 'user'
        );
        return $this->fetchAll($query);
    }

    function add() {
        $data = array(
            'name' => $_POST['name'],
            'name_kana' => $_POST['name_kana'],
            'registration_number' => $_POST['registration_number'],
            'company_name' => $_POST['company_name'],
            'postal_code' => $_POST['postal_code'],
            'address1' => $_POST['address1'],
            'address2' => $_POST['address2'],
            'tel' => $_POST['tel'],
            'fax' => $_POST['fax'],
            'email' => $_POST['email'],
            'type' => $_POST['type'],
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
            'name_kana' => $_POST['name_kana'],
            'registration_number' => $_POST['registration_number'],
            'company_name' => $_POST['company_name'],
            'postal_code' => $_POST['postal_code'],
            'address1' => $_POST['address1'],
            'address2' => $_POST['address2'],
            'tel' => $_POST['tel'],
            'fax' => $_POST['fax'],
            'email' => $_POST['email'],
            'type' => $_POST['type'],
            'description' => $_POST['description'],
            'updated_at' => date('Y-m-d H:i:s')
        );
        return $this->query_update($data, ['id' => $id]);
    }

    function delete() {
        $id = $_GET['id'];
        // Check if branch is in use
        $query = sprintf(
            "SELECT COUNT(*) as count FROM " . DB_PREFIX . "projects WHERE branch_id = %d",
            intval($id)
        );
        $result = $this->fetchOne($query);
        
        if ($result['count'] > 0) {
            throw new Exception('この支社は使用中のため削除できません。');
        }

        $query = sprintf("DELETE FROM {$this->table} WHERE id = %d", intval($id));
        return $this->query($query);
    }

    function get() {
        $id = $_GET['id'];
        $query = sprintf(
            "SELECT c.*
            FROM {$this->table} c
            WHERE c.id = %d",
            intval($id)
        );
        return $this->fetchOne($query);
    }

    function get_employees() {
        $id = $_GET['id'];
        $query = sprintf(
            "SELECT userid, realname FROM " . DB_PREFIX . "user WHERE branch_id = %d",
            intval($id)
        );
        return $this->fetchAll($query);
    }
    function add_employee() {
        $query = sprintf(
            "UPDATE " . DB_PREFIX . "user SET branch_id = %d WHERE userid = %d",
            intval($_POST['branch_id']),
            intval($_POST['userid'])
        );
        return $this->query($query);
    }
    function delete_employee() {
        $query = sprintf(
            "UPDATE " . DB_PREFIX . "user SET branch_id = NULL WHERE userid = %d",
            intval($_POST['userid'])
        );
        return $this->query($query);
    }
} 