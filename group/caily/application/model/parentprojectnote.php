<?php

class ParentProjectNote extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'parent_project_notes';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'parent_project_id' => array(),
            'user_id' => array(),
            'title' => array(),
            'content' => array(),
            'is_important' => array(),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search'))
        );
        $this->connect();
    }

    function create($params = null) {
        $data = array(
            'parent_project_id' => isset($_POST['parent_project_id']) ? intval($_POST['parent_project_id']) : 0,
            'user_id' => $_SESSION['userid'],
            'title' => isset($_POST['title']) ? $_POST['title'] : '',
            'content' => isset($_POST['content']) ? $_POST['content'] : '',
            'is_important' => isset($_POST['is_important']) ? intval($_POST['is_important']) : 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );

        if (!$data['parent_project_id'] || !$data['title']) {
            return ['status' => 'error', 'error' => 'Missing required fields'];
        }

        $result = $this->query_insert($data);
        if ($result) {
            return ['status' => 'success', 'id' => $result];
        } else {
            return ['status' => 'error', 'error' => 'Failed to create note'];
        }
    }

    function update($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'error' => 'No note id'];

        $note = $this->getById($id);
        if (!$note) {
            return ['status' => 'error', 'error' => 'Note not found'];
        }

        $currentUserId = $_SESSION['userid'];
        $isCreator = (strval($note['user_id']) === strval($currentUserId));
        $isAdmin = (isset($_SESSION['authority']) && $_SESSION['authority'] === 'administrator');

        if (!$isCreator && !$isAdmin) {
            return ['status' => 'error', 'error' => 'Permission denied'];
        }

        $data = array(
            'title' => isset($_POST['title']) ? $_POST['title'] : '',
            'content' => isset($_POST['content']) ? $_POST['content'] : '',
            'is_important' => isset($_POST['is_important']) ? intval($_POST['is_important']) : 0,
            'updated_at' => date('Y-m-d H:i:s')
        );

        if (!$data['title']) {
            return ['status' => 'error', 'error' => 'Title is required'];
        }

        $result = $this->query_update($data, ['id' => $id]);
        if ($result) {
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'error' => 'Failed to update note'];
        }
    }

    function delete($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'error' => 'No note id'];

        $note = $this->getById($id);
        if (!$note) {
            return ['status' => 'error', 'error' => 'Note not found'];
        }

        $currentUserId = $_SESSION['userid'];
        $isCreator = (strval($note['user_id']) === strval($currentUserId));
        $isAdmin = (isset($_SESSION['authority']) && $_SESSION['authority'] === 'administrator');

        if (!$isCreator && !$isAdmin) {
            return ['status' => 'error', 'error' => 'Permission denied'];
        }

        $result = $this->query_delete(['id' => $id]);
        if ($result) {
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'error' => 'Failed to delete note'];
        }
    }

    function getById($id) {
        $query = sprintf(
            "SELECT n.*, u.realname
            FROM {$this->table} n
            LEFT JOIN " . DB_PREFIX . "user u ON n.user_id = u.userid
            WHERE n.id = %d",
            intval($id)
        );
        return $this->fetchOne($query);
    }

    function getByParentProject($parent_project_id) {
        $query = sprintf(
            "SELECT n.*, u.realname
            FROM {$this->table} n
            LEFT JOIN " . DB_PREFIX . "user u ON n.user_id = u.userid
            WHERE n.parent_project_id = %d
            ORDER BY n.is_important DESC, n.created_at DESC",
            intval($parent_project_id)
        );
        return $this->fetchAll($query);
    }

    function list($params = null) {
        $parent_project_id = isset($_GET['parent_project_id']) ? intval($_GET['parent_project_id']) : 0;
        if (!$parent_project_id) {
            return ['status' => 'error', 'error' => 'Parent project ID is required'];
        }

        $notes = $this->getByParentProject($parent_project_id);
        return ['status' => 'success', 'data' => $notes];
    }

    function get($params = null) {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$id) {
            return ['status' => 'error', 'error' => 'Note ID is required'];
        }

        $note = $this->getById($id);
        if ($note) {
            return ['status' => 'success', 'data' => $note];
        } else {
            return ['status' => 'error', 'error' => 'Note not found'];
        }
    }
}
