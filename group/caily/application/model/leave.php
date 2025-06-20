<?php

class Leave extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'leaves';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'user_id' => array(),
            'start_datetime' => array(),
            'end_datetime' => array(),
            'days' => array(),
            'leave_type' => array(), // paid, unpaid
            'paid_type' => array(), // full, am, pm
            'unpaid_type' => array(), // congratulatory, menstrual, child_nursing
            'reason' => array(),
            'note' => array(),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search'))
        );
        $this->connect();
    }

    function list() {
        $query = sprintf(
            "SELECT * FROM {$this->table} ORDER BY start_datetime DESC"
        );
        return $this->fetchAll($query);
    }

    function add() {
        $data = array(
            'user_id' => $_POST['user_id'],
            'start_datetime' => $_POST['start_datetime'],
            'end_datetime' => $_POST['end_datetime'],
            'days' => $_POST['days'],
            'leave_type' => $_POST['leave_type'],
            'paid_type' => $_POST['leave_type'] === 'paid' ? $_POST['paid_type'] : null,
            'unpaid_type' => $_POST['leave_type'] === 'unpaid' ? $_POST['unpaid_type'] : null,
            'reason' => $_POST['reason'],
            'note' => $_POST['note'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );
        return $this->query_insert($data);
    }

    function edit() {
        $id = $_GET['id'];
        $data = array(
            'start_datetime' => $_POST['start_datetime'],
            'end_datetime' => $_POST['end_datetime'],
            'days' => $_POST['days'],
            'leave_type' => $_POST['leave_type'],
            'paid_type' => $_POST['leave_type'] === 'paid' ? $_POST['paid_type'] : null,
            'unpaid_type' => $_POST['leave_type'] === 'unpaid' ? $_POST['unpaid_type'] : null,
            'reason' => $_POST['reason'],
            'note' => $_POST['note'],
            'updated_at' => date('Y-m-d H:i:s')
        );
        return $this->query_update($data, ['id' => $id]);
    }

    function delete() {
        $id = $_GET['id'];
        return $this->query_delete(['id' => $id]);
    }

    function get() {
        $id = $_GET['id'];
        $query = sprintf(
            "SELECT * FROM {$this->table} WHERE id = %d",
            intval($id)
        );
        return $this->fetchOne($query);
    }

    function listByUser($user_id) {
        $query = sprintf(
            "SELECT * FROM {$this->table} WHERE user_id = '%s' ORDER BY start_datetime DESC",
            $this->quote($user_id)
        );
        return $this->fetchAll($query);
    }
} 