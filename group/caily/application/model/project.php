<?php

class Project extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'projects';
        // Add integer fields that should not be quoted
        $this->donotquote = array_merge($this->donotquote, array(
            'parent_folder_id', 'folder_id', 'project_id', 'user_id', 'file_size'
        ));
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
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'error' => 'Update failed'];
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
        //     $this->logProjectAction($project_id, 'member_added', 'メンバー追加', $role, $username);
        // }
        return $result;
    }

    function removeMember($project_id, $user_id) {
        $this->table = DB_PREFIX . 'project_members';
        $result = $this->query_delete(['project_id' => $project_id, 'user_id' => $user_id]);
        $this->table = DB_PREFIX . 'projects'; // Reset table back to projects
        // if ($result) {
        //     $this->logProjectAction($project_id, 'member_removed', 'メンバー削除', '', $user_id);
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
            $this->notifyProjectStatusChanged($project_number,$id, $name, $data['status'], array_column($projectMembers, 'userid'));
            $this->logProjectAction($id, 'status_changed', 'ステータス変更', $old['status'], $status);
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
            $this->logProjectAction($id, 'priority_updated', '優先度変更', $old['priority'], $priority);
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
            $this->logProjectAction($data['project_id'], 'comment', 'コメント追加', '', mb_substr(strip_tags($data['content']),0,30));
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
            $commenter = $this->fetchOne(sprintf("SELECT realname, user_image FROM " . DB_PREFIX . "user WHERE userid = %d", intval($commentUserId)));
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
                    'title' => '案件でメンションされました',
                    'message' => sprintf('%sさんが案件「%s」であなたをメンションしました', 
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

    /**
     * Gửi thông báo cho các hành động của dự án hoặc tác vụ
     * @param array $params Tham số thông báo
     * @return bool Kết quả gửi thông báo
     */
    function sendProjectNotification($params = null) {
        try {
            require_once(DIR_ROOT . '/application/model/NotificationService.php');
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
            $this->log('Error sending project notification: ' . $e->getMessage());
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
                'action' => 'comment_added'
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
}

?>