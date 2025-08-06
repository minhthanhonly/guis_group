<?php

class Project extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'projects';
        // Add integer fields that should not be quoted
        $this->donotquote = array_merge($this->donotquote, array(
            'parent_folder_id', 'folder_id', 'project_id', 'file_size'
        ));
        $this->schema = array(
            'id' => array('except' => array('search')),
            'parent_project_id' => array(),
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
            'is_kadai' => array(), //boolean field to identify child projects
        );
        $this->connect();
    }

    function list() {
        $draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;
        $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
        $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $order_column = isset($_GET['order_column']) ? $_GET['order_column'] : 'end_date';
        $order_dir = isset($_GET['order_dir']) ? $_GET['order_dir'] : 'ASC';
        
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


        // Nếu có filterKeyword thì chỉ áp dụng điều kiện tìm kiếm keyword, bỏ qua các filter nâng cao khác
        $hasKeyword = isset($_GET['filterKeyword']) && $_GET['filterKeyword'] !== '';
        $hasStatus = isset($_GET['status']) && $_GET['status'] !== '' && $_GET['status'] !== 'all';
        $showInactive = isset($_GET['showInactive']) && $_GET['showInactive'] == '1';
        if ($hasKeyword) {
            $kw = $this->escape($_GET['filterKeyword']);
            $whereArr[] = "(p.name LIKE '%$kw%' 
            OR p.project_number LIKE '%$kw%' 
            OR p.description LIKE '%$kw%' 
            OR p.tags LIKE '%$kw%'
            OR c.company_name LIKE '%$kw%'
            OR c.company_name_kana LIKE '%$kw%'
            OR c.name_kana LIKE '%$kw%'
            OR c.name LIKE '%$kw%')";
        } else {
            // --- Advanced Filters ---
            $hasStartMonth = isset($_GET['filterStartMonth']) && $_GET['filterStartMonth'] !== '';
            $hasEndMonth = isset($_GET['filterEndMonth']) && $_GET['filterEndMonth'] !== '';
            if ($hasStartMonth && $hasEndMonth) {
                // Convert to first day of start month and last day of end month
                $startMonth = $this->escape($_GET['filterStartMonth']);
                $endMonth = $this->escape($_GET['filterEndMonth']);
                $startDate = "$startMonth-01";
                // Calculate last day of end month
                $endDate = date('Y-m-t', strtotime($endMonth . '-01'));
                $whereArr[] = "(p.start_date <= '$endDate' AND p.end_date >= '$startDate')";
            } else if ($hasStartMonth) {
                $month = $this->escape($_GET['filterStartMonth']);
                $whereArr[] = "DATE_FORMAT(p.start_date, '%Y-%m') = '$month'";
            } else if ($hasEndMonth) {
                $month = $this->escape($_GET['filterEndMonth']);
                $whereArr[] = "DATE_FORMAT(p.end_date, '%Y-%m') = '$month'";
            }
            if (isset($_GET['filterPriority']) && $_GET['filterPriority'] !== '') {
                $priority = $this->escape($_GET['filterPriority']);
                $whereArr[] = "p.priority = '$priority'";
            }
            if (isset($_GET['filterProgress']) && $_GET['filterProgress'] !== '') {
                $progress = $_GET['filterProgress'];
                if ($progress === '100') {
                    $whereArr[] = "p.progress = 100";
                } else if ($progress === '0-50') {
                    $whereArr[] = "p.progress >= 0 AND p.progress <= 50";
                } else if ($progress === '51-99') {
                    $whereArr[] = "p.progress >= 51 AND p.progress <= 99";
                }
            }
            if (isset($_GET['filterTimeLeft']) && $_GET['filterTimeLeft'] !== '') {
                $val = $_GET['filterTimeLeft'];
                if ($val === 'overdue') {
                    $whereArr[] = "p.end_date < NOW() AND p.status NOT IN ('completed', 'cancelled', 'deleted')";
                } else if (is_numeric($val)) {
                    $whereArr[] = "p.end_date >= NOW() AND p.end_date <= DATE_ADD(NOW(), INTERVAL ".$this->quote($val)." DAY) AND p.status NOT IN ('completed', 'cancelled', 'deleted')";
                }
            }
            // Điều kiện mặc định: nếu không có status và không bật showInactive thì chỉ hiển thị active
            if (!$hasStatus && !$showInactive) {
                $whereArr[] = "p.status NOT IN ('completed', 'cancelled', 'deleted')";
            }
        }

        $where = implode(" AND ", $whereArr);
        if (!empty($where)) {
            $where = " WHERE " . $where;
        }

        // Sắp xếp ưu tiên nếu showInactive=1
        $orderBy = '';
        $orderBy = sprintf('ORDER BY p.%s %s', $this->escape($order_column), $this->escape($order_dir));
        if (isset($_GET['showInactive']) && $_GET['showInactive'] == '1') {
            // Active lên trước, sau đó mới completed/cancelled/deleted, rồi mới sắp xếp end_date, status
            $orderBy .= ", (CASE WHEN p.status IN ('completed','cancelled','deleted') THEN 1 ELSE 0 END) ASC, p.end_date ASC, p.status ASC";
        }

        // Get total records count
        $totalQuery = "SELECT COUNT(*) as count FROM {$this->table} p
        JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id
        LEFT JOIN " . DB_PREFIX . "parent_projects pp ON p.parent_project_id = pp.id
        LEFT JOIN " . DB_PREFIX . "customer c ON c.company_name = pp.company_name AND c.name = pp.contact_name
        " . $where;
        $totalRecords = $this->fetchOne($totalQuery)['count'];

        // Get filtered records count
        $filteredRecords = $totalRecords;

        // Get data for current page
        $query = sprintf(
            "SELECT p.*, d.name as department_name,
            c.name as contact_name, c.company_name, c.category_id as category_id, c.department as branch_name,
            CONCAT(c.name, ' ', c.title) as customer_name,
            pp.company_name as parent_company_name, pp.contact_name as parent_contact_name,
            (SELECT GROUP_CONCAT(CONCAT(pm.user_id, ':', u.realname, ':', COALESCE(u.user_image, '')) SEPARATOR '|') 
             FROM " . DB_PREFIX . "project_members pm 
             LEFT JOIN " . DB_PREFIX . "user u ON pm.user_id = u.id 
             WHERE p.id = pm.project_id AND pm.role = 'member') as assignment_id,
            (SELECT GROUP_CONCAT(CONCAT(pm.user_id, ':', u.realname, ':', COALESCE(u.user_image, '')) SEPARATOR '|') 
             FROM " . DB_PREFIX . "project_members pm 
             LEFT JOIN " . DB_PREFIX . "user u ON pm.user_id = u.id 
             WHERE p.id = pm.project_id AND pm.role = 'manager') as manager_id
            FROM {$this->table} p 
            JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id
            LEFT JOIN " . DB_PREFIX . "parent_projects pp ON p.parent_project_id = pp.id
            LEFT JOIN " . DB_PREFIX . "customer c ON c.company_name = pp.company_name AND c.name = pp.contact_name
            %s
            %s
            LIMIT %d, %d",
            $where,
            $orderBy,
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
                $whereArr[] = "p.status NOT IN ('deleted', 'draft')";
            } else if ($_GET['status'] == 'active') {
                $whereArr[] = "p.status NOT IN ('deleted', 'draft', 'completed', 'cancelled')";
            } else {
                $whereArr[] = sprintf("p.status = '%s'", $_GET['status']);
            }
        } else {
            $whereArr[] = "p.status NOT IN ('deleted', 'draft')";
        }
        // --- Advanced Filters ---
        $hasKeyword = isset($_GET['filterKeyword']) && $_GET['filterKeyword'] !== '';
        if ($hasKeyword) {
            $kw = $this->escape($_GET['filterKeyword']);
            $whereArr[] = "(p.name LIKE '%$kw%' 
                OR p.project_number LIKE '%$kw%' 
                OR p.description LIKE '%$kw%' 
                OR p.tags LIKE '%$kw%'
                OR c.name LIKE '%$kw%')";
        } else {
            if (isset($_GET['filterStartMonth']) && $_GET['filterStartMonth'] !== '') {
                $month = $this->escape($_GET['filterStartMonth']);
                $whereArr[] = "DATE_FORMAT(p.start_date, '%Y-%m') = '$month'";
            }
            if (isset($_GET['filterEndMonth']) && $_GET['filterEndMonth'] !== '') {
                $month = $this->escape($_GET['filterEndMonth']);
                $whereArr[] = "DATE_FORMAT(p.end_date, '%Y-%m') = '$month'";
            }
            if (isset($_GET['filterPriority']) && $_GET['filterPriority'] !== '') {
                $priority = $this->escape($_GET['filterPriority']);
                $whereArr[] = "p.priority = '$priority'";
            }
            if (isset($_GET['filterProgress']) && $_GET['filterProgress'] !== '') {
                $progress = $_GET['filterProgress'];
                if ($progress === '100') {
                    $whereArr[] = "p.progress = 100";
                } else if ($progress === '0-50') {
                    $whereArr[] = "p.progress >= 0 AND p.progress <= 50";
                } else if ($progress === '51-99') {
                    $whereArr[] = "p.progress >= 51 AND p.progress <= 99";
                }
            }
            if (isset($_GET['filterTimeLeft']) && $_GET['filterTimeLeft'] !== '') {
                $val = $_GET['filterTimeLeft'];
                if ($val === 'overdue') {
                    $whereArr[] = "p.end_date < NOW() AND p.status NOT IN ('completed', 'cancelled', 'deleted')";
                } else if (is_numeric($val)) {
                    $whereArr[] = "p.end_date >= NOW() AND p.end_date <= DATE_ADD(NOW(), INTERVAL ".$this->quote($val)." DAY) AND p.status NOT IN ('completed', 'cancelled', 'deleted')";
                }
            }
        }
        $where = implode(" AND ", $whereArr);
        if (!empty($where)) {
            $where = " WHERE " . $where;
        }
        // Get data for Gantt chart (all projects, no pagination)
        $query = sprintf(
            "SELECT p.*, d.name as department_name,
            c.name as contact_name, c.name as company_name, c.category_id as category_id, c.department as branch_name,
            CONCAT(c.name, ' ', c.title) as customer_name,
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
            JOIN " . DB_PREFIX . "customer c ON c.id = SUBSTRING_INDEX(p.customer_id, ',', 1)
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

    /**
     * Validate and ensure UTF-8 MB4 compatibility for strings containing emojis
     */
    private function validateUTF8MB4($str) {
        if (empty($str)) {
            return $str;
        }
        
        // Ensure the string is valid UTF-8
        if (!mb_check_encoding($str, 'UTF-8')) {
            // Try to convert from other encodings
            $str = mb_convert_encoding($str, 'UTF-8', 'auto');
        }
        
        // Clean up any malformed UTF-8 sequences
        $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
        
        return $str;
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
        // Validate and sanitize input to ensure UTF-8 MB4 compatibility
        $name = isset($_POST['name']) ? $this->validateUTF8MB4($_POST['name']) : '';
        $description = isset($_POST['description']) ? $this->validateUTF8MB4($_POST['description']) : '';
        
        $data = array(
            'parent_project_id' => isset($_POST['parent_project_id']) ? intval($_POST['parent_project_id']) : null,
            'project_number' => isset($_POST['project_number']) ? $_POST['project_number'] : '',
            'name' => $name,
            'description' => $description,
            'status' => isset($_POST['status']) ? $_POST['status'] : 'draft',
            'priority' => isset($_POST['priority']) ? $_POST['priority'] : 'medium',
            'department_id' => isset($_POST['department_id']) ? intval($_POST['department_id']) : null,
            'progress' => isset($_POST['progress']) ? intval($_POST['progress']) : 0,
            'estimated_hours' => isset($_POST['estimated_hours']) ? floatval($_POST['estimated_hours']) : 0,
            'actual_hours' => 0,
            'building_size' => isset($_POST['building_size']) ? $_POST['building_size'] : '',
            'building_type' => isset($_POST['building_type']) ? $_POST['building_type'] : '',
            'building_number' => isset($_POST['building_number']) ? $_POST['building_number'] : '',
            'building_branch' => isset($_POST['building_branch']) ? $_POST['building_branch'] : '',
            'project_order_type' => isset($_POST['project_order_type']) ? $_POST['project_order_type'] : '',
            'amount' => isset($_POST['amount']) ? floatval($_POST['amount']) : 0,
            'teams' => isset($_POST['teams']) ? $_POST['teams'] : '',
            'is_kadai' => isset($_POST['is_kadai']) ? intval($_POST['is_kadai']) : 0,
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
                'success' => false,
                'message' => 'Project name is required'
            ];
        }
        // Check duplicate project_number
        if (!empty($data['project_number'])) {
            $query = sprintf("SELECT id FROM %s WHERE project_number = '%s'", $this->table, $this->escape($data['project_number']));
            $exists = $this->fetchOne($query);
            if ($exists) {
                return [
                    'success' => false,
                    'message' => 'Project number already exists'
                ];
            }
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
        $members = [];
        $managers = [];
        if (isset($_POST['members']) && !empty($_POST['members'])) {
            $members = explode(',', $_POST['members']);
        }
        if (isset($_POST['managers']) && !empty($_POST['managers'])) {
            $managers = explode(',', $_POST['managers']);
        }
        $listAllUserIds = array_merge($members, $managers);
        $listAllUsers = $this->getListUserName($listAllUserIds);
        $new_users = array_filter($listAllUsers, function($user) use ($members) {
            return in_array($user['id'], $members);
        });
        $new_managers = array_filter($listAllUsers, function($user) use ($managers) {
            return in_array($user['id'], $managers);
        });

        if (isset($_POST['members']) && !empty($_POST['members'])) {
            foreach ($members as $user_id) {
                if (!empty($user_id)) {
                    $this->addMember($project_id, intval($user_id), $new_users[$user_id]['userid'], 'member');
                }
            }
        }

        // Add managers if provided
        if (isset($_POST['managers']) && !empty($_POST['managers'])) {
            foreach ($managers as $user_id) {
                if (!empty($user_id)) {
                    $this->addMember($project_id, intval($user_id), $new_managers[$user_id]['userid'],'manager');
                }
            }
        }
        
        $this->notifyProjectCreated($project_id, $data['name'], array_column($listAllUsers, 'userid'));
        $this->logProjectAction($project_id, 'created', '案件作成', '', '');

        return [
            'status' => 'success',
            'project_id' => $project_id,
            'message' => 'Project created successfully'
        ];
    }

    // Lấy danh sách username từ danh sách id người dùng
    function getListUserName($listUserIds) {
        $listAllUserIds = array_map('intval', $listUserIds);
        $listAllUserIds = array_unique($listAllUserIds);
        $listAllUsers = [];
        if (!empty($listUserIds)) {
            $placeholders = str_repeat('%d,', count($listAllUserIds) - 1) . '%d';
            $query = sprintf("SELECT id, userid FROM " . DB_PREFIX . "user WHERE id IN ($placeholders)", ...$listAllUserIds); 
            $listAllUsers = $this->fetchAll($query);
        }
        return $listAllUsers;
    }

    
    function update() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'error' => 'No project id'];
        $old = $this->getById($id);
        
        // Validate and sanitize input to ensure UTF-8 MB4 compatibility
        $name = isset($_POST['name']) ? $this->validateUTF8MB4($_POST['name']) : '';
        $description = isset($_POST['description']) ? $this->validateUTF8MB4($_POST['description']) : '';
        
        $data = array(
            'name' => $name,
            'description' => $description,
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
            'amount' => isset($_POST['amount']) ? floatval($_POST['amount']) : 0,
            'estimate_status' => isset($_POST['estimate_status']) ? $_POST['estimate_status'] : '未発行',
            'invoice_status' => isset($_POST['invoice_status']) ? $_POST['invoice_status'] : '未発行',
            'tags' => isset($_POST['tags']) ? $_POST['tags'] : '',
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $_SESSION['userid']
        );
        
        // Add department_id and parent_project_id support for child projects
        if (isset($_POST['department_id'])) {
            $data['department_id'] = intval($_POST['department_id']);
        }
        if (isset($_POST['parent_project_id'])) {
            $data['parent_project_id'] = intval($_POST['parent_project_id']);
        }
        if (isset($_POST['is_kadai'])) {
            $data['is_kadai'] = intval($_POST['is_kadai']);
        }
        
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
        
        try {
        $result = $this->query_update($data, ['id' => $id]);
        
        // Handle actual_end_date NULL case separately (only if status is not completed)
        if ($result && $data['status'] != 'completed') {
            $query = sprintf("UPDATE %s SET actual_end_date = NULL WHERE id = %d", $this->table, $id);
            $this->query($query);
            }
        } catch (Exception $e) {
            error_log('Project update error: ' . $e->getMessage());
            return ['status' => 'error', 'error' => 'Database error: ' . $e->getMessage()];
        }

      
      
        $exist_managers = $this->getMembers(['project_id' => $id, 'role' => 'manager']);
        $exist_members = $this->getMembers(['project_id' => $id, 'role' => 'member']);
        $managers = [];
        $members = [];
        if ($result && isset($_POST['managers'])) {
            $managers = explode(',', $_POST['managers']);
        }
        if ($result && isset($_POST['members'])) {
            $members = explode(',', $_POST['members']);
            $members = array_diff($members, $managers);

            $exist_members_ids = array_column($exist_members, 'user_id');
            $new_members = array_diff($members, $exist_members_ids);
            $removed_members = array_diff($exist_members_ids, $members);
            
            // Get all users in one query
            $listAllUserIds = array_merge($new_members, $removed_members);
            $listAllUserIds = array_unique($listAllUserIds);
            $listAllUserIds = array_map('intval', $listAllUserIds);
            $listAllUsers = [];
            if (!empty($listAllUserIds)) {
                $placeholders = str_repeat('%d,', count($listAllUserIds) - 1) . '%d';
                $query = sprintf("SELECT id, userid FROM " . DB_PREFIX . "user WHERE id IN ($placeholders)", ...$listAllUserIds);
                $listAllUsers = $this->fetchAll($query);
            }

            $new_users = array_filter($listAllUsers, function($user) use ($new_members) {
                return in_array($user['id'], $new_members);
            });
            $removed_users = array_filter($listAllUsers, function($user) use ($removed_members) {
                return in_array($user['id'], $removed_members);
            });
            
            // Xóa members cũ
            $this->query("DELETE FROM " . DB_PREFIX . "project_members WHERE project_id = " . intval($id));
            // Thêm members mới
            foreach ($members as $user_id) {
                $this->addMember($id, $user_id, $new_users[$user_id]['userid']);
            }

            if (!empty($members)) {
                $this->notifyMemberAdded($data['project_number'], $id, $data['name'], array_column($new_users, 'userid'), 'member');
            }
            if (!empty($removed_users)) {
                $this->notifyMemberRemoved($data['project_number'], $id, $data['name'], array_column($removed_users, 'userid'), 'member');
            }
        }
       
        if ($result && isset($_POST['managers'])) {
            $exist_managers_ids = array_column($exist_managers, 'user_id');
            $new_managers = array_diff($managers, $exist_managers_ids);
            $removed_managers = array_diff($exist_managers_ids, $managers);

            $listAllUserIds = array_merge($new_managers, $removed_managers);
            $listAllUserIds = array_unique($listAllUserIds);
            $listAllUserIds = array_map('intval', $listAllUserIds);
            $listAllUsers = [];
            if (!empty($listAllUserIds)) {
                $placeholders = str_repeat('%d,', count($listAllUserIds) - 1) . '%d';
                $query = sprintf("SELECT id, userid FROM " . DB_PREFIX . "user WHERE id IN ($placeholders)", ...$listAllUserIds);
                $listAllUsers = $this->fetchAll($query);
            }

            //always add managers
            $new_users = array_filter($listAllUsers, function($user) use ($managers) {
                return in_array($user['id'], $managers);
            });
            $removed_users = array_filter($listAllUsers, function($user) use ($removed_managers) {
                return in_array($user['id'], $removed_managers);
            });
            foreach ($managers as $user_id) {
                $this->addMember($id, $user_id, $new_users[$user_id]['userid'], 'manager');
            }
            if (!empty($new_managers)) {
                $this->notifyMemberAdded($data['project_number'], $id, $data['name'], array_column($new_users, 'userid'), 'manager');
            }
            if (!empty($removed_managers)) {
                $this->notifyMemberRemoved($data['project_number'], $id, $data['name'], array_column($removed_users, 'userid'), 'manager');
            }
        }
        if ($result) {
            $this->logProjectAction($id, 'updated', '案件情報を変更');
            return ['success' => true, 'message' => 'Project updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Update failed'];
        }
    }


    function delete() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'error' => 'No project id'];
        $old = $this->getById($id);
        $result = $this->query_update(['status' => 'deleted'], ['id' => $id]);
        if ($result) {
            $this->logProjectAction($id, 'deleted', '案件を削除', $old['status'], 'deleted');
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'error' => 'Delete failed'];
        }
    }

    function getMembers($params = null) {
        // Handle both direct project_id parameter and params array from API
        if (is_array($params)) {
            $project_id = isset($params['project_id']) ? $params['project_id'] : 0;
            $role = isset($params['role']) ? $params['role'] : '';
        } else {
            $project_id = $params;
        }
        
        $query = sprintf(
            "SELECT pm.*, u.realname as user_name, user_image, u.userid
            FROM " . DB_PREFIX . "project_members pm 
            LEFT JOIN " . DB_PREFIX . "user u ON pm.user_id = u.id 
            WHERE pm.project_id = %d %s 
            ORDER BY pm.created_at DESC",
            intval($project_id),
            $role ? "AND pm.role = '$role'" : ''
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

    function addMember($project_id, $user_id, $username, $role = 'member') {
        
        if(!$user_id) return false;
        

        $data = array(
            'project_id' => $project_id,
            'user_id' => $user_id,
            'userid' => $username,
            'role' => $role,
            'created_at' => date('Y-m-d H:i:s')
        );
        $this->table = DB_PREFIX . 'project_members';
        $result = $this->query_insert($data);
        $this->table = DB_PREFIX . 'projects'; // Reset table back to projects
        // if ($result) {
        // }
        return $result;
    }

    function removeMember($project_id, $user_id) {
        $this->table = DB_PREFIX . 'project_members';
        $result = $this->query_delete(['project_id' => $project_id, 'user_id' => $user_id]);
        $this->table = DB_PREFIX . 'projects'; // Reset table back to projects
        // if ($result) {
        // }
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
            (SELECT COUNT(*) FROM " . DB_PREFIX . "project_drawings WHERE project_id = p.id) as drawing_count,
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
        $name = isset($_POST['name']) ? $_POST['name'] : '';
        $project_number = isset($_POST['project_number']) ? $_POST['project_number'] : '';
        
        if (!$id || !$status) {
            return false;
        }
        
        $old = $this->getById($id);
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
        if ($result) {
            $projectMembers = $this->getMembers(['project_id' => $id]);
            if($old['status'] != $status) {
                $this->notifyProjectStatusChanged($project_number,$id, $name, $data['status'], array_column($projectMembers, 'userid'));
                $this->logProjectAction($id, 'status_changed', 'ステータス変更', $old['status'], $status);
            }
           
        }
        
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
        $old = $this->getById($id);
        $data = array(
            'start_date' => $start_date,
            'end_date' => $end_date,
            'updated_at' => date('Y-m-d H:i:s')
        );
        $result = $this->query_update($data, ['id' => $id]);
        if ($result) {
            $this->logProjectAction($id, 'date_updated', '日程変更', $old['start_date'].'~'.$old['end_date'], $start_date.'~'.$end_date);
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
        
        $old = $this->getById($id);
        $data = array(
            'priority' => $priority,
            'updated_at' => date('Y-m-d H:i:s')
        );

        $result = $this->query_update($data, ['id' => $id]);
        if ($result) {
            if($old['priority'] != $priority) {
                $this->logProjectAction($id, 'priority_updated', '優先度変更', $old['priority'], $priority);
            }
        }
        return $result;
    }

    function getComments() {
        $project_id = isset($_GET['project_id']) ? $_GET['project_id'] : 0;
        if(!$project_id) return [];
        
        // Get pagination parameters
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
        $offset = ($page - 1) * $per_page;
        
        $query = sprintf(
            "SELECT c.*, u.realname as user_name, u.user_image,
                    (SELECT COUNT(*) FROM " . DB_PREFIX . "comment_likes cl WHERE cl.comment_id = c.id) as like_count,
                    (SELECT GROUP_CONCAT(cl.user_id) FROM " . DB_PREFIX . "comment_likes cl WHERE cl.comment_id = c.id) as liked_by,
                    (SELECT GROUP_CONCAT(CONCAT(cl.user_id, ':', cl.name) SEPARATOR '|') 
                     FROM " . DB_PREFIX . "comment_likes cl 
                     WHERE cl.comment_id = c.id) as liked_by_names
            FROM " . DB_PREFIX . "comments c 
            LEFT JOIN " . DB_PREFIX . "user u ON c.user_id = u.userid 
            WHERE c.project_id = %d 
            ORDER BY c.created_at DESC
            LIMIT %d OFFSET %d",
            intval($project_id),
            intval($per_page),
            intval($offset)
        );
        
        $comments = $this->fetchAll($query);
        
        // Process liked_by string to array and liked_by_names
        foreach ($comments as &$comment) {
            if ($comment['liked_by']) {
                $comment['liked_by'] = explode(',', $comment['liked_by']);
            } else {
                $comment['liked_by'] = [];
            }
            
            // Process liked_by_names
            if ($comment['liked_by_names']) {
                $likedByNames = [];
                $namePairs = explode('|', $comment['liked_by_names']);
                foreach ($namePairs as $pair) {
                    if (strpos($pair, ':') !== false) {
                        list($userId, $name) = explode(':', $pair, 2);
                        $likedByNames[] = $name;
                    }
                }
                $comment['liked_by_names'] = $likedByNames;
            } else {
                $comment['liked_by_names'] = [];
            }
            
            $comment['like_count'] = intval($comment['like_count']);
        }
        
        return $comments;
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
            return ['success' => true, 'message' => 'Comment added successfully'];
        }
        return ['success' => false, 'message' => 'Comment addition failed'];
    }

    function toggleLike() {

        $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
        $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : 0;
        $action = isset($_POST['action']) ? $_POST['action'] : 'like';
        $name = isset($_POST['name']) ? $_POST['name'] : '';
        
        if (!$comment_id || !$user_id) {
            return ['success' => false, 'message' => 'Invalid parameters'];
        }
        
        // Check if like already exists
        $existingLike = $this->fetchOne(sprintf(
            "SELECT id FROM " . DB_PREFIX . "comment_likes 
             WHERE comment_id = %d AND user_id = '%s'",
            $comment_id, $user_id
        ));
        
        if ($action === 'like') {
            if ($existingLike) {
                return ['success' => false, 'message' => 'Already liked'];
            }
            
            // Add like
            $likeData = array(
                'comment_id' => $comment_id,
                'user_id' => $user_id,
                'name' => $name,
                'created_at' => date('Y-m-d H:i:s')
            );
            
            $this->table = DB_PREFIX . 'comment_likes';
            $result = $this->query_insert($likeData);
            $this->table = DB_PREFIX . 'projects'; // Reset table back to projects
            
        } else {
            if (!$existingLike) {
                return ['success' => false, 'message' => 'Not liked yet'];
            }
            
            // Remove like
            $result = $this->query(sprintf(
                "DELETE FROM " . DB_PREFIX . "comment_likes 
                 WHERE comment_id = %d AND user_id = '%s'",
                $comment_id, $user_id
            ));
        }
        
        if ($result) {
            // Get updated like count
            $likeCount = $this->fetchOne(sprintf(
                "SELECT COUNT(*) as count FROM " . DB_PREFIX . "comment_likes 
                 WHERE comment_id = %d",
                $comment_id
            ));
            
            // Get updated liked_by_names
            $likedByNames = $this->fetchOne(sprintf(
                "SELECT GROUP_CONCAT(cl.name SEPARATOR '|') as names
                 FROM " . DB_PREFIX . "comment_likes cl 
                 WHERE cl.comment_id = %d",
                $comment_id
            ));
            
            $likedByNamesArray = [];
            if ($likedByNames && $likedByNames['names']) {
                $likedByNamesArray = explode('|', $likedByNames['names']);
            }
            
            return [
                'success' => true,
                'like_count' => intval($likeCount['count']),
                'is_liked' => $action === 'like',
                'liked_by_names' => $likedByNamesArray
            ];
        }
        
        return ['success' => false, 'message' => 'Database error'];
    }

    // Detect mentions and send notifications
    private function sendMentionNotifications($projectId, $content, $commentUserId, $commentId) {
        try {
            require_once('NotificationService.php');
            $notiService = new NotificationService();
            $notiService->sendProjectCommentNotification($projectId, $commentId);
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
                    'title' => '案件でメンションされました',
                    'message' => sprintf('%sさんが案件「%s」であなたをメンションしました', 
                        $_SESSION['realname'], 
                        $project['name']
                    ),
                    'data' => [
                        'project_id' => $projectId,
                        'project_name' => $project['name'],
                        'comment_id' => $commentId,
                        'comment_content' => $content,
                        'commenter_id' => $commentUserId,
                        'commenter_name' => $_SESSION['realname'],
                        'avatar' => $this->getUserImage(),
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
        
        // First, try to extract from HTML mentions with data attributes
        if (strpos($content, 'data-user-id') !== false) {
            // Extract user IDs from HTML mentions - improved regex for multiple mentions
            preg_match_all('/<span[^>]*data-user-id="([^"]+)"[^>]*data-user-name="([^"]+)"[^>]*>@([^<]+)<\/span>/', $content, $matches);
            if (!empty($matches[1])) {
                $userIds = $matches[1];
                
                // Remove duplicates while preserving order
                $uniqueUserIds = array_unique($userIds);
                
                // Get user info by user IDs - using safe parameter binding
                if (!empty($uniqueUserIds)) {
                    $placeholders = str_repeat('?,', count($uniqueUserIds) - 1) . '?';
                    $query = "SELECT userid, realname, user_image FROM " . DB_PREFIX . "user WHERE userid IN ($placeholders)";
                    $users = $this->fetchAllWithParams($query, $uniqueUserIds);
                    
                    foreach ($users as $user) {
                        $mentionedUsers[] = $user;
                    }
                }
            }
        }
       
        // If no HTML mentions found, try plain text mentions
        if (empty($mentionedUsers)) {
            $plainText = strip_tags($content);
            preg_match_all('/@([^\s]+)/', $plainText, $matches);
            
            if (!empty($matches[1])) {
                $mentionedUsernames = $matches[1];
                
                // Remove duplicates while preserving order
                $uniqueUsernames = array_unique($mentionedUsernames);
                
                if (!empty($uniqueUsernames)) {
                    // Get mentioned users from database by username - using safe parameter binding
                    $placeholders = str_repeat('?,', count($uniqueUsernames) - 1) . '?';
                    $query = "SELECT userid, realname, user_image FROM " . DB_PREFIX . "user WHERE realname IN ($placeholders)";
                    $mentionedUsers = $this->fetchAllWithParams($query, $uniqueUsernames);
                }
            }
        }
        
        return $mentionedUsers;
    }

    /**
     * Fetch all records with prepared statement parameters
     * @param string $query SQL query with placeholders
     * @param array $params Parameters to bind
     * @return array Results
     */
    private function fetchAllWithParams($query, $params) {
        $this->connect();
        if (!$this->handler) {
            return [];
        }
        
        // Prepare statement
        $stmt = mysqli_prepare($this->handler, $query);
        if (!$stmt) {
            error_log("Prepare failed: " . mysqli_error($this->handler));
            return [];
        }
        
        // Bind parameters
        if (!empty($params)) {
            // Create types string (assuming all are strings for user IDs/names)
            $types = str_repeat('s', count($params));
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        
        // Execute query
        if (!mysqli_stmt_execute($stmt)) {
            error_log("Execute failed: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return [];
        }
        
        // Get results
        $result = mysqli_stmt_get_result($stmt);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        
        mysqli_stmt_close($stmt);
        return $data;
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
        require_once('projectnote.php');
        $noteModel = new ProjectNote();
        return $noteModel->create($params);
    }

    function updateNote($params = null) {
        require_once('projectnote.php');
        $noteModel = new ProjectNote();
        return $noteModel->update($params);
    }

    function deleteNote($params = null) {
        require_once('projectnote.php');
        $noteModel = new ProjectNote();
        return $noteModel->delete($params);
    }

    function getNotes($params = null) {
        require_once('projectnote.php');
        $noteModel = new ProjectNote();
        return $noteModel->list($params);
    }

  
    function sendProjectNotification($params = null) {
        try {
            require_once('NotificationService.php');
            $notiService = new NotificationService();
            
            // Validate required parameters
            if (!isset($params['event']) || !isset($params['title']) || !isset($params['message'])) {
                return false;
            }
            
            // Default values
            $defaultParams = [
                'project_id' => 0,
                'task_id' => 0,
                'user_ids' => [],
                'group_ids' => [],
                'department_ids' => [],
                'exclude_user_ids' => [],
                'data' => [],
                'url' => '',
                'priority' => 'normal', // low, normal, high, urgent
                'type' => 'project' // project, task, comment, mention, etc.
            ];
            
            $params = array_merge($defaultParams, $params);
            
            // Get target users based on different criteria
            $targetUserIds = [];
            
            // 1. Direct user IDs
            if (!empty($params['user_ids'])) {
                $targetUserIds = array_merge($targetUserIds, $params['user_ids']);
            }
            
            // 2. Users from groups
            if (!empty($params['group_ids'])) {
                $groupUserIds = $this->getUsersByGroups($params['group_ids']);
                $targetUserIds = array_merge($targetUserIds, $groupUserIds);
            }
            
            // 3. Users from departments
            if (!empty($params['department_ids'])) {
                $deptUserIds = $this->getUsersByDepartments($params['department_ids']);
                $targetUserIds = array_merge($targetUserIds, $deptUserIds);
            }
            
            // 4. Project members (if project_id is provided)
            if ($params['project_id'] > 0) {
                $projectMembers = $this->getProjectMemberIds($params['project_id']);
                $targetUserIds = array_merge($targetUserIds, $projectMembers);
            }
            
            // Remove duplicates and excluded users
            $targetUserIds = array_unique($targetUserIds);
            $targetUserIds = array_diff($targetUserIds, $params['exclude_user_ids']);
            
            // Remove current user if not explicitly included
            $targetUserIds = array_diff($targetUserIds, [$_SESSION['userid']]);
            
            if (empty($targetUserIds)) {
                error_log('No target users found for notification');
                return false;
            }
            
            // Prepare notification payload
            $payload = [
                'event' => $params['event'],
                'title' => $params['title'],
                'message' => $params['message'],
                'data' => array_merge($params['data'], [
                    'project_id' => $params['project_id'],
                    'task_id' => $params['task_id'],
                    'type' => $params['type'],
                    'priority' => $params['priority'],
                    'sender_id' => $_SESSION['userid'],
                    'sender_name' => $_SESSION['realname'] ?? 'Unknown',
                    'timestamp' => date('Y-m-d H:i:s')
                ]),
                'url' => $params['url'],
                'user_ids' => array_values($targetUserIds)
            ];
            
            // Send notification
            $result = $notiService->create($payload);
            
            if ($result) {
                return true;
            } else {
                return false;
            }
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Lấy danh sách user IDs theo nhóm
     */
    private function getUsersByGroups($groupIds) {
        if (empty($groupIds)) return [];
        
        $groupIds = array_map('intval', $groupIds);
        $placeholders = str_repeat('%d,', count($groupIds) - 1) . '%d';
        
        $query = sprintf("SELECT DISTINCT userid FROM " . DB_PREFIX . "user WHERE user_group IN ($placeholders)", ...$groupIds);
        $users = $this->fetchAll($query);
        
        return array_column($users, 'userid');
    }
    
    /**
     * Lấy danh sách user IDs theo phòng ban
     */
    private function getUsersByDepartments($departmentIds) {
        if (empty($departmentIds)) return [];
        
        $departmentIds = array_map('intval', $departmentIds);
        $placeholders = str_repeat('%d,', count($departmentIds) - 1) . '%d';
        
        $query = sprintf("SELECT DISTINCT userid FROM " . DB_PREFIX . "user_department WHERE department_id IN ($placeholders)", ...$departmentIds);
        $users = $this->fetchAll($query);
        
        return array_column($users, 'userid');
    }
    
    /**
     * Lấy danh sách user IDs của thành viên dự án
     */
    private function getProjectMemberIds($projectId) {
        $query = sprintf("SELECT DISTINCT userid FROM " . DB_PREFIX . "project_members WHERE project_id = %d", intval($projectId));
        $members = $this->fetchAll($query);
        
        return array_column($members, 'user_id');
    }
    
    /**
     * Hàm tiện ích để gửi thông báo khi tạo dự án mới
     */
    function notifyProjectCreated($projectId, $projectName, $userIds = null) {
        $params = [
            'event' => 'project_created',
            'title' => '新しい案件が作成されました',
            'message' => sprintf('%sが案件「%s」を作成しました', $this->getUserRealname(), $projectName),
            'project_id' => $projectId,
            'user_ids' => $userIds ? $userIds : [],
            'data' => [
                'project_name' => $projectName,
                'action' => 'created',
                'avatar' => $this->getUserImage(),
                'url' => "/project/detail.php?id=$projectId",
            ],
            'type' => 'project'
        ];
        
        return $this->sendProjectNotification($params);
    }
    
    /**
     * Hàm tiện ích để gửi thông báo khi cập nhật dự án
     */
    function notifyProjectUpdated($projectId, $projectName, $changes = []) {
        $params = [
            'event' => 'project_updated',
            'title' => '案件が更新されました',
            'message' => sprintf('案件「%s」が更新されました', $projectName),
            'project_id' => $projectId,
            'data' => [
                'project_name' => $projectName,
                'changes' => $changes,
                'action' => 'updated'
            ],
            'url' => "/project/detail.php?id=$projectId",
            'type' => 'project'
        ];
        
        return $this->sendProjectNotification($params);
    }
    
    /**
     * Hàm tiện ích để gửi thông báo khi thay đổi trạng thái dự án
     */
    function notifyProjectStatusChanged($projectNumber, $projectId, $projectName, $newStatus, $memberIds) {
        $statusLabels = [
            'draft' => '下書き',
            'open' => 'オープン',
            'confirming' => '確認中',
            'in_progress' => '進行中',
            'completed' => '完了',
            'paused' => '一時停止',
            'cancelled' => 'キャンセル',
            'deleted' => '削除'
        ];
        
        $newStatusLabel = $statusLabels[$newStatus] ?? $newStatus;
        
        $params = [
            'event' => 'project_status_changed',
            'title' => '#'.$projectNumber.': ステータスが変更されました',
            'message' => sprintf('%sが案件「%s」のステータスを「%s」に変更しました', $this->getUserRealname(), $projectName, $newStatusLabel),
            'project_id' => $projectId,
            'user_ids' => $memberIds,
            'data' => [
                'project_name' => $projectName,
                'project_number' => $projectNumber,
                'action' => 'status_changed',
                'avatar' => $this->getUserImage(),
                'url' => "/project/detail.php?id=$projectId",
            ],
            'type' => 'project',
            'priority' => $newStatus === 'completed' ? 'high' : 'normal'
        ];
        
        return $this->sendProjectNotification($params);
    }

    // function removeCurrentUserFromNotification($usernames) {
    //     if (isset($usernames)) {
    //         $usernames = array_diff($usernames, [$_SESSION['userid']]);
    //     }
    //     return $usernames;
    // }
    
    /**
     * Hàm tiện ích để gửi thông báo khi thêm thành viên vào dự án
     */
    function notifyMemberAdded($projectNumber, $projectId, $projectName, $memberIds, $role = 'member') {
        if (empty($memberIds)) return false;
        
        $roleLabel = $role === 'manager' ? 'マネージャー' : 'メンバー';
        
        $params = [
            'event' => 'project_member_added',
            'title' => '#'.$projectNumber.': メンバーが追加されました',
            'message' => sprintf('%sがあなたを案件「%s」に%sを追加しました', $this->getUserRealname(), $projectName, $roleLabel),
            'project_id' => $projectId,
            'user_ids' => $memberIds,
            'data' => [
                'project_name' => $projectName,
                'avatar' => $this->getUserImage(),
                'role' => $role,
                'role_label' => $roleLabel,
                'action' => 'member_added',
                'url' => "/project/detail.php?id=$projectId",
            ],
            'type' => 'project'
        ];
        
        return $this->sendProjectNotification($params);
    }

    function getUserImage() {
        if (isset($_SESSION['user_image']) && $_SESSION['user_image'] != '') {
            return  '/assets/upload/avatar/'. $_SESSION['user_image'];
        }
        return  '/assets/img/avatars/1.png';
    }

    function getUserRealname() {
        if (isset($_SESSION['lastname']) && $_SESSION['lastname'] != '') {
            return $_SESSION['lastname'] . 'さん';
        }
        return $_SESSION['realname']. 'さん';
    }

    /**
     * Hàm tiện ích để gửi thông báo khi xóa thành viên khỏi dự án
     */
    function notifyMemberRemoved($projectNumber, $projectId, $projectName, $memberIds, $role = 'member') {
        if (empty($memberIds)) return false;
        
        $roleLabel = $role === 'manager' ? 'マネージャー' : 'メンバー';
        
        $params = [
            'event' => 'project_member_removed',
            'title' => '#'.$projectNumber.': メンバーが削除されました',
            'message' => sprintf('%sがあなたを案件「%s」から%sを削除しました', $this->getUserRealname(), $projectName, $roleLabel),
            'project_id' => $projectId,
            'user_ids' => $memberIds,
            'data' => [
                'project_name' => $projectName,
                'avatar' => $this->getUserImage(),
                'role' => $role,
                'role_label' => $roleLabel,
                'action' => 'member_removed',
                'url' => "",
            ],
            'type' => 'project'
        ];
        
        return $this->sendProjectNotification($params);
    }
    
    /**
     * Hàm tiện ích để gửi thông báo khi có comment mới
     */
    function notifyNewComment($projectId, $projectName, $commentId, $commentContent, $excludeUserId = null) {
        $params = [
            'event' => 'project_comment_added',
            'title' => '案件に新しいコメントがあります',
            'message' => sprintf('案件「%s」に新しいコメントが追加されました', $projectName),
            'project_id' => $projectId,
            'exclude_user_ids' => $excludeUserId ? [$excludeUserId] : [],
            'data' => [
                'project_name' => $projectName,
                'comment_id' => $commentId,
                'comment_content' => substr($commentContent, 0, 100) . (strlen($commentContent) > 100 ? '...' : ''),
                'action' => 'comment_added',
                'avatar' => $this->getUserImage(),
            ],
            'url' => "/project/detail.php?id=$projectId#comment-$commentId",
            'type' => 'comment'
        ];
        
        return $this->sendProjectNotification($params);
    }

    // Lấy lịch sử hành động của dự án
    function getLogs($params = null) {
        $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
        if (!$project_id) return [];

        // Nếu có bảng project_logs thì lấy từ đó, nếu không thì trả về mảng mẫu
        $query = sprintf(
            "SELECT l.*, u.realname, u.user_image FROM " . DB_PREFIX . "project_logs l
            LEFT JOIN " . DB_PREFIX . "user u ON l.user_id = u.userid
            WHERE l.project_id = %d ORDER BY l.time DESC",
            $project_id
        );
        $logs = $this->fetchAll($query);
        // Nếu không có bảng logs, trả về mảng mẫu (có thể xóa đoạn này nếu đã có bảng)
        // if (!$logs) {
        //     $logs = [
        //         ['action'=>'created','time'=>'2024-06-01 10:00:00','realname'=>'管理者','user_image'=>'','note'=>'案件作成'],
        //         ['action'=>'updated','time'=>'2024-06-02 12:00:00','realname'=>'山田太郎','user_image'=>'','note'=>'説明を修正'],
        //     ];
        // }
        return $logs;
    }

    // Ghi log hành động dự án
    private function logProjectAction($project_id, $action, $note = '', $value1 = '', $value2 = '') {
        $user_id = $_SESSION['userid'] ?? '';
        $username = $_SESSION['realname'] ?? '';
        $data = [
            'project_id' => $project_id,
            'user_id' => $user_id,
            'username' => $username,
            'action' => $action,
            'note' => $note,
            'value1' => $value1,
            'value2' => $value2,
            'time' => date('Y-m-d H:i:s')
        ];
        $this->table = DB_PREFIX . 'project_logs';
        $this->query_insert($data);
        $this->table = DB_PREFIX . 'projects'; // reset lại table
    }

    // Attachment management methods
    function getAttachments($params = null) {
        $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
        $folder_id = isset($_GET['folder_id']) ? intval($_GET['folder_id']) : null;
        
        if (!$project_id) {
            return ['status' => 'error', 'message' => 'Project ID is required'];
        }
        
        // Get folders
        $folderQuery = sprintf(
            "SELECT f.*, u.realname as created_by_name,
                    (SELECT COUNT(*) FROM " . DB_PREFIX . "project_attachments a WHERE a.folder_id = f.id) as file_count
             FROM " . DB_PREFIX . "project_folders f
             LEFT JOIN " . DB_PREFIX . "user u ON f.created_by = u.userid
             WHERE f.project_id = %d AND %s
             ORDER BY f.name ASC",
            $project_id,
            $folder_id ? "f.parent_folder_id = $folder_id" : "f.parent_folder_id IS NULL"
        );
        $folders = $this->fetchAll($folderQuery);
        
        // Get files
        $fileQuery = sprintf(
            "SELECT a.*, u.realname as uploaded_by_name
             FROM " . DB_PREFIX . "project_attachments a
             LEFT JOIN " . DB_PREFIX . "user u ON a.uploaded_by = u.userid
             WHERE a.project_id = %d AND %s
             ORDER BY a.uploaded_at DESC",
            $project_id,
            $folder_id ? "a.folder_id = $folder_id" : "a.folder_id IS NULL"
        );
        $files = $this->fetchAll($fileQuery);
        
        // Get breadcrumbs
        $breadcrumbs = [];
        if ($folder_id) {
            $breadcrumbs = $this->getBreadcrumbs($folder_id);
        }
        
        return [
            'status' => 'success',
            'folders' => $folders ?: [],
            'files' => $files ?: [],
            'breadcrumbs' => $breadcrumbs
        ];
    }
    
    private function getBreadcrumbs($folder_id) {
        $breadcrumbs = [];
        $current_id = $folder_id;
        
        while ($current_id) {
            $query = sprintf(
                "SELECT id, name, parent_folder_id FROM " . DB_PREFIX . "project_folders WHERE id = %d",
                $current_id
            );
            $folder = $this->fetchOne($query);
            
            if ($folder) {
                array_unshift($breadcrumbs, [
                    'id' => $folder['id'],
                    'name' => $folder['name']
                ]);
                $current_id = $folder['parent_folder_id'];
            } else {
                break;
            }
        }
        
        return $breadcrumbs;
    }
    
    function createFolder($params = null) {
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $parent_folder_id = isset($_POST['parent_folder_id']) && $_POST['parent_folder_id'] !== '' ? intval($_POST['parent_folder_id']) : null;
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        
        if (!$project_id || !$name) {
            return ['status' => 'error', 'message' => 'Project ID and folder name are required'];
        }
        
        // Check if folder name already exists in the same location
        $checkQuery = sprintf(
            "SELECT id FROM " . DB_PREFIX . "project_folders 
             WHERE project_id = %d AND name = '%s' AND %s",
            $project_id,
            $this->escape($name),
            $parent_folder_id ? "parent_folder_id = $parent_folder_id" : "parent_folder_id IS NULL"
        );
        
        if ($this->fetchOne($checkQuery)) {
            return ['status' => 'error', 'message' => 'Folder with this name already exists'];
        }
        
        $data = [
            'project_id' => $project_id,
            'name' => $name,
            'created_by' => $_SESSION['userid'] ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Only add parent_folder_id if it's not null
        if ($parent_folder_id !== null) {
            $data['parent_folder_id'] = $parent_folder_id;
        }
        
        $this->table = DB_PREFIX . 'project_folders';
        $result = $this->query_insert($data);
        $this->table = DB_PREFIX . 'projects'; // reset table
        
        if ($result) {
            return ['status' => 'success', 'message' => 'Folder created successfully'];
        } else {
            return ['status' => 'error', 'message' => 'Failed to create folder'];
        }
    }
    
    function updateFolder($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        
        if (!$id || !$name) {
            return ['status' => 'error', 'message' => 'Folder ID and name are required'];
        }
        
        // Get folder info
        $folderQuery = sprintf(
            "SELECT project_id, parent_folder_id FROM " . DB_PREFIX . "project_folders WHERE id = %d",
            $id
        );
        $folder = $this->fetchOne($folderQuery);
        
        if (!$folder) {
            return ['status' => 'error', 'message' => 'Folder not found'];
        }
        
        // Check if folder name already exists in the same location
        $checkQuery = sprintf(
            "SELECT id FROM " . DB_PREFIX . "project_folders 
             WHERE project_id = %d AND name = '%s' AND id != %d AND %s",
            $folder['project_id'],
            $this->escape($name),
            $id,
            $folder['parent_folder_id'] ? "parent_folder_id = " . $folder['parent_folder_id'] : "parent_folder_id IS NULL"
        );
        
        if ($this->fetchOne($checkQuery)) {
            return ['status' => 'error', 'message' => 'Folder with this name already exists'];
        }
        
        $data = [
            'name' => $name,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $this->table = DB_PREFIX . 'project_folders';
        $result = $this->query_update($data, ['id' => $id]);
        $this->table = DB_PREFIX . 'projects'; // reset table
        
        if ($result) {
            return ['status' => 'success', 'message' => 'Folder updated successfully'];
        } else {
            return ['status' => 'error', 'message' => 'Failed to update folder'];
        }
    }
    
    function deleteFolder($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            return ['status' => 'error', 'message' => 'Folder ID is required'];
        }
        
        // Get folder info
        $folderQuery = sprintf(
            "SELECT project_id FROM " . DB_PREFIX . "project_folders WHERE id = %d",
            $id
        );
        $folder = $this->fetchOne($folderQuery);
        
        if (!$folder) {
            return ['status' => 'error', 'message' => 'Folder not found'];
        }
        
        // Recursively delete subfolders and files
        $this->deleteSubfoldersAndFiles($id);
        
        // Delete the folder itself
        $this->table = DB_PREFIX . 'project_folders';
        $result = $this->query_delete(['id' => $id]);
        $this->table = DB_PREFIX . 'projects'; // reset table
        
        if ($result) {
            return ['status' => 'success', 'message' => 'Folder deleted successfully'];
        } else {
            return ['status' => 'error', 'message' => 'Failed to delete folder'];
        }
    }
    
    private function deleteSubfoldersAndFiles($folder_id) {
        // Get all subfolders
        $subfoldersQuery = sprintf(
            "SELECT id FROM " . DB_PREFIX . "project_folders WHERE parent_folder_id = %d",
            $folder_id
        );
        $subfolders = $this->fetchAll($subfoldersQuery);
        
        // Recursively delete subfolders
        foreach ($subfolders as $subfolder) {
            $this->deleteSubfoldersAndFiles($subfolder['id']);
            $this->table = DB_PREFIX . 'project_folders';
            $this->query_delete(['id' => $subfolder['id']]);
        }
        
        // Get all files in this folder
        $filesQuery = sprintf(
            "SELECT id, file_path FROM " . DB_PREFIX . "project_attachments WHERE folder_id = %d",
            $folder_id
        );
        $files = $this->fetchAll($filesQuery);
        
        // Delete files from filesystem and database
        foreach ($files as $file) {
            if (file_exists('..' . $file['file_path'])) {
                unlink('..' . $file['file_path']);
            }
            $this->table = DB_PREFIX . 'project_attachments';
            $this->query_delete(['id' => $file['id']]);
        }
        
        $this->table = DB_PREFIX . 'projects'; // reset table
    }
    
    function uploadAttachment($params = null) {
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $folder_id = isset($_POST['folder_id']) && $_POST['folder_id'] !== '' ? intval($_POST['folder_id']) : null;
        
        if (!$project_id) {
            return ['success' => false, 'error' => 'Project ID is required'];
        }
        
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'No file uploaded or upload error'];
        }
        
        $file = $_FILES['image'];
        $originalName = $file['name'];
        $fileSize = $file['size'];
        $tmpName = $file['tmp_name'];
        
        // Validate file size (10MB max)
        if ($fileSize > 100 * 1024 * 1024) {
            return ['success' => false, 'error' => 'File size exceeds 100MB limit'];
        }
        
        // Create upload directory (relative to project root)
        $uploadDir = "../assets/upload/project-attachments/$project_id/";
        if ($folder_id) {
            $uploadDir .= "folder-$folder_id/";
        }
        
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return ['success' => false, 'error' => 'Failed to create upload directory'];
            }
        }
        
        // Check if file with same original name already exists in this folder
        $existingFileQuery = sprintf(
            "SELECT id, file_path FROM " . DB_PREFIX . "project_attachments 
             WHERE project_id = %d AND original_name = '%s' AND %s",
            $project_id,
            $this->escape($originalName),
            $folder_id ? "folder_id = $folder_id" : "folder_id IS NULL"
        );
        $existingFile = $this->fetchOne($existingFileQuery) ?: null;
        
        // Use original filename (replace if exists)
        $filename = $originalName;
        $filePath = $uploadDir . $filename;
        
        // If file exists, delete the old physical file first
        if ($existingFile) {
            // Try to delete old file from filesystem (use the relative path from database)
            $oldFilePath = str_replace(ROOT, '../', $existingFile['file_path']);
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }
        }
        
        // Move uploaded file (this will overwrite existing file)
        if (!move_uploaded_file($tmpName, $filePath)) {
            return ['success' => false, 'error' => 'Failed to move uploaded file'];
        }

        // Create URL path for database storage
        $uploadDir2 = "assets/upload/project-attachments/$project_id/";
        if ($folder_id) {
            $uploadDir2 .= "folder-$folder_id/";
        }
        $urlFile = ROOT . $uploadDir2 . $filename;
        
        // Prepare data for database
        $data = [
            'project_id' => $project_id,
            'original_name' => $originalName,
            'file_name' => $filename,
            'file_path' => $urlFile,
            'file_size' => $fileSize,
            'mime_type' => $file['type'],
            'uploaded_by' => $_SESSION['userid'] ?? 0,
            'uploaded_at' => date('Y-m-d H:i:s')
        ];
        
        // Only add folder_id if it's not null
        if ($folder_id !== null) {
            $data['folder_id'] = $folder_id;
        }
        
        $this->table = DB_PREFIX . 'project_attachments';
        
        if ($existingFile) {
            // Update existing record
            $result = $this->query_update($data, ['id' => $existingFile['id']]);
            $fileId = $existingFile['id'];
            $message = 'File replaced successfully';
        } else {
            // Insert new record
            $result = $this->query_insert($data);
            $fileId = $result;
            $message = 'File uploaded successfully';
        }
        
        $this->table = DB_PREFIX . 'projects'; // reset table
        
        if ($result) {
            return [
                'success' => true,
                'message' => $message,
                'file' => [
                    'id' => $fileId,
                    'original_name' => $originalName,
                    'file_name' => $filename,
                    'file_size' => $fileSize,
                    'file_path' => $urlFile
                ]
            ];
        } else {
            // Clean up file if database operation failed
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            return ['success' => false, 'error' => 'Failed to save file information'];
        }
    }
    
    function deleteAttachment($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            return ['status' => 'error', 'message' => 'Attachment ID is required'];
        }
        
        // Get file info
        $fileQuery = sprintf(
            "SELECT file_path FROM " . DB_PREFIX . "project_attachments WHERE id = %d",
            $id
        );
        $file = $this->fetchOne($fileQuery);
        
        if (!$file) {
            return ['status' => 'error', 'message' => 'Attachment not found'];
        }
        
        // Delete file from filesystem
        if (file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }
        
        // Delete from database
        $this->table = DB_PREFIX . 'project_attachments';
        $result = $this->query_delete(['id' => $id]);
        $this->table = DB_PREFIX . 'projects'; // reset table
        
        if ($result) {
            return ['status' => 'success', 'message' => 'Attachment deleted successfully'];
        } else {
            return ['status' => 'error', 'message' => 'Failed to delete attachment'];
        }
    }
    
    function downloadAttachment($params = null) {
        $this->serveSecureFile(true); // true = force download
    }
    
    function viewAttachment($params = null) {
        $this->serveSecureFile(false); // false = inline view
    }
    
    private function serveSecureFile($forceDownload = true) {
        // Check if user is logged in
        if (!isset($_SESSION['userid']) || !$_SESSION['userid']) {
            http_response_code(401);
            die('認証が必要です。ログインしてください。');
        }
        
        $file_id = isset($_GET['file_id']) ? intval($_GET['file_id']) : 0;
        
        if (!$file_id) {
            http_response_code(400);
            die('ファイルIDが指定されていません。');
        }
        
        // Get file info from database
        $fileQuery = sprintf(
            "SELECT a.*, p.name as project_name 
             FROM " . DB_PREFIX . "project_attachments a
             LEFT JOIN " . DB_PREFIX . "projects p ON a.project_id = p.id
             WHERE a.id = %d",
            $file_id
        );
        $file = $this->fetchOne($fileQuery);
        
        if (!$file) {
            http_response_code(404);
            die('ファイルが見つかりません。');
        }
        
        // Check if user has permission to access this project
        // if (!$this->checkPermission($file['project_id'], $_SESSION['userid'])) {
        //     http_response_code(403);
        //     die('このファイルにアクセスする権限がありません。');
        // }
        
        // Convert URL path to filesystem path for checking
        $realFilePath = '..' . $file['file_path'];
        
        // Check if file exists on filesystem
        if (!file_exists($realFilePath)) {
            http_response_code(404);
            die('ファイルが見つかりません。');
        }
        
        // Update file array with real path for serving
        $file['real_file_path'] = $realFilePath;
        
        // Serve the file
        $this->serveFile($file, $forceDownload);
    }
    
    private function serveFile($file, $forceDownload = true) {
        // Get file info - use real_file_path if available, otherwise convert from URL path
        $filePath = isset($file['real_file_path']) ? $file['real_file_path'] : str_replace(ROOT, '../', $file['file_path']);
        $fileName = $file['original_name'];
        $fileSize = filesize($filePath);
        $mimeType = $file['mime_type'] ?: 'application/octet-stream';
        
        // Set headers
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $fileSize);
        
        if ($forceDownload) {
            // Force download
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
        } else {
            // Inline view (for images, PDFs, etc.)
            header('Content-Disposition: inline; filename="' . $fileName . '"');
        }
        
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Clear output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Output file
        readfile($filePath);
        exit;
    }

    // Main attachment page method - called by the controller
    function attachment($directory = null, $prefix = null, $filename = null, $type = '') {
        // If called with parameters, use parent method for file download
        if ($directory && $prefix && $filename) {
            return parent::attachment($directory, $prefix, $filename, $type);
        }
        
        // This method is called when accessing attachment.php
        // It just needs to exist to prevent the error
        // The actual functionality is handled by the Vue.js frontend
        return [];
    }

    // Cập nhật nội dung comment dự án
    function updateComment() {
        $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
        $content = isset($_POST['content']) ? $_POST['content'] : '';
        $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : '';
        if (!$comment_id || !$user_id) {
            return ['success' => false, 'message' => 'Invalid parameters'];
        }
        // Lấy comment cũ
        $this->table = DB_PREFIX . 'comments';
        $comment = $this->fetchOne("SELECT * FROM " . DB_PREFIX . "comments WHERE id = $comment_id");
        if (!$comment) {
            $this->table = DB_PREFIX . 'projects';
            return ['success' => false, 'message' => 'Comment not found'];
        }
        // Chỉ cho phép sửa nếu là chủ comment hoặc admin
        if ($_SESSION['authority'] != 'administrator' && $comment['user_id'] != $user_id) {
            $this->table = DB_PREFIX . 'projects';
            return ['success' => false, 'message' => 'Permission denied'];
        }
        // Cập nhật nội dung
        $data = [
            'content' => $content,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $result = $this->query_update($data, ['id' => $comment_id]);
        $this->table = DB_PREFIX . 'projects';
        if ($result) {
            return ['success' => true, 'message' => 'Comment updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Update failed'];
        }
    }

    // Xóa comment dự án
    function deleteComment() {
        $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
        $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : '';
        if (!$comment_id || !$user_id) {
            return ['success' => false, 'message' => 'Invalid parameters'];
        }
        $this->table = DB_PREFIX . 'comments';
        $comment = $this->fetchOne("SELECT * FROM " . DB_PREFIX . "comments WHERE id = $comment_id");
        if (!$comment) {
            $this->table = DB_PREFIX . 'projects';
            return ['success' => false, 'message' => 'Comment not found'];
        }
        // Chỉ cho phép xóa nếu là chủ comment hoặc admin
        if ($_SESSION['authority'] != 'administrator' && $comment['user_id'] != $user_id) {
            $this->table = DB_PREFIX . 'projects';
            return ['success' => false, 'message' => 'Permission denied'];
        }
        $result = $this->query_delete(['id' => $comment_id]);
        $this->table = DB_PREFIX . 'projects';
        if ($result) {
            return ['success' => true, 'message' => 'Comment deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Delete failed'];
        }
    }

    function generateProjectNumber() {
        $department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
        $this->table = DB_PREFIX . 'projects';

        // Lấy 50 project_number mới nhất của phòng ban
        $query = "SELECT project_number FROM " . DB_PREFIX . "projects WHERE department_id = $department_id ORDER BY id DESC LIMIT 50";
        $result = $this->fetchAll($query);

        $prefix = '';
        if (!empty($result)) {
            // Lấy prefix là phần ký tự đầu tiên (không phải số) của project_number đầu tiên
            if (preg_match('/^([^0-9]*)/', $result[0]['project_number'], $matches)) {
                $prefix = $matches[1];
            }
        }

        $maxNumber = 0;
        foreach ($result as $row) {
            // Tìm số ở cuối project_number
            if (preg_match('/(\d+)\s*$/', $row['project_number'], $matches)) {
                $num = intval($matches[1]);
                if ($num > $maxNumber) {
                    $maxNumber = $num;
                }
            }
        }
        $nextNumber = $maxNumber + 1;
        // Format lại số, ví dụ: PRJ-001 hoặc chỉ 001 nếu không có prefix
        $project_number = $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        return $project_number;
    }

    /**
     * Lấy thống kê dự án cho dashboard
     */
    function getDashboardStats() {
        try {
            $user_id = $_SESSION['id'];
            $is_admin = $_SESSION['authority'] == 'administrator';
            $selected_department = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
            
            // Base where clause for permissions
            $permissionWhere = "";
            // if (!$is_admin) {
            //     $permissionWhere = sprintf(
            //         " AND (p.created_by = %d OR EXISTS (
            //             SELECT 1 FROM " . DB_PREFIX . "project_members pm 
            //             WHERE pm.project_id = p.id AND pm.user_id = %d
            //         ))",
            //         $user_id,
            //         $user_id
            //     );
            // }

            // Add department filter if specified
            $departmentWhere = "";
            if ($selected_department > 0) {
                $departmentWhere = sprintf(" AND p.department_id = %d", $selected_department);
            }

            $stats = [];

            // 1. Thống kê tổng quan (không filter theo tháng)
            $totalQuery = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN p.status NOT IN ('completed', 'cancelled', 'deleted') THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN p.status IN ('paused', 'cancelled') THEN 1 ELSE 0 END) as inactive
                FROM {$this->table} p 
                WHERE p.status != 'deleted' $permissionWhere $departmentWhere";
            $overview = $this->fetchOne($totalQuery);
            $stats['overview'] = $overview;

            // 2. Thống kê theo phòng ban (không filter theo tháng)
            $departmentQuery = "SELECT 
                d.name as department_name,
                d.id as department_id,
                COUNT(*) as count
                FROM {$this->table} p 
                LEFT JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id
                WHERE p.status != 'deleted' $permissionWhere $departmentWhere
                GROUP BY p.department_id, d.name, d.id
                ORDER BY count DESC";
            $departmentStats = $this->fetchAll($departmentQuery);
            $stats['by_department'] = $departmentStats;

            // 3. Dự án mới tạo trong tháng hiện tại
            $currentMonth = date('Y-m');
            $newThisMonthQuery = "SELECT COUNT(*) as count
                FROM {$this->table} p 
                WHERE DATE_FORMAT(p.created_at, '%Y-%m') = '$currentMonth'
                AND p.status != 'deleted' $permissionWhere $departmentWhere";
            $newThisMonth = $this->fetchOne($newThisMonthQuery);
            $stats['new_this_month'] = $newThisMonth['count'];

            // 4. Thống kê tài chính theo tháng cho từng phòng ban
            $financialQuery = "SELECT 
                d.name as department_name,
                d.id as department_id,
                DATE_FORMAT(p.created_at, '%Y-%m') as month,
                SUM(p.amount) as total_amount,
                SUM(CASE WHEN p.estimate_status = '未発行' THEN p.amount ELSE 0 END) as pending_estimates,
                SUM(CASE WHEN p.invoice_status = '未発行' THEN p.amount ELSE 0 END) as pending_invoices,
                COUNT(*) as project_count
                FROM {$this->table} p 
                LEFT JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id
                WHERE p.status != 'deleted' $permissionWhere $departmentWhere
                AND p.amount > 0
                GROUP BY p.department_id, d.name, d.id, DATE_FORMAT(p.created_at, '%Y-%m')
                ORDER BY d.name, month DESC";
            $financial = $this->fetchAll($financialQuery);
            
            $stats['financial_by_department'] = $financial;

            // 5. Tổng thống kê tài chính
            $totalFinancialQuery = "SELECT 
                SUM(p.amount) as total_amount,
                SUM(CASE WHEN p.estimate_status = '未発行' THEN p.amount ELSE 0 END) as pending_estimates,
                SUM(CASE WHEN p.invoice_status = '未発行' THEN p.amount ELSE 0 END) as pending_invoices
                FROM {$this->table} p 
                WHERE p.status != 'deleted' $permissionWhere $departmentWhere";
            $totalFinancial = $this->fetchOne($totalFinancialQuery);
            $stats['total_financial'] = $totalFinancial;

            // 6. Thống kê theo tháng (lấy dữ liệu thực tế có sẵn)
            $monthlyStatsQuery = "SELECT 
                DATE_FORMAT(p.created_at, '%Y-%m') as month,
                COUNT(*) as total_projects,
                SUM(CASE WHEN p.status NOT IN ('completed', 'cancelled', 'deleted', 'paused') THEN 1 ELSE 0 END) as active_projects,
                SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) as completed_projects,
                SUM(p.amount) as total_amount
                FROM {$this->table} p 
                WHERE p.status != 'deleted' $permissionWhere $departmentWhere
                GROUP BY DATE_FORMAT(p.created_at, '%Y-%m')
                ORDER BY month DESC
                LIMIT 12";
            $monthlyStats = $this->fetchAll($monthlyStatsQuery);
            
            // Đảo ngược thứ tự để hiển thị từ cũ đến mới
            $monthlyStats = array_reverse($monthlyStats);
            
            $stats['monthly_stats'] = $monthlyStats;

            // 7. Danh sách các tháng có dữ liệu
            $monthsQuery = "SELECT DISTINCT 
                DATE_FORMAT(p.created_at, '%Y-%m') as month,
                DATE_FORMAT(p.created_at, '%Y年%m月') as month_label
                FROM {$this->table} p 
                WHERE p.status != 'deleted' $permissionWhere $departmentWhere
                ORDER BY month DESC
                LIMIT 24";
            $availableMonths = $this->fetchAll($monthsQuery);
            $stats['available_months'] = $availableMonths;

            return $stats;
        } catch (Exception $e) {
            error_log('Error in getDashboardStats: ' . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'overview' => ['total' => 0, 'active' => 0, 'completed' => 0, 'inactive' => 0],
                'by_department' => [],
                'new_this_month' => 0,
                'financial_by_department' => [],
                'total_financial' => ['total_amount' => 0, 'pending_estimates' => 0, 'pending_invoices' => 0],
                'monthly_stats' => [],
                'available_months' => []
            ];
        }
    }
}

?>