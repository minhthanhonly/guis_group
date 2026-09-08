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
            'construction_city' => array(),
            'structure_type' => array(),
            'spec_features' => array(),
            'scale' => array(),
            'type1' => array(),
            'type2' => array(),
            'type3' => array(),
            'request_type' => array(),
            //'desired_delivery_date' => array(),
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
        $this->ensureParentProjectsUtf8mb4();
        $this->ensureSpecFeatureColumns();
    }

    private function ensureSpecFeatureColumns() {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            foreach (array(
                'construction_city' => "ADD COLUMN `construction_city` varchar(255) DEFAULT NULL COMMENT '建築地（市区町村）'",
                'structure_type' => "ADD COLUMN `structure_type` varchar(20) DEFAULT NULL COMMENT 'W|S_RC'",
                'spec_features' => "ADD COLUMN `spec_features` varchar(500) DEFAULT NULL COMMENT 'CSV feature keys'",
            ) as $col => $ddl) {
                $exists = $this->fetchOne("SHOW COLUMNS FROM `{$this->table}` LIKE '{$col}'");
                if (!$exists) {
                    $this->query("ALTER TABLE `{$this->table}` {$ddl}");
                }
            }
        } catch (Exception $e) {
            error_log('ParentProject ensureSpecFeatureColumns: ' . $e->getMessage());
        }
    }

    /**
     * Ensure parent_projects text columns accept 4-byte UTF-8 (rare kanji / emoji).
     * Fixes: Incorrect string value ... for column 'project_name'
     */
    private function ensureParentProjectsUtf8mb4() {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $row = $this->fetchOne(sprintf(
                "SELECT CHARACTER_SET_NAME AS cs, COLLATION_NAME AS cl
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = '%s'
                   AND COLUMN_NAME = 'project_name'
                 LIMIT 1",
                $this->quote($this->table)
            ));
            if (!$row || empty($row['cs'])) {
                return;
            }
            $cs = strtolower((string)$row['cs']);
            if (strpos($cs, 'utf8mb4') === 0) {
                return;
            }
            $this->query(
                "ALTER TABLE `{$this->table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
        } catch (Exception $e) {
            error_log('ensureParentProjectsUtf8mb4 failed: ' . $e->getMessage());
        }
    }

    function list() {
        $draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;
        $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
        $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
        $search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
        
        // Validate and sanitize order_column to prevent SQL injection
        $allowed_columns = [
            'id', 'project_number', 'project_name', 'construction_number', 
            'company_name', 'scale', 'type1', 'type2', 'requests', 'request_date', 'created_at', 'updated_at',
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

        // Search functionality (flexible: trim, full/half-width, spaces)
        if ($search !== '') {
            $searchWhere = $this->buildPaletteSearchWhere($search, [
                'p.company_name',
                'p.branch_name',
                'p.contact_name',
                'p.construction_number',
                'p.project_name',
                'p.project_number',
                'CAST(p.id AS CHAR)',
            ], 'p.id');
            if ($searchWhere !== '') {
                $whereArr[] = $searchWhere;
            }
        }

        $requestFilter = isset($_GET['request_filter']) ? trim((string)$_GET['request_filter']) : '';
        $allowedRequestFilters = ['意匠', '設備', '3D設備', '省エネ', 'その他', '3D'];
        if ($requestFilter !== '' && in_array($requestFilter, $allowedRequestFilters, true)) {
            $whereArr[] = sprintf(
                "FIND_IN_SET('%s', REPLACE(p.requests, ' ', ''))",
                $this->quote($requestFilter)
            );
        }

        $where = !empty($whereArr) ? "WHERE " . implode(" AND ", $whereArr) : "";
        
        // Get total count
        $countQuery = sprintf("SELECT COUNT(*) as total FROM %s p %s", $this->table, $where);
        $totalRecords = $this->fetchOne($countQuery)['total'];
        
        // Get filtered count
        $filteredRecords = $totalRecords;
        
        $childCountJoin = '';
        if ($order_column === 'child_project_count') {
            $childCountJoin = " LEFT JOIN (
                SELECT parent_project_id, COUNT(*) as child_project_count
                FROM " . DB_PREFIX . "projects
                GROUP BY parent_project_id
            ) ppc ON ppc.parent_project_id = p.id ";
            $orderBy = "ORDER BY COALESCE(ppc.child_project_count, 0) $order_dir";
        } elseif ($order_column === 'created_by_name') {
            $orderBy = "ORDER BY u.realname $order_dir";
        } else {
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
        
        $query = sprintf(
            "SELECT p.*, u.realname as created_by_name
             FROM %s p 
             LEFT JOIN " . DB_PREFIX . "user u ON p.created_by = u.userid
             %s
             %s
             %s
             LIMIT %d, %d",
            $this->table,
            $childCountJoin,
            $where,
            $orderBy,
            $start,
            $length
        );
        
        $data = $this->fetchAll($query);
        $this->attachParentProjectListAggregates($data, $user_id);

        return array(
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data
        );
    }

    private function attachParentProjectListAggregates(array &$data, $userId) {
        if (empty($data)) {
            return;
        }
        $parentIds = array_values(array_filter(array_map('intval', array_column($data, 'id')), function ($id) {
            return $id > 0;
        }));
        if (empty($parentIds)) {
            return;
        }
        $idsList = implode(',', $parentIds);
        $userId = (int)$userId;

        $childMap = [];
        $childRows = $this->fetchAll(sprintf(
            "SELECT parent_project_id, COUNT(*) as child_project_count FROM %sprojects WHERE parent_project_id IN (%s) GROUP BY parent_project_id",
            DB_PREFIX,
            $idsList
        ));
        foreach ($childRows as $row) {
            $childMap[(int)$row['parent_project_id']] = (int)$row['child_project_count'];
        }

        $favoriteSet = [];
        $favRows = $this->fetchAll(sprintf(
            "SELECT parent_project_id FROM %sparent_project_favorites WHERE user_id = %d AND parent_project_id IN (%s)",
            DB_PREFIX,
            $userId,
            $idsList
        ));
        foreach ($favRows as $row) {
            $favoriteSet[(int)$row['parent_project_id']] = true;
        }

        $notesMap = [];
        $noteRows = $this->fetchAll(sprintf(
            "SELECT parent_project_id, id, content, is_important, created_at
             FROM %sparent_project_notes
             WHERE parent_project_id IN (%s)
             ORDER BY parent_project_id, is_important DESC, created_at DESC",
            DB_PREFIX,
            $idsList
        ));
        foreach ($noteRows as $row) {
            $pid = (int)$row['parent_project_id'];
            if (!isset($notesMap[$pid])) {
                $notesMap[$pid] = [];
            }
            $notesMap[$pid][] = $row['id'] . '::' . ($row['content'] ?? '');
        }

        $fulfilledMap = [];
        $fulfilledRows = $this->fetchAll(sprintf(
            "SELECT p.parent_project_id, d.name AS department_name
             FROM %sprojects p
             LEFT JOIN %sdepartments d ON d.id = p.department_id
             WHERE p.parent_project_id IN (%s)
               AND p.status NOT IN ('deleted')",
            DB_PREFIX,
            DB_PREFIX,
            $idsList
        ));
        $deptToRequest = array(
            '設備設計' => '設備',
            '意匠設計' => '意匠',
            '省エネ計算' => '省エネ',
            '技術課設備' => '3D設備',
        );
        foreach ($fulfilledRows as $row) {
            $pid = (int)$row['parent_project_id'];
            $deptName = trim((string)($row['department_name'] ?? ''));
            $type = isset($deptToRequest[$deptName]) ? $deptToRequest[$deptName] : '';
            if ($type === '') {
                continue;
            }
            if (!isset($fulfilledMap[$pid])) {
                $fulfilledMap[$pid] = [];
            }
            if (!in_array($type, $fulfilledMap[$pid], true)) {
                $fulfilledMap[$pid][] = $type;
            }
        }

        foreach ($data as &$row) {
            $pid = (int)$row['id'];
            $row['child_project_count'] = $childMap[$pid] ?? 0;
            $row['is_favorite'] = isset($favoriteSet[$pid]) ? 1 : 0;
            $row['notes_display'] = isset($notesMap[$pid]) ? implode(' | ', $notesMap[$pid]) : '';
            $row['fulfilled_request_types'] = isset($fulfilledMap[$pid]) ? array_values($fulfilledMap[$pid]) : [];
        }
        unset($row);
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
            'request_date' => (isset($_POST['request_date']) && trim($_POST['request_date']) !== '') ? $_POST['request_date'] : null,
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
            'construction_city' => isset($_POST['construction_city']) ? $_POST['construction_city'] : '',
            'structure_type' => isset($_POST['structure_type']) ? $_POST['structure_type'] : '',
            'spec_features' => isset($_POST['spec_features']) ? $_POST['spec_features'] : '',
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

        try {
            // Insert parent project data
            $parent_project_id = $this->query_insert($data);
            
            if (!$parent_project_id) {
                return [
                    'status' => 'error',
                    'error' => '建物の作成に失敗しました'
                ];
            }
            
            // Log the creation
            $this->logParentProjectAction($parent_project_id, 'created', '建物作成', '', '');

            return [
                'status' => 'success',
                'parent_project_id' => $parent_project_id,
                'message' => '建物を作成しました'
            ];
        } catch (Exception $e) {
            error_log('Parent project create error: ' . $e->getMessage());
            $msg = $e->getMessage();
            if (stripos($msg, 'Incorrect string value') !== false) {
                // Retry once after forcing utf8mb4 (in case static cache skipped ALTER)
                try {
                    $this->query(
                        "ALTER TABLE `{$this->table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
                    );
                    $parent_project_id = $this->query_insert($data);
                    if ($parent_project_id) {
                        $this->logParentProjectAction($parent_project_id, 'created', '建物作成', '', '');
                        return [
                            'status' => 'success',
                            'parent_project_id' => $parent_project_id,
                            'message' => '建物を作成しました'
                        ];
                    }
                } catch (Exception $e2) {
                    error_log('Parent project create retry after utf8mb4 failed: ' . $e2->getMessage());
                    $msg = $e2->getMessage();
                }
            }
            return [
                'status' => 'error',
                'error' => 'データベースエラー: ' . $msg
            ];
        }
    }

    function update($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'error' => '建物IDが指定されていません'];

        $old = $this->getById($id);
        if (!$old) return ['status' => 'error', 'error' => '建物が見つかりません'];

        // Partial update: use existing values when field not in $_POST or empty (avoid '' for datetime)
        $company_name = isset($_POST['company_name']) ? $this->validateUTF8MB4($_POST['company_name']) : (isset($old['company_name']) ? $old['company_name'] : '');
        $project_name = isset($_POST['project_name']) ? $this->validateUTF8MB4($_POST['project_name']) : (isset($old['project_name']) ? $old['project_name'] : '');
        $requests = isset($_POST['requests']) ? $this->validateUTF8MB4($_POST['requests']) : (isset($old['requests']) ? $old['requests'] : '');
        $materials = isset($_POST['materials']) ? $this->validateUTF8MB4($_POST['materials']) : (isset($old['materials']) ? $old['materials'] : '');
        $notes = isset($_POST['notes']) ? $this->validateUTF8MB4($_POST['notes']) : (isset($old['notes']) ? $old['notes'] : '');

        $requestDate = null;
        if (isset($_POST['request_date']) && trim((string)$_POST['request_date']) !== '') {
            $requestDate = trim($_POST['request_date']);
        } elseif (isset($old['request_date']) && trim((string)$old['request_date']) !== '') {
            $requestDate = $old['request_date'];
        }
        // Only include request_date in $data if we have a valid value (never '' for datetime column)
        $data = array(
            'company_name' => $company_name,
            'branch_name' => isset($_POST['branch_name']) ? $_POST['branch_name'] : (isset($old['branch_name']) ? $old['branch_name'] : ''),
            'contact_name' => isset($_POST['contact_name']) ? $_POST['contact_name'] : (isset($old['contact_name']) ? $old['contact_name'] : ''),
            'customer_id' => isset($_POST['customer_id']) ? (trim((string)$_POST['customer_id']) !== '' ? intval($_POST['customer_id']) : null) : (isset($old['customer_id']) ? $old['customer_id'] : null),
            'guis_receiver' => isset($_POST['guis_receiver']) ? $_POST['guis_receiver'] : (isset($old['guis_receiver']) ? $old['guis_receiver'] : ''),
            'construction_number' => isset($_POST['construction_number']) ? $_POST['construction_number'] : (isset($old['construction_number']) ? $old['construction_number'] : ''),
            'project_name' => $project_name,
            'scale' => isset($_POST['scale']) ? $_POST['scale'] : (isset($old['scale']) ? $old['scale'] : ''),
            'type1' => isset($_POST['type1']) ? $_POST['type1'] : (isset($old['type1']) ? $old['type1'] : ''),
            'type2' => isset($_POST['type2']) ? $_POST['type2'] : (isset($old['type2']) ? $old['type2'] : ''),
            'type3' => isset($_POST['type3']) ? $_POST['type3'] : (isset($old['type3']) ? $old['type3'] : ''),
            'request_type' => isset($_POST['request_type']) ? $_POST['request_type'] : (isset($old['request_type']) ? $old['request_type'] : ''),
            'requests' => $requests,
            'materials' => $materials,
            'structural_office' => isset($_POST['structural_office']) ? $_POST['structural_office'] : (isset($old['structural_office']) ? $old['structural_office'] : ''),
            'project_number' => isset($_POST['project_number']) ? $_POST['project_number'] : (isset($old['project_number']) ? $old['project_number'] : ''),
            'construction_branch' => isset($_POST['construction_branch']) ? $_POST['construction_branch'] : (isset($old['construction_branch']) ? $old['construction_branch'] : ''),
            'construction_city' => isset($_POST['construction_city']) ? $_POST['construction_city'] : (isset($old['construction_city']) ? $old['construction_city'] : ''),
            'structure_type' => isset($_POST['structure_type']) ? $_POST['structure_type'] : (isset($old['structure_type']) ? $old['structure_type'] : ''),
            'spec_features' => isset($_POST['spec_features']) ? $_POST['spec_features'] : (isset($old['spec_features']) ? $old['spec_features'] : ''),
            'notes' => $notes,
            'status' => isset($_POST['status']) ? $_POST['status'] : (isset($old['status']) ? $old['status'] : 'draft'),
            'updated_by' => isset($_SESSION['userid']) ? $_SESSION['userid'] : '',
            'updated_at' => date('Y-m-d H:i:s')
        );
        if ($requestDate !== null && $requestDate !== '') {
            $data['request_date'] = $requestDate;
        }

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
            $msg = $e->getMessage();
            if (stripos($msg, 'Incorrect string value') !== false) {
                try {
                    $this->query(
                        "ALTER TABLE `{$this->table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
                    );
                    $result = $this->query_update($data, ['id' => $id]);
                    if ($result) {
                        $this->logParentProjectAction($id, 'updated', '建物情報を変更');
                        return ['status' => 'success', 'message' => '建物を更新しました'];
                    }
                } catch (Exception $e2) {
                    error_log('Parent project update retry after utf8mb4 failed: ' . $e2->getMessage());
                    $msg = $e2->getMessage();
                }
            }
            return ['status' => 'error', 'error' => 'データベースエラー: ' . $msg];
        }
    }

    /**
     * Update parent project's construction_number from project list context.
     * Permission: administrator, project manager of the child project, or department project_manager.
     * Input: project_id, construction_number
     */
    function updateConstructionNumberByProject($params = null) {
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : (isset($params['project_id']) ? intval($params['project_id']) : 0);
        $construction_number = isset($_POST['construction_number']) ? trim((string)$_POST['construction_number']) : (isset($params['construction_number']) ? trim((string)$params['construction_number']) : '');

        if ($project_id <= 0) {
            return ['status' => 'error', 'message' => 'project_id is required'];
        }

        $project = $this->fetchOne(
            sprintf(
                "SELECT id, parent_project_id, department_id FROM " . DB_PREFIX . "projects WHERE id = %d LIMIT 1",
                $project_id
            )
        );
        if (!$project) {
            return ['status' => 'error', 'message' => 'プロジェクトが見つかりません'];
        }

        $parent_project_id = isset($project['parent_project_id']) ? intval($project['parent_project_id']) : 0;
        if ($parent_project_id <= 0) {
            return ['status' => 'error', 'message' => '親案件が設定されていません'];
        }

        // Reuse existing project permission rule:
        // administrator OR project manager OR department project_manager.
        require_once(DIR_MODEL . 'project.php');
        $projectModel = new Project();
        $canEdit = $projectModel->canUserEditProject($project_id);
        $projectModel->close();
        if (!$canEdit) {
            return ['status' => 'error', 'message' => 'Forbidden', 'http_status' => 403];
        }

        $oldParent = $this->fetchOne(
            sprintf(
                "SELECT id, construction_number FROM " . DB_PREFIX . "parent_projects WHERE id = %d LIMIT 1",
                $parent_project_id
            )
        );
        if (!$oldParent) {
            return ['status' => 'error', 'message' => '親案件が見つかりません'];
        }

        $oldValue = isset($oldParent['construction_number']) ? (string)$oldParent['construction_number'] : '';
        if ($oldValue === $construction_number) {
            return [
                'status' => 'success',
                'message' => '工事番号を更新しました',
                'parent_project_id' => $parent_project_id,
                'construction_number' => $construction_number
            ];
        }

        $updateData = [
            'construction_number' => $construction_number,
            'updated_by' => isset($_SESSION['userid']) ? $_SESSION['userid'] : '',
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $result = $this->query_update($updateData, ['id' => $parent_project_id]);
        if (!$result) {
            return ['status' => 'error', 'message' => '工事番号の更新に失敗しました'];
        }

        $this->logParentProjectAction($parent_project_id, 'updated', '工事番号を変更', $oldValue, $construction_number);
        return [
            'status' => 'success',
            'message' => '工事番号を更新しました',
            'parent_project_id' => $parent_project_id,
            'construction_number' => $construction_number
        ];
    }

    /**
     * Parse request_date to datetime Y-m-d H:i:s (GMT+9 / Asia/Tokyo). Empty/invalid returns null.
     * Supports: "Mon Jun 30 22:00:00 GMT+07:00 2025" (JS Date string → convert to JST),
     * "2025/07/09" (Y/m/d), "7/9" (m/d), "07/09/2025" (m/d/Y).
     */
        private function parse_request_date_to_datetime($input) {
            $input = trim($input ?? '');
        if ($input === '') {
            return null;
        }
        if (stripos($input, 'GMT') !== false) {
            try {
                $dt = new \DateTime($input);
                $dt->setTimezone(new \DateTimeZone('Asia/Tokyo'));
                return $dt->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                // fall through to slash parsing
            }
        }
        $parts = preg_split('#\s*/\s*#', $input, -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) === 3) {
            $p0 = (int) $parts[0];
            $p1 = (int) $parts[1];
            $p2 = (int) $parts[2];
            if (strlen(trim($parts[0])) === 4) {
                $y = $p0;
                $m = $p1;
                $d = $p2;
            } elseif (strlen(trim($parts[2])) === 4) {
                $m = $p0;
                $d = $p1;
                $y = $p2;
            } else {
                $y = $p0;
                $m = $p1;
                $d = $p2;
                if ($y < 100) {
                    $y += 2000;
                }
            }
            if ($y < 100) {
                $y += 2000;
            }
            if ($m >= 1 && $m <= 12 && $d >= 1 && $d <= 31 && checkdate($m, $d, $y)) {
                return sprintf('%04d-%02d-%02d 00:00:00', $y, $m, $d);
            }
        }
        if (count($parts) === 2) {
            $m = (int) $parts[0];
            $d = (int) $parts[1];
            $y = (int) date('Y');
            if ($m >= 1 && $m <= 12 && $d >= 1 && $d <= 31 && checkdate($m, $d, $y)) {
                return sprintf('%04d-%02d-%02d 00:00:00', $y, $m, $d);
            }
        }
        return null;
    }

    /**
     * Find parent_project by construction_number and customer_id. Returns row with id or null.
     */
    function get_by_construction_number_and_customer_id($project_name, $customer_id) {
        $project_name = trim($project_name ?? '');
        $customer_id = intval($customer_id);
        if ($project_name === '' || $customer_id <= 0) {
            return null;
        }
        $query = sprintf(
            "SELECT id FROM %s WHERE TRIM(COALESCE(project_name,'')) = '%s' AND customer_id = %d LIMIT 1",
            $this->table,
            $this->quote($project_name),
            $customer_id
        );
        return $this->fetchOne($query);
    }

    /**
     * Public API (no login): insert or update parent_project.
     * Params: customer_id, request_date, construction_number, project_name, scale, type1, request_type, status (default completed).
     * Loads customer to get company_name, branch_name (branch), contact_name (name). If construction_number + customer_id exists then update, else insert.
     */
    function upsert_parent_project_public($params) {

        
        $hash = array(
            'status' => 'error',
            'message_code' => '',
            'id' => null,
        );
        $customer_id = isset($params['customer_id']) ? intval($params['customer_id']) : 0;
        $construction_number = isset($params['construction_number']) ? trim($params['construction_number']) : '';
        if ($customer_id <= 0) {
            $hash['message_code'] = 'customer_id is required';
            return $hash;
        }
        // if ($construction_number === '') {
        //     $hash['message_code'] = 'construction_number is required';
        //     return $hash;
        // }
        require_once dirname(__FILE__) . '/customer.php';
        $customerModel = new Customer();
        $customerModel->connect();
        
        $customer = $customerModel->get_customer_by_id($customer_id);
        $customerModel->close();
        if (!$customer || empty($customer['company_name'])) {
            $hash['message_code'] = 'customer not found';
            return $hash;
        }
      
        $company_name = isset($customer['company_name']) ? trim($customer['company_name']) : '';
        $branch_name = isset($customer['branch']) ? trim($customer['branch']) : '';
        $contact_name = isset($customer['name']) ? trim($customer['name']) : '';
        $request_date = $this->parse_request_date_to_datetime(isset($params['request_date']) ? $params['request_date'] : '');
        $project_name = isset($params['project_name']) ? trim($params['project_name']) : '';
        $scale = isset($params['scale']) ? trim($params['scale']) : '';
        $type1 = isset($params['type1']) ? trim($params['type1']) : '';
        $request_type = isset($params['request_type']) ? trim($params['request_type']) : '';
        
        $requests = isset($params['requests']) ? trim($params['requests']) : '';
        $status = isset($params['status']) && trim($params['status'] ?? '') !== '' ? trim($params['status']) : 'completed';
        $department_id = isset($params['department_id']) && $params['department_id'] !== '' && $params['department_id'] !== null
            ? intval($params['department_id']) : 5;
        $existing = $this->get_by_construction_number_and_customer_id($project_name, $customer_id);
       
        if ($existing && !empty($existing['id'])) {
            $data = array(
                'guis_receiver' =>  'admin',
                'company_name' => $company_name,
                'branch_name' => $branch_name,
                'contact_name' => $contact_name,
                'customer_id' => $customer_id,
                'construction_number' => $construction_number,
                'project_name' => $project_name,
                'scale' => $scale,
                'type1' => $type1,
                'request_type' => $request_type,
                'requests' => $requests,
                //'status' => $status,
                //'department_id' => $department_id,
                'updated_by' => 'admin',
                'updated_at' => date('Y-m-d H:i:s'),
            );
            if ($request_date !== null) {
                $data['request_date'] = $request_date;
            }
            $result = $this->query_update($data, array('id' => $existing['id']));
            if ($result) {
                $hash['status'] = 'success';
                $hash['message_code'] = 'updated';
                $hash['id'] = (int) $existing['id'];
            } else {
                $hash['message_code'] = 'update failed';
            }
            return $hash;
        }
        $gen = $this->generateProjectNumber();
        $project_number = isset($gen['project_number']) ? $gen['project_number'] : '';
        $data = array(
            'guis_receiver' =>  'admin',
            'company_name' => $company_name,
            'branch_name' => $branch_name,
            'contact_name' => $contact_name,
            'customer_id' => $customer_id,
            'construction_number' => $construction_number,
            'project_name' => $project_name,
            'scale' => $scale,
            'type1' => $type1,
            'request_type' => $request_type,
            //'status' => $status,
            'project_number' => $project_number,
            'construction_branch' => '',
            'type2' => '',
            'type3' => '',
            'requests' => $requests,
            'materials' => '',
            'structural_office' => '',
            'notes' => '',
            //'department_id' => $department_id,
            'created_by' => 'admin',
            'created_at' => date('Y-m-d H:i:s'),
        );
        if ($request_date !== null) {
            $data['request_date'] = $request_date;
        }
       
        $new_id = $this->query_insert($data);
        if ($new_id) {
            $hash['status'] = 'success';
            $hash['message_code'] = 'created';
            $hash['id'] = (int) $new_id;
            $hash['request_date'] = $request_date;
            $hash['request_date_original'] = isset($params['request_date']) ? $params['request_date'] : '';
        } else {
            $hash['message_code'] = 'insert failed';
        }
        return $hash;
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
        if (empty($_SESSION['authority']) || $_SESSION['authority'] !== 'administrator') {
            return ['status' => 'error', 'error' => '管理者のみ削除できます。', 'http_status' => 403];
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) {
            return ['status' => 'error', 'error' => '建物IDが指定されていません'];
        }

        $confirm = isset($_POST['confirm']) ? trim((string)$_POST['confirm']) : '';
        if ($confirm !== 'DELETE') {
            return ['status' => 'error', 'error' => '確認のため DELETE と入力してください'];
        }

        $currentProject = $this->getById($id);
        if (!$currentProject) {
            return ['status' => 'error', 'error' => '建物が見つかりません'];
        }

        try {
            $deletedChildren = $this->hardDeleteParentProjectCascade($id);
            return [
                'status' => 'success',
                'message' => '建物を削除しました',
                'deleted_children' => $deletedChildren
            ];
        } catch (Exception $e) {
            error_log('Parent project hard delete error: ' . $e->getMessage());
            return ['status' => 'error', 'error' => '削除に失敗しました: ' . $e->getMessage()];
        }
    }

    /**
     * Hard-delete a parent project and all dependent child-project data (DB + attachment files).
     * @return int number of child projects deleted
     */
    private function hardDeleteParentProjectCascade($parentProjectId) {
        $parentProjectId = intval($parentProjectId);
        if ($parentProjectId <= 0) {
            throw new Exception('Invalid parent project id');
        }

        $childRows = $this->fetchAll(sprintf(
            "SELECT id FROM %sprojects WHERE parent_project_id = %d",
            DB_PREFIX,
            $parentProjectId
        ));
        $childIds = [];
        if (is_array($childRows)) {
            foreach ($childRows as $row) {
                if (!empty($row['id'])) {
                    $childIds[] = intval($row['id']);
                }
            }
        }

        foreach ($childIds as $childId) {
            $this->hardDeleteChildProjectCascade($childId);
        }

        $this->hardDeleteParentOnlyData($parentProjectId);

        $result = $this->query_delete(['id' => $parentProjectId]);
        if (!$result) {
            throw new Exception('Failed to delete parent project row');
        }

        return count($childIds);
    }

    private function tableExists($tableName) {
        static $cache = [];
        if (isset($cache[$tableName])) {
            return $cache[$tableName];
        }
        $escaped = $this->quote($tableName);
        $row = $this->fetchOne("SHOW TABLES LIKE '" . $escaped . "'");
        $cache[$tableName] = !empty($row);
        return $cache[$tableName];
    }

    private function tableHasColumn($tableName, $columnName) {
        static $cache = [];
        $key = $tableName . '.' . $columnName;
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        if (!$this->tableExists($tableName)) {
            $cache[$key] = false;
            return false;
        }
        $row = $this->fetchOne(sprintf(
            "SHOW COLUMNS FROM `%s` LIKE '%s'",
            str_replace('`', '``', $tableName),
            $this->quote($columnName)
        ));
        $cache[$key] = !empty($row);
        return $cache[$key];
    }

    private function safeDeleteByIds($table, $column, array $ids) {
        if (!$ids || !$this->tableExists($table) || !$this->tableHasColumn($table, $column)) {
            return;
        }
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return;
        }
        $chunks = array_chunk($ids, 500);
        foreach ($chunks as $chunk) {
            $this->query(sprintf(
                "DELETE FROM %s WHERE `%s` IN (%s)",
                $table,
                $column,
                implode(',', $chunk)
            ));
        }
    }

    private function safeDeleteTaskLinks(array $taskIds) {
        $table = DB_PREFIX . 'task_links';
        if (!$taskIds || !$this->tableExists($table)) {
            return;
        }
        $taskIds = array_values(array_filter(array_map('intval', $taskIds)));
        if (!$taskIds) {
            return;
        }
        $hasSource = $this->tableHasColumn($table, 'source_task_id');
        $hasTarget = $this->tableHasColumn($table, 'target_task_id');
        $hasTaskId = $this->tableHasColumn($table, 'task_id');
        $chunks = array_chunk($taskIds, 500);
        foreach ($chunks as $chunk) {
            $idList = implode(',', $chunk);
            if ($hasSource || $hasTarget) {
                $parts = [];
                if ($hasSource) {
                    $parts[] = "source_task_id IN ($idList)";
                }
                if ($hasTarget) {
                    $parts[] = "target_task_id IN ($idList)";
                }
                $this->query(sprintf("DELETE FROM %s WHERE %s", $table, implode(' OR ', $parts)));
            } elseif ($hasTaskId) {
                $this->query(sprintf("DELETE FROM %s WHERE task_id IN (%s)", $table, $idList));
            }
        }
    }

    private function hardDeleteChildProjectCascade($projectId) {
        $projectId = intval($projectId);
        if ($projectId <= 0) {
            return;
        }

        $taskRows = $this->tableExists(DB_PREFIX . 'tasks')
            ? $this->fetchAll(sprintf("SELECT id FROM %stasks WHERE project_id = %d", DB_PREFIX, $projectId))
            : [];
        $taskIds = [];
        if (is_array($taskRows)) {
            foreach ($taskRows as $row) {
                if (!empty($row['id'])) {
                    $taskIds[] = intval($row['id']);
                }
            }
        }

        if ($taskIds) {
            $this->safeDeleteByIds(DB_PREFIX . 'time_entries', 'task_id', $taskIds);
            $this->safeDeleteTaskLinks($taskIds);
            $this->safeDeleteByIds(DB_PREFIX . 'task_logs', 'task_id', $taskIds);
            $this->safeDeleteByIds(DB_PREFIX . 'task_reactions', 'task_id', $taskIds);
            $this->safeDeleteByIds(DB_PREFIX . 'task_assignees', 'task_id', $taskIds);
            $this->safeDeleteByIds(DB_PREFIX . 'task_acknowledgements', 'task_id', $taskIds);
            $this->safeDeleteByIds(DB_PREFIX . 'project_drawings', 'task_id', $taskIds);

            // Comments / threads / likes linked to tasks
            if ($this->tableExists(DB_PREFIX . 'comments') && $this->tableHasColumn(DB_PREFIX . 'comments', 'task_id')) {
                $commentRows = $this->fetchAll(sprintf(
                    "SELECT id, thread_id FROM %scomments WHERE task_id IN (%s)",
                    DB_PREFIX,
                    implode(',', $taskIds)
                ));
                $commentIds = [];
                $threadIds = [];
                if (is_array($commentRows)) {
                    foreach ($commentRows as $c) {
                        if (!empty($c['id'])) {
                            $commentIds[] = intval($c['id']);
                        }
                        if (!empty($c['thread_id'])) {
                            $threadIds[] = intval($c['thread_id']);
                        }
                    }
                }
                if ($commentIds) {
                    $this->safeDeleteByIds(DB_PREFIX . 'comment_likes', 'comment_id', $commentIds);
                    $this->safeDeleteByIds(DB_PREFIX . 'comments', 'id', $commentIds);
                }
                if ($threadIds) {
                    $threadIds = array_values(array_unique($threadIds));
                    $this->safeDeleteByIds(DB_PREFIX . 'comment_thread_reads', 'thread_id', $threadIds);
                    $this->safeDeleteByIds(DB_PREFIX . 'comment_threads', 'id', $threadIds);
                }
            }

            $this->safeDeleteByIds(DB_PREFIX . 'tasks', 'id', $taskIds);
        }

        $this->safeDeleteByIds(DB_PREFIX . 'project_drawings', 'project_id', [$projectId]);
        $this->safeDeleteByIds(DB_PREFIX . 'project_members', 'project_id', [$projectId]);
        $this->safeDeleteByIds(DB_PREFIX . 'project_favorites', 'project_id', [$projectId]);
        $this->safeDeleteByIds(DB_PREFIX . 'project_notes', 'project_id', [$projectId]);
        $this->safeDeleteByIds(DB_PREFIX . 'project_logs', 'project_id', [$projectId]);

        // Attachments / folders by project_id
        if ($this->tableExists(DB_PREFIX . 'project_attachments')) {
            $attRows = $this->fetchAll(sprintf(
                "SELECT id, file_path FROM %sproject_attachments WHERE project_id = %d",
                DB_PREFIX,
                $projectId
            ));
            if (is_array($attRows)) {
                foreach ($attRows as $att) {
                    $this->unlinkAttachmentFile(isset($att['file_path']) ? $att['file_path'] : '');
                }
            }
            $this->safeDeleteByIds(DB_PREFIX . 'project_attachments', 'project_id', [$projectId]);
        }
        $this->safeDeleteByIds(DB_PREFIX . 'project_folders', 'project_id', [$projectId]);

        $this->deleteProjectAttachmentDirectory($projectId);

        if ($this->tableExists(DB_PREFIX . 'projects')) {
            $this->query(sprintf("DELETE FROM %sprojects WHERE id = %d", DB_PREFIX, $projectId));
        }
    }

    private function hardDeleteParentOnlyData($parentProjectId) {
        $parentProjectId = intval($parentProjectId);

        // Quotations under this parent
        if ($this->tableExists(DB_PREFIX . 'quotations')) {
            $qRows = $this->fetchAll(sprintf(
                "SELECT id FROM %squotations WHERE parent_project_id = %d",
                DB_PREFIX,
                $parentProjectId
            ));
            $qIds = [];
            if (is_array($qRows)) {
                foreach ($qRows as $q) {
                    if (!empty($q['id'])) {
                        $qIds[] = intval($q['id']);
                    }
                }
            }
            if ($qIds) {
                $this->safeDeleteByIds(DB_PREFIX . 'quotation_items', 'quotation_id', $qIds);
                $this->safeDeleteByIds(DB_PREFIX . 'quotation_history', 'quotation_id', $qIds);
                $this->safeDeleteByIds(DB_PREFIX . 'quotations', 'id', $qIds);
            }
        }

        $this->safeDeleteByIds(DB_PREFIX . 'parent_project_favorites', 'parent_project_id', [$parentProjectId]);
        $this->safeDeleteByIds(DB_PREFIX . 'parent_project_notes', 'parent_project_id', [$parentProjectId]);
        $this->safeDeleteByIds(DB_PREFIX . 'parent_projects_logs', 'parent_project_id', [$parentProjectId]);
        $this->safeDeleteByIds(DB_PREFIX . 'parent_project_logs', 'parent_project_id', [$parentProjectId]);

        if ($this->tableExists(DB_PREFIX . 'project_attachments')) {
            $attRows = $this->fetchAll(sprintf(
                "SELECT id, file_path FROM %sproject_attachments WHERE parent_project_id = %d",
                DB_PREFIX,
                $parentProjectId
            ));
            if (is_array($attRows)) {
                foreach ($attRows as $att) {
                    $this->unlinkAttachmentFile(isset($att['file_path']) ? $att['file_path'] : '');
                }
            }
            $this->safeDeleteByIds(DB_PREFIX . 'project_attachments', 'parent_project_id', [$parentProjectId]);
        }
        $this->safeDeleteByIds(DB_PREFIX . 'project_folders', 'parent_project_id', [$parentProjectId]);

        $this->deleteParentAttachmentDirectory($parentProjectId);
    }

    private function unlinkAttachmentFile($filePath) {
        $filePath = trim((string)$filePath);
        if ($filePath === '') {
            return;
        }
        $candidates = [
            $filePath,
            dirname(__DIR__, 2) . '/' . ltrim(str_replace('\\', '/', $filePath), '/'),
            dirname(__DIR__) . '/../' . ltrim(str_replace('\\', '/', $filePath), '/')
        ];
        foreach ($candidates as $path) {
            if ($path && is_file($path)) {
                @unlink($path);
                return;
            }
        }
    }

    private function deleteProjectAttachmentDirectory($projectId) {
        $bases = [
            dirname(__DIR__, 2) . '/assets/upload/project-attachments/' . intval($projectId),
            dirname(__DIR__) . '/../assets/upload/project-attachments/' . intval($projectId),
        ];
        foreach ($bases as $dir) {
            if (is_dir($dir)) {
                $this->deleteDirectory($dir);
            }
        }
    }

    private function deleteParentAttachmentDirectory($parentProjectId) {
        $bases = [
            dirname(__DIR__, 2) . '/assets/upload/parent-project-attachments/' . intval($parentProjectId),
            dirname(__DIR__) . '/../assets/upload/parent-project-attachments/' . intval($parentProjectId),
        ];
        foreach ($bases as $dir) {
            if (is_dir($dir)) {
                $this->deleteDirectory($dir);
            }
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

    /**
     * Get parent_project by project_number (exact match). Used for AI when user says "tòa nhà #P000008".
     * @param string $project_number e.g. "P000008"
     * @return array|null row with id, project_name, project_number or null
     */
    public function getByProjectNumber($project_number) {
        $pn = trim((string) $project_number);
        if ($pn === '') {
            return null;
        }
        $query = "SELECT id, project_name, project_number, construction_number FROM " . $this->table
            . " WHERE project_number = '" . $this->quote($pn) . "' AND status != 'deleted' LIMIT 1";
        return $this->fetchOne($query);
    }

    /**
     * Phase 2.1 – Parent project list/read for AI context.
     * Returns minimal list or single parent project (id, project_name, construction_number, status, department_id, company_name)
     * with existing permission/filters (status != deleted, optional department_id, status). No write operations.
     *
     * @param array $options ['department_id' => int, 'status' => string, 'limit' => int, 'id' => int for single]
     * @return array
     */
    public function getForAiContext($options = []) {
        $department_id = isset($options['department_id']) ? intval($options['department_id']) : null;
        $status_raw = isset($options['status']) ? trim($options['status']) : null;
        $status = ($status_raw !== null && $status_raw !== '') ? $this->quote($status_raw) : null;
        $limit = isset($options['limit']) ? min(100, max(1, intval($options['limit']))) : 50;
        $id = isset($options['id']) ? intval($options['id']) : null;

        $whereArr = ["p.status != 'deleted'"];
        if ($department_id !== null && $department_id > 0) {
            $whereArr[] = "p.department_id = " . $department_id;
        }
        if ($status !== null && $status !== '') {
            $whereArr[] = "p.status = '" . $status . "'";
        }
        if ($id !== null && $id > 0) {
            $whereArr[] = "p.id = " . $id;
        }
        $where = "WHERE " . implode(" AND ", $whereArr);

        $fields = "p.id, p.project_name, p.construction_number, p.status, p.department_id, p.company_name";
        $query = "SELECT " . $fields . " FROM " . $this->table . " p " . $where;
        if ($id !== null && $id > 0) {
            $row = $this->fetchOne($query);
            return $row ? [$row] : [];
        }
        $query .= " ORDER BY p.updated_at DESC LIMIT " . $limit;
        return $this->fetchAll($query);
    }

    function getChildProjects($params = null) {
        // Handle both direct parent_project_id parameter and params array from API
        if (is_array($params)) {
            $parent_project_id = isset($params['parent_project_id']) ? $params['parent_project_id'] : 0;
        } else {
            $parent_project_id = $params;
        }
        
        $user_id = $_SESSION['id'];
        
        $query = sprintf(
            "SELECT p.*, d.name as department_name,
            COALESCE(NULLIF(TRIM(c.company_name), ''), NULLIF(TRIM(cp.company_name), '')) as company_name,
            COALESCE(NULLIF(TRIM(c.branch), ''), NULLIF(TRIM(cp.branch), '')) as branch_name,
            COALESCE(NULLIF(TRIM(c.name), ''), NULLIF(TRIM(cp.name), '')) as contact_name,
            COALESCE(NULLIF(TRIM(p.guis_receiver), ''), NULLIF(TRIM(pp.guis_receiver), '')) as effective_guis_receiver,
            gu.realname as guis_receiver_name
            FROM " . DB_PREFIX . "projects p 
            LEFT JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id
            LEFT JOIN " . DB_PREFIX . "customer c ON c.id = SUBSTRING_INDEX(p.customer_id, ',', 1)
            LEFT JOIN %sparent_projects pp ON pp.id = p.parent_project_id
            LEFT JOIN " . DB_PREFIX . "customer cp ON cp.id = SUBSTRING_INDEX(pp.customer_id, ',', 1)
            LEFT JOIN " . DB_PREFIX . "user gu ON gu.userid = COALESCE(NULLIF(TRIM(p.guis_receiver), ''), NULLIF(TRIM(pp.guis_receiver), ''))
            WHERE p.parent_project_id = %d
            ORDER BY p.created_at DESC",
            DB_PREFIX,
            intval($parent_project_id)
        );
        $projects = $this->fetchAll($query);
        $this->attachProjectFavoriteAggregates($projects, $user_id);
        $membersByProject = $this->fetchProjectMembersByProjectIds(array_column($projects, 'id'));
        foreach ($projects as &$project) {
            $pid = (int)$project['id'];
            $project['manager_id'] = isset($membersByProject[$pid]['manager'])
                ? implode('|', $membersByProject[$pid]['manager']) : '';
            if (!empty($project['yotei']) && is_string($project['yotei'])) {
                $decoded = json_decode($project['yotei'], true);
                $project['yotei'] = is_array($decoded) ? $decoded : null;
            } elseif (empty($project['yotei'])) {
                $project['yotei'] = null;
            }
        }
        unset($project);
        return $projects;
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
            "SELECT f.*, u.realname as created_by_name
             FROM " . DB_PREFIX . "project_folders f
             LEFT JOIN " . DB_PREFIX . "user u ON f.created_by = u.userid
             WHERE f.parent_project_id = %d AND %s
             ORDER BY f.name ASC",
            $parent_project_id,
            $folder_id ? "f.parent_folder_id = $folder_id" : "f.parent_folder_id IS NULL"
        );
        $folders = $this->fetchAll($folderQuery);
        $this->attachFolderListAggregates($folders, ['parent_project_id' => $parent_project_id]);
        
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
        $folder_id = (int)$folder_id;
        if ($folder_id <= 0) {
            return [];
        }
        $start = $this->fetchOne(sprintf(
            "SELECT parent_project_id FROM %sproject_folders WHERE id = %d",
            DB_PREFIX,
            $folder_id
        ));
        if (!$start || empty($start['parent_project_id'])) {
            return [];
        }
        $allFolders = $this->fetchAll(sprintf(
            "SELECT id, name, parent_folder_id FROM %sproject_folders WHERE parent_project_id = %d",
            DB_PREFIX,
            (int)$start['parent_project_id']
        ));
        $folderById = [];
        foreach ($allFolders as $folder) {
            $folderById[(int)$folder['id']] = $folder;
        }
        $breadcrumbs = $this->buildFolderBreadcrumbsFromMap($folder_id, $folderById);
        
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
        // Check if info mode is requested
        if (isset($_GET['info']) && $_GET['info'] == '1') {
            return $this->getAttachmentInfo();
        }
        
        // Check if direct download is requested (bypass detail page)
        if (isset($_GET['download']) && $_GET['download'] == '1') {
            $this->serveFile(true); // force download
            return;
        }
        
        // Check if inline view is explicitly requested
        if (isset($_GET['inline']) && $_GET['inline'] == '1') {
            $this->serveFile(false); // inline view
            return;
        }
        
        // Default: redirect to detail page
        $file_id = isset($_GET['file_id']) ? intval($_GET['file_id']) : 0;
        if ($file_id) {
            header('Location: ' . ROOT . 'parent_project/file-view.php?file_id=' . $file_id);
            exit;
        }
        
        // Fallback: serve file inline if no file_id
        $this->serveFile(false);
    }

    function downloadFile($params = null) {
        $this->serveFile(true); // true = force download
    }
    
    function getAttachmentInfo($params = null) {
        // Check if user is logged in
        if (!isset($_SESSION['userid']) || !$_SESSION['userid']) {
            return ['success' => false, 'message' => '認証が必要です。ログインしてください。'];
        }
        
        $file_id = isset($_GET['file_id']) ? intval($_GET['file_id']) : 0;
        $folder_id = isset($_GET['folder_id']) ? intval($_GET['folder_id']) : 0;
        
        if (!$file_id && !$folder_id) {
            return ['success' => false, 'message' => 'ファイルIDまたはフォルダIDが指定されていません。'];
        }
        
        if ($file_id) {
            // Get file info
            $fileQuery = sprintf(
                "SELECT a.*, pp.project_name as parent_project_name, u.realname as uploaded_by_name
                 FROM " . DB_PREFIX . "project_attachments a
                 LEFT JOIN " . DB_PREFIX . "parent_projects pp ON a.parent_project_id = pp.id
                 LEFT JOIN " . DB_PREFIX . "user u ON a.uploaded_by = u.userid
                 WHERE a.id = %d",
                $file_id
            );
            $file = $this->fetchOne($fileQuery);
            
            if (!$file) {
                return ['success' => false, 'message' => 'ファイルが見つかりません。'];
            }
            
            // Check if file exists
            $realFilePath = '..' . $file['file_path'];
            $file['exists'] = file_exists($realFilePath);
            
            return [
                'success' => true,
                'type' => 'file',
                'data' => $file
            ];
        } else {
            // Get folder info
            $folderQuery = sprintf(
                "SELECT f.*, pp.project_name as parent_project_name, u.realname as created_by_name
                 FROM " . DB_PREFIX . "project_folders f
                 LEFT JOIN " . DB_PREFIX . "parent_projects pp ON f.parent_project_id = pp.id
                 LEFT JOIN " . DB_PREFIX . "user u ON f.created_by = u.userid
                 WHERE f.id = %d",
                $folder_id
            );
            $folder = $this->fetchOne($folderQuery);
            
            if (!$folder) {
                return ['success' => false, 'message' => 'フォルダが見つかりません。'];
            }
            $folderRows = [$folder];
            $this->attachFolderListAggregates($folderRows, ['include_subfolder_count' => true]);
            $folder = $folderRows[0];
            
            // Get total size of files in folder (including subfolders)
            $totalSize = $this->getFolderTotalSize($folder_id);
            $folder['total_size'] = $totalSize;
            
            return [
                'success' => true,
                'type' => 'folder',
                'data' => $folder
            ];
        }
    }
    
    private function getFolderTotalSize($folder_id) {
        $totalSize = 0;
        
        // Get files in this folder
        $fileQuery = sprintf(
            "SELECT SUM(file_size) as total FROM " . DB_PREFIX . "project_attachments WHERE folder_id = %d",
            $folder_id
        );
        $result = $this->fetchOne($fileQuery);
        if ($result && $result['total']) {
            $totalSize += $result['total'];
        }
        
        // Get subfolders
        $subfolderQuery = sprintf(
            "SELECT id FROM " . DB_PREFIX . "project_folders WHERE parent_folder_id = %d",
            $folder_id
        );
        $subfolders = $this->fetchAll($subfolderQuery);
        
        // Recursively calculate size of subfolders
        foreach ($subfolders as $subfolder) {
            $totalSize += $this->getFolderTotalSize($subfolder['id']);
        }
        
        return $totalSize;
    }
    
    function downloadFolderZip($params = null) {
        // Check if user is logged in
        if (!isset($_SESSION['userid']) || !$_SESSION['userid']) {
            http_response_code(401);
            die('認証が必要です。ログインしてください。');
        }
        
        $folder_id = isset($_GET['folder_id']) && $_GET['folder_id'] !== '' ? intval($_GET['folder_id']) : null;
        $parent_project_id = isset($_GET['parent_project_id']) ? intval($_GET['parent_project_id']) : 0;
        
        // If no folder_id, download all files in parent project (root)
        if ($folder_id === null) {
            if (!$parent_project_id) {
                http_response_code(400);
                die('建物プロジェクトIDが指定されていません。');
            }
            return $this->downloadParentProjectZip($parent_project_id);
        }
        
        // Check if ZipArchive class is available
        if (!class_exists('ZipArchive')) {
            // Try to use shell command as fallback (if available)
            if (function_exists('shell_exec') && !empty(shell_exec('which zip'))) {
                return $this->downloadFolderZipShell($folder_id);
            } else {
                http_response_code(500);
                die('ZIP機能を使用するにはPHPのzip拡張機能が必要です。サーバー管理者に連絡してください。');
            }
        }
        
        // Get folder info
        $folderQuery = sprintf(
            "SELECT f.*, pp.project_name as parent_project_name
             FROM " . DB_PREFIX . "project_folders f
             LEFT JOIN " . DB_PREFIX . "parent_projects pp ON f.parent_project_id = pp.id
             WHERE f.id = %d",
            $folder_id
        );
        $folder = $this->fetchOne($folderQuery);
        
        if (!$folder) {
            http_response_code(404);
            die('フォルダが見つかりません。');
        }
        
        // Create zip file
        $zipFileName = tempnam(sys_get_temp_dir(), 'folder_') . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            http_response_code(500);
            die('ZIPファイルの作成に失敗しました。');
        }
        
        // Add files to zip recursively
        $this->addFolderToZip($zip, $folder_id, $folder['name']);
        
        $zip->close();
        
        // Check if zip file was created successfully
        if (!file_exists($zipFileName) || filesize($zipFileName) === 0) {
            http_response_code(500);
            die('ZIPファイルの作成に失敗しました。');
        }
        
        // Send zip file
        $encodedFileName = rawurlencode($folder['name'] . '.zip');
        header('Content-Type: application/zip');
        header('Content-Length: ' . filesize($zipFileName));
        header('Content-Disposition: attachment; filename="' . $encodedFileName . '"; filename*=UTF-8\'\'' . $encodedFileName);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Clear output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Output zip file
        readfile($zipFileName);
        
        // Delete temporary file
        @unlink($zipFileName);
        exit;
    }
    
    // Download all attachments in a parent project as ZIP (root level)
    private function downloadParentProjectZip($parent_project_id) {
        // Check if ZipArchive class is available
        if (!class_exists('ZipArchive')) {
            // Try to use shell command as fallback (if available)
            if (function_exists('shell_exec') && !empty(shell_exec('which zip'))) {
                return $this->downloadParentProjectZipShell($parent_project_id);
            } else {
                http_response_code(500);
                die('ZIP機能を使用するにはPHPのzip拡張機能が必要です。サーバー管理者に連絡してください。');
            }
        }
        
        // Get parent project info
        $projectQuery = sprintf(
            "SELECT * FROM " . DB_PREFIX . "parent_projects WHERE id = %d",
            $parent_project_id
        );
        $project = $this->fetchOne($projectQuery);
        
        if (!$project) {
            http_response_code(404);
            die('建物プロジェクトが見つかりません。');
        }
        
        // Create zip file
        $zipFileName = tempnam(sys_get_temp_dir(), 'parent_project_') . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            http_response_code(500);
            die('ZIPファイルの作成に失敗しました。');
        }
        
        // Add root files (files without folder_id)
        $rootFilesQuery = sprintf(
            "SELECT * FROM " . DB_PREFIX . "project_attachments WHERE parent_project_id = %d AND folder_id IS NULL",
            $parent_project_id
        );
        $rootFiles = $this->fetchAll($rootFilesQuery);
        
        foreach ($rootFiles as $file) {
            $filePath = '..' . $file['file_path'];
            if (file_exists($filePath)) {
                $zip->addFile($filePath, $file['original_name']);
            }
        }
        
        // Add all folders recursively
        $rootFoldersQuery = sprintf(
            "SELECT * FROM " . DB_PREFIX . "project_folders WHERE parent_project_id = %d AND parent_folder_id IS NULL",
            $parent_project_id
        );
        $rootFolders = $this->fetchAll($rootFoldersQuery);
        
        foreach ($rootFolders as $folder) {
            $this->addFolderToZip($zip, $folder['id'], $folder['name']);
        }
        
        $zip->close();
        
        // Check if zip file was created successfully
        if (!file_exists($zipFileName) || filesize($zipFileName) === 0) {
            http_response_code(500);
            die('ZIPファイルの作成に失敗しました。');
        }
        
        // Send zip file
        $encodedFileName = rawurlencode($project['project_name'] . '_attachments.zip');
        header('Content-Type: application/zip');
        header('Content-Length: ' . filesize($zipFileName));
        header('Content-Disposition: attachment; filename="' . $encodedFileName . '"; filename*=UTF-8\'\'' . $encodedFileName);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Clear output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Output zip file
        readfile($zipFileName);
        
        // Delete temporary file
        @unlink($zipFileName);
        exit;
    }
    
    private function addFolderToZip($zip, $folder_id, $basePath = '') {
        // Get files in this folder
        $fileQuery = sprintf(
            "SELECT * FROM " . DB_PREFIX . "project_attachments WHERE folder_id = %d",
            $folder_id
        );
        $files = $this->fetchAll($fileQuery);
        
        foreach ($files as $file) {
            $filePath = '..' . $file['file_path'];
            if (file_exists($filePath)) {
                $zipPath = $basePath . '/' . $file['original_name'];
                $zip->addFile($filePath, $zipPath);
            }
        }
        
        // Get subfolders
        $subfolderQuery = sprintf(
            "SELECT * FROM " . DB_PREFIX . "project_folders WHERE parent_folder_id = %d",
            $folder_id
        );
        $subfolders = $this->fetchAll($subfolderQuery);
        
        foreach ($subfolders as $subfolder) {
            $subfolderPath = $basePath . '/' . $subfolder['name'];
            $this->addFolderToZip($zip, $subfolder['id'], $subfolderPath);
        }
    }
    
    // Fallback method using shell command if ZipArchive is not available
    private function downloadFolderZipShell($folder_id) {
        // Get folder info
        $folderQuery = sprintf(
            "SELECT f.*, pp.project_name as parent_project_name
             FROM " . DB_PREFIX . "project_folders f
             LEFT JOIN " . DB_PREFIX . "parent_projects pp ON f.parent_project_id = pp.id
             WHERE f.id = %d",
            $folder_id
        );
        $folder = $this->fetchOne($folderQuery);
        
        if (!$folder) {
            http_response_code(404);
            die('フォルダが見つかりません。');
        }
        
        // Create temporary directory
        $tempDir = sys_get_temp_dir() . '/zip_' . uniqid();
        mkdir($tempDir, 0755, true);
        
        // Copy files to temp directory
        $this->copyFolderToTemp($tempDir, $folder_id, $folder['name']);
        
        // Create zip using shell command
        $zipFileName = sys_get_temp_dir() . '/folder_' . $folder_id . '_' . time() . '.zip';
        $command = "cd " . escapeshellarg($tempDir) . " && zip -r " . escapeshellarg($zipFileName) . " . 2>&1";
        $output = shell_exec($command);
        
        // Clean up temp directory
        $this->deleteDirectory($tempDir);
        
        if (!file_exists($zipFileName)) {
            http_response_code(500);
            die('ZIPファイルの作成に失敗しました。');
        }
        
        // Send zip file
        $encodedFileName = rawurlencode($folder['name'] . '.zip');
        header('Content-Type: application/zip');
        header('Content-Length: ' . filesize($zipFileName));
        header('Content-Disposition: attachment; filename="' . $encodedFileName . '"; filename*=UTF-8\'\'' . $encodedFileName);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Clear output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Output zip file
        readfile($zipFileName);
        
        // Delete temporary file
        @unlink($zipFileName);
        exit;
    }
    
    // Fallback method for parent project zip using shell command
    private function downloadParentProjectZipShell($parent_project_id) {
        // Get parent project info
        $projectQuery = sprintf(
            "SELECT * FROM " . DB_PREFIX . "parent_projects WHERE id = %d",
            $parent_project_id
        );
        $project = $this->fetchOne($projectQuery);
        
        if (!$project) {
            http_response_code(404);
            die('建物プロジェクトが見つかりません。');
        }
        
        // Create temporary directory
        $tempDir = sys_get_temp_dir() . '/zip_' . uniqid();
        mkdir($tempDir, 0755, true);
        
        // Copy root files
        $rootFilesQuery = sprintf(
            "SELECT * FROM " . DB_PREFIX . "project_attachments WHERE parent_project_id = %d AND folder_id IS NULL",
            $parent_project_id
        );
        $rootFiles = $this->fetchAll($rootFilesQuery);
        
        foreach ($rootFiles as $file) {
            $filePath = '..' . $file['file_path'];
            if (file_exists($filePath)) {
                copy($filePath, $tempDir . '/' . $file['original_name']);
            }
        }
        
        // Copy all folders recursively
        $rootFoldersQuery = sprintf(
            "SELECT * FROM " . DB_PREFIX . "project_folders WHERE parent_project_id = %d AND parent_folder_id IS NULL",
            $parent_project_id
        );
        $rootFolders = $this->fetchAll($rootFoldersQuery);
        
        foreach ($rootFolders as $folder) {
            $this->copyFolderToTemp($tempDir, $folder['id'], $folder['name']);
        }
        
        // Create zip using shell command
        $zipFileName = sys_get_temp_dir() . '/parent_project_' . $parent_project_id . '_' . time() . '.zip';
        $command = "cd " . escapeshellarg($tempDir) . " && zip -r " . escapeshellarg($zipFileName) . " . 2>&1";
        $output = shell_exec($command);
        
        // Clean up temp directory
        $this->deleteDirectory($tempDir);
        
        if (!file_exists($zipFileName)) {
            http_response_code(500);
            die('ZIPファイルの作成に失敗しました。');
        }
        
        // Send zip file
        $encodedFileName = rawurlencode($project['project_name'] . '_attachments.zip');
        header('Content-Type: application/zip');
        header('Content-Length: ' . filesize($zipFileName));
        header('Content-Disposition: attachment; filename="' . $encodedFileName . '"; filename*=UTF-8\'\'' . $encodedFileName);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Clear output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Output zip file
        readfile($zipFileName);
        
        // Delete temporary file
        @unlink($zipFileName);
        exit;
    }
    
    private function copyFolderToTemp($tempDir, $folder_id, $basePath) {
        $targetDir = $tempDir . '/' . $basePath;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        // Get files in this folder
        $fileQuery = sprintf(
            "SELECT * FROM " . DB_PREFIX . "project_attachments WHERE folder_id = %d",
            $folder_id
        );
        $files = $this->fetchAll($fileQuery);
        
        foreach ($files as $file) {
            $filePath = '..' . $file['file_path'];
            if (file_exists($filePath)) {
                $targetPath = $targetDir . '/' . $file['original_name'];
                copy($filePath, $targetPath);
            }
        }
        
        // Get subfolders
        $subfolderQuery = sprintf(
            "SELECT * FROM " . DB_PREFIX . "project_folders WHERE parent_folder_id = %d",
            $folder_id
        );
        $subfolders = $this->fetchAll($subfolderQuery);
        
        foreach ($subfolders as $subfolder) {
            $this->copyFolderToTemp($tempDir, $subfolder['id'], $basePath . '/' . $subfolder['name']);
        }
    }
    
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
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

    // Note methods (メモ for parent project)
    function addNote($params = null) {
        require_once('parentprojectnote.php');
        $noteModel = new ParentProjectNote();
        return $noteModel->create($params);
    }

    function updateNote($params = null) {
        require_once('parentprojectnote.php');
        $noteModel = new ParentProjectNote();
        return $noteModel->update($params);
    }

    function deleteNote($params = null) {
        require_once('parentprojectnote.php');
        $noteModel = new ParentProjectNote();
        return $noteModel->delete($params);
    }

    function getNotes($params = null) {
        require_once('parentprojectnote.php');
        $noteModel = new ParentProjectNote();
        return $noteModel->list($params);
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

    /**
     * Update customer info for all parent projects with the same customer_id
     */
    function updateCustomerInfoForAllProjects($params = null) {
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        $company_name = isset($_POST['company_name']) ? $this->validateUTF8MB4($_POST['company_name']) : '';
        $branch_name = isset($_POST['branch_name']) ? $this->validateUTF8MB4($_POST['branch_name']) : '';
        $contact_name = isset($_POST['contact_name']) ? $this->validateUTF8MB4($_POST['contact_name']) : '';
        
        if (!$customer_id) {
            return ['status' => 'error', 'message' => 'Customer ID is required'];
        }
        
        $data = [
            'company_name' => $company_name,
            'branch_name' => $branch_name,
            'contact_name' => $contact_name,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        try {
            // Use custom SQL for WHERE customer_id since query_update doesn't support this condition
            $sql = sprintf(
                "UPDATE %s SET 
                    company_name = '%s',
                    branch_name = '%s', 
                    contact_name = '%s',
                    updated_at = '%s'
                WHERE customer_id = %d",
                $this->table,
                $this->quote($company_name),
                $this->quote($branch_name),
                $this->quote($contact_name),
                date('Y-m-d H:i:s'),
                $customer_id
            );
            
            $result = $this->query($sql);
            
            if ($result) {
                $affected_rows = mysqli_affected_rows($this->handler);
                
                return [
                    'status' => 'success', 
                    'message' => "Updated {$affected_rows} parent project(s)",
                    'affected_rows' => $affected_rows
                ];
            } else {
                return ['status' => 'error', 'message' => 'Failed to update parent projects'];
            }
        } catch (Exception $e) {
            error_log("Exception in updateCustomerInfoForAllProjects: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Command palette: quick search parent projects (construction number, id, customer, branch).
     */
    function paletteSearch() {
        if (empty($_SESSION['show_project'])) {
            return [];
        }
        $q = $this->getPaletteSearchQuery();
        if ($q === '') {
            return [];
        }
        $whereArr = ["p.status != 'deleted'"];
        $searchWhere = $this->buildPaletteSearchWhere($q, [
            'p.company_name',
            'p.branch_name',
            'p.contact_name',
            'p.construction_number',
            'p.project_name',
            'p.project_number',
            'CAST(p.id AS CHAR)',
        ], 'p.id');
        if ($searchWhere !== '') {
            $whereArr[] = $searchWhere;
        }
        $where = 'WHERE ' . implode(' AND ', $whereArr);
        $query = sprintf(
            "SELECT p.id, p.project_name, p.construction_number, p.company_name, p.branch_name, p.project_number
             FROM %s p
             %s
             ORDER BY p.updated_at DESC
             LIMIT 10",
            $this->table,
            $where
        );
        return $this->fetchAll($query);
    }
}
?> 