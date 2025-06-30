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
            'updated_by' => array(), //userid
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
            'building_branch' => array(), //list of category_id
            'project_order_type' => array(), //edit, new, custom
            'project_estimate_id' => array(), 
            'teams' => array(), 
            'amount' => array(), //edit, new, custom
            'estimate_status' => array(), //未発行, 発行済み, 承認済み, 却下, 調整
            'invoice_status' => array(), //未発行, 発行済み, 承認済み, 却下, 調整
            'tags' => array(), //project tags for search and organization
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
        $user_id = $_SESSION['id'];
        $is_department_manager = false;
        if (isset($_GET['department_id'])) {
            $department_id = $_GET['department_id'];
            $query = sprintf(
                "SELECT COUNT(id) as count FROM " . DB_PREFIX . "user_department WHERE userid = '%s' AND department_id = %d AND (project_manager = 1 OR project_director = 1)",
                $_SESSION['userid'],
                $department_id);
            $is_department_manager = $this->fetchOne($query)['count'] > 0;
        }

        if ($_SESSION['authority'] != 'administrator' && !$is_department_manager) {
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
            c.name as contact_name, c.company_name, c.category_id as category_id, c.department as branch_name,
            CONCAT(gc.name, ' ', gc.title) as customer_name,
            (SELECT GROUP_CONCAT(CONCAT(pm.user_id, ':', u.realname, ':', COALESCE(u.user_image, '')) SEPARATOR '|') 
             FROM " . DB_PREFIX . "project_members pm 
             LEFT JOIN " . DB_PREFIX . "user u ON pm.user_id = u.id 
             WHERE p.id = pm.project_id AND pm.role = 'member') as assignment_id,
            (SELECT GROUP_CONCAT(CONCAT(pm.user_id, ':', u.realname, ':', COALESCE(u.user_image, '')) SEPARATOR '|') 
             FROM " . DB_PREFIX . "project_members pm 
             LEFT JOIN " . DB_PREFIX . "user u ON pm.user_id = u.id 
             WHERE p.id = pm.project_id AND pm.role = 'manager') as manager_id,
            (SELECT GROUP_CONCAT(pm.user_id) FROM " . DB_PREFIX . "project_members pm WHERE p.id = pm.project_id AND pm.role = 'viewer') as viewer_id
            FROM {$this->table} p 
            LEFT JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id
            LEFT JOIN " . DB_PREFIX . "customer c ON c.id = SUBSTRING_INDEX(p.customer_id, ',', 1)
            LEFT JOIN " . DB_PREFIX . "customer gc ON gc.id = SUBSTRING_INDEX(p.customer_id, ',', 1)
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

    function listForGantt() {
        $whereArr = [];
        
        // Add permission check
        $user_id = $_SESSION['id'];
        $is_department_manager = false;
        if (isset($_GET['department_id'])) {
            $department_id = $_GET['department_id'];
            $query = sprintf(
                "SELECT COUNT(id) as count FROM " . DB_PREFIX . "user_department WHERE userid = '%s' AND department_id = %d AND (project_manager = 1 OR project_director = 1)",
                $_SESSION['userid'],
                $department_id);
            $is_department_manager = $this->fetchOne($query)['count'] > 0;
        }

        if ($_SESSION['authority'] != 'administrator' && !$is_department_manager) {
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
        if (isset($_GET['status'])) {
            if ($_GET['status'] == 'all') {
                // For 'all' status, only exclude deleted projects
                $whereArr[] = "p.status NOT IN ('deleted', 'draft')";
            } else if ($_GET['status'] == 'active') {
                // For 'active' status, exclude deleted and draft projects
                $whereArr[] = "p.status NOT IN ('deleted', 'draft', 'completed', 'cancelled')";
            } else {
                // For specific status
                $whereArr[] = sprintf("p.status = '%s'", $_GET['status']);
            }
        } else {
            // Default: exclude deleted and draft projects (active projects)
            $whereArr[] = "AND p.status NOT IN ('deleted', 'draft')";
        }

        $where = implode(" AND ", $whereArr);
        if (!empty($where)) {
            $where = " WHERE " . $where;
        }

        // Get data for Gantt chart (all projects, no pagination)
        $query = sprintf(
            "SELECT p.*, d.name as department_name,
            c.name as contact_name, c.company_name, c.category_id as category_id, c.department as branch_name,
            CONCAT(gc.name, ' ', gc.title) as customer_name,
            (SELECT GROUP_CONCAT(CONCAT(pm.user_id, ':', u.realname, ':', COALESCE(u.user_image, '')) SEPARATOR '|') 
             FROM " . DB_PREFIX . "project_members pm 
             LEFT JOIN " . DB_PREFIX . "user u ON pm.user_id = u.id 
             WHERE p.id = pm.project_id AND pm.role = 'member') as assignment_id,
            (SELECT GROUP_CONCAT(CONCAT(pm.user_id, ':', u.realname, ':', COALESCE(u.user_image, '')) SEPARATOR '|') 
             FROM " . DB_PREFIX . "project_members pm 
             LEFT JOIN " . DB_PREFIX . "user u ON pm.user_id = u.id 
             WHERE p.id = pm.project_id AND pm.role = 'manager') as manager_id
            FROM {$this->table} p 
            LEFT JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id
            LEFT JOIN " . DB_PREFIX . "customer c ON c.id = SUBSTRING_INDEX(p.customer_id, ',', 1)
            LEFT JOIN " . DB_PREFIX . "customer gc ON gc.id = SUBSTRING_INDEX(p.customer_id, ',', 1)
            %s
            ORDER BY p.start_date ASC, p.created_at DESC",
            $where
        );

        $data = $this->fetchAll($query);

        return $data;
    }

    private function escape($str) {
        return $this->quote($str);
    }

    function checkPermission($project_id, $user_id) {
        $query = sprintf(
            "SELECT COUNT(*) as count FROM " . DB_PREFIX . "project_members WHERE project_id = %d AND user_id = %d",
            intval($project_id),
            intval($user_id)
        );
    }


    function create($params = null) {
    
            // Get data from $_POST if no params provided
        $data = array(
            'project_number' => isset($_POST['project_number']) ? $_POST['project_number'] : '',
            'name' => isset($_POST['name']) ? $_POST['name'] : '',
            'description' => isset($_POST['description']) ? $_POST['description'] : '',
            'status' => isset($_POST['status']) ? $_POST['status'] : 'draft',
            'priority' => isset($_POST['priority']) ? $_POST['priority'] : 'medium',
            'department_id' => isset($_POST['department_id']) ? intval($_POST['department_id']) : null,
            'progress' => isset($_POST['progress']) ? intval($_POST['progress']) : 0,
            'estimated_hours' => isset($_POST['estimated_hours']) ? floatval($_POST['estimated_hours']) : 0,
            'actual_hours' => 0,
            'customer_id' => isset($_POST['customer_id']) ? $_POST['customer_id'] : null,
            'building_size' => isset($_POST['building_size']) ? $_POST['building_size'] : '',
            'building_type' => isset($_POST['building_type']) ? $_POST['building_type'] : '',
            'building_number' => isset($_POST['building_number']) ? $_POST['building_number'] : '',
            'building_branch' => isset($_POST['building_branch']) ? $_POST['building_branch'] : '',
            'project_order_type' => isset($_POST['project_order_type']) ? $_POST['project_order_type'] : '',
            'amount' => isset($_POST['amount']) ? floatval($_POST['amount']) : 0,
            'teams' => isset($_POST['teams']) ? $_POST['teams'] : '',
            'created_by' => $_SESSION['userid'],
            'created_at' => date('Y-m-d H:i:s'),
        );

        // Save custom field set id and custom fields JSON if provided
        if (isset($_POST['department_custom_fields_set_id']) && $_POST['department_custom_fields_set_id'] != '') {
            $data['department_custom_fields_set_id'] = $_POST['department_custom_fields_set_id'];
        }
        if (isset($_POST['custom_fields']) && $_POST['custom_fields'] != '') {
            $data['custom_fields'] = $_POST['custom_fields'];
        }
        if(isset($_POST['start_date']) && $_POST['start_date'] != ''){
            $data['start_date'] = date('Y-m-d H:i', strtotime($_POST['start_date']));
        }
        if(isset($_POST['actual_end_date']) && $_POST['actual_end_date'] != ''){
            $data['actual_end_date'] = date('Y-m-d H:i', strtotime($_POST['actual_end_date']));
        }
        if(isset($_POST['end_date']) && $_POST['end_date'] != ''){
            $data['end_date'] = date('Y-m-d H:i', strtotime($_POST['end_date']));
        }
       

        // Validate required fields
        if (empty($data['name'])) {
            return [
                'status' => 'error',
                'message' => 'Project name is required'
            ];
        }

        // Insert project data
        $project_id = $this->query_insert($data);
        
        if (!$project_id) {
            return [
                'status' => 'error',
                'message' => 'Failed to create project'
            ];
        }

        // Add members if provided
        if (isset($_POST['members']) && !empty($_POST['members'])) {
            $members = explode(',', $_POST['members']);
            foreach ($members as $user_id) {
                if (!empty($user_id)) {
                    $this->addMember($project_id, intval($user_id), 'member');
            }
        }
        }

        // Add managers if provided
        if (isset($_POST['managers']) && !empty($_POST['managers'])) {
            $managers = explode(',', $_POST['managers']);
            foreach ($managers as $user_id) {
                if (!empty($user_id)) {
                    $this->addMember($project_id, intval($user_id), 'manager');
            }
        }
        }

        return [
            'status' => 'success',
            'project_id' => $project_id,
            'message' => 'Project created successfully'
        ];
    }


    
    function update() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'error' => 'No project id'];
        
        $data = array(
            'name' => isset($_POST['name']) ? $_POST['name'] : '',
            'description' => isset($_POST['description']) ? $_POST['description'] : '',
            'building_branch' => isset($_POST['building_branch']) ? $_POST['building_branch'] : '',
            'building_size' => isset($_POST['building_size']) ? $_POST['building_size'] : '',
            'building_type' => isset($_POST['building_type']) ? $_POST['building_type'] : '',
            'building_number' => isset($_POST['building_number']) ? $_POST['building_number'] : '',
            'project_number' => isset($_POST['project_number']) ? $_POST['project_number'] : '',
            'progress' => isset($_POST['progress']) ? $_POST['progress'] : 0,
            'status' => isset($_POST['status']) ? $_POST['status'] : 'draft',
            'teams' => isset($_POST['teams']) ? $_POST['teams'] : '',
            'project_order_type' => isset($_POST['project_order_type']) ? $_POST['project_order_type'] : '',
            'priority' => isset($_POST['priority']) ? $_POST['priority'] : 'medium',
            'customer_id' => isset($_POST['customer_id']) ? $_POST['customer_id'] : '',
            'amount' => isset($_POST['amount']) ? floatval($_POST['amount']) : 0,
            'estimate_status' => isset($_POST['estimate_status']) ? $_POST['estimate_status'] : '未発行',
            'invoice_status' => isset($_POST['invoice_status']) ? $_POST['invoice_status'] : '未発行',
            'tags' => isset($_POST['tags']) ? $_POST['tags'] : '',
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $_SESSION['userid']
        );
        
        // Save custom field set id and custom fields JSON if provided
        if (isset($_POST['department_custom_fields_set_id']) && $_POST['department_custom_fields_set_id'] != '') {
            $data['department_custom_fields_set_id'] = $_POST['department_custom_fields_set_id'];
        }
        if (isset($_POST['custom_fields']) && $_POST['custom_fields'] != '') {
            $data['custom_fields'] = $_POST['custom_fields'];
        }
        if(isset($_POST['start_date']) && $_POST['start_date'] != ''){
            $data['start_date'] = date('Y-m-d H:i', strtotime($_POST['start_date']));
        }
        if(isset($_POST['end_date']) && $_POST['end_date'] != ''){
            $data['end_date'] = date('Y-m-d H:i', strtotime($_POST['end_date']));
        }
        
        // Auto-set actual_end_date if status is completed
        if ($data['status'] == 'completed') {
            $data['actual_end_date'] = date('Y-m-d H:i:s');
            $data['progress'] = 100;
        }
        
        $result = $this->query_update($data, ['id' => $id]);
        
        // Handle actual_end_date NULL case separately (only if status is not completed)
        if ($result && $data['status'] != 'completed') {
            $query = sprintf("UPDATE %s SET actual_end_date = NULL WHERE id = %d", $this->table, $id);
            $this->query($query);
        }

        if ($result && isset($_POST['members'])) {
            $members = explode(',', $_POST['members']);
            // Xóa members cũ
            $this->query("DELETE FROM " . DB_PREFIX . "project_members WHERE project_id = " . intval($id));
            // Thêm members mới
            foreach ($members as $user_id) {
                $this->addMember($id, $user_id);
            }
        }
       
        if ($result && isset($_POST['managers'])) {
            $managers = explode(',', $_POST['managers']);
            foreach ($managers as $user_id) {
                $this->addMember($id, $user_id, 'manager');
            }
        }
        if ($result) {
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'error' => 'Update failed'];
        }
    }


    function delete() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'error' => 'No project id'];
        $result = $this->query_update(['status' => 'deleted'], ['id' => $id]);
        if ($result) {
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'error' => 'Delete failed'];
        }
    }

    function getMembers($params = null) {
        // Handle both direct project_id parameter and params array from API
        if (is_array($params)) {
            $project_id = isset($params['project_id']) ? $params['project_id'] : 0;
        } else {
            $project_id = $params;
        }
        
        $query = sprintf(
            "SELECT pm.*, u.realname as user_name, user_image, u.userid
            FROM " . DB_PREFIX . "project_members pm 
            LEFT JOIN " . DB_PREFIX . "user u ON pm.user_id = u.id 
            WHERE pm.project_id = %d 
            ORDER BY pm.created_at DESC",
            intval($project_id)
        );
        return $this->fetchAll($query);
    }

    function getTasks($projectId) {
        $query = sprintf(
            "SELECT t.*, u.realname as assigned_to_name
            FROM " . DB_PREFIX . "tasks t 
            LEFT JOIN " . DB_PREFIX . "user u ON t.assigned_to = u.id 
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

    function getById($params = null) {
        // Handle both direct ID parameter and params array from API
        if (is_array($params)) {
            $id = isset($params['id']) ? $params['id'] : 0;
        } else {
            $id = $params;
        }
        
        $query = sprintf(
            "SELECT p.*, d.name as department_name,
            c.name as contact_name, c.company_name, c.department as branch_name, c.category_id as category_id, 
            (SELECT COUNT(*) FROM " . DB_PREFIX . "tasks WHERE project_id = p.id) as task_count,
            (SELECT COUNT(*) FROM " . DB_PREFIX . "project_members WHERE project_id = p.id) as member_count
            FROM {$this->table} p 
            LEFT JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id
            LEFT JOIN " . DB_PREFIX . "customer c ON c.id = SUBSTRING_INDEX(p.customer_id, ',', 1)
            WHERE p.id = %d",
            intval($id)
        );
        return $this->fetchOne($query);
    }

    function updateProgress($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $progress = isset($_POST['progress']) ? intval($_POST['progress']) : 0;
        if (!$id) return false;
        $data = array(
            'progress' => $progress,
            'updated_by' => $_SESSION['userid'],
            'updated_at' => date('Y-m-d H:i:s')
        );
        return $this->query_update($data, ['id' => $id]);
    }

    function updatePojectTags($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $tags = isset($_POST['tags']) ? $_POST['tags'] : '';
        if (!$id) return false;

        $data = array(
            'tags' => $tags,
            'updated_at' => date('Y-m-d H:i:s')
        );
        return $this->query_update($data, ['id' => $id]);
    }

    function updateStatus($params = null) {
        // Get data directly from $_POST
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $status = isset($_POST['status']) ? $_POST['status'] : '';
        
        if (!$id || !$status) {
            return false;
        }
        
        $data = array(
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        // Cập nhật actual_start_date nếu chuyển sang in_progress
        if ($status == 'in_progress') {
            $project = $this->getById($id);
            if (!$project['actual_start_date']) {
                $data['actual_start_date'] = date('Y-m-d H:i:s');
            }
        }
        
        // Cập nhật actual_end_date nếu chuyển sang completed
        if ($status == 'completed') {
            $data['actual_end_date'] = date('Y-m-d H:i:s');
            $data['progress'] = 100;
        }
        
        $result = $this->query_update($data, ['id' => $id]);
        
        // Clear actual_end_date if status is not completed
        if ($result && $status != 'completed') {
            $query = sprintf("UPDATE %s SET actual_end_date = NULL WHERE id = %d", $this->table, $id);
            $this->query($query);
        }
        
        return $result;
    }

    function updateProjectDate() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
        $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
        if (!$id || !$start_date || !$end_date) return [
            'status' => 'error',
            'error' => 'No project id or start date or end date'
        ];
        $data = array(
            'start_date' => $start_date,
            'end_date' => $end_date,
            'updated_at' => date('Y-m-d H:i:s')
        );
        $result = $this->query_update($data, ['id' => $id]);
        if ($result) {
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'error' => 'Update failed'];
        }
    }

    function updatePriority($params = null) {
        // Get data directly from $_POST
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $priority = isset($_POST['priority']) ? $_POST['priority'] : '';
        
        if (!$id || !$priority) {
            return false;
        }
        
        $data = array(
            'priority' => $priority,
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        return $this->query_update($data, ['id' => $id]);
    }

    function getComments() {
        $project_id = isset($_GET['project_id']) ? $_GET['project_id'] : 0;
        if(!$project_id) return [];
        
        // Get pagination parameters
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
        $offset = ($page - 1) * $per_page;
        
        $query = sprintf(
            "SELECT c.*, u.realname as user_name, u.user_image
            FROM " . DB_PREFIX . "comments c 
            LEFT JOIN " . DB_PREFIX . "user u ON c.user_id = u.userid 
            WHERE c.project_id = %d 
            ORDER BY c.created_at DESC
            LIMIT %d OFFSET %d",
            intval($project_id),
            intval($per_page),
            intval($offset)
        );
        return $this->fetchAll($query);
    }

    function addComment($data) {
        $data = $_POST;
        $commentData = array(
            'project_id' => $data['project_id'],
            'user_id' => $data['user_id'],
            'content' => $data['content'],
            'created_at' => date('Y-m-d H:i:s')
        );
        
        $this->table = DB_PREFIX . 'comments';
        $result = $this->query_insert($commentData);
        $this->table = DB_PREFIX . 'projects'; // Reset table back to projects
        
        // Send mention notifications if comment was added successfully
        if ($result) {
            $this->sendMentionNotifications($data['project_id'], $data['content'], $data['user_id'], $result);
        }
        
        return $result;
    }

    // Detect mentions and send notifications
    private function sendMentionNotifications($projectId, $content, $commentUserId, $commentId) {
        try {
            require_once(DIR_ROOT . '/application/model/NotificationService.php');
            $notiService = new NotificationService();
            
            // Extract mentioned users from content
            $mentionedUsers = $this->extractMentions($content);
            
            if (empty($mentionedUsers)) {
                return; // No mentions found
            }
            
            // Get project info for notification
            $project = $this->getById($projectId);
            if (!$project) {
                return;
            }
            
            // Get commenter info
            $commenter = $this->fetchOne("SELECT realname, user_image FROM " . DB_PREFIX . "user WHERE userid = ?", [$commentUserId]);
            if (!$commenter) {
                return;
            }
            
            // Track sent notifications to avoid duplicates
            $sentUserIds = [];
            
            // Send notification to each mentioned user
            foreach ($mentionedUsers as $mentionedUser) {
                // Don't send notification if user mentions themselves
                if ($mentionedUser['userid'] == $commentUserId) {
                    continue;
                }
                
                // Don't send duplicate notifications to the same user
                if (in_array($mentionedUser['userid'], $sentUserIds)) {
                    continue;
                }
                
                $sentUserIds[] = $mentionedUser['userid'];
                
                $payload = [
                    'event' => 'project_mention',
                    'title' => 'プロジェクトでメンションされました',
                    'message' => sprintf('%sさんがプロジェクト「%s」であなたをメンションしました', 
                        $commenter['realname'], 
                        $project['name']
                    ),
                    'data' => [
                        'project_id' => $projectId,
                        'project_name' => $project['name'],
                        'comment_id' => $commentId,
                        'comment_content' => $content,
                        'commenter_id' => $commentUserId,
                        'commenter_name' => $commenter['realname'],
                        'commenter_image' => $commenter['user_image'],
                        'url' => "/project/detail.php?id=$projectId#comment-$commentId"
                    ],
                    'project_id' => $projectId,
                    'user_ids' => [$mentionedUser['userid']]
                ];
                
                $notiService->create($payload);
            }
            
        } catch (Exception $e) {
            error_log('Failed to send mention notification: ' . $e->getMessage());
        }
    }
    
    // Extract mentions from content (supports both plain text and HTML)
    private function extractMentions($content) {
        $mentionedUsers = [];
        
        // Debug logging
        error_log("Extracting mentions from content: " . substr($content, 0, 200) . "...");
        
        // First, try to extract from HTML mentions with data attributes
        if (strpos($content, 'data-user-id') !== false) {
            error_log("Found HTML mentions, extracting...");
            // Extract user IDs from HTML mentions - improved regex for multiple mentions
            preg_match_all('/<span[^>]*data-user-id="([^"]+)"[^>]*data-user-name="([^"]+)"[^>]*>@([^<]+)<\/span>/', $content, $matches);
            if (!empty($matches[1])) {
                $userIds = $matches[1];
                error_log("Found user IDs: " . implode(', ', $userIds));
                
                // Remove duplicates while preserving order
                $uniqueUserIds = array_unique($userIds);
                
                // Get user info by user IDs
                $placeholders = str_repeat('?,', count($uniqueUserIds) - 1) . '?';
                $query = "SELECT userid, realname, user_image FROM " . DB_PREFIX . "user WHERE userid IN ($placeholders)";
                $users = $this->fetchAll($query, $uniqueUserIds);
                
                foreach ($users as $user) {
                    $mentionedUsers[] = $user;
                }
                error_log("Found " . count($mentionedUsers) . " mentioned users from HTML");
            }
        }
       
        // If no HTML mentions found, try plain text mentions
        if (empty($mentionedUsers)) {
            error_log("No HTML mentions found, trying plain text...");
            $plainText = strip_tags($content);
            preg_match_all('/@([^\s]+)/', $plainText, $matches);
            
            if (!empty($matches[1])) {
                $mentionedUsernames = $matches[1];
                error_log("Found usernames: " . implode(', ', $mentionedUsernames));
                
                // Remove duplicates while preserving order
                $uniqueUsernames = array_unique($mentionedUsernames);
                
                if (!empty($uniqueUsernames)) {
                    // Get mentioned users from database by username
                    $placeholders = str_repeat('?,', count($uniqueUsernames) - 1) . '?';
                    $query = "SELECT userid, realname, user_image FROM " . DB_PREFIX . "user WHERE realname IN ($placeholders)";
                    $mentionedUsers = $this->fetchAll($query, $uniqueUsernames);
                    error_log("Found " . count($mentionedUsers) . " mentioned users from plain text");
                }
            }
        }
        
        error_log("Total mentioned users: " . count($mentionedUsers));
        return $mentionedUsers;
    }

    function updateProjectStatus($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'error' => 'No project id'];
        
        $data = array(
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $_SESSION['userid']
        );
        
        // Add fields if they exist in POST
        if (isset($_POST['amount'])) {
            $data['amount'] = floatval($_POST['amount']);
        }
        if (isset($_POST['estimate_status'])) {
            $data['estimate_status'] = $_POST['estimate_status'];
        }
        if (isset($_POST['invoice_status'])) {
            $data['invoice_status'] = $_POST['invoice_status'];
        }
        
        $result = $this->query_update($data, ['id' => $id]);
        
        if ($result) {
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'error' => 'Update failed'];
        }
    }

    function updateTeams($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $teams = isset($_POST['teams']) ? $_POST['teams'] : '';
        if (!$id) return ['success' => false, 'error' => 'No project id'];
        $data = array(
            'teams' => $teams,
            'updated_at' => date('Y-m-d H:i:s')
        );
        $result = $this->query_update($data, ['id' => $id]);
        if ($result) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Update failed'];
        }
    }

    // Note methods
    function addNote($params = null) {
        require_once(DIR_ROOT . '/application/model/projectnote.php');
        $noteModel = new ProjectNote();
        return $noteModel->create($params);
    }

    function updateNote($params = null) {
        require_once(DIR_ROOT . '/application/model/projectnote.php');
        $noteModel = new ProjectNote();
        return $noteModel->update($params);
    }

    function deleteNote($params = null) {
        require_once(DIR_ROOT . '/application/model/projectnote.php');
        $noteModel = new ProjectNote();
        return $noteModel->delete($params);
    }

    function getNotes($params = null) {
        require_once(DIR_ROOT . '/application/model/projectnote.php');
        $noteModel = new ProjectNote();
        return $noteModel->list($params);
    }
}

?>