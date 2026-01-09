<?php

class ParentProject extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'parent_projects';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'company_name' => array('notnull'),
            'branch_name' => array(),
            'contact_name' => array(),
            'customer_id' => array(),
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
            'requests' => array(),
            'materials' => array(),
            'structural_office' => array(),
            'notes' => array(),
            'status' => array(),
            'department_id' => array(),
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
        
        // Validate and sanitize order_column to prevent SQL injection
        $allowed_columns = [
            'id', 'project_number', 'project_name', 'construction_number', 
            'company_name', 'request_date', 'created_at', 'updated_at',
            'status', 'child_project_count', 'created_by_name'
        ];
        $order_column = isset($_GET['order_column']) ? $_GET['order_column'] : 'created_at';
        if (!in_array($order_column, $allowed_columns)) {
            $order_column = 'created_at';
        }
        
        // Validate order_dir
        $order_dir = isset($_GET['order_dir']) ? strtoupper($_GET['order_dir']) : 'DESC';
        if (!in_array($order_dir, ['ASC', 'DESC'])) {
            $order_dir = 'DESC';
        }
        
        $whereArr = [];
        
        // Add permission check
        $user_id = $_SESSION['id'];
        // if ($_SESSION['authority'] != 'administrator') {
        //     $whereArr[] = sprintf("p.created_by = %d", $user_id);
        // }

        if (isset($_GET['status']) && $_GET['status'] != '') {
            $whereArr[] = sprintf("p.status = '%s'", $_GET['status']);
        } else {
            $whereArr[] = "p.status != 'deleted'";
        }

        // Search functionality
        if (!empty($search)) {
            $search = addslashes($search);
            $whereArr[] = "(p.company_name LIKE '%$search%' 
                OR p.branch_name LIKE '%$search%' 
                OR p.contact_name LIKE '%$search%' 
                OR p.construction_number LIKE '%$search%' 
                OR p.project_name LIKE '%$search%')";
        }

        $where = !empty($whereArr) ? "WHERE " . implode(" AND ", $whereArr) : "";
        
        // Get total count
        $countQuery = sprintf("SELECT COUNT(*) as total FROM %s p %s", $this->table, $where);
        $totalRecords = $this->fetchOne($countQuery)['total'];
        
        // Get filtered count
        $filteredRecords = $totalRecords;
        
        // Handle special columns that are computed (child_project_count, created_by_name)
        if ($order_column === 'child_project_count') {
            $orderBy = "ORDER BY child_project_count $order_dir";
        } elseif ($order_column === 'created_by_name') {
            $orderBy = "ORDER BY u.realname $order_dir";
        } else {
            // Regular column from parent_projects table
            $orderBy = "ORDER BY p.$order_column $order_dir";
        }
        
        // Check if filtering by favorites
        $user_id = $_SESSION['id'];
        $favoritesOnly = isset($_GET['favorites_only']) && $_GET['favorites_only'] == '1';
        
        if ($favoritesOnly) {
            $whereArr[] = sprintf(
                "EXISTS (SELECT 1 FROM " . DB_PREFIX . "parent_project_favorites f WHERE f.parent_project_id = p.id AND f.user_id = %d)",
                $user_id
            );
            $where = !empty($whereArr) ? "WHERE " . implode(" AND ", $whereArr) : "";
            // Recalculate counts with favorite filter
            $countQuery = sprintf("SELECT COUNT(*) as total FROM %s p %s", $this->table, $where);
            $totalRecords = $this->fetchOne($countQuery)['total'];
            $filteredRecords = $totalRecords;
        }
        
        // Get data for current page
        $query = sprintf(
            "SELECT p.*, 
                    (SELECT COUNT(*) FROM " . DB_PREFIX . "projects WHERE parent_project_id = p.id) as child_project_count,
                    u.realname as created_by_name,
                    CASE WHEN EXISTS (SELECT 1 FROM " . DB_PREFIX . "parent_project_favorites f WHERE f.parent_project_id = p.id AND f.user_id = %d) THEN 1 ELSE 0 END as is_favorite
             FROM %s p 
             LEFT JOIN " . DB_PREFIX . "user u ON p.created_by = u.userid
             %s %s LIMIT %d, %d",
            $user_id,
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
        $requests = isset($_POST['requests']) ? $this->validateUTF8MB4($_POST['requests']) : '';
        $materials = isset($_POST['materials']) ? $this->validateUTF8MB4($_POST['materials']) : '';
        $notes = isset($_POST['notes']) ? $this->validateUTF8MB4($_POST['notes']) : '';
        
        $data = array(
            'company_name' => $company_name,
            'branch_name' => isset($_POST['branch_name']) ? $_POST['branch_name'] : '',
            'contact_name' => isset($_POST['contact_name']) ? $_POST['contact_name'] : '',
            'customer_id' => isset($_POST['customer_id']) ? intval($_POST['customer_id']) : null,
            'guis_receiver' => isset($_POST['guis_receiver']) ? $_POST['guis_receiver'] : '',
            'request_date' => isset($_POST['request_date']) ? $_POST['request_date'] : null,
            'construction_number' => isset($_POST['construction_number']) ? $_POST['construction_number'] : '',
            'project_name' => $project_name,
            'scale' => isset($_POST['scale']) ? $_POST['scale'] : '',
            'type1' => isset($_POST['type1']) ? $_POST['type1'] : '',
            'type2' => isset($_POST['type2']) ? $_POST['type2'] : '',
            'type3' => isset($_POST['type3']) ? $_POST['type3'] : '',
            'request_type' => isset($_POST['request_type']) ? $_POST['request_type'] : '',
            //'desired_delivery_date' => isset($_POST['desired_delivery_date']) ? $_POST['desired_delivery_date'] : null,
            'requests' => $requests,
            'materials' => $materials,
            'structural_office' => isset($_POST['structural_office']) ? $_POST['structural_office'] : '',
            'notes' => $notes,
            'status' => isset($_POST['status']) ? $_POST['status'] : 'draft',
            'project_number' => isset($_POST['project_number']) ? $_POST['project_number'] : '',
            'construction_branch' => isset($_POST['construction_branch']) ? $_POST['construction_branch'] : '',
         //   'department_id' => isset($_POST['department_id']) ? intval($_POST['department_id']) : null,
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

        // if (empty($data['desired_delivery_date'])) {
        //     return [
        //         'status' => 'error',
        //         'message' => '希望納期は必須です'
        //     ];
        // }

                // Insert parent project data
        $parent_project_id = $this->query_insert($data);
        
        if (!$parent_project_id) {
            return [
                'status' => 'error',
                'message' => '建物の作成に失敗しました'
            ];
        }
        
        // Log the creation
        $this->logParentProjectAction($parent_project_id, 'created', '建物作成', '', '');
        
        // Send notification to department managers if department is specified
        // $department_id = isset($_POST['department_id']) ? intval($_POST['department_id']) : 0;
        // if ($department_id > 0) {
        //     $this->notifyParentProjectCreated($parent_project_id, $project_name, $department_id);
        // }

        return [
            'status' => 'success',
            'parent_project_id' => $parent_project_id,
            'message' => '建物を作成しました'
        ];
    }

    function update($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'error' => '建物IDが指定されていません'];
        
        // Validate and sanitize input to ensure UTF-8 MB4 compatibility
        $company_name = isset($_POST['company_name']) ? $this->validateUTF8MB4($_POST['company_name']) : '';
        $project_name = isset($_POST['project_name']) ? $this->validateUTF8MB4($_POST['project_name']) : '';
        $requests = isset($_POST['requests']) ? $this->validateUTF8MB4($_POST['requests']) : '';
        $materials = isset($_POST['materials']) ? $this->validateUTF8MB4($_POST['materials']) : '';
        $notes = isset($_POST['notes']) ? $this->validateUTF8MB4($_POST['notes']) : '';
        
        $data = array(
            'company_name' => $company_name,
            'branch_name' => isset($_POST['branch_name']) ? $_POST['branch_name'] : '',
            'contact_name' => isset($_POST['contact_name']) ? $_POST['contact_name'] : '',
            'customer_id' => isset($_POST['customer_id']) ? intval($_POST['customer_id']) : null,
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
            'requests' => $requests,
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
                // Log the update action
                $this->logParentProjectAction($id, 'updated', '建物情報を変更');
                return ['status' => 'success', 'message' => '建物を更新しました'];
            } else {
                return ['status' => 'error', 'error' => '更新に失敗しました'];
            }
        } catch (Exception $e) {
            error_log('Parent project update error: ' . $e->getMessage());
            return ['status' => 'error', 'error' => 'データベースエラー: ' . $e->getMessage()];
        }
    }

    function updateStatus($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'error' => '建物IDが指定されていません'];
        
        $status = isset($_POST['status']) ? $_POST['status'] : '';
        if (empty($status)) return ['status' => 'error', 'error' => 'ステータスが指定されていません'];
        
        // Get current status for logging
        $currentProject = $this->getById($id);
        $oldStatus = $currentProject ? $currentProject['status'] : '';
        
        // Validate status value (you can add more validation if needed)
        $validStatuses = ['draft', 'under_contract', 'in_progress', 'completed', 'cancelled'];
        if (!in_array($status, $validStatuses)) {
            return ['status' => 'error', 'error' => '無効なステータス値です'];
        }
        
        $data = array(
            'status' => $status,
            'updated_by' => $_SESSION['userid'],
            'updated_at' => date('Y-m-d H:i:s')
        );

        try {
            $result = $this->query_update($data, ['id' => $id]);
            
            if ($result) {
                // Log the status change
                $this->logParentProjectAction($id, 'status_changed', 'ステータス変更', $oldStatus, $status);
                return ['status' => 'success', 'message' => 'ステータスを更新しました'];
            } else {
                return ['status' => 'error', 'error' => 'ステータスの更新に失敗しました'];
            }
        } catch (Exception $e) {
            error_log('Parent project status update error: ' . $e->getMessage());
            return ['status' => 'error', 'error' => 'データベースエラー: ' . $e->getMessage()];
        }
    }

    function delete($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'error' => '建物IDが指定されていません'];

        // Check if there are child projects
        $childProjectsQuery = sprintf(
            "SELECT COUNT(*) as count FROM " . DB_PREFIX . "projects WHERE parent_project_id = %d",
            $id
        );
        $childCount = $this->fetchOne($childProjectsQuery)['count'];
        
        if ($childCount > 0) {
            return [
                'status' => 'error', 
                'error' => 'この建物には案件が存在するため削除できません。先に案件を削除してください。'
            ];
        }

        $result = $this->query_delete(['id' => $id]);
        
        if ($result) {
            // Log the deletion
            $this->logParentProjectAction($id, 'deleted', '建物を削除', $currentProject['status'] ?? '', 'deleted');
            return ['status' => 'success', 'message' => '建物を削除しました'];
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
        
        $user_id = $_SESSION['id'];
        
        $query = sprintf(
            "SELECT p.*, 
                    CASE WHEN EXISTS (SELECT 1 FROM " . DB_PREFIX . "parent_project_favorites f WHERE f.parent_project_id = p.id AND f.user_id = %d) THEN 1 ELSE 0 END as is_favorite
             FROM %s p 
             WHERE p.id = %d",
            $user_id,
            $this->table,
            intval($id)
        );
        return $this->fetchOne($query);
    }

    function getChildProjects($params = null) {
        // Handle both direct parent_project_id parameter and params array from API
        if (is_array($params)) {
            $parent_project_id = isset($params['parent_project_id']) ? $params['parent_project_id'] : 0;
        } else {
            $parent_project_id = $params;
        }
        
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

    // Attachment management methods
    function getByParentProject($params = null) {
        $parent_project_id = isset($_GET['parent_project_id']) ? intval($_GET['parent_project_id']) : 0;
        $folder_id = isset($_GET['folder_id']) ? intval($_GET['folder_id']) : null;
        
        if (!$parent_project_id) {
            return ['success' => false, 'message' => 'Parent Project ID is required'];
        }
        
        // Get folders
        $folderQuery = sprintf(
            "SELECT f.*, u.realname as created_by_name,
                    (SELECT COUNT(*) FROM " . DB_PREFIX . "project_attachments a WHERE a.folder_id = f.id AND a.parent_project_id = %d) as file_count
             FROM " . DB_PREFIX . "project_folders f
             LEFT JOIN " . DB_PREFIX . "user u ON f.created_by = u.userid
             WHERE f.parent_project_id = %d AND %s
             ORDER BY f.name ASC",
            $parent_project_id,
            $parent_project_id,
            $folder_id ? "f.parent_folder_id = $folder_id" : "f.parent_folder_id IS NULL"
        );
        $folders = $this->fetchAll($folderQuery);
        
        // Get files
        $fileQuery = sprintf(
            "SELECT a.*, u.realname as uploaded_by_name
             FROM " . DB_PREFIX . "project_attachments a
             LEFT JOIN " . DB_PREFIX . "user u ON a.uploaded_by = u.userid
             WHERE a.parent_project_id = %d AND %s
             ORDER BY a.uploaded_at DESC",
            $parent_project_id,
            $folder_id ? "a.folder_id = $folder_id" : "a.folder_id IS NULL"
        );
        $files = $this->fetchAll($fileQuery);
        
        // Get breadcrumbs
        $breadcrumbs = [];
        if ($folder_id) {
            $breadcrumbs = $this->getAttachmentBreadcrumbs($folder_id);
        }
        
        return [
            'success' => true,
            'folders' => $folders ?: [],
            'files' => $files ?: [],
            'breadcrumbs' => $breadcrumbs
        ];
    }
    
    private function getAttachmentBreadcrumbs($folder_id) {
        $breadcrumbs = [];
        $current_folder_id = $folder_id;
        
        while ($current_folder_id) {
            $query = sprintf(
                "SELECT id, name, parent_folder_id FROM " . DB_PREFIX . "project_folders WHERE id = %d",
                $current_folder_id
            );
            $folder = $this->fetchOne($query);
            
            if ($folder) {
                array_unshift($breadcrumbs, [
                    'id' => $folder['id'],
                    'name' => $folder['name']
                ]);
                $current_folder_id = $folder['parent_folder_id'];
            } else {
                break;
            }
        }
        
        return $breadcrumbs;
    }

    function createFolder($params = null) {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $parent_project_id = isset($_POST['parent_project_id']) ? intval($_POST['parent_project_id']) : 0;
        $parent_folder_id = isset($_POST['parent_folder_id']) && $_POST['parent_folder_id'] !== '' ? intval($_POST['parent_folder_id']) : null;
        
        if (!$name) {
            return ['success' => false, 'message' => 'Folder name is required'];
        }
        
        if (!$parent_project_id) {
            return ['success' => false, 'message' => 'Parent Project ID is required'];
        }
        
        // Check if folder with same name exists in the same location
        $existingQuery = sprintf(
            "SELECT id FROM " . DB_PREFIX . "project_folders 
             WHERE parent_project_id = %d AND name = '%s' AND %s",
            $parent_project_id,
            $this->quote($name),
            $parent_folder_id ? "parent_folder_id = $parent_folder_id" : "parent_folder_id IS NULL"
        );
        $existing = $this->fetchOne($existingQuery);
        
        if ($existing) {
            return ['success' => false, 'message' => 'Folder with this name already exists'];
        }
        
        $data = [
            'name' => $name,
            'parent_project_id' => $parent_project_id,
            'created_by' => $_SESSION['userid'] ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($parent_folder_id !== null) {
            $data['parent_folder_id'] = $parent_folder_id;
        }
        
        $this->table = DB_PREFIX . 'project_folders';
        $result = $this->query_insert($data);

        $this->table = DB_PREFIX . 'parent_projects'; // reset table
        
        if ($result) {
            return ['success' => true, 'message' => 'Folder created successfully', 'folder_id' => $result];
        } else {
            return ['success' => false, 'message' => 'Failed to create folder'];
        }
    }

    function updateFolder($params = null) {
        $folder_id = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : 0;
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        
        if (!$folder_id || !$name) {
            return ['success' => false, 'message' => 'Folder ID and name are required'];
        }
        
        $this->table = DB_PREFIX . 'project_folders';
        $result = $this->query_update(['name' => $name, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $folder_id]);
        $this->table = DB_PREFIX . 'parent_projects'; // reset table
        
        if ($result) {
            return ['success' => true, 'message' => 'Folder updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to update folder'];
        }
    }

    function deleteFolder($params = null) {
        $folder_id = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : 0;
        
        if (!$folder_id) {
            return ['success' => false, 'message' => 'Folder ID is required'];
        }
        
        // Delete all files in the folder first
        $filesQuery = sprintf(
            "SELECT id, file_path FROM " . DB_PREFIX . "project_attachments WHERE folder_id = %d",
            $folder_id
        );
        $files = $this->fetchAll($filesQuery);
        
        foreach ($files as $file) {
            $this->deleteFileById($file['id']);
        }
        
        // Delete subfolders recursively
        $subfoldersQuery = sprintf(
            "SELECT id FROM " . DB_PREFIX . "project_folders WHERE parent_folder_id = %d",
            $folder_id
        );
        $subfolders = $this->fetchAll($subfoldersQuery);
        
        foreach ($subfolders as $subfolder) {
            $this->deleteFolder(['folder_id' => $subfolder['id']]);
        }
        
        // Delete the folder itself
        $this->table = DB_PREFIX . 'project_folders';
        $result = $this->query_delete(['id' => $folder_id]);
        $this->table = DB_PREFIX . 'parent_projects'; // reset table
        
        if ($result) {
            return ['success' => true, 'message' => 'Folder deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete folder'];
        }
    }

    function uploadAttachment($params = null) {
        $parent_project_id = isset($_POST['parent_project_id']) ? intval($_POST['parent_project_id']) : 0;
        $folder_id = isset($_POST['folder_id']) && $_POST['folder_id'] !== '' ? intval($_POST['folder_id']) : null;
        
        if (!$parent_project_id) {
            return ['success' => false, 'error' => 'Parent Project ID is required'];
        }
        
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'No file uploaded or upload error'];
        }
        
        $file = $_FILES['image'];
        $originalName = $file['name'];
        $fileSize = $file['size'];
        $tmpName = $file['tmp_name'];
        
        // Validate file size (100MB max)
        if ($fileSize > 100 * 1024 * 1024) {
            return ['success' => false, 'error' => 'File size exceeds 100MB limit'];
        }
        
        // Create upload directory
        $uploadDir = "../assets/upload/parent-project-attachments/$parent_project_id/";
        if ($folder_id) {
            $uploadDir .= "folder-$folder_id/";
        }
        
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return ['success' => false, 'error' => 'Failed to create upload directory'];
            }
        }
        
        // Check if file with same original name already exists
        $existingFileQuery = sprintf(
            "SELECT id, file_path FROM " . DB_PREFIX . "project_attachments 
             WHERE parent_project_id = %d AND original_name = '%s' AND %s",
            $parent_project_id,
            $this->quote($originalName),
            $folder_id ? "folder_id = $folder_id" : "folder_id IS NULL"
        );
        $existingFile = $this->fetchOne($existingFileQuery);
        
        // Use original filename (replace if exists)
        $filename = $originalName;
        $filePath = $uploadDir . $filename;
        
        // If file exists, delete the old physical file first
        if ($existingFile) {
            $oldFilePath = str_replace(ROOT, '../', $existingFile['file_path']);
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }
        }
        
        // Move uploaded file
        if (!move_uploaded_file($tmpName, $filePath)) {
            return ['success' => false, 'error' => 'Failed to move uploaded file'];
        }

        // Create URL path for database storage
        $uploadDir2 = "assets/upload/parent-project-attachments/$parent_project_id/";
        if ($folder_id) {
            $uploadDir2 .= "folder-$folder_id/";
        }
        $urlFile = ROOT . $uploadDir2 . $filename;
        
        // Prepare data for database
        $data = [
            'parent_project_id' => $parent_project_id,
            'original_name' => $originalName,
            'file_name' => $filename,
            'file_path' => $urlFile,
            'file_size' => $fileSize,
            'mime_type' => $file['type'],
            'uploaded_by' => $_SESSION['userid'] ?? 0,
            'uploaded_at' => date('Y-m-d H:i:s')
        ];
        
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
        
        $this->table = DB_PREFIX . 'parent_projects'; // reset table
        
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

    function deleteFile($params = null) {
        $file_id = isset($_POST['file_id']) ? intval($_POST['file_id']) : 0;
        
        if (!$file_id) {
            return ['success' => false, 'message' => 'File ID is required'];
        }
        
        return $this->deleteFileById($file_id);
    }

    function deleteFiles($params = null) {
        $file_ids = isset($_POST['file_ids']) ? $_POST['file_ids'] : [];
        
        $file_ids = explode(',', $file_ids);
        if (empty($file_ids)) {
            return ['success' => false, 'message' => 'File IDs are required'];
        }
        
        
        $deletedCount = 0;
        foreach ($file_ids as $file_id) {
            $result = $this->deleteFileById(intval($file_id));
            if ($result['success']) {
                $deletedCount++;
            }
        }
        
        if ($deletedCount > 0) {
            return ['success' => true, 'message' => "$deletedCount files deleted successfully"];
        } else {
            return ['success' => false, 'message' => 'No files were deleted'];
        }
    }

    private function deleteFileById($file_id) {
        // Get file info
        $fileQuery = sprintf(
            "SELECT file_path FROM " . DB_PREFIX . "project_attachments WHERE id = %d",
            $file_id
        );
        $file = $this->fetchOne($fileQuery);
        
        if (!$file) {
            return ['success' => false, 'message' => 'File not found'];
        }
        
        // Delete file from filesystem
        $filePath = str_replace(ROOT, '../', $file['file_path']);
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Delete from database
        $this->table = DB_PREFIX . 'project_attachments';
        $result = $this->query_delete(['id' => $file_id]);
        $this->table = DB_PREFIX . 'parent_projects'; // reset table
        
        if ($result) {
            return ['success' => true, 'message' => 'File deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete file'];
        }
    }

    function viewFile($params = null) {
        $this->serveFile(false); // false = inline view
    }

    function downloadFile($params = null) {
        $this->serveFile(true); // true = force download
    }

    private function serveFile($forceDownload = true) {
        $file_id = isset($_GET['file_id']) ? intval($_GET['file_id']) : 0;
        
        if (!$file_id) {
            http_response_code(400);
            die('File ID is required');
        }
        
        // Get file info
        $fileQuery = sprintf(
            "SELECT * FROM " . DB_PREFIX . "project_attachments WHERE id = %d",
            $file_id
        );
        $file = $this->fetchOne($fileQuery);
        
        if (!$file) {
            http_response_code(404);
            die('File not found');
        }
        
        // Convert URL path to filesystem path
        // $filePath = str_replace(ROOT, '../', $file['file_path']);
        $filePath = '..' . $file['file_path'];
        
        if (!file_exists($filePath)) {
            http_response_code(404);
            die('File not found on disk');
        }
        
        $fileName = $file['original_name'];
        $fileSize = filesize($filePath);
        $mimeType = $file['mime_type'] ?: 'application/octet-stream';
        
        // Set headers
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $fileSize);
        
        if ($forceDownload) {
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
        } else {
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

    /**
     * Send notification when parent project is created
     * @param int $parentProjectId Parent project ID
     * @param string $projectName Project name
     * @param int $departmentId Department ID
     * @return bool Success status
     */
    function notifyParentProjectCreated($parentProjectId, $projectName, $departmentId) {
        try {
            // Get department managers
            $managerIds = $this->getDepartmentManagers($departmentId);
            error_log('managerIds: ' . print_r($managerIds, true));
            
            if (empty($managerIds)) {
                return false; // No managers found
            }

            require_once('NotificationService.php');
            $notiService = new NotificationService();
            
            $params = [
                'event' => 'parent_project_created',
                'title' => '新しい建物が作成されました',
                'message' => sprintf('%sが建物「%s」を作成しました', $this->getUserRealname(), $projectName),
                'data' => [
                    'parent_project_id' => $parentProjectId,
                    'project_name' => $projectName,
                    'department_id' => $departmentId,
                    'action' => 'created',
                    'avatar' => $this->getUserImage(),
                    'url' => "/parent_project/detail.php?id=$parentProjectId",
                    'sender_id' => $_SESSION['userid'],
                    'sender_name' => $_SESSION['realname'] ?? 'Unknown',
                    'timestamp' => date('Y-m-d H:i:s')
                ],
                'user_ids' => $managerIds
            ];
            
            $result = $notiService->create($params);
            
            // Return true if notification was sent successfully
            return is_array($result) && isset($result['status']) && $result['status'] === 'success';
            
        } catch (Exception $e) {
            error_log('Failed to send parent project creation notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get department managers user IDs
     * @param int $departmentId Department ID
     * @return array Array of user IDs
     */
    private function getDepartmentManagers($departmentId) {
        try {
            $query = sprintf(
                "SELECT ud.userid 
                 FROM " . DB_PREFIX . "user_department ud
                 LEFT JOIN " . DB_PREFIX . "user u ON ud.userid = u.userid
                 WHERE ud.department_id = %d 
                 AND ud.project_manager = 1
                 AND (u.is_suspend = 0 OR u.is_suspend IS NULL OR u.is_suspend = '')",
                intval($departmentId)
            );
            
            $managers = $this->fetchAll($query);
            
            if (!$managers) {
                return [];
            }
            
            return array_column($managers, 'userid');
            
        } catch (Exception $e) {
            error_log('Error getting department managers: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get current user's image
     * @return string User image path
     */
    private function getUserImage() {
        if (isset($_SESSION['user_image']) && $_SESSION['user_image'] != '') {
            return '/assets/upload/avatar/' . $_SESSION['user_image'];
        }
        return '/assets/img/avatars/1.png';
    }

    /**
     * Get current user's real name with honorific
     * @return string User real name with さん
     */
    private function getUserRealname() {
        if (isset($_SESSION['lastname']) && $_SESSION['lastname'] != '') {
            return $_SESSION['lastname'] . 'さん';
        }
        return $_SESSION['realname'] . 'さん';
    }

    /**
     * Get logs for a parent project
     */
    function getLogs($params = null) {
        $parent_project_id = isset($_GET['parent_project_id']) ? intval($_GET['parent_project_id']) : 0;
        if (!$parent_project_id) return [];

        $query = sprintf(
            "SELECT l.*, u.realname, u.user_image FROM " . DB_PREFIX . "parent_projects_logs l
            LEFT JOIN " . DB_PREFIX . "user u ON l.user_id = u.userid
            WHERE l.parent_project_id = %d ORDER BY l.time DESC",
            $parent_project_id
        );
        $logs = $this->fetchAll($query);
        return $logs;
    }

    /**
     * Toggle favorite status for a parent project
     */
    function toggleFavorite($params = null) {
        $parent_project_id = isset($_POST['parent_project_id']) ? intval($_POST['parent_project_id']) : 0;
        $user_id = $_SESSION['id'];
        
        if (!$parent_project_id || !$user_id) {
            return array(
                'status' => 'error',
                'message' => 'Invalid parameters'
            );
        }
        
        // Check if favorite exists
        $checkQuery = sprintf(
            "SELECT id FROM " . DB_PREFIX . "parent_project_favorites 
             WHERE parent_project_id = %d AND user_id = %d",
            $parent_project_id,
            $user_id
        );
        $existing = $this->fetchOne($checkQuery);
        
        if ($existing) {
            // Remove favorite
            $deleteQuery = sprintf(
                "DELETE FROM " . DB_PREFIX . "parent_project_favorites 
                 WHERE parent_project_id = %d AND user_id = %d",
                $parent_project_id,
                $user_id
            );
            $this->query($deleteQuery);
            return array(
                'status' => 'success',
                'is_favorite' => false,
                'message' => 'お気に入りから削除しました'
            );
        } else {
            // Add favorite
            $insertQuery = sprintf(
                "INSERT INTO " . DB_PREFIX . "parent_project_favorites (parent_project_id, user_id, created_at) 
                 VALUES (%d, %d, NOW())",
                $parent_project_id,
                $user_id
            );
            $this->query($insertQuery);
            return array(
                'status' => 'success',
                'is_favorite' => true,
                'message' => 'お気に入りに追加しました'
            );
        }
    }

    /**
     * Clear all favorites for current user
     */
    function clearAllFavorites($params = null) {
        $user_id = $_SESSION['id'];
        
        if (!$user_id) {
            return array(
                'status' => 'error',
                'message' => 'Invalid user'
            );
        }
        
        $deleteQuery = sprintf(
            "DELETE FROM " . DB_PREFIX . "parent_project_favorites 
             WHERE user_id = %d",
            $user_id
        );
        $this->query($deleteQuery);
        
        return array(
            'status' => 'success',
            'message' => 'すべてのお気に入りを削除しました'
        );
    }

    /**
     * Log parent project action
     */
    private function logParentProjectAction($parent_project_id, $action, $note = '', $value1 = '', $value2 = '') {
        $user_id = $_SESSION['userid'] ?? '';
        $username = $_SESSION['realname'] ?? '';
        $data = [
            'parent_project_id' => $parent_project_id,
            'user_id' => $user_id,
            'username' => $username,
            'action' => $action,
            'note' => $note,
            'value1' => $value1,
            'value2' => $value2,
            'time' => date('Y-m-d H:i:s')
        ];
        $this->table = DB_PREFIX . 'parent_projects_logs';
        $this->query_insert($data);
        $this->table = DB_PREFIX . 'parent_projects'; // reset table
    }
}
?> 