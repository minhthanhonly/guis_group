<?php

class Project extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'projects';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'project_number' => array(),
            'name' => array(),
            'description' => array(),
            'priority' => array(), //low, medium, high, urgent
            'status' => array(), //draft, open, in_progress, completed, paused, cancelled, deleted
            'start_date' => array(), //timestamp
            'end_date' => array(), //timestamp
            'actual_start_date' => array(), //timestamp
            'actual_end_date' => array(), //timestamp
            'created_by' => array(), //userid
            'created_at' => array('except' => array('search')), //timestamp
            'updated_at' => array('except' => array('search')), //timestamp
            'department_id' => array(), //
            'progress' => array(), //0-100
            'estimated_hours' => array(), //float
            'actual_hours' => array(), //float
            'customer_id' => array(),
            'building_size' => array(), //string
            'building_type' => array(), //string
            'buiding_number' => array(), //list of category_id
            'project_order_type' => array(), //edit, new, custom
            'project_estimate_id' => array(), 
            'amount' => array(), //edit, new, custom
        );
        $this->connect();
    }

    function list() {
        $draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;
        $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
        $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $order_column = isset($_GET['order_column']) ? $_GET['order_column'] : 'created_at';
        $order_dir = isset($_GET['order_dir']) ? $_GET['order_dir'] : 'DESC';
        
        $whereArr = [];
        
        // Add permission check
        $user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $_SESSION['user_id'];
        if (strlen($user_id) > 0 && $_SESSION['authority'] != 'administrator') {
            $whereArr[] = sprintf(
                "(p.created_by = %d OR EXISTS (
                    SELECT 1 FROM " . DB_PREFIX . "project_members pm 
                    WHERE pm.project_id = p.id AND pm.user_id = %d
                ))",
                $user_id,
                $user_id
            );
        }

        if (isset($_GET['department_id'])) {
            $whereArr[] = sprintf("p.department_id = %d", intval($_GET['department_id']));
        }
        if (isset($_GET['status']) && $_GET['status'] != 'all') {
            $whereArr[] = sprintf("p.status = '%s'", $_GET['status']);
        } else {
            $whereArr[] = "p.status != 'deleted'";
        }

        // Add search condition
        if (!empty($search)) {
            $whereArr[] = sprintf(
                "(p.name LIKE '%%%s%%' OR p.project_number LIKE '%%%s%%' OR p.description LIKE '%%%s%%')",
                $this->escape($search),
                $this->escape($search),
                $this->escape($search)
            );
        }

        $where = implode(" AND ", $whereArr);
        if (!empty($where)) {
            $where = " WHERE " . $where;
        }

        // Get total records count
        $totalQuery = "SELECT COUNT(*) as count FROM {$this->table} p" . $where;
        $totalRecords = $this->fetchOne($totalQuery)['count'];

        // Get filtered records count
        $filteredRecords = $totalRecords;

        // Get data for current page
        $query = sprintf(
            "SELECT p.*, d.name as department_name,
            (SELECT GROUP_CONCAT(user_id) FROM groupware_project_members pm WHERE p.id = pm.project_id AND pm.role = 'member') as assignment_id,
            (SELECT GROUP_CONCAT(user_id) FROM groupware_project_members pm WHERE p.id = pm.project_id AND pm.role = 'manager') as manager_id,
            (SELECT GROUP_CONCAT(user_id) FROM groupware_project_members pm WHERE p.id = pm.project_id AND pm.role = 'viewer') as viewer_id
            FROM {$this->table} p 
            LEFT JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id
            %s
            ORDER BY p.%s %s
            LIMIT %d, %d",
            $where,
            $this->escape($order_column),
            $this->escape($order_dir),
            $start,
            $length
        );
        
        $data = $this->fetchAll($query);

        return array(
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data
        );
    }

    private function escape($str) {
        return $this->quote($str);
    }

    function list_old() {
        $perPage = isset($_GET['perPage']) ? intval($_GET['perPage']) : 20;
        $currentPage = isset($_GET['currentPage']) ? intval($_GET['currentPage']) : 1;
        $offset = ($currentPage - 1) * $perPage;
        $whereArr = [];
        
        // Add permission check
        $user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $_SESSION['user_id'];
        if (strlen($user_id) > 0 && $_SESSION['authority'] != 'administrator') {
            $whereArr[] = sprintf(
                "(p.created_by = %d OR EXISTS (
                    SELECT 1 FROM " . DB_PREFIX . "project_members pm 
                    WHERE pm.project_id = p.id AND pm.user_id = %d
                ))",
                $user_id,
                $user_id
            );
        }

        if (isset($_GET['department_id'])) {
            $whereArr[] = sprintf("p.department_id = %d", intval($_GET['department_id']));
        }
        if (isset($_GET['status']) && $_GET['status'] != 'all') {
            $whereArr[] = sprintf("p.status = '%s'", $_GET['status']);
        }
        $where = implode(" AND ", $whereArr);

        if (!empty($where)) {
            $where = " WHERE " . $where;
        }

        $query = sprintf(
            "SELECT p.*, d.name as department_name,
            (SELECT user_id FROM groupware_project_members pm WHERE p.id = pm.project_id AND pm.role = 'member') as assignment_id,
            (SELECT user_id FROM groupware_project_members pm WHERE p.id = pm.project_id AND pm.role = 'manager') as manager_id,
            (SELECT user_id FROM groupware_project_members pm WHERE p.id = pm.project_id AND pm.role = 'viewer') as viewer_id
            FROM {$this->table} p 
            LEFT JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id
            %s
            ORDER BY p.created_at DESC
            LIMIT %d, %d",
            $where,
            $offset,
            $perPage
        );
        return $this->fetchAll($query);
    }

    function checkPermission($project_id, $user_id) {
        $query = sprintf(
            "SELECT COUNT(*) as count FROM " . DB_PREFIX . "project_members WHERE project_id = %d AND user_id = %d",
            intval($project_id),
            intval($user_id)
        );
    }

    function add() {
        // Lấy dữ liệu từ request
        $data = array(
            'project_number' => isset($_POST['project_number']) ? $_POST['project_number'] : '',
            'name' => $_POST['name'],
            'description' => isset($_POST['description']) ? $_POST['description'] : '',
            'status' => isset($_POST['status']) ? $_POST['status'] : 'draft',
            'priority' => isset($_POST['priority']) ? $_POST['priority'] : 'medium',
            'start_date' => isset($_POST['start_date']) && !empty($_POST['start_date']) ? date('Y-m-d', strtotime($_POST['start_date'])) : null,
            'end_date' => isset($_POST['end_date']) && !empty($_POST['end_date']) ? date('Y-m-d', strtotime($_POST['end_date'])) : null,
            'created_by' => isset($_POST['created_by']) ? $_POST['created_by'] : $_SESSION['user_id'],
            'department_id' => isset($_POST['department_id']) ? $_POST['department_id'] : null,
            'progress' => 0,
            'estimated_hours' => isset($_POST['estimated_hours']) ? $_POST['estimated_hours'] : 0,
            'actual_hours' => 0,
            'customer_id' => isset($_POST['customer_id']) ? $_POST['customer_id'] : null,
            'building_size' => isset($_POST['building_size']) ? $_POST['building_size'] : '',
            'building_type' => isset($_POST['building_type']) ? $_POST['building_type'] : '',
            'project_order_type' => isset($_POST['project_order_type']) ? $_POST['project_order_type'] : '',
            'amount' => isset($_POST['amount']) ? $_POST['amount'] : 0,
            'created_at' => date('Y-m-d H:i:s'),
        );
        
        $project_id = $this->query_insert($data);
        
        // Thêm members nếu có
        if ($project_id && isset($_POST['members']) && is_array($_POST['members'])) {
            foreach ($_POST['members'] as $user_id) {
                $this->addMember($project_id, $user_id);
            }
        }
        
        return $project_id;
    }

    function edit($id) {
        $data = array(
            'project_number' => isset($_POST['project_number']) ? $_POST['project_number'] : '',
            'name' => $_POST['name'],
            'description' => isset($_POST['description']) ? $_POST['description'] : '',
            'status' => isset($_POST['status']) ? $_POST['status'] : 'draft',
            'priority' => isset($_POST['priority']) ? $_POST['priority'] : 'medium',
            'start_date' => isset($_POST['start_date']) && !empty($_POST['start_date']) ? date('Y-m-d', strtotime($_POST['start_date'])) : null,
            'end_date' => isset($_POST['end_date']) && !empty($_POST['end_date']) ? date('Y-m-d', strtotime($_POST['end_date'])) : null,
            'department_id' => isset($_POST['department_id']) ? $_POST['department_id'] : null,
            'estimated_hours' => isset($_POST['estimated_hours']) ? $_POST['estimated_hours'] : 0,
            'customer_id' => isset($_POST['customer_id']) ? $_POST['customer_id'] : null,
            'building_size' => isset($_POST['building_size']) ? $_POST['building_size'] : '',
            'building_type' => isset($_POST['building_type']) ? $_POST['building_type'] : '',
            'project_order_type' => isset($_POST['project_order_type']) ? $_POST['project_order_type'] : '',
            'amount' => isset($_POST['amount']) ? $_POST['amount'] : 0,
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        $result = $this->query_update($data, ['id' => $id]);
        
        // Cập nhật members nếu có
        if ($result && isset($_POST['members']) && is_array($_POST['members'])) {
            // Xóa members cũ
            $this->query("DELETE FROM " . DB_PREFIX . "project_members WHERE project_id = " . intval($id));
            
            // Thêm members mới
            foreach ($_POST['members'] as $user_id) {
                $this->addMember($id, $user_id);
            }
        }
        
        return $result;
    }

    function delete($id) {
        // Check if project has tasks or members
        // $query = sprintf(
        //     "SELECT COUNT(*) as count FROM " . DB_PREFIX . "tasks WHERE project_id = %d",
        //     intval($id)
        // );
        // $tasks = $this->fetchOne($query)['count'];

        // if ($tasks > 0) {
        //     throw new Exception('このプロジェクトにはタスクが存在するため、削除できません。');
        // }
        
        // Xóa project members
        // $this->query("DELETE FROM " . DB_PREFIX . "project_members WHERE project_id = " . intval($id));
        
        // Xóa project
        return $this->query_update(['status' => 'deleted'], ['id' => $id]);
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
            "SELECT t.*, u.name as assigned_to_name
            FROM " . DB_PREFIX . "tasks t 
            LEFT JOIN " . DB_PREFIX . "users u ON t.assigned_to = u.id 
            WHERE t.project_id = %d 
            ORDER BY t.created_at DESC",
            intval($projectId)
        );
        return $this->fetchAll($query);
    }

    function addMember($project_id, $user_id, $role = 'member') {
        $data = array(
            'project_id' => $project_id,
            'user_id' => $user_id,
            'role' => $role,
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
            "SELECT p.*, d.name as department_name, 
            (SELECT COUNT(*) FROM " . DB_PREFIX . "tasks WHERE project_id = p.id) as task_count,
            (SELECT COUNT(*) FROM " . DB_PREFIX . "project_members WHERE project_id = p.id) as member_count
            FROM {$this->table} p 
            LEFT JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id 
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

    function updateStatus($id, $status) {
        $data = array(
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        // Cập nhật actual_start_date nếu chuyển sang in_progress
        if ($status == 'in_progress') {
            $project = $this->getById($id);
            if (!$project['actual_start_date']) {
                $data['actual_start_date'] = date('Y-m-d');
            }
        }
        
        // Cập nhật actual_end_date nếu chuyển sang completed
        if ($status == 'completed') {
            $data['actual_end_date'] = date('Y-m-d');
            $data['progress'] = 100;
        }
        
        return $this->query_update($data, ['id' => $id]);
    }

    function getComments($project_id) {
        $query = sprintf(
            "SELECT c.*, u.realname as user_name 
            FROM " . DB_PREFIX . "project_comments c 
            LEFT JOIN " . DB_PREFIX . "user u ON c.user_id = u.userid 
            WHERE c.project_id = %d 
            ORDER BY c.created_at DESC",
            intval($project_id)
        );
        return $this->fetchAll($query);
    }

    function addComment($data) {
        $commentData = array(
            'project_id' => $data['project_id'],
            'user_id' => $data['user_id'],
            'content' => $data['content'],
            'created_at' => date('Y-m-d H:i:s')
        );
        
        $this->table = DB_PREFIX . 'project_comments';
        $result = $this->query_insert($commentData);
        $this->table = DB_PREFIX . 'projects'; // Reset table back to projects
        return $result;
    }
}

?>