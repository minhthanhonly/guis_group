<?php

class ParentProject extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'parent_projects';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'company_name' => array('notnull'),
            'branch_name' => array(),
            'contact_name' => array(),
            'guis_receiver' => array(),
            'request_date' => array(),
            'construction_number' => array(),
            'project_number' => array(),
            'project_name' => array('notnull'),
            'construction_branch' => array(),
            'scale' => array(),
            'type1' => array(),
            'type2' => array(),
            'type3' => array(),
            'request_type' => array(),
            'desired_delivery_date' => array(),
            'materials' => array(),
            'structural_office' => array(),
            'notes' => array(),
            'status' => array(),
            'created_by' => array(),
            'updated_by' => array(),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search'))
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
        if ($_SESSION['authority'] != 'administrator') {
            $whereArr[] = sprintf("created_by = %d", $user_id);
        }

        if (isset($_GET['status']) && $_GET['status'] != 'all') {
            $whereArr[] = sprintf("status = '%s'", $_GET['status']);
        } else {
            $whereArr[] = "status != 'deleted'";
        }

        // Search functionality
        if (!empty($search)) {
            $search = $this->escape($search);
            $whereArr[] = "(company_name LIKE '%$search%' 
                OR branch_name LIKE '%$search%' 
                OR contact_name LIKE '%$search%' 
                OR construction_number LIKE '%$search%' 
                OR project_name LIKE '%$search%')";
        }

        $where = !empty($whereArr) ? "WHERE " . implode(" AND ", $whereArr) : "";
        
        // Get total count
        $countQuery = sprintf("SELECT COUNT(*) as total FROM %s %s", $this->table, $where);
        $totalRecords = $this->fetchOne($countQuery)['total'];
        
        // Get filtered count
        $filteredRecords = $totalRecords;
        
        // Order by
        $orderBy = "ORDER BY $order_column $order_dir";
        
        // Get data for current page
        $query = sprintf(
            "SELECT * FROM %s %s %s LIMIT %d, %d",
            $this->table,
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

    function create($params = null) {
        // Validate and sanitize input to ensure UTF-8 MB4 compatibility
        $company_name = isset($_POST['company_name']) ? $this->validateUTF8MB4($_POST['company_name']) : '';
        $project_name = isset($_POST['project_name']) ? $this->validateUTF8MB4($_POST['project_name']) : '';
        $materials = isset($_POST['materials']) ? $this->validateUTF8MB4($_POST['materials']) : '';
        $notes = isset($_POST['notes']) ? $this->validateUTF8MB4($_POST['notes']) : '';
        
        $data = array(
            'company_name' => $company_name,
            'branch_name' => isset($_POST['branch_name']) ? $_POST['branch_name'] : '',
            'contact_name' => isset($_POST['contact_name']) ? $_POST['contact_name'] : '',
            'guis_receiver' => isset($_POST['guis_receiver']) ? $_POST['guis_receiver'] : '',
            'request_date' => isset($_POST['request_date']) ? $_POST['request_date'] : null,
            'construction_number' => isset($_POST['construction_number']) ? $_POST['construction_number'] : '',
            'project_name' => $project_name,
            'scale' => isset($_POST['scale']) ? $_POST['scale'] : '',
            'type1' => isset($_POST['type1']) ? $_POST['type1'] : '',
            'type2' => isset($_POST['type2']) ? $_POST['type2'] : '',
            'type3' => isset($_POST['type3']) ? $_POST['type3'] : '',
            'request_type' => isset($_POST['request_type']) ? $_POST['request_type'] : '',
            'desired_delivery_date' => isset($_POST['desired_delivery_date']) ? $_POST['desired_delivery_date'] : null,
            'materials' => $materials,
            'structural_office' => isset($_POST['structural_office']) ? $_POST['structural_office'] : '',
            'notes' => $notes,
            'status' => isset($_POST['status']) ? $_POST['status'] : 'draft',
            'project_number' => isset($_POST['project_number']) ? $_POST['project_number'] : '',
            'construction_branch' => isset($_POST['construction_branch']) ? $_POST['construction_branch'] : '',
            'created_by' => $_SESSION['userid'],
            'created_at' => date('Y-m-d H:i:s')
        );

        // Validate required fields
        if (empty($data['company_name'])) {
            return [
                'status' => 'error',
                'message' => '会社名は必須です'
            ];
        }

        if (empty($data['project_name'])) {
            return [
                'status' => 'error',
                'message' => '案件名は必須です'
            ];
        }

        // Insert parent project data
        $parent_project_id = $this->query_insert($data);
        
        if (!$parent_project_id) {
            return [
                'status' => 'error',
                'message' => '親プロジェクトの作成に失敗しました'
            ];
        }

        return [
            'status' => 'success',
            'parent_project_id' => $parent_project_id,
            'message' => '親プロジェクトを作成しました'
        ];
    }

    function update($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'error' => '親プロジェクトIDが指定されていません'];
        
        // Validate and sanitize input to ensure UTF-8 MB4 compatibility
        $company_name = isset($_POST['company_name']) ? $this->validateUTF8MB4($_POST['company_name']) : '';
        $project_name = isset($_POST['project_name']) ? $this->validateUTF8MB4($_POST['project_name']) : '';
        $materials = isset($_POST['materials']) ? $this->validateUTF8MB4($_POST['materials']) : '';
        $notes = isset($_POST['notes']) ? $this->validateUTF8MB4($_POST['notes']) : '';
        
        $data = array(
            'company_name' => $company_name,
            'branch_name' => isset($_POST['branch_name']) ? $_POST['branch_name'] : '',
            'contact_name' => isset($_POST['contact_name']) ? $_POST['contact_name'] : '',
            'guis_receiver' => isset($_POST['guis_receiver']) ? $_POST['guis_receiver'] : '',
            'request_date' => isset($_POST['request_date']) ? $_POST['request_date'] : null,
            'construction_number' => isset($_POST['construction_number']) ? $_POST['construction_number'] : '',
            'project_name' => $project_name,
            'scale' => isset($_POST['scale']) ? $_POST['scale'] : '',
            'type1' => isset($_POST['type1']) ? $_POST['type1'] : '',
            'type2' => isset($_POST['type2']) ? $_POST['type2'] : '',
            'type3' => isset($_POST['type3']) ? $_POST['type3'] : '',
            'request_type' => isset($_POST['request_type']) ? $_POST['request_type'] : '',
            'desired_delivery_date' => isset($_POST['desired_delivery_date']) ? $_POST['desired_delivery_date'] : null,
            'materials' => $materials,
            'structural_office' => isset($_POST['structural_office']) ? $_POST['structural_office'] : '',
            'project_number' => isset($_POST['project_number']) ? $_POST['project_number'] : '',
            'construction_branch' => isset($_POST['construction_branch']) ? $_POST['construction_branch'] : '',
            'notes' => $notes,
            'status' => isset($_POST['status']) ? $_POST['status'] : 'draft',
            'updated_by' => $_SESSION['userid'],
            'updated_at' => date('Y-m-d H:i:s')
        );

        try {
            $result = $this->query_update($data, ['id' => $id]);
            
            if ($result) {
                return ['status' => 'success', 'message' => '親プロジェクトを更新しました'];
            } else {
                return ['status' => 'error', 'error' => '更新に失敗しました'];
            }
        } catch (Exception $e) {
            error_log('Parent project update error: ' . $e->getMessage());
            return ['status' => 'error', 'error' => 'データベースエラー: ' . $e->getMessage()];
        }
    }

    function delete($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'error' => '親プロジェクトIDが指定されていません'];

        // Check if there are child projects
        $childProjectsQuery = sprintf(
            "SELECT COUNT(*) as count FROM " . DB_PREFIX . "projects WHERE parent_project_id = %d",
            $id
        );
        $childCount = $this->fetchOne($childProjectsQuery)['count'];
        
        if ($childCount > 0) {
            return [
                'status' => 'error', 
                'error' => 'この親プロジェクトには子プロジェクトが存在するため削除できません。先に子プロジェクトを削除してください。'
            ];
        }

        $result = $this->query_delete(['id' => $id]);
        
        if ($result) {
            return ['status' => 'success', 'message' => '親プロジェクトを削除しました'];
        } else {
            return ['status' => 'error', 'error' => '削除に失敗しました'];
        }
    }

    function getById($params = null) {
        // Handle both direct ID parameter and params array from API
        if (is_array($params)) {
            $id = isset($params['id']) ? $params['id'] : 0;
        } else {
            $id = $params;
        }
        
        $query = sprintf(
            "SELECT * FROM %s WHERE id = %d",
            $this->table,
            intval($id)
        );
        return $this->fetchOne($query);
    }

    function getChildProjects($parent_project_id) {
        $query = sprintf(
            "SELECT p.*, d.name as department_name,
            c.name as contact_name, c.company_name, c.department as branch_name
            FROM " . DB_PREFIX . "projects p 
            LEFT JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id
            LEFT JOIN " . DB_PREFIX . "customer c ON c.id = SUBSTRING_INDEX(p.customer_id, ',', 1)
            WHERE p.parent_project_id = %d
            ORDER BY p.created_at DESC",
            intval($parent_project_id)
        );
        return $this->fetchAll($query);
    }

    /**
     * Generate a unique project number for parent projects
     */
    function generateProjectNumber() {
        // Get the latest 50 project numbers to analyze the pattern
        $query = "SELECT project_number FROM " . $this->table . " ORDER BY id DESC LIMIT 50";
        $result = $this->fetchAll($query);

        $prefix = 'P'; // Default prefix for parent projects
        // if (!empty($result)) {
        //     // Extract prefix from the first project number (non-numeric characters at the beginning)
        //     if (preg_match('/^([^0-9]*)/', $result[0]['project_number'], $matches)) {
        //         $prefix = $matches[1];
        //     }
        // }

        $maxNumber = 0;
        foreach ($result as $row) {
            // Find the number at the end of project_number
            if (preg_match('/(\d+)\s*$/', $row['project_number'], $matches)) {
                $num = intval($matches[1]);
                if ($num > $maxNumber) {
                    $maxNumber = $num;
                }
            }
        }
        
        $nextNumber = $maxNumber + 1;
        // Format the number, e.g., PP-001 or just 001 if no prefix
        $project_number = $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
        
        return [
            'status' => 'success',
            'project_number' => $project_number
        ];
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
}
?> 