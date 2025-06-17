<?php

class Task extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'tasks';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'project_id' => array('type' => 'int'),
            'parent_id' => array('type' => 'int'),
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
        $whereArr = [];
        
        if (isset($_GET['project_id'])) {
            $whereArr[] = sprintf("t.project_id = %d", intval($_GET['project_id']));
        }
        
        if (isset($_GET['parent_id'])) {
            $whereArr[] = sprintf("t.parent_id = %d", intval($_GET['parent_id']));
        } else if (!isset($_GET['include_subtasks'])) {
            $whereArr[] = "t.parent_id IS NULL";
        }
        
        $where = !empty($whereArr) ? "WHERE " . implode(" AND ", $whereArr) : "";
        
        $query = sprintf(
            "SELECT t.*, p.name as project_name, u.name as assigned_to_name
            (SELECT COUNT(*) FROM {$this->table} WHERE parent_id = t.id) as subtask_count
            FROM {$this->table} t 
            LEFT JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id 
            LEFT JOIN " . DB_PREFIX . "user u ON t.assigned_to = u.id 
            %s
            ORDER BY t.created_at DESC",
            $where
        );
        
        $tasks = $this->fetchAll($query);
        
        if (isset($_GET['include_subtasks'])) {
            foreach ($tasks as &$task) {
                if ($task['subtask_count'] > 0) {
                    $task['subtasks'] = $this->getSubtasks($task['id']);
                }
            }
        }
        
        return $tasks;
    }

    function getSubtasks($parent_id) {
        $query = sprintf(
            "SELECT t.*, u.name as assigned_to_name
            FROM {$this->table} t 
            LEFT JOIN " . DB_PREFIX . "user u ON t.assigned_to = u.id 
            WHERE t.parent_id = %d 
            ORDER BY t.created_at ASC",
            intval($parent_id)
        );
        return $this->fetchAll($query);
    }

    function add() {
        $data = array(
            'project_id' => $_POST['project_id'],
            'parent_id' => isset($_POST['parent_id']) && $_POST['parent_id'] ? $_POST['parent_id'] : null,
            'title' => $_POST['title'],
            'description' => isset($_POST['description']) ? $_POST['description'] : '',
            'status' => isset($_POST['status']) ? $_POST['status'] : 'new',
            'priority' => isset($_POST['priority']) ? $_POST['priority'] : 'medium',
            'assigned_to' => isset($_POST['assigned_to']) ? $_POST['assigned_to'] : null,
            'created_by' => isset($_POST['created_by']) ? $_POST['created_by'] : $_SESSION['user_id'],
            'due_date' => isset($_POST['due_date']) ? $_POST['due_date'] : null,
            'category_id' => isset($_POST['category_id']) ? $_POST['category_id'] : null,
            'estimated_hours' => isset($_POST['estimated_hours']) ? $_POST['estimated_hours'] : 0,
            'actual_hours' => isset($_POST['actual_hours']) ? $_POST['actual_hours'] : 0,
            'progress' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );
                        $task_id = $this->query_insert($data);                if ($task_id && $data['project_id']) {            $this->updateProjectProgress($data['project_id']);        }                return $task_id;
    }

    function edit($id) {
        $data = array(
            'project_id' => $_POST['project_id'],
            'parent_id' => isset($_POST['parent_id']) && $_POST['parent_id'] ? $_POST['parent_id'] : null,
            'title' => $_POST['title'],
            'description' => isset($_POST['description']) ? $_POST['description'] : '',
            'status' => isset($_POST['status']) ? $_POST['status'] : 'new',
            'priority' => isset($_POST['priority']) ? $_POST['priority'] : 'medium',
            'assigned_to' => isset($_POST['assigned_to']) ? $_POST['assigned_to'] : null,
            'due_date' => isset($_POST['due_date']) ? $_POST['due_date'] : null,
            'category_id' => isset($_POST['category_id']) ? $_POST['category_id'] : null,
            'estimated_hours' => isset($_POST['estimated_hours']) ? $_POST['estimated_hours'] : 0,
            'actual_hours' => isset($_POST['actual_hours']) ? $_POST['actual_hours'] : 0,
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        if (isset($_POST['progress'])) {
            $data['progress'] = intval($_POST['progress']);
        }
                        $result = $this->query_update($data, ['id' => $id]);                $task = $this->getById($id);        if ($result && $task['parent_id']) {            $this->updateParentTaskProgress($task['parent_id']);        }                if ($result && $task['project_id']) {            $this->updateProjectProgress($task['project_id']);        }                return $result;
    }

    function delete($id) {
        $query = sprintf(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE parent_id = %d",
            intval($id)
        );
        $subtasks = $this->fetchOne($query)['count'];
        
        if ($subtasks > 0) {
            throw new Exception('このタスクにはサブタスクが存在するため、削除できません。');
        }
        
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
        
        $task = $this->getById($id);
                        $result = $this->query_delete(['id' => $id]);                if ($result && $task['parent_id']) {            $this->updateParentTaskProgress($task['parent_id']);        }                if ($result && $task['project_id']) {            $this->updateProjectProgress($task['project_id']);        }                return $result;
    }

    function updateStatus($id, $status) {
        $data = array(
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        if ($status == 'completed') {
            $data['progress'] = 100;
        } else if ($status == 'new') {
                        $data['progress'] = 0;        }                $result = $this->query_update($data, ['id' => $id]);                $task = $this->getById($id);
        if ($result && $task['parent_id']) {
            $this->updateParentTaskProgress($task['parent_id']);
        }
        if ($result && $task['project_id']) {
            $this->updateProjectProgress($task['project_id']);
        }
        
        return $result;
    }

    function updateProgress($id, $progress) {
        $data = array(
            'progress' => intval($progress),
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        if ($progress == 100) {
            $data['status'] = 'completed';
        } else if ($progress > 0) {
            $task = $this->getById($id);
            if ($task['status'] == 'new') {
                $data['status'] = 'in_progress';
            }
                }                $result = $this->query_update($data, ['id' => $id]);                $task = $this->getById($id);
        if ($result && $task['parent_id']) {
            $this->updateParentTaskProgress($task['parent_id']);
        }
        if ($result && $task['project_id']) {
            $this->updateProjectProgress($task['project_id']);
        }
        
        return $result;
    }

    function updateParentTaskProgress($parent_id) {
        $query = sprintf(
            "UPDATE {$this->table} 
            SET progress = (
                SELECT COALESCE(AVG(progress), 0) 
                FROM {$this->table} 
                WHERE parent_id = %d
            ),
            updated_at = '%s'
            WHERE id = %d",
            intval($parent_id),
            date('Y-m-d H:i:s'),
            intval($parent_id)
        );
        return $this->query($query);
    }

    function updateProjectProgress($project_id) {
        $query = sprintf(
            "UPDATE " . DB_PREFIX . "projects 
            SET progress = (
                SELECT COALESCE(AVG(progress), 0) 
                FROM {$this->table} 
                WHERE project_id = %d AND parent_id IS NULL
            ),
            updated_at = '%s'
            WHERE id = %d",
            intval($project_id),
            date('Y-m-d H:i:s'),
            intval($project_id)
        );
        return $this->query($query);
    }

    function getTimeEntries($taskId) {
        $query = sprintf(
            "SELECT te.*, u.name as user_name 
            FROM " . DB_PREFIX . "time_entries te 
            LEFT JOIN " . DB_PREFIX . "user u ON te.user_id = u.id 
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
            LEFT JOIN " . DB_PREFIX . "user u ON c.user_id = u.id 
            WHERE c.task_id = %d 
            ORDER BY c.created_at DESC",
            intval($taskId)
        );
        return $this->fetchAll($query);
    }

    function addTimeEntry($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
                $this->table = DB_PREFIX . 'time_entries';        $result = $this->query_insert($data);        $this->table = DB_PREFIX . 'tasks';
        return $result;
    }

    function updateTimeEntry($data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->table = DB_PREFIX . 'time_entries';
        $result = $this->query_update($data, ['task_id' => $data['task_id']]);
        $this->table = DB_PREFIX . 'tasks';
        return $result;
    }

    function addComment($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
                $this->table = DB_PREFIX . 'comments';        $result = $this->query_insert($data);        $this->table = DB_PREFIX . 'tasks';
        return $result;
    }

    function getById($id) {
        $query = sprintf(
            "SELECT t.*, p.name as project_name, u.name as assigned_to_name 
            FROM {$this->table} t 
            LEFT JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id 
            LEFT JOIN " . DB_PREFIX . "user u ON t.assigned_to = u.id 
            WHERE t.id = %d",
            intval($id)
        );
        return $this->fetchOne($query);
    }
} 