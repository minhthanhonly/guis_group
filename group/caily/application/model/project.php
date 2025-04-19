<?php

class Project extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'projects';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'code' => array(),
            'address' => array(),
            'tags' => array(),
            'notes' => array(),
            'specifications' => array(),
            'estimated_hours' => array('type' => 'float'),
            'actual_hours' => array('type' => 'float'),
            'assignees' => array(),
            'responsible_person' => array(),
            'viewable_groups' => array(),
            'status' => array(),
            'progress' => array()
        );
        $this->connect();
    }

    function list() {
        $query = "SELECT * FROM {$this->table} ORDER BY created_at DESC";
        return $this->fetchAll($query);
    }

    function add($data) {
        $query = sprintf(
            "INSERT INTO {$this->table} (code, address, tags, notes, specifications, estimated_hours, actual_hours, assignees, responsible_person, viewable_groups, status, progress, created_at) VALUES ('%s', '%s', '%s', '%s', '%s', %d, %d, '%s', '%s', '%s', '%s', %d, NOW())",
            $this->quote($data['code']),
            $this->quote($data['address']),
            $this->quote($data['tags']),
            $this->quote($data['notes']),
            $this->quote($data['specifications']),
            intval($data['estimated_hours']),
            intval($data['actual_hours']),
            $this->quote($data['assignees']),
            $this->quote($data['responsible_person']),
            $this->quote($data['viewable_groups']),
            $this->quote($data['status']),
            intval($data['progress'])
        );
        return $this->query($query);
    }

    function edit($id, $data) {
        $query = sprintf(
            "UPDATE {$this->table} SET code = '%s', address = '%s', tags = '%s', notes = '%s', specifications = '%s', estimated_hours = %f, actual_hours = %f, assignees = '%s', responsible_person = '%s', viewable_groups = '%s', status = '%s', progress = %d WHERE id = %d",
            $this->quote($data['code']),
            $this->quote($data['address']),
            $this->quote($data['tags']),
            $this->quote($data['notes']),
            $this->quote($data['specifications']),
            floatval($data['estimated_hours']),
            floatval($data['actual_hours']),
            $this->quote($data['assignees']),
            $this->quote($data['responsible_person']),
            $this->quote($data['viewable_groups']),
            $this->quote($data['status']),
            intval($data['progress']),
            intval($id)
        );
        return $this->query($query);
    }

    function delete($id) {
        $query = sprintf("DELETE FROM {$this->table} WHERE id = %d", intval($id));
        return $this->query($query);
    }

    function get($id) {
        $query = sprintf("SELECT * FROM {$this->table} WHERE id = %d", intval($id));
        return $this->fetchOne($query);
    }
}

?>