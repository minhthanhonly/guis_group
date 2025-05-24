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
            'status' => array(), //draft, open, in_progress, completed, paused, cancelled
            'start_date' => array(), //timestamp
            'end_date' => array(), //timestamp
            'actual_start_date' => array(), //timestamp
            'actual_end_date' => array(), //timestamp
            'created_by' => array(), //userid
            'created_at' => array('except' => array('search')), //timestamp
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
        $perPage = isset($_GET['perPage']) ? intval($_GET['perPage']) : 20;
        $currentPage = isset($_GET['currentPage']) ? intval($_GET['currentPage']) : 1;
        $offset = ($currentPage - 1) * $perPage;
        $whereArr = [];
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
            "SELECT p.*,
            (SELECT user_id FROM groupware_project_members pm WHERE p.id = pm.project_id AND pm.role = 'member') as assignment_id,
            (SELECT user_id FROM groupware_project_members pm WHERE p.id = pm.project_id AND pm.role = 'manager') as manager_id,
            (SELECT user_id FROM groupware_project_members pm WHERE p.id = pm.project_id AND pm.role = 'viewer') as viewer_id
            FROM {$this->table} p 
            %s
            ORDER BY p.created_at DESC
            LIMIT %d, %d",
            $where,
            $offset,
            $perPage
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