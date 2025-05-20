<?php

class Tag extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'tags';
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
        $query = "SELECT * FROM {$this->table} ORDER BY name ASC";
        return $this->fetchAll($query);
    }

    function add($data) {
        $query = sprintf(
            "INSERT INTO {$this->table} (name, description, created_at) VALUES ('%s', '%s', NOW())",
            $this->quote($data['name']),
            $this->quote($data['description'])
        );
        return $this->query($query);
    }

    function edit($id, $data) {
        $query = sprintf(
            "UPDATE {$this->table} SET name = '%s', description = '%s', updated_at = NOW() WHERE id = %d",
            $this->quote($data['name']),
            $this->quote($data['description']),
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

    function getProjectTags($project_id) {
        $query = sprintf(
            "SELECT t.* FROM {$this->table} t 
            INNER JOIN " . DB_PREFIX . "project_tags pt ON t.id = pt.tag_id 
            WHERE pt.project_id = %d 
            ORDER BY t.name ASC",
            intval($project_id)
        );
        return $this->fetchAll($query);
    }

    function addProjectTag($project_id, $tag_id) {
        $query = sprintf(
            "INSERT INTO " . DB_PREFIX . "project_tags (project_id, tag_id) VALUES (%d, %d)",
            intval($project_id),
            intval($tag_id)
        );
        return $this->query($query);
    }

    function removeProjectTag($project_id, $tag_id) {
        $query = sprintf(
            "DELETE FROM " . DB_PREFIX . "project_tags WHERE project_id = %d AND tag_id = %d",
            intval($project_id),
            intval($tag_id)
        );
        return $this->query($query);
    }
} 