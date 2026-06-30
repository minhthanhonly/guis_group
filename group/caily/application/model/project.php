<?php

class Project extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'projects';
        // Add integer fields that should not be quoted
        $this->donotquote = array_merge($this->donotquote, array(
            'parent_folder_id', 'folder_id', 'project_id', 'file_size', 'amount',
            'invoice_amount', 'payment_amount', 'version'
        ));
        $this->schema = array(
            'id' => array('except' => array('search')),
            'parent_project_id' => array(),
            'project_number' => array(),
            'name' => array(),
            'description' => array(),
            'priority' => array(), //low, medium, high, urgent
            'status' => array(), //draft, open, in_progress, completed, paused, cancelled, deleted
            'previous_status' => array(), //stores the previous status before being cancelled
            'start_date' => array(), //timestamp
            'end_date' => array(), //timestamp
            'actual_start_date' => array(), //timestamp
            'actual_end_date' => array(), //timestamp
            'tantou' => array(), //CAILY or GUIS
            'caily_nouki' => array(), //CAILY納期
            'caily_nouki_status' => array(), //CAILY納期状況
            'guis_nouki' => array(), //GUIS納期
            'guis_nouki_status' => array(), //GUIS納期状況
            'created_by' => array(), //userid
            'updated_by' => array(), //userid
            'version' => array(),
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
            'estimate_status' => array(), //未発行, 発行済
            'estimate_date' => array(),
            'estimate_number' => array(),
            'invoice_status' => array(), //未発行, 発行済
            'invoice_date' => array(),
            'invoice_amount' => array(),
            'invoice_number' => array(),
            'payment_status' => array(), //未入金, 入金済, 入金拒否
            'payment_date' => array(),
            'payment_amount' => array(),
            'receipt_number' => array(),
            'payment_note' => array(),
            'tags' => array(), //project tags for search and organization
            'is_kadai' => array(), //boolean field to identify child projects
        );
        $this->connect();
    }

    /**
     * Customer join: one row per parent (company_name + contact_name), avoids duplicate projects.
     */
    private function getProjectCustomerJoinSql() {
        return "LEFT JOIN " . DB_PREFIX . "customer c ON c.id = (
            SELECT MIN(c2.id) FROM " . DB_PREFIX . "customer c2
            WHERE c2.company_name = pp.company_name AND TRIM(COALESCE(c2.name,'')) = TRIM(COALESCE(pp.contact_name,''))
        )";
    }

    private function getProjectListCustomerJoinSql() {
        return "
            LEFT JOIN " . DB_PREFIX . "customer pc ON pc.id = SUBSTRING_INDEX(p.customer_id, ',', 1)
            LEFT JOIN " . DB_PREFIX . "customer pp_c ON pp_c.id = SUBSTRING_INDEX(pp.customer_id, ',', 1)";
    }

    private function getProjectListGuisUserJoinSql() {
        return "LEFT JOIN " . DB_PREFIX . "user gu ON gu.userid = COALESCE(NULLIF(TRIM(p.guis_receiver), ''), NULLIF(TRIM(pp.guis_receiver), ''))";
    }

    private function sqlEffectiveCompanyName() {
        return "COALESCE(NULLIF(TRIM(pc.company_name), ''), NULLIF(TRIM(pp_c.company_name), ''), NULLIF(TRIM(pp.company_name), ''))";
    }

    private function sqlEffectiveBranchName() {
        return "COALESCE(NULLIF(TRIM(pc.branch), ''), NULLIF(TRIM(pp_c.branch), ''), NULLIF(TRIM(pp.branch_name), ''))";
    }

    private function sqlEffectiveContactName() {
        return "COALESCE(NULLIF(TRIM(pc.name), ''), NULLIF(TRIM(pp_c.name), ''), NULLIF(TRIM(pp.contact_name), ''))";
    }

    private function sqlEffectiveCustomerName() {
        $contact = $this->sqlEffectiveContactName();
        $title = "COALESCE(NULLIF(TRIM(pc.title), ''), NULLIF(TRIM(pp_c.title), ''), '')";
        return "TRIM(CONCAT({$contact}, ' ', {$title}))";
    }

    private function appendTeamFilterWhere(array &$whereArr, $filterTeamRaw) {
        if ($filterTeamRaw === '' || $filterTeamRaw === null) {
            return;
        }
        $teamParts = array_values(array_unique(array_filter(array_map('trim', explode(',', (string)$filterTeamRaw)))));
        if (empty($teamParts)) {
            return;
        }
        $teamConds = array();
        foreach ($teamParts as $teamPart) {
            if ($teamPart === 'none') {
                $teamConds[] = "(p.teams IS NULL OR p.teams = '')";
            } elseif (ctype_digit($teamPart)) {
                $teamConds[] = 'FIND_IN_SET(' . intval($teamPart) . ', p.teams) > 0';
            }
        }
        if (!empty($teamConds)) {
            $whereArr[] = '(' . implode(' OR ', $teamConds) . ')';
        }
    }

    /**
     * ORDER BY expression for project list (computed columns are not p.* fields).
     */
    function resolveProjectListOrderExpression($order_column, $user_id) {
        $order_column = preg_replace('/[^a-z0-9_]/i', '', (string)$order_column);
        $user_id = intval($user_id);

        if ($order_column === 'is_favorite') {
            return sprintf(
                'CASE WHEN EXISTS (SELECT 1 FROM %sproject_favorites f WHERE f.project_id = p.id AND f.user_id = %d) THEN 1 ELSE 0 END',
                DB_PREFIX,
                $user_id
            );
        }

        if ($order_column === 'parent_branch_name') {
            return $this->sqlEffectiveBranchName();
        }
        if ($order_column === 'parent_guis_receiver') {
            return 'gu.realname';
        }

        $parentColumns = array(
            'parent_construction_number' => 'pp.construction_number',
            'parent_scale' => 'pp.scale',
            'parent_type1' => 'pp.type1',
            'parent_type2' => 'pp.type2',
        );
        if (isset($parentColumns[$order_column])) {
            return $parentColumns[$order_column];
        }

        if ($order_column === 'department_name') {
            return 'd.name';
        }

        $projectColumns = array(
            'id', 'name', 'description', 'priority', 'status', 'start_date', 'end_date',
            'actual_start_date', 'actual_end_date', 'tantou', 'caily_nouki', 'caily_nouki_status',
            'guis_nouki', 'guis_nouki_status', 'progress', 'amount', 'project_order_type',
            'project_number', 'created_at', 'updated_at', 'teams', 'building_size', 'is_kadai',
        );
        if (in_array($order_column, $projectColumns, true)) {
            return 'p.' . $order_column;
        }

        return 'p.end_date';
    }

    /**
     * Attach favorites, members, confirmation notes, task counts in batched queries (avoids per-row subqueries).
     */
    private function attachProjectListAggregates(array &$data, $userId) {
        if (empty($data)) {
            return;
        }
        $projectIds = array_values(array_unique(array_map('intval', array_column($data, 'id'))));
        $projectIds = array_filter($projectIds, function ($id) { return $id > 0; });
        if (empty($projectIds)) {
            return;
        }
        $idsList = implode(',', $projectIds);
        $userId = intval($userId);

        $favoriteSet = [];
        $favRows = $this->fetchAll(sprintf(
            "SELECT project_id FROM %sproject_favorites WHERE user_id = %d AND project_id IN (%s)",
            DB_PREFIX,
            $userId,
            $idsList
        ));
        foreach ($favRows as $row) {
            $favoriteSet[(int)$row['project_id']] = true;
        }

        $membersByProject = $this->fetchProjectMembersByProjectIds($projectIds);

        $notesCaily = [];
        $notesGuis = [];
        $this->query("SET SESSION group_concat_max_len = 1048576");
        $noteRows = $this->fetchAll(sprintf(
            "SELECT project_id, needs_confirmation, id, content, is_important
             FROM %sproject_notes
             WHERE project_id IN (%s) AND needs_confirmation IN (1, 2)
             ORDER BY project_id, needs_confirmation, is_important DESC, created_at DESC",
            DB_PREFIX,
            $idsList
        ));
        foreach ($noteRows as $row) {
            $pid = (int)$row['project_id'];
            $part = $row['id'] . '_:_' . ($row['content'] ?? '') . '_:_' . ($row['is_important'] ?? 0);
            if ((int)$row['needs_confirmation'] === 1) {
                if (!isset($notesCaily[$pid])) {
                    $notesCaily[$pid] = [];
                }
                $notesCaily[$pid][] = $part;
            } elseif ((int)$row['needs_confirmation'] === 2) {
                if (!isset($notesGuis[$pid])) {
                    $notesGuis[$pid] = [];
                }
                $notesGuis[$pid][] = $part;
            }
        }

        $taskStats = [];
        $taskRows = $this->fetchAll(sprintf(
            "SELECT project_id,
                    COUNT(*) as task_count,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_task_count
             FROM %stasks
             WHERE project_id IN (%s)
             GROUP BY project_id",
            DB_PREFIX,
            $idsList
        ));
        foreach ($taskRows as $row) {
            $taskStats[(int)$row['project_id']] = $row;
        }

        foreach ($data as &$project) {
            $pid = (int)$project['id'];
            $project['is_favorite'] = isset($favoriteSet[$pid]) ? 1 : 0;
            $project['assignment_id'] = isset($membersByProject[$pid]['member'])
                ? implode('|', $membersByProject[$pid]['member']) : '';
            $project['manager_id'] = isset($membersByProject[$pid]['manager'])
                ? implode('|', $membersByProject[$pid]['manager']) : '';
            $project['confirmation_notes_caily'] = isset($notesCaily[$pid])
                ? implode('_|_', $notesCaily[$pid]) : '';
            $project['confirmation_notes_guis'] = isset($notesGuis[$pid])
                ? implode('_|_', $notesGuis[$pid]) : '';
            if (isset($taskStats[$pid])) {
                $project['task_count'] = (int)$taskStats[$pid]['task_count'];
                $project['completed_task_count'] = (int)$taskStats[$pid]['completed_task_count'];
            } else {
                $project['task_count'] = 0;
                $project['completed_task_count'] = 0;
            }
        }
        unset($project);
    }

    private function attachProjectDetailAggregates(array &$project, $userId) {
        $projectId = (int)$project['id'];
        $userId = (int)$userId;
        if ($projectId <= 0) {
            return;
        }
        $stats = $this->fetchOne(sprintf(
            "SELECT
                (SELECT COUNT(*) FROM %stasks WHERE project_id = %d) as task_count,
                (SELECT COALESCE(SUM(COALESCE(pd.drawing_count, 1)), 0) FROM %sproject_drawings pd WHERE pd.project_id = %d) as drawing_count,
                (SELECT COUNT(*) FROM %sproject_members WHERE project_id = %d) as member_count,
                (SELECT 1 FROM %sproject_favorites f WHERE f.project_id = %d AND f.user_id = %d LIMIT 1) as is_favorite",
            DB_PREFIX,
            $projectId,
            DB_PREFIX,
            $projectId,
            DB_PREFIX,
            $projectId,
            DB_PREFIX,
            $projectId,
            $userId
        ));
        $project['task_count'] = (int)($stats['task_count'] ?? 0);
        $project['drawing_count'] = (int)($stats['drawing_count'] ?? 0);
        $project['member_count'] = (int)($stats['member_count'] ?? 0);
        $project['is_favorite'] = !empty($stats['is_favorite']) ? 1 : 0;
    }

    private function attachProjectMemberAggregates(array &$data) {
        if (empty($data)) {
            return;
        }
        $projectIds = array_values(array_unique(array_map('intval', array_column($data, 'id'))));
        $membersByProject = $this->fetchProjectMembersByProjectIds($projectIds);
        foreach ($data as &$project) {
            $pid = (int)$project['id'];
            $project['assignment_id'] = isset($membersByProject[$pid]['member'])
                ? implode('|', $membersByProject[$pid]['member']) : '';
            $project['manager_id'] = isset($membersByProject[$pid]['manager'])
                ? implode('|', $membersByProject[$pid]['manager']) : '';
        }
        unset($project);
    }

    function list() {
        $draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;
        $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
        $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $order_column = isset($_GET['order_column']) ? $_GET['order_column'] : 'end_date';
        $order_dir = isset($_GET['order_dir']) ? $_GET['order_dir'] : 'ASC';
        $status = isset($_GET['status']) ? $_GET['status'] : 'all';
        
        $whereArr = [];
        
        $user_id = $_SESSION['id'];
        
        // Filter "My Projects" - show only projects where user is member or manager
        if (isset($_GET['my_projects']) && $_GET['my_projects'] == '1') {
            $whereArr[] = sprintf(
                "EXISTS (
                    SELECT 1 FROM " . DB_PREFIX . "project_members pm 
                    WHERE pm.project_id = p.id AND pm.user_id = %d AND pm.role IN ('member', 'manager')
                )",
                $user_id
            );
        }
        
        // Filter favorites only
        $favoritesOnly = isset($_GET['favorites_only']) && $_GET['favorites_only'] == '1';
        if ($favoritesOnly) {
            $whereArr[] = sprintf(
                "EXISTS (SELECT 1 FROM " . DB_PREFIX . "project_favorites f WHERE f.project_id = p.id AND f.user_id = %d)",
                $user_id
            );
        }

        if (isset($_GET['department_id'])) {
            $whereArr[] = sprintf("p.department_id = %d", intval($_GET['department_id']));
        }
        
        // Nếu có filterKeyword hoặc filterProjectId thì chỉ áp dụng điều kiện tìm kiếm, bỏ qua các filter nâng cao khác
        $hasKeyword = isset($_GET['filterKeyword']) && trim($_GET['filterKeyword']) !== '';
        $hasProjectId = !empty($_GET['filterProjectId']) && intval($_GET['filterProjectId']) > 0;
        $filterByIdOrKeyword = $hasKeyword || $hasProjectId;
        $hasStatus = isset($_GET['status']) && $_GET['status'] !== '' && $_GET['status'] !== 'all';
        $showInactive = isset($_GET['showInactive']) && $_GET['showInactive'] == '1';
        
        // Filter by project ID (案件ID)
        if ($hasProjectId) {
            $whereArr[] = "p.id = " . intval($_GET['filterProjectId']);
        }
        
        if ($filterByIdOrKeyword) {
            // Khi filter theo ID hoặc keyword: chỉ áp dụng điều kiện keyword (nếu có), bỏ qua các filter nâng cao khác
            if ($hasKeyword) {
                $whereArr[] = $this->buildKeywordFilterWhere($_GET['filterKeyword']);
            }
            // Status: khi filter theo ID hoặc keyword thì chỉ ẩn deleted, không áp dụng status dropdown và showInactive
            $whereArr[] = "p.status != 'deleted'";
        } else {
            // Status filter (chỉ áp dụng khi không có filterByIdOrKeyword)
            if (isset($_GET['status']) && $_GET['status'] != 'all') {
                $whereArr[] = sprintf("p.status = '%s'", $_GET['status']);
            } else {
                $whereArr[] = "p.status != 'deleted'";
            }
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
            // Filter by specific dates == today (start_date / CAILY納期 / GUIS納期 / end_date / custom datetime fields)
            if (isset($_GET['filterToday']) && $_GET['filterToday'] !== '') {
                $val = $_GET['filterToday'];
                if ($val === 'start_today') {
                    $whereArr[] = "DATE(p.start_date) = CURDATE()";
                } elseif ($val === 'caily_today') {
                    $whereArr[] = "DATE(p.caily_nouki) = CURDATE() AND p.status NOT IN ('completed', 'cancelled', 'deleted')";
                } elseif ($val === 'guis_today') {
                    $whereArr[] = "DATE(p.guis_nouki) = CURDATE() AND p.status NOT IN ('completed', 'cancelled', 'deleted')";
                } elseif ($val === 'end_today') {
                    $whereArr[] = "DATE(p.end_date) = CURDATE() AND p.status NOT IN ('completed', 'cancelled', 'deleted')";
                } elseif (strpos($val, 'cf:') === 0) {
                    // Custom datetime field: value = 'cf:' + encodeURIComponent(label)
                    $encoded = substr($val, 3);
                    $label = urldecode($encoded);
                    if ($label !== '') {
                        $labelEsc = $this->escape($label);
                        // custom_fields.value có thể dùng các format như 'Y/m/d' hoặc 'Y/n/j' → tạo nhiều pattern theo ngày hôm nay
                        $y = date('Y');
                        $mNum = date('n'); // 1-12
                        $dNum = date('j'); // 1-31
                        $mm = sprintf('%02d', $mNum);
                        $dd = sprintf('%02d', $dNum);
                        $patterns = array(
                            $y . '/' . $mm . '/' . $dd, // Y/mm/dd
                            $y . '/' . $mNum . '/' . $dNum, // Y/m/d
                            $y . '/' . $mm . '/' . $dNum, // Y/mm/d
                            $y . '/' . $mNum . '/' . $dd, // Y/m/dd
                        );
                        $likeParts = array();
                        foreach ($patterns as $patt) {
                            $pEsc = $this->escape($patt);
                            // Tìm đúng object có label tương ứng VÀ value bắt đầu bằng ngày hôm nay (bất kể có giờ hay không)
                            // Ví dụ: ..."label":"構造データ送付 (CAILY)","value":"2026/3/17"...
                            // hoặc ..."value":"2026/03/17 09:00"...
                            $likeParts[] = "(p.custom_fields LIKE '%\"label\":\"" . $labelEsc . "\",\"value\":\"" . $pEsc . "%')";
                        }
                        if (!empty($likeParts)) {
                            $whereArr[] = "(p.custom_fields IS NOT NULL AND p.custom_fields != '' AND (" . implode(' OR ', $likeParts) . "))";
                        }
                    }
                }
            }
            // Filter by project_order_type (契約図 / 新規 / 修正 / その他)
            if (isset($_GET['filterProjectOrderType']) && $_GET['filterProjectOrderType'] !== '') {
                $type = $_GET['filterProjectOrderType'];
                if ($type === 'contract') {
                    $whereArr[] = "p.project_order_type LIKE '%契約図%'";
                } elseif ($type === 'new') {
                    // 新規: chứa 実施図 hoặc 新規, nhưng KHÔNG chứa 修正 (loại các case như '新規修正')
                    $whereArr[] = "((p.project_order_type LIKE '%実施図%' OR p.project_order_type LIKE '%新規%') 
                        AND p.project_order_type NOT LIKE '%修正%')";
                } elseif ($type === 'edit') {
                    $whereArr[] = "p.project_order_type LIKE '%修正%'";
                } elseif ($type === 'other') {
                    $whereArr[] = "(p.project_order_type IS NOT NULL AND p.project_order_type != '' 
                        AND p.project_order_type NOT LIKE '%契約図%' 
                        AND p.project_order_type NOT LIKE '%実施図%' 
                        AND p.project_order_type NOT LIKE '%新規%' 
                        AND p.project_order_type NOT LIKE '%修正%')";
                }
            }
            // Filter by team (teams column chứa id team, dạng comma-separated; hỗ trợ nhiều team)
            if (isset($_GET['filterTeam']) && $_GET['filterTeam'] !== '') {
                $this->appendTeamFilterWhere($whereArr, $_GET['filterTeam']);
            }
            // Filter by tantou (担当: CAILY / GUIS)
            if (isset($_GET['filterTantou']) && $_GET['filterTantou'] !== '') {
                $tantou = $this->escape($_GET['filterTantou']);
                $whereArr[] = "p.tantou = '".$tantou."'";
            }
            // Filter projects without start_date or end_date
            if (isset($_GET['filterNoDates']) && $_GET['filterNoDates'] === '1') {
                $whereArr[] = "(p.start_date IS NULL OR p.end_date IS NULL)";
            }
            // Điều kiện mặc định: nếu không có status và không bật showInactive thì chỉ hiển thị active
            if (!$hasStatus && !$showInactive) {
                $whereArr[] = "p.status NOT IN ('completed', 'cancelled', 'deleted')";
            }
            
            // if($status != 'cancelled') {
            //    // Mặc định không hiển thị những dự án có is_kadai = 1, trừ khi showKadai = 1
            //     // $showKadai = isset($_GET['showKadai']) && $_GET['showKadai'] == '1';
            //     // if (!$showKadai) {
            //     //     $whereArr[] = "p.is_kadai != 1";
            //     // }
            // }
            
        }

        $where = implode(" AND ", $whereArr);
        if (!empty($where)) {
            $where = " WHERE " . $where;
        }

        // Sắp xếp ưu tiên nếu showInactive=1
        $orderBy = '';
        
        // Sắp xếp status theo thứ tự giống JS: draft, open, confirming, quotation, contract, in_progress, completed, paused, cancelled
        $statusOrder = "CASE p.status 
            WHEN 'draft' THEN 1 
            WHEN 'open' THEN 2 
            WHEN 'confirming' THEN 3 
            WHEN 'quotation' THEN 4 
            WHEN 'contract' THEN 5 
            WHEN 'in_progress' THEN 6 
            WHEN 'completed' THEN 9 
            WHEN 'paused' THEN 7 
            WHEN 'cancelled' THEN 8 
            ELSE 10 
        END";
        
        $order_dir = strtoupper($order_dir) === 'DESC' ? 'DESC' : 'ASC';
        $orderExpr = $this->resolveProjectListOrderExpression($order_column, $user_id);
        if ($order_column === 'status') {
            $orderBy = sprintf('ORDER BY %s %s', $statusOrder, $order_dir);
        } else {
            $orderBy = sprintf('ORDER BY %s ASC, %s %s', $statusOrder, $orderExpr, $order_dir);
        }
        
        if (isset($_GET['showInactive']) && $_GET['showInactive'] == '1') {
            // Active lên trước, sau đó mới completed/cancelled/deleted, rồi mới sắp xếp end_date, status
            $orderBy .= ", (CASE WHEN p.status IN ('completed','cancelled','deleted') THEN 1 ELSE 0 END) ASC, p.end_date ASC";
        }

        $listJoins = $this->getProjectListCustomerJoinSql() . "\n            " . $this->getProjectListGuisUserJoinSql();

        // Get total records count
        $totalQuery = "SELECT COUNT(*) as count FROM {$this->table} p
        JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id
        LEFT JOIN " . DB_PREFIX . "parent_projects pp ON p.parent_project_id = pp.id
        " . $listJoins . "
        " . $where;
        $totalRecords = $this->fetchOne($totalQuery)['count'];
        $filteredRecords = $totalRecords;

        // Get data for current page
        $query = sprintf(
            "SELECT p.*, d.name as department_name,
            %s as effective_company_name,
            %s as effective_contact_name,
            %s as parent_branch_name,
            pp.branch_name as parent_project_branch_name,
            NULLIF(TRIM(pc.branch), '') as project_branch_name,
            %s as customer_name,
            COALESCE(pc.category_id, pp_c.category_id) as category_id,
            pp.company_name as parent_company_name, pp.contact_name as parent_contact_name, pp.construction_number as parent_construction_number,
            pp.scale as parent_scale, pp.type1 as parent_type1, pp.type2 as parent_type2,
            gu.realname as parent_guis_receiver
            FROM {$this->table} p
            JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id
            LEFT JOIN " . DB_PREFIX . "parent_projects pp ON p.parent_project_id = pp.id
            " . $listJoins . "
            %s
            %s
            LIMIT %d, %d",
            $this->sqlEffectiveCompanyName(),
            $this->sqlEffectiveContactName(),
            $this->sqlEffectiveBranchName(),
            $this->sqlEffectiveCustomerName(),
            $where,
            $orderBy,
            $start,
            $length
        );

        $data = $this->fetchAll($query);
        $this->attachProjectListAggregates($data, $user_id);
        
        // Set default quotation status for projects without quotations
        foreach ($data as &$project) {
            if (empty($project['quotation_status'])) {
                $project['quotation_status'] = '未発行';
            }
        }
        unset($project);

        // Notes by display_column for each project (để hiển thị note dưới ô cột tương ứng)
        $projectIds = array_filter(array_column($data, 'id'));
        $notesByProject = [];
        if (!empty($projectIds)) {
            $idsList = implode(',', array_map('intval', $projectIds));
            $notesTable = DB_PREFIX . 'project_notes';
            $notesRows = $this->fetchAll(
                "SELECT project_id, display_column, id, content, is_important FROM {$notesTable} " .
                "WHERE project_id IN ({$idsList}) AND display_column IS NOT NULL AND TRIM(display_column) != '' " .
                "ORDER BY is_important DESC, created_at DESC"
            );
            foreach ($notesRows as $nr) {
                $pid = (int) $nr['project_id'];
                $col = trim((string) ($nr['display_column'] ?? ''));
                if ($col === '') continue;
                if (!isset($notesByProject[$pid])) $notesByProject[$pid] = [];
                if (!isset($notesByProject[$pid][$col])) $notesByProject[$pid][$col] = [];
                $notesByProject[$pid][$col][] = ['id' => $nr['id'], 'content' => $nr['content'] ?? '', 'is_important' => $nr['is_important'] ?? 0];
            }
        }
        foreach ($data as &$project) {
            $project['notes_by_display_column'] = isset($notesByProject[$project['id']]) ? $notesByProject[$project['id']] : [];
        }
        unset($project);

        return array(
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data
        );
    }
    
    function list_kadai() {
        $whereArr = [];

        $department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : null;
        
        // Add permission check
        $user_id = $_SESSION['userid'];
        // $managerIds = $this->getDepartmentManagers($department_id);

        // if($_SESSION['authority'] != 'administrator' && !in_array($user_id, $managerIds)){
        //     return array(
        //         'status' => 'error',
        //         'message' => '権限がありません'
        //     );
        // }

        
        // Only show kadai projects
        $whereArr[] = "p.is_kadai = 1";
        
        // Exclude deleted and cancelled projects
        $whereArr[] = "p.status NOT IN ('deleted', 'cancelled')";

        // If department_id is set, add the department_id to the where clause
        if ($department_id) {
            $whereArr[] = sprintf("p.department_id = %d", $department_id);
        }
        
        $where = implode(" AND ", $whereArr);
        if (!empty($where)) {
            $where = " WHERE " . $where;
        }
        
        // Get data for kadai projects
        $query = sprintf(
            "SELECT p.*, d.name as department_name,
            c.name as contact_name, c.company_name, c.category_id as category_id, c.branch as branch_name,
            CONCAT(c.name, ' ', c.title) as customer_name,
            pp.company_name as parent_company_name, pp.contact_name as parent_contact_name
            FROM {$this->table} p 
            JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id
            LEFT JOIN " . DB_PREFIX . "parent_projects pp ON p.parent_project_id = pp.id
            " . $this->getProjectCustomerJoinSql() . "
            %s
            ORDER BY p.created_at DESC
            LIMIT 2000",
            $where
        );
        
        $data = $this->fetchAll($query);
        
        return array(
            'status' => 'success',
            'data' => $data
        );
    }

    function listForGantt() {
        $whereArr = [];
        // Add permission check + "my projects" filter
        $user_id = $_SESSION['id'];
        $myProjects = isset($_GET['my_projects']) && $_GET['my_projects'] == '1';
        $is_department_manager = false;
        if (isset($_GET['department_id'])) {
            $department_id = $_GET['department_id'];
            $query = sprintf(
                "SELECT COUNT(id) as count FROM " . DB_PREFIX . "user_department WHERE userid = '%s' AND department_id = %d AND (project_manager = 1 OR project_director = 1 OR project_director_stat = 1 OR project_director_view = 1 OR project_director_edit = 1)",
                $_SESSION['userid'],
                $department_id);
            $is_department_manager = $this->fetchOne($query)['count'] > 0;
        }
        // Nếu bật "私の案件" thì luôn chỉ lấy dự án do mình tạo hoặc là member
        // Nếu không bật, thì chỉ non-admin, non-manager mới bị giới hạn như cũ
        if ($myProjects) {
            $whereArr[] = sprintf(
                "(p.created_by = %d OR EXISTS (
                    SELECT 1 FROM " . DB_PREFIX . "project_members pm 
                    WHERE pm.project_id = p.id AND pm.user_id = %d
                ))",
                $user_id,
                $user_id
            );
        } elseif ($_SESSION['authority'] != 'administrator' && !$is_department_manager) {
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
        // Filter by team_id if provided
        if (isset($_GET['team_id']) && $_GET['team_id'] !== '') {
            $team_id = intval($_GET['team_id']);
            $whereArr[] = sprintf("FIND_IN_SET(%d, p.teams) > 0", $team_id);
        }
        $hasKeyword = isset($_GET['filterKeyword']) && trim($_GET['filterKeyword']) !== '';
        $hasProjectId = !empty($_GET['filterProjectId']) && intval($_GET['filterProjectId']) > 0;
        $filterByIdOrKeyword = $hasKeyword || $hasProjectId;

        // Status: khi filter theo ID hoặc keyword thì chỉ ẩn deleted, không áp dụng status dropdown và showInactive
        if ($filterByIdOrKeyword) {
            $whereArr[] = "p.status != 'deleted'";
        } else {
            $showInactive = isset($_GET['showInactive']) && $_GET['showInactive'] === '1';
            if (isset($_GET['status'])) {
                if ($_GET['status'] == 'all') {
                    if ($showInactive) {
                        $whereArr[] = "p.status != 'deleted'";
                    } else {
                        $whereArr[] = "p.status NOT IN ('deleted','completed','cancelled')";
                    }
                } else if ($_GET['status'] == 'active') {
                    $whereArr[] = "p.status NOT IN ('deleted', 'draft', 'completed', 'cancelled')";
                } else {
                    $whereArr[] = sprintf("p.status = '%s'", $this->escape($_GET['status']));
                }
            } else {
                $whereArr[] = "p.status != 'deleted'";
            }
        }
        // Filter by project ID (案件ID)
        if ($hasProjectId) {
            $whereArr[] = "p.id = " . intval($_GET['filterProjectId']);
        }
        // Khi filter theo ID hoặc keyword: chỉ áp dụng điều kiện keyword (nếu có), bỏ qua các filter nâng cao khác
        if ($filterByIdOrKeyword) {
            if ($hasKeyword) {
                $whereArr[] = $this->buildKeywordFilterWhere($_GET['filterKeyword']);
            }
        } else {
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
            // Filter by project_order_type (契約図 / 新規 / 修正 / その他)
            if (isset($_GET['filterProjectOrderType']) && $_GET['filterProjectOrderType'] !== '') {
                $type = $_GET['filterProjectOrderType'];
                if ($type === 'contract') {
                    $whereArr[] = "p.project_order_type LIKE '%契約図%'";
                } elseif ($type === 'new') {
                    $whereArr[] = "((p.project_order_type LIKE '%実施図%' OR p.project_order_type LIKE '%新規%') 
                        AND p.project_order_type NOT LIKE '%修正%')";
                } elseif ($type === 'edit') {
                    $whereArr[] = "p.project_order_type LIKE '%修正%'";
                } elseif ($type === 'other') {
                    $whereArr[] = "(p.project_order_type IS NOT NULL AND p.project_order_type != '' 
                        AND p.project_order_type NOT LIKE '%契約図%' 
                        AND p.project_order_type NOT LIKE '%実施図%' 
                        AND p.project_order_type NOT LIKE '%新規%' 
                        AND p.project_order_type NOT LIKE '%修正%')";
                }
            }
            // Filter by team (teams column chứa id team, dạng comma-separated) - advanced filter
            if (isset($_GET['filterTeam']) && $_GET['filterTeam'] !== '') {
                $this->appendTeamFilterWhere($whereArr, $_GET['filterTeam']);
            }
            // Filter by tantou (担当: CAILY / GUIS)
            if (isset($_GET['filterTantou']) && $_GET['filterTantou'] !== '') {
                $tantou = $this->escape($_GET['filterTantou']);
                $whereArr[] = "p.tantou = '".$tantou."'";
            }
            // Filter projects without start_date or end_date
            if (isset($_GET['filterNoDates']) && $_GET['filterNoDates'] === '1') {
                $whereArr[] = "(p.start_date IS NULL OR p.end_date IS NULL)";
            }
        }
        // 表示期間: chỉ lấy dự án giao với khoảng gantt_start_date ~ gantt_end_date (hoặc 期間未定)
        if (!empty($_GET['gantt_start_date']) && !empty($_GET['gantt_end_date'])) {
            $gantt_start = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['gantt_start_date']) ? $_GET['gantt_start_date'] : null;
            $gantt_end   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['gantt_end_date'])   ? $_GET['gantt_end_date']   : null;
            if ($gantt_start && $gantt_end) {
                $gs = "'" . $this->quote($gantt_start) . "'";
                $ge = "'" . $this->quote($gantt_end) . "'";
                $whereArr[] = "(
                    (p.start_date IS NOT NULL AND p.end_date IS NOT NULL AND DATE(p.start_date) <= $ge AND DATE(p.end_date) >= $gs)
                    OR (p.start_date IS NOT NULL AND p.end_date IS NULL AND DATE(p.start_date) <= $ge)
                    OR (p.start_date IS NULL AND p.end_date IS NOT NULL AND DATE(p.end_date) >= $gs)
                    OR (p.start_date IS NULL AND p.end_date IS NULL)
                )";
            }
        }
        $where = implode(" AND ", $whereArr);
        if (!empty($where)) {
            $where = " WHERE " . $where;
        }
        $listJoins = $this->getProjectListCustomerJoinSql();
        $ganttLimit = 3000;
        $query = sprintf(
            "SELECT p.*, d.name as department_name,
            %s as branch_name,
            %s as contact_name,
            %s as company_name,
            COALESCE(pc.category_id, pp_c.category_id) as category_id,
            %s as customer_name,
            CONCAT_WS(' ', NULLIF(pp.type1, ''), NULLIF(pp.type2, '')) as building_type,
            pp.scale as building_size,
            pp.construction_number as construction_number
            FROM {$this->table} p 
            LEFT JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id
            LEFT JOIN " . DB_PREFIX . "parent_projects pp ON p.parent_project_id = pp.id
            " . $listJoins . "
            %s
            ORDER BY p.start_date ASC, p.created_at DESC
            LIMIT %d",
            $this->sqlEffectiveBranchName(),
            $this->sqlEffectiveContactName(),
            $this->sqlEffectiveCompanyName(),
            $this->sqlEffectiveCustomerName(),
            $where,
            $ganttLimit
        );
        $data = $this->fetchAll($query);
        $this->attachProjectMemberAggregates($data);
        return $data;
    }

    private function escape($str) {
        return $this->quote($str);
    }

    private function buildKeywordFilterWhere($rawKeyword) {
        return $this->buildFlexibleLikeWhere($rawKeyword, [
            'p.name',
            'CAST(p.id AS CHAR)',
            'p.tags',
            $this->sqlEffectiveCompanyName(),
            'pc.company_name_kana',
            'pp_c.company_name_kana',
            $this->sqlEffectiveBranchName(),
            $this->sqlEffectiveContactName(),
            'pc.name_kana',
            'pp_c.name_kana',
            'pp.construction_number',
            'pp.scale',
            'pp.type1',
            'pp.type2',
        ]);
    }

    /**
     * Phase 1.2 – Permission helper for backend.
     * Returns true only if the current session user may edit the given project:
     * - Session authority is 'administrator', or
     * - User is manager of that project (project_members.role = 'manager'), or
     * - User has project_manager for the project's department (user_department.project_manager = 1).
     * Used by AI-triggered and other APIs before any project/task/member write.
     *
     * @param int $project_id
     * @return bool
     */
    public function canUserEditProject($project_id) {
        $project_id = intval($project_id);
        if ($project_id <= 0) {
            return false;
        }
        $project = $this->fetchOne("SELECT id, department_id FROM " . $this->table . " WHERE id = " . $project_id);
        if (!$project || !isset($project['department_id'])) {
            return false;
        }
        $current_user_id = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
        $current_userid = isset($_SESSION['userid']) ? $this->quote($_SESSION['userid']) : '';
        if (!$current_user_id && !$current_userid) {
            return false;
        }
        if (isset($_SESSION['authority']) && $_SESSION['authority'] === 'administrator') {
            return true;
        }
        $memberCheck = $this->fetchOne(
            "SELECT COUNT(*) as c FROM " . DB_PREFIX . "project_members " .
            "WHERE project_id = " . $project_id . " AND user_id = " . $current_user_id . " AND role = 'manager'"
        );
        if ($memberCheck && isset($memberCheck['c']) && (int)$memberCheck['c'] > 0) {
            return true;
        }
        $dept_id = intval($project['department_id']);
        if ($dept_id > 0 && $current_userid) {
            $deptCheck = $this->fetchOne(
                "SELECT COUNT(*) as c FROM " . DB_PREFIX . "user_department " .
                "WHERE department_id = " . $dept_id . " AND userid = '" . $current_userid . "' AND project_manager = 1"
            );
            if ($deptCheck && isset($deptCheck['c']) && (int)$deptCheck['c'] > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true if the current user may edit sensitive project fields (amount, name, start_date, end_date, caily_nouki, guis_nouki, tantou).
     * Only administrator or department project_manager (user_department.project_manager = 1); project member manager is not enough.
     *
     * @param int $project_id
     * @return bool
     */
    public function canUserEditProjectSensitiveFields($project_id) {
        $project_id = intval($project_id);
        if ($project_id <= 0) {
            return false;
        }
        if (isset($_SESSION['authority']) && $_SESSION['authority'] === 'administrator') {
            return true;
        }
        $project = $this->fetchOne("SELECT department_id FROM " . $this->table . " WHERE id = " . $project_id);
        if (!$project || !isset($project['department_id'])) {
            return false;
        }
        $dept_id = intval($project['department_id']);
        $current_userid = isset($_SESSION['userid']) ? $this->escape($_SESSION['userid']) : '';
        if ($dept_id <= 0 || !$current_userid) {
            return false;
        }
        $row = $this->fetchOne(
            "SELECT COUNT(*) as c FROM " . DB_PREFIX . "user_department " .
            "WHERE department_id = " . $dept_id . " AND userid = '" . $current_userid . "' AND project_manager = 1"
        );
        return $row && isset($row['c']) && (int)$row['c'] > 0;
    }

    /**
     * Phase 2.2 – Project list/read for AI context.
     * Returns minimal list or single project (id, name, status, department_id, manager_ids, member_count)
     * with existing list/detail permission applied (department filter, admin/department-manager or member visibility).
     *
     * @param array $options ['department_id' => int, 'status' => string, 'limit' => int, 'id' => int for single]
     * @return array
     */
    public function getForAiContext($options = []) {
        $department_id = isset($options['department_id']) ? intval($options['department_id']) : null;
        $status = isset($options['status']) ? trim($options['status']) : null;
        $id = isset($options['id']) ? intval($options['id']) : null;
        $ids = isset($options['ids']) && is_array($options['ids']) ? array_values(array_unique(array_filter(array_map('intval', $options['ids'])))) : [];
        $searchFilters = isset($options['search_filters']) && is_array($options['search_filters']) ? $options['search_filters'] : [];
        
        // Luôn giới hạn ở 20 dự án gần nhất cho display (theo updated_at DESC)
        // Nếu có search filters → vẫn chỉ hiển thị 20 dự án gần nhất
        // Nếu không có search filters → limit theo option hoặc mặc định 20
        if (!empty($searchFilters)) {
            // Có search filters → vẫn chỉ hiển thị 20 dự án gần nhất
            $limit = 20;
        } else {
            // Không có search filters → limit tối đa 20, mặc định 20
            $limit = isset($options['limit']) ? min(20, max(1, intval($options['limit']))) : 20;
        }

        $user_id = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
        $is_admin = (isset($_SESSION['authority']) && $_SESSION['authority'] === 'administrator');
        $is_dept_manager = false;
        if ($department_id > 0 && isset($_SESSION['userid'])) {
            $q = sprintf(
                "SELECT COUNT(id) as c FROM " . DB_PREFIX . "user_department WHERE userid = '%s' AND department_id = %d AND (project_manager = 1 OR project_director = 1 OR project_director_stat = 1 OR project_director_view = 1 OR project_director_edit = 1)",
                $this->quote($_SESSION['userid']),
                $department_id
            );
            $row = $this->fetchOne($q);
            $is_dept_manager = ($row && isset($row['c']) && (int)$row['c'] > 0);
        }
        $whereArr = ["p.status != 'deleted'"];
        if (!$is_admin && !$is_dept_manager) {
            $whereArr[] = sprintf(
                "(p.created_by = %d OR EXISTS (SELECT 1 FROM " . DB_PREFIX . "project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d))",
                $user_id,
                $user_id
            );
        }
        if ($department_id !== null && $department_id > 0) {
            $whereArr[] = "p.department_id = " . $department_id;
        }
        if ($status !== null && $status !== '') {
            if ($status === 'not_started') {
                $whereArr[] = "p.status IN ('quotation','draft','contract','open','confirming')";
            } else {
                $whereArr[] = "p.status = '" . $this->quote($status) . "'";
            }
        }
        if (!empty($ids)) {
            $whereArr[] = "p.id IN (" . implode(",", array_map("intval", $ids)) . ")";
        } elseif ($id !== null && $id > 0) {
            $whereArr[] = "p.id = " . $id;
        }
        // Thêm search filters cho parent_project fields, team_id, person_name, overdue, tantou
        if (!empty($searchFilters)) {
            if (isset($searchFilters['construction_number']) && trim($searchFilters['construction_number']) !== '') {
                $whereArr[] = "pp.construction_number LIKE '%" . $this->quote($searchFilters['construction_number']) . "%'";
            }
            if (isset($searchFilters['contact_name']) && trim($searchFilters['contact_name']) !== '') {
                $whereArr[] = "pp.contact_name LIKE '%" . $this->quote($searchFilters['contact_name']) . "%'";
            }
            if (isset($searchFilters['company_name']) && trim($searchFilters['company_name']) !== '') {
                $whereArr[] = "pp.company_name LIKE '%" . $this->quote($searchFilters['company_name']) . "%'";
            }
            if (isset($searchFilters['project_name']) && trim($searchFilters['project_name']) !== '') {
                $whereArr[] = "pp.project_name LIKE '%" . $this->quote($searchFilters['project_name']) . "%'";
            }
            if (isset($searchFilters['branch_name']) && trim($searchFilters['branch_name']) !== '') {
                $whereArr[] = "pp.branch_name LIKE '%" . $this->quote($searchFilters['branch_name']) . "%'";
            }
            // Filter theo team_id hoặc team_ids (nhiều team: dự án thuộc BẤT KỲ team nào trong danh sách)
            if (!empty($searchFilters['team_ids']) && is_array($searchFilters['team_ids'])) {
                $teamIds = array_values(array_unique(array_filter(array_map('intval', $searchFilters['team_ids']))));
                if (!empty($teamIds)) {
                    $conds = array_map(function ($tid) { return "FIND_IN_SET(" . $tid . ", p.teams) > 0"; }, $teamIds);
                    $whereArr[] = "(" . implode(" OR ", $conds) . ")";
                }
            } elseif (isset($searchFilters['team_id']) && intval($searchFilters['team_id']) > 0) {
                $team_id = intval($searchFilters['team_id']);
                $whereArr[] = "FIND_IN_SET(" . $team_id . ", p.teams) > 0";
            }
            // Filter theo tantou (担当会社: CAILY / GUIS)
            if (isset($searchFilters['tantou']) && in_array($searchFilters['tantou'], ['CAILY','GUIS'], true)) {
                $whereArr[] = "p.tantou = '" . $this->quote($searchFilters['tantou']) . "'";
            }
            // Filter theo tên người tham gia (member hoặc manager): hỗ trợ nhiều tên (Thom,Hoàng) – dự án phải có TẤT CẢ các người tham gia
            if (isset($searchFilters['person_name']) && trim($searchFilters['person_name']) !== '') {
                $personRaw = trim($searchFilters['person_name']);
                $personParts = array_map('trim', preg_split('/[\s,]+/', $personRaw, -1, PREG_SPLIT_NO_EMPTY));
                $personParts = array_unique(array_filter($personParts));
                foreach ($personParts as $oneName) {
                    if ($oneName === '') continue;
                    $person = $this->quote($oneName);
                    $whereArr[] = "EXISTS (
                        SELECT 1
                        FROM " . DB_PREFIX . "project_members pm_person
                        LEFT JOIN " . DB_PREFIX . "user u_person ON pm_person.user_id = u_person.id
                        WHERE pm_person.project_id = p.id
                          AND u_person.realname LIKE '%" . $person . "%'
                    )";
                }
            }
            // Filter theo overdue (đã trễ kỳ hạn): end_date < NOW() và status chưa completed/cancelled/deleted
            if (isset($searchFilters['overdue']) && $searchFilters['overdue']) {
                $now = date('Y-m-d H:i:s');
                $whereArr[] = "(p.end_date IS NOT NULL AND p.end_date < '" . $this->quote($now) . "' AND p.status NOT IN ('completed','cancelled','deleted'))";
            }
            // Filter theo date (ngày cụ thể hoặc date_type như yesterday, today, this_week, etc.)
            // Tìm các dự án có khoảng thời gian (start_date đến end_date) chứa thời gian đó
            if (isset($searchFilters['date']) && !empty($searchFilters['date'])) {
                // Ngày cụ thể: tìm các dự án mà ngày đó nằm trong khoảng start_date đến end_date
                $date = trim($searchFilters['date']);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    $dateStart = $date . ' 00:00:00';
                    $dateEnd = $date . ' 23:59:59';
                    // Dự án giao với ngày đó: start_date <= cuối ngày VÀ (end_date >= đầu ngày HOẶC end_date IS NULL)
                    $whereArr[] = "(p.start_date IS NOT NULL AND p.start_date <= '" . $this->quote($dateEnd) . "' AND (p.end_date IS NULL OR p.end_date >= '" . $this->quote($dateStart) . "'))";
                }
            } elseif (isset($searchFilters['date_start']) && isset($searchFilters['date_end']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($searchFilters['date_start'])) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($searchFilters['date_end']))) {
                // Khoảng ngày: dự án có [start_date, end_date] giao với [date_start, date_end]
                // Giao khi: start_date <= date_end VÀ (end_date >= date_start HOẶC end_date IS NULL) → ví dụ dự án 10/1–21/1 vẫn nằm trong khoảng 1/1–20/1
                $rangeStart = trim($searchFilters['date_start']) . ' 00:00:00';
                $rangeEnd = trim($searchFilters['date_end']) . ' 23:59:59';
                $whereArr[] = "(p.start_date IS NOT NULL AND p.start_date <= '" . $this->quote($rangeEnd) . "' AND (p.end_date IS NULL OR p.end_date >= '" . $this->quote($rangeStart) . "'))";
            } elseif (isset($searchFilters['date_type']) && !empty($searchFilters['date_type'])) {
                // Date type: yesterday, today, tomorrow, this_week, last_week, next_week, this_month, last_month, next_month
                $dateType = trim($searchFilters['date_type']);
                $today = date('Y-m-d');
                switch ($dateType) {
                    case 'yesterday':
                        $yesterday = date('Y-m-d', strtotime('-1 day'));
                        // Tính toán date range: từ đầu ngày đến cuối ngày hôm qua
                        $dateStart = $yesterday . ' 00:00:00';
                        $dateEnd = $yesterday . ' 23:59:59';
                        // Dự án phải có start_date <= cuối ngày hôm qua VÀ (end_date >= đầu ngày hôm qua HOẶC end_date IS NULL)
                        $whereArr[] = "(p.start_date IS NOT NULL AND p.start_date <= '" . $this->quote($dateEnd) . "' AND (p.end_date IS NULL OR p.end_date >= '" . $this->quote($dateStart) . "'))";
                        break;
                    case 'today':
                        // Tính toán date range: từ đầu ngày đến cuối ngày hôm nay
                        $dateStart = $today . ' 00:00:00';
                        $dateEnd = $today . ' 23:59:59';
                        // Dự án phải có start_date <= cuối ngày hôm nay VÀ (end_date >= đầu ngày hôm nay HOẶC end_date IS NULL)
                        $whereArr[] = "(p.start_date IS NOT NULL AND p.start_date <= '" . $this->quote($dateEnd) . "' AND (p.end_date IS NULL OR p.end_date >= '" . $this->quote($dateStart) . "'))";
                        break;
                    case 'tomorrow':
                        $tomorrow = date('Y-m-d', strtotime('+1 day'));
                        // Tính toán date range: từ đầu ngày đến cuối ngày mai
                        $dateStart = $tomorrow . ' 00:00:00';
                        $dateEnd = $tomorrow . ' 23:59:59';
                        // Dự án phải có start_date <= cuối ngày mai VÀ (end_date >= đầu ngày mai HOẶC end_date IS NULL)
                        $whereArr[] = "(p.start_date IS NOT NULL AND p.start_date <= '" . $this->quote($dateEnd) . "' AND (p.end_date IS NULL OR p.end_date >= '" . $this->quote($dateStart) . "'))";
                        break;
                    case 'this_week':
                        $weekStart = date('Y-m-d', strtotime('monday this week'));
                        $weekEnd = date('Y-m-d', strtotime('sunday this week'));
                        // Khoảng thời gian tuần này phải giao với khoảng start_date đến end_date
                        // Tức là: start_date <= weekEnd AND (end_date >= weekStart OR end_date IS NULL)
                        $whereArr[] = "(DATE(p.start_date) <= '" . $this->quote($weekEnd) . "' AND (DATE(p.end_date) >= '" . $this->quote($weekStart) . "' OR p.end_date IS NULL))";
                        break;
                    case 'last_week':
                        $lastWeekStart = date('Y-m-d', strtotime('monday last week'));
                        $lastWeekEnd = date('Y-m-d', strtotime('sunday last week'));
                        // Khoảng thời gian tuần trước phải giao với khoảng start_date đến end_date
                        $whereArr[] = "(DATE(p.start_date) <= '" . $this->quote($lastWeekEnd) . "' AND (DATE(p.end_date) >= '" . $this->quote($lastWeekStart) . "' OR p.end_date IS NULL))";
                        break;
                    case 'next_week':
                        $nextWeekStart = date('Y-m-d', strtotime('monday next week'));
                        $nextWeekEnd = date('Y-m-d', strtotime('sunday next week'));
                        $whereArr[] = "(DATE(p.start_date) <= '" . $this->quote($nextWeekEnd) . "' AND (DATE(p.end_date) >= '" . $this->quote($nextWeekStart) . "' OR p.end_date IS NULL))";
                        break;
                    case 'this_month':
                        $monthStart = date('Y-m-01');
                        $monthEnd = date('Y-m-t');
                        // Khoảng thời gian tháng này phải giao với khoảng start_date đến end_date
                        $whereArr[] = "(DATE(p.start_date) <= '" . $this->quote($monthEnd) . "' AND (DATE(p.end_date) >= '" . $this->quote($monthStart) . "' OR p.end_date IS NULL))";
                        break;
                    case 'last_month':
                        $lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
                        $lastMonthEnd = date('Y-m-t', strtotime('last day of last month'));
                        // Khoảng thời gian tháng trước phải giao với khoảng start_date đến end_date
                        $whereArr[] = "(DATE(p.start_date) <= '" . $this->quote($lastMonthEnd) . "' AND (DATE(p.end_date) >= '" . $this->quote($lastMonthStart) . "' OR p.end_date IS NULL))";
                        break;
                    case 'next_month':
                        $nextMonthStart = date('Y-m-01', strtotime('first day of next month'));
                        $nextMonthEnd = date('Y-m-t', strtotime('last day of next month'));
                        $whereArr[] = "(DATE(p.start_date) <= '" . $this->quote($nextMonthEnd) . "' AND (DATE(p.end_date) >= '" . $this->quote($nextMonthStart) . "' OR p.end_date IS NULL))";
                        break;
                }
            }
        }
        $where = "WHERE " . implode(" AND ", $whereArr);

        $fields = "p.id, p.name, p.status, p.priority, p.progress, p.amount, p.guis_nouki, p.guis_nouki_status, p.caily_nouki, p.caily_nouki_status, p.department_id, p.start_date, p.end_date, p.tantou, p.teams,
            d.name as department_name,
            pp.company_name as parent_company_name,
            pp.project_name as parent_project_name,
            pp.branch_name as parent_branch_name,
            pp.contact_name as parent_contact_name,
            pp.construction_number as parent_construction_number";
        $query = "SELECT " . $fields . " FROM " . $this->table . " p 
            LEFT JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id 
            LEFT JOIN " . DB_PREFIX . "parent_projects pp ON p.parent_project_id = pp.id " . $where;
        if ($id !== null && $id > 0) {
            $row = $this->fetchOne($query);
            if (!$row) {
                return [];
            }
            $results = [$row];
            $this->attachAiContextProjectAggregates($results);
            return $results;
        }
        $query .= " ORDER BY p.updated_at DESC";
        // Luôn thêm LIMIT để chỉ lấy 20 dự án gần nhất
        $query .= " LIMIT " . $limit;
        $results = $this->fetchAll($query);
        $this->attachAiContextProjectAggregates($results);
        return $results;
    }

    private function attachAiContextProjectAggregates(array &$projects) {
        if (empty($projects)) {
            return;
        }
        $projectIds = array_values(array_filter(array_map('intval', array_column($projects, 'id')), function ($id) {
            return $id > 0;
        }));
        if (empty($projectIds)) {
            return;
        }
        $idsList = implode(',', $projectIds);

        $nameMap = ['manager' => [], 'member' => []];
        $memberRows = $this->fetchAll(sprintf(
            "SELECT pm.project_id, pm.role, GROUP_CONCAT(u.realname ORDER BY u.realname SEPARATOR ', ') as names
             FROM %sproject_members pm
             LEFT JOIN %suser u ON pm.user_id = u.id
             WHERE pm.project_id IN (%s) AND pm.role IN ('manager', 'member')
             GROUP BY pm.project_id, pm.role",
            DB_PREFIX,
            DB_PREFIX,
            $idsList
        ));
        foreach ($memberRows as $row) {
            $pid = (int)$row['project_id'];
            $role = $row['role'] === 'manager' ? 'manager' : 'member';
            $nameMap[$role][$pid] = $row['names'] ?? '';
        }

        $teamIds = [];
        foreach ($projects as $project) {
            if (empty($project['teams'])) {
                continue;
            }
            foreach (explode(',', (string)$project['teams']) as $teamId) {
                $teamId = (int)trim($teamId);
                if ($teamId > 0) {
                    $teamIds[$teamId] = true;
                }
            }
        }
        $teamNameMap = [];
        if (!empty($teamIds)) {
            $teamIdList = implode(',', array_keys($teamIds));
            $teamRows = $this->fetchAll(sprintf(
                "SELECT id, name FROM %steam WHERE id IN (%s)",
                DB_PREFIX,
                $teamIdList
            ));
            foreach ($teamRows as $row) {
                $teamNameMap[(int)$row['id']] = $row['name'];
            }
        }

        foreach ($projects as &$project) {
            $pid = (int)$project['id'];
            $project['manager_names'] = $nameMap['manager'][$pid] ?? '';
            $project['member_names'] = $nameMap['member'][$pid] ?? '';
            $teamNames = [];
            if (!empty($project['teams'])) {
                foreach (explode(',', (string)$project['teams']) as $teamId) {
                    $teamId = (int)trim($teamId);
                    if ($teamId > 0 && isset($teamNameMap[$teamId])) {
                        $teamNames[] = $teamNameMap[$teamId];
                    }
                }
            }
            $project['team_names'] = implode(', ', $teamNames);
        }
        unset($project);
    }

    /**
     * Nhóm và người chưa được phân công dự án trong khoảng thời gian (rảnh việc).
     * Dùng cho AI khi user hỏi "nhóm nào tuần sau rảnh", "ai rảnh tuần sau".
     * Visibility giống getForAiContext (admin/dept_manager thấy hết; user thường chỉ dự án mình tạo hoặc tham gia).
     *
     * @param int $department_id bắt buộc (chỉ xét team/user trong department này)
     * @param string $dateStart Y-m-d
     * @param string $dateEnd Y-m-d
     * @return array ['free_teams' => [['id'=>N,'name'=>'...'], ...], 'free_members' => [['id'=>N,'realname'=>'...'], ...], 'period' => ['date_start'=>..., 'date_end'=>...]]
     */
    public function getFreeTeamsAndMembersInPeriod($department_id, $dateStart, $dateEnd) {
        $department_id = (int) $department_id;
        if ($department_id <= 0) {
            return ['free_teams' => [], 'free_members' => [], 'period' => ['date_start' => $dateStart, 'date_end' => $dateEnd]];
        }
        $user_id = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
        $is_admin = (isset($_SESSION['authority']) && $_SESSION['authority'] === 'administrator');
        $is_dept_manager = false;
        if ($department_id > 0 && isset($_SESSION['userid'])) {
            $q = sprintf(
                "SELECT COUNT(id) as c FROM " . DB_PREFIX . "user_department WHERE userid = '%s' AND department_id = %d AND (project_manager = 1 OR project_director = 1 OR project_director_stat = 1 OR project_director_view = 1 OR project_director_edit = 1)",
                $this->quote($_SESSION['userid']),
                $department_id
            );
            $row = $this->fetchOne($q);
            $is_dept_manager = ($row && isset($row['c']) && (int) $row['c'] > 0);
        }
        $permWhere = "";
        if (!$is_admin && !$is_dept_manager) {
            $permWhere = sprintf(
                " AND (p.created_by = %d OR EXISTS (SELECT 1 FROM " . DB_PREFIX . "project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d))",
                $user_id,
                $user_id
            );
        }
        $rangeStart = $dateStart . ' 00:00:00';
        $rangeEnd = $dateEnd . ' 23:59:59';
        $overlapWhere = "p.status != 'deleted' AND p.department_id = " . $department_id . $permWhere
            . " AND (p.start_date IS NOT NULL AND p.start_date <= '" . $this->quote($rangeEnd) . "' AND (p.end_date IS NULL OR p.end_date >= '" . $this->quote($rangeStart) . "'))";
        $projectIds = $this->fetchAll("SELECT p.id, p.teams FROM " . $this->table . " p WHERE " . $overlapWhere);
        $busyTeamIds = [];
        $overlappingIds = [];
        foreach ($projectIds as $row) {
            $overlappingIds[] = (int) $row['id'];
            if (!empty($row['teams']) && trim($row['teams']) !== '') {
                foreach (array_map('intval', explode(',', trim($row['teams']))) as $tid) {
                    if ($tid > 0) {
                        $busyTeamIds[$tid] = true;
                    }
                }
            }
        }
        $allTeams = $this->fetchAll("SELECT id, name FROM " . DB_PREFIX . "team WHERE department_id = " . $department_id . " AND is_active = 1 ORDER BY name");
        $free_teams = [];
        foreach ($allTeams as $t) {
            $tid = (int) $t['id'];
            if (!isset($busyTeamIds[$tid])) {
                $free_teams[] = ['id' => $tid, 'name' => isset($t['name']) ? $t['name'] : ''];
            }
        }
        $busyUserIds = [];
        if (!empty($overlappingIds)) {
            $placeholders = implode(',', array_map('intval', $overlappingIds));
            $rows = $this->fetchAll("SELECT DISTINCT user_id FROM " . DB_PREFIX . "project_members WHERE project_id IN (" . $placeholders . ") AND user_id > 0");
            foreach ($rows as $r) {
                $busyUserIds[(int) $r['user_id']] = true;
            }
        }
        $deptUsers = $this->fetchAll(
            "SELECT u.id, u.realname FROM " . DB_PREFIX . "user u "
            . "JOIN " . DB_PREFIX . "user_department ud ON u.userid = ud.userid WHERE ud.department_id = " . $department_id . " ORDER BY u.realname"
        );
        $free_members = [];
        foreach ($deptUsers as $u) {
            $uid = (int) $u['id'];
            if (!isset($busyUserIds[$uid])) {
                $free_members[] = ['id' => $uid, 'realname' => isset($u['realname']) ? $u['realname'] : ''];
            }
        }
        return [
            'free_teams' => $free_teams,
            'free_members' => $free_members,
            'period' => ['date_start' => $dateStart, 'date_end' => $dateEnd],
        ];
    }

    /**
     * Phase 2.4 – Statistics aggregation for AI.
     * Returns project counts (overview, by department) for use as context sent to Gemini.
     * Respects visibility: non-admin sees only projects they created or are member of (same as getForAiContext).
     *
     * @param int|null $department_id optional filter
     * @return array ['overview' => [...], 'by_department' => [...]]
     */
    public function getStatsForAiContext($department_id = null, $statusFilter = null, $searchFilters = []) {
        $user_id = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
        $is_admin = (isset($_SESSION['authority']) && $_SESSION['authority'] === 'administrator');
        $permWhere = "";
        if (!$is_admin && $user_id) {
            $permWhere = sprintf(
                " AND (p.created_by = %d OR EXISTS (SELECT 1 FROM " . DB_PREFIX . "project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d))",
                $user_id,
                $user_id
            );
        }
        $deptWhere = ($department_id !== null && $department_id > 0) ? sprintf(" AND p.department_id = %d", $department_id) : "";
        // Thêm status filter nếu có
        $statusWhere = "";
        if ($statusFilter !== null && $statusFilter !== '') {
            if ($statusFilter === 'not_started') {
                // "Chưa tiến hành" = chưa bắt đầu: quotation, draft, contract, open, confirming
                $statusWhere = " AND p.status IN ('quotation','draft','contract','open','confirming')";
            } else {
                $statusWhere = " AND p.status = '" . $this->quote($statusFilter) . "'";
            }
        }
        $baseWhere = "WHERE p.status != 'deleted'" . $permWhere . $deptWhere . $statusWhere;

        // Thêm các filter bổ sung (team_id, tantou, person_name, overdue, date, parent_project fields) giống getForAiContext
        $extraWhere = "";
        $joinParent = "";
        if (is_array($searchFilters) && !empty($searchFilters)) {
            // parent_project fields (cần JOIN pp) – construction_number, branch_name, company_name, project_name, contact_name
            if (isset($searchFilters['construction_number']) && trim($searchFilters['construction_number']) !== '') {
                $joinParent = " LEFT JOIN " . DB_PREFIX . "parent_projects pp ON p.parent_project_id = pp.id";
                $extraWhere .= " AND pp.construction_number LIKE '%" . $this->quote($searchFilters['construction_number']) . "%'";
            }
            if (isset($searchFilters['branch_name']) && trim($searchFilters['branch_name']) !== '') {
                if ($joinParent === '') $joinParent = " LEFT JOIN " . DB_PREFIX . "parent_projects pp ON p.parent_project_id = pp.id";
                $extraWhere .= " AND pp.branch_name LIKE '%" . $this->quote($searchFilters['branch_name']) . "%'";
            }
            if (isset($searchFilters['company_name']) && trim($searchFilters['company_name']) !== '') {
                if ($joinParent === '') $joinParent = " LEFT JOIN " . DB_PREFIX . "parent_projects pp ON p.parent_project_id = pp.id";
                $extraWhere .= " AND pp.company_name LIKE '%" . $this->quote($searchFilters['company_name']) . "%'";
            }
            if (isset($searchFilters['project_name']) && trim($searchFilters['project_name']) !== '') {
                if ($joinParent === '') $joinParent = " LEFT JOIN " . DB_PREFIX . "parent_projects pp ON p.parent_project_id = pp.id";
                $extraWhere .= " AND pp.project_name LIKE '%" . $this->quote($searchFilters['project_name']) . "%'";
            }
            if (isset($searchFilters['contact_name']) && trim($searchFilters['contact_name']) !== '') {
                if ($joinParent === '') $joinParent = " LEFT JOIN " . DB_PREFIX . "parent_projects pp ON p.parent_project_id = pp.id";
                $extraWhere .= " AND pp.contact_name LIKE '%" . $this->quote($searchFilters['contact_name']) . "%'";
            }
            // team_id / team_ids (nhiều team: dự án thuộc bất kỳ team nào trong danh sách)
            if (!empty($searchFilters['team_ids']) && is_array($searchFilters['team_ids'])) {
                $teamIds = array_values(array_unique(array_filter(array_map('intval', $searchFilters['team_ids']))));
                if (!empty($teamIds)) {
                    $conds = array_map(function ($tid) { return "FIND_IN_SET(" . $tid . ", p.teams) > 0"; }, $teamIds);
                    $extraWhere .= " AND (" . implode(" OR ", $conds) . ")";
                }
            } elseif (isset($searchFilters['team_id']) && intval($searchFilters['team_id']) > 0) {
                $team_id = intval($searchFilters['team_id']);
                $extraWhere .= " AND FIND_IN_SET(" . $team_id . ", p.teams) > 0";
            }
            // tantou (担当会社: CAILY / GUIS)
            if (isset($searchFilters['tantou']) && in_array($searchFilters['tantou'], ['CAILY','GUIS'], true)) {
                $extraWhere .= " AND p.tantou = '" . $this->quote($searchFilters['tantou']) . "'";
            }
            // person_name (nhiều tên: Thom,Hoàng – dự án phải có TẤT CẢ các người tham gia)
            if (isset($searchFilters['person_name']) && trim($searchFilters['person_name']) !== '') {
                $personRaw = trim($searchFilters['person_name']);
                $personParts = array_map('trim', preg_split('/[\s,]+/', $personRaw, -1, PREG_SPLIT_NO_EMPTY));
                $personParts = array_unique(array_filter($personParts));
                foreach ($personParts as $oneName) {
                    if ($oneName === '') continue;
                    $person = $this->quote($oneName);
                    $extraWhere .= " AND EXISTS (
                        SELECT 1
                        FROM " . DB_PREFIX . "project_members pm_person
                        LEFT JOIN " . DB_PREFIX . "user u_person ON pm_person.user_id = u_person.id
                        WHERE pm_person.project_id = p.id
                          AND u_person.realname LIKE '%" . $person . "%'
                    )";
                }
            }
            // overdue: end_date < NOW() và status chưa completed/cancelled/deleted
            if (isset($searchFilters['overdue']) && $searchFilters['overdue']) {
                $now = date('Y-m-d H:i:s');
                $extraWhere .= " AND (p.end_date IS NOT NULL AND p.end_date < '" . $this->quote($now) . "' AND p.status NOT IN ('completed','cancelled','deleted'))";
            }
            // Date filters (ngày cụ thể hoặc khoảng thời gian) – áp dụng giống logic trong getForAiContext
            if (isset($searchFilters['date']) && !empty($searchFilters['date'])) {
                $date = trim($searchFilters['date']);
                if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date)) {
                    $dateStart = $date . ' 00:00:00';
                    $dateEnd = $date . ' 23:59:59';
                    $extraWhere .= " AND (p.start_date IS NOT NULL AND p.start_date <= '" . $this->quote($dateEnd) . "' AND (p.end_date IS NULL OR p.end_date >= '" . $this->quote($dateStart) . "'))";
                }
            } elseif (isset($searchFilters['date_start']) && isset($searchFilters['date_end']) && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', trim($searchFilters['date_start'])) && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', trim($searchFilters['date_end']))) {
                $rangeStart = trim($searchFilters['date_start']) . ' 00:00:00';
                $rangeEnd = trim($searchFilters['date_end']) . ' 23:59:59';
                $extraWhere .= " AND (p.start_date IS NOT NULL AND p.start_date <= '" . $this->quote($rangeEnd) . "' AND (p.end_date IS NULL OR p.end_date >= '" . $this->quote($rangeStart) . "'))";
            } elseif (isset($searchFilters['date_type']) && !empty($searchFilters['date_type'])) {
                $dateType = trim($searchFilters['date_type']);
                $today = date('Y-m-d');
                switch ($dateType) {
                    case 'yesterday':
                        $yesterday = date('Y-m-d', strtotime('-1 day'));
                        $dateStart = $yesterday . ' 00:00:00';
                        $dateEnd = $yesterday . ' 23:59:59';
                        $extraWhere .= " AND (p.start_date IS NOT NULL AND p.start_date <= '" . $this->quote($dateEnd) . "' AND (p.end_date IS NULL OR p.end_date >= '" . $this->quote($dateStart) . "'))";
                        break;
                    case 'today':
                        $dateStart = $today . ' 00:00:00';
                        $dateEnd = $today . ' 23:59:59';
                        $extraWhere .= " AND (p.start_date IS NOT NULL AND p.start_date <= '" . $this->quote($dateEnd) . "' AND (p.end_date IS NULL OR p.end_date >= '" . $this->quote($dateStart) . "'))";
                        break;
                    case 'tomorrow':
                        $tomorrow = date('Y-m-d', strtotime('+1 day'));
                        $dateStart = $tomorrow . ' 00:00:00';
                        $dateEnd = $tomorrow . ' 23:59:59';
                        $extraWhere .= " AND (p.start_date IS NOT NULL AND p.start_date <= '" . $this->quote($dateEnd) . "' AND (p.end_date IS NULL OR p.end_date >= '" . $this->quote($dateStart) . "'))";
                        break;
                    case 'this_week':
                        $weekStart = date('Y-m-d', strtotime('monday this week'));
                        $weekEnd = date('Y-m-d', strtotime('sunday this week'));
                        $extraWhere .= " AND (DATE(p.start_date) <= '" . $this->quote($weekEnd) . "' AND (DATE(p.end_date) >= '" . $this->quote($weekStart) . "' OR p.end_date IS NULL))";
                        break;
                    case 'last_week':
                        $lastWeekStart = date('Y-m-d', strtotime('monday last week'));
                        $lastWeekEnd = date('Y-m-d', strtotime('sunday last week'));
                        $extraWhere .= " AND (DATE(p.start_date) <= '" . $this->quote($lastWeekEnd) . "' AND (DATE(p.end_date) >= '" . $this->quote($lastWeekStart) . "' OR p.end_date IS NULL))";
                        break;
                    case 'next_week':
                        $nextWeekStart = date('Y-m-d', strtotime('monday next week'));
                        $nextWeekEnd = date('Y-m-d', strtotime('sunday next week'));
                        $extraWhere .= " AND (DATE(p.start_date) <= '" . $this->quote($nextWeekEnd) . "' AND (DATE(p.end_date) >= '" . $this->quote($nextWeekStart) . "' OR p.end_date IS NULL))";
                        break;
                    case 'this_month':
                        $monthStart = date('Y-m-01');
                        $monthEnd = date('Y-m-t');
                        $extraWhere .= " AND (DATE(p.start_date) <= '" . $this->quote($monthEnd) . "' AND (DATE(p.end_date) >= '" . $this->quote($monthStart) . "' OR p.end_date IS NULL))";
                        break;
                    case 'last_month':
                        $lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
                        $lastMonthEnd = date('Y-m-t', strtotime('last day of last month'));
                        $extraWhere .= " AND (DATE(p.start_date) <= '" . $this->quote($lastMonthEnd) . "' AND (DATE(p.end_date) >= '" . $this->quote($lastMonthStart) . "' OR p.end_date IS NULL))";
                        break;
                    case 'next_month':
                        $nextMonthStart = date('Y-m-01', strtotime('first day of next month'));
                        $nextMonthEnd = date('Y-m-t', strtotime('last day of next month'));
                        $extraWhere .= " AND (DATE(p.start_date) <= '" . $this->quote($nextMonthEnd) . "' AND (DATE(p.end_date) >= '" . $this->quote($nextMonthStart) . "' OR p.end_date IS NULL))";
                        break;
                }
            }
        }

        $baseWhereWithFilters = $baseWhere . $extraWhere;
        $fromClause = "FROM " . $this->table . " p " . $joinParent . " " . $baseWhereWithFilters;

        // Khi có statusFilter, active và completed phải phản ánh đúng theo filter
        // Nếu statusFilter = 'in_progress', thì active = total và completed = 0
        // Nếu statusFilter = 'completed', thì active = 0 và completed = total
        // Nếu không có statusFilter, tính như bình thường
        $overviewSql = "";
        $byDeptSql = "";
        if ($statusFilter !== null && $statusFilter !== '') {
            // Có statusFilter → active và completed phải phản ánh đúng theo filter
            if ($statusFilter === 'in_progress') {
                $overviewSql =
                    "SELECT COUNT(*) as total, " .
                    "COUNT(*) as active, " .
                    "0 as completed " .
                    $fromClause;
            } elseif ($statusFilter === 'completed') {
                $overviewSql =
                    "SELECT COUNT(*) as total, " .
                    "0 as active, " .
                    "COUNT(*) as completed " .
                    $fromClause;
            } elseif ($statusFilter === 'not_started') {
                // "Chưa tiến hành" → total = số dự án chưa bắt đầu, active/completed = 0
                $overviewSql =
                    "SELECT COUNT(*) as total, " .
                    "0 as active, " .
                    "0 as completed " .
                    $fromClause;
            } else {
                // Status khác → active = total (vì đã filter theo status đó), completed = 0
                $overviewSql =
                    "SELECT COUNT(*) as total, " .
                    "COUNT(*) as active, " .
                    "0 as completed " .
                    $fromClause;
            }
        } else {
            // Không có statusFilter → tính như bình thường
            $overviewSql =
                "SELECT COUNT(*) as total, " .
                "SUM(CASE WHEN p.status NOT IN ('completed','cancelled','deleted') THEN 1 ELSE 0 END) as active, " .
                "SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) as completed " .
                $fromClause;
        }
        $byDeptSql =
            "SELECT d.id as department_id, d.name as department_name, COUNT(*) as count " .
            "FROM " . $this->table . " p " .
            $joinParent .
            " LEFT JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id " .
            $baseWhereWithFilters . " GROUP BY p.department_id, d.id, d.name ORDER BY count DESC";

        // Thực thi SQL
        $overview = $this->fetchOne($overviewSql);
        $by_department = $this->fetchAll($byDeptSql);

        return ['overview' => $overview ?: [], 'by_department' => $by_department ?: []];
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
        if (isset($_POST['start_date']) && $_POST['start_date'] != '') {
            $data['start_date'] = $this->normalize_datetime_with_default($_POST['start_date'], '09:00');
        }
        if (isset($_POST['actual_end_date']) && $_POST['actual_end_date'] != '') {
            $data['actual_end_date'] = $this->normalize_datetime_with_default($_POST['actual_end_date'], '18:00');
        }
        if (isset($_POST['end_date']) && $_POST['end_date'] != '') {
            $data['end_date'] = $this->normalize_datetime_with_default($_POST['end_date'], '18:00');
        }

        // 担当, CAILY納期, GUIS納期 (child project) — date-only → 18:00
        if (array_key_exists('tantou', $_POST)) {
            $data['tantou'] = (isset($_POST['tantou']) && in_array($_POST['tantou'], ['CAILY', 'GUIS'], true)) ? $_POST['tantou'] : null;
        }
        if (array_key_exists('caily_nouki', $_POST)) {
            $val = isset($_POST['caily_nouki']) ? trim($_POST['caily_nouki']) : '';
            if ($val !== '') {
                $parsed = $this->normalize_datetime_with_default($val, '18:00');
                if ($parsed !== null) {
                    $data['caily_nouki'] = $parsed;
                }
            }
        }
        if (array_key_exists('caily_nouki_status', $_POST)) {
            $data['caily_nouki_status'] = trim((string)$_POST['caily_nouki_status']);
        }
        if (array_key_exists('guis_nouki', $_POST)) {
            $val = isset($_POST['guis_nouki']) ? trim($_POST['guis_nouki']) : '';
            if ($val !== '') {
                $parsed = $this->normalize_datetime_with_default($val, '18:00');
                if ($parsed !== null) {
                    $data['guis_nouki'] = $parsed;
                }
            }
        }
        if (array_key_exists('guis_nouki_status', $_POST)) {
            $data['guis_nouki_status'] = trim((string)$_POST['guis_nouki_status']);
        }
        if (isset($_POST['customer_id']) && $_POST['customer_id'] !== '' && $_POST['customer_id'] !== '0') {
            $data['customer_id'] = intval($_POST['customer_id']);
        }
        if (isset($_POST['guis_receiver']) && $_POST['guis_receiver'] !== '') {
            $data['guis_receiver'] = $this->validateUTF8MB4($_POST['guis_receiver']);
        }
        // 総額 (amount) - 必ずリクエストから取得して数値で保存
        $data['amount'] = (array_key_exists('amount', $_POST) && $_POST['amount'] !== '' && $_POST['amount'] !== null)
            ? floatval($_POST['amount'])
            : 0;
       

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
        $listAllUserIds = array_filter(array_map('intval', $listAllUserIds));
        $listAllUserIds = array_unique($listAllUserIds);
        $listAllUsers = $this->getListUserName($listAllUserIds);
        // Index by user id để addMember nhận đúng userid
        $users_by_id = [];
        foreach ($listAllUsers as $u) {
            $users_by_id[(int)$u['id']] = $u;
        }

        // Add managers if provided
        if (isset($_POST['managers']) && !empty($_POST['managers'])) {
            foreach ($managers as $user_id) {
                if (!empty($user_id)) {
                    $uid = intval($user_id);
                    $username = isset($users_by_id[$uid]) ? $users_by_id[$uid]['userid'] : '';
                    $this->addMember($project_id, $uid, $username, 'manager');
                }
            }
        }
        
        if (isset($_POST['members']) && !empty($_POST['members'])) {
            foreach ($members as $user_id) {
                if (!empty($user_id)) {
                    $uid = intval($user_id);
                    $username = isset($users_by_id[$uid]) ? $users_by_id[$uid]['userid'] : '';
                    $this->addMember($project_id, $uid, $username, 'member');
                }
            }
        }

        
        $this->notifyProjectCreated($project_id, $data['name'], array_column($listAllUsers, 'userid'));
        $this->logProjectAction($project_id, 'created', '案件作成', '', '');

        // Khi tạo dự án con: tạo 2 task mặc định (đồng bộ 2 bản vẽ kèm giá theo % tổng tiền)
        $this->createChildProjectDefaultTasks($project_id, $data);

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

    /**
     * Validate client version against DB for optimistic locking.
     */
    private function assertProjectVersionMatches(array $old) {
        if (!array_key_exists('version', $_POST)) {
            return [
                'ok' => false,
                'response' => [
                    'status' => 'error',
                    'error' => 'version_required',
                    'message' => 'バージョン情報がありません。ページを再読み込みしてください。',
                ],
            ];
        }
        $clientVersion = intval($_POST['version']);
        $dbVersion = intval($old['version'] ?? 1);
        if ($clientVersion !== $dbVersion) {
            return [
                'ok' => false,
                'response' => [
                    'status' => 'error',
                    'error' => 'version_conflict',
                    'message' => '他のユーザーが先に更新しました。ページを再読み込みしてください。',
                    'current_version' => $dbVersion,
                ],
            ];
        }
        return ['ok' => true, 'expected_version' => $dbVersion];
    }

    /**
     * UPDATE with version increment; fails when row version changed concurrently.
     */
    private function performVersionedProjectUpdate($id, array $data, $expectedVersion) {
        $newVersion = intval($expectedVersion) + 1;
        $data['version'] = $newVersion;
        $result = $this->query_update($data, [
            'id' => intval($id),
            'version' => intval($expectedVersion),
        ]);
        if (!$result) {
            return [
                'ok' => false,
                'response' => [
                    'status' => 'error',
                    'error' => 'version_conflict',
                    'message' => '他のユーザーが先に更新しました。ページを再読み込みしてください。',
                    'current_version' => $newVersion,
                ],
            ];
        }
        return ['ok' => true, 'version' => $newVersion];
    }

    
    function update() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'error' => 'No project id'];
        // Phase 4.1 – Permission check for project update (AI-triggered or any caller)
        if (!$this->canUserEditProject($id)) {
            return ['status' => 'error', 'error' => 'Forbidden', 'http_status' => 403];
        }
        $old = $this->getById($id);
        if (!$old) {
            return ['status' => 'error', 'error' => 'Project not found'];
        }

        $versionCheck = $this->assertProjectVersionMatches($old);
        if (!$versionCheck['ok']) {
            return $versionCheck['response'];
        }
        $expectedVersion = $versionCheck['expected_version'];

        // Sensitive fields (amount, name, start_date, end_date, caily_nouki, guis_nouki, tantou) require project_manager or administrator
        $sensitiveFields = ['amount', 'name', 'start_date', 'end_date', 'caily_nouki', 'guis_nouki', 'tantou'];
        $changingSensitive = false;
        foreach ($sensitiveFields as $field) {
            if (!array_key_exists($field, $_POST)) {
                continue;
            }
            $newVal = isset($_POST[$field]) ? trim((string)$_POST[$field]) : '';
            $oldVal = isset($old[$field]) ? $old[$field] : '';
            if ($field === 'amount') {
                if (floatval($_POST[$field]) != floatval($oldVal)) {
                    $changingSensitive = true;
                    break;
                }
            } elseif ($newVal !== (is_string($oldVal) ? trim($oldVal) : (string)$oldVal)) {
                $changingSensitive = true;
                break;
            }
        }
        // if ($changingSensitive && !$this->canUserEditProjectSensitiveFields($id)) {
        //     return ['status' => 'error', 'error' => 'Forbidden: only project_manager or administrator can change amount, name, dates (start_date, end_date, caily_nouki, guis_nouki), or tantou', 'http_status' => 403];
        // }

        // Chỉ cập nhật các trường có trong request; trường không gửi lên không bị ghi đè
        $data = array(
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $_SESSION['userid']
        );
        if (array_key_exists('name', $_POST)) {
            $data['name'] = $this->validateUTF8MB4($_POST['name']);
        }
        if (array_key_exists('description', $_POST)) {
            $data['description'] = $this->validateUTF8MB4($_POST['description']);
        }
        if (array_key_exists('building_branch', $_POST)) {
            $data['building_branch'] = $_POST['building_branch'];
        }
        if (array_key_exists('building_size', $_POST)) {
            $data['building_size'] = $_POST['building_size'];
        }
        if (array_key_exists('building_type', $_POST)) {
            $data['building_type'] = $_POST['building_type'];
        }
        if (array_key_exists('building_number', $_POST)) {
            $data['building_number'] = $_POST['building_number'];
        }
        if (array_key_exists('project_number', $_POST)) {
            $data['project_number'] = $_POST['project_number'];
        }
        if (array_key_exists('status', $_POST)) {
            $data['status'] = $_POST['status'];
        }
        if (array_key_exists('project_order_type', $_POST)) {
            $data['project_order_type'] = $_POST['project_order_type'];
        }
        if (array_key_exists('priority', $_POST)) {
            $data['priority'] = $_POST['priority'];
        }
        if (array_key_exists('estimate_status', $_POST)) {
            $data['estimate_status'] = $_POST['estimate_status'];
        }
        if (array_key_exists('invoice_status', $_POST)) {
            $data['invoice_status'] = $_POST['invoice_status'];
        }
        if (array_key_exists('tags', $_POST)) {
            $data['tags'] = $_POST['tags'];
        }
        if (array_key_exists('amount', $_POST)) {
            $data['amount'] = floatval($_POST['amount']);
        }
        if (array_key_exists('teams', $_POST)) {
            $data['teams'] = $_POST['teams'];
        }
        if (array_key_exists('progress', $_POST)) {
            $data['progress'] = intval($_POST['progress']);
        }
        if (array_key_exists('department_id', $_POST)) {
            $data['department_id'] = intval($_POST['department_id']);
        }
        if (array_key_exists('parent_project_id', $_POST)) {
            $data['parent_project_id'] = intval($_POST['parent_project_id']);
        }
        if (array_key_exists('is_kadai', $_POST)) {
            $data['is_kadai'] = intval($_POST['is_kadai']);
        }
        if (array_key_exists('department_custom_fields_set_id', $_POST) && $_POST['department_custom_fields_set_id'] != '') {
            $data['department_custom_fields_set_id'] = $_POST['department_custom_fields_set_id'];
        }
        if (array_key_exists('custom_fields', $_POST) && $_POST['custom_fields'] != '') {
            $data['custom_fields'] = $_POST['custom_fields'];
        }
        if (array_key_exists('start_date', $_POST) && $_POST['start_date'] != '') {
            $data['start_date'] = $this->normalize_datetime_with_default($_POST['start_date'], '09:00');
        }
        if (array_key_exists('end_date', $_POST) && $_POST['end_date'] != '') {
            $data['end_date'] = $this->normalize_datetime_with_default($_POST['end_date'], '18:00');
        }
        if (array_key_exists('tantou', $_POST)) {
            $data['tantou'] = (isset($_POST['tantou']) && $_POST['tantou'] !== '' && in_array($_POST['tantou'], ['CAILY', 'GUIS'], true)) ? $_POST['tantou'] : null;
        }
        // Handle datetime fields - start_date 9:00, end/nouki/actual_end 18:00 when date-only
        $nullDatetimeFields = [];
        if (array_key_exists('caily_nouki', $_POST)) {
            $val = isset($_POST['caily_nouki']) ? trim($_POST['caily_nouki']) : '';
            if ($val !== '') {
                $parsed = $this->normalize_datetime_with_default($val, '18:00');
                if ($parsed !== null) {
                    $data['caily_nouki'] = $parsed;
                } else {
                    $nullDatetimeFields[] = 'caily_nouki';
                }
            } else {
                $nullDatetimeFields[] = 'caily_nouki';
            }
        }
        if (array_key_exists('caily_nouki_status', $_POST)) {
            $data['caily_nouki_status'] = trim((string)$_POST['caily_nouki_status']);
        }
        if (array_key_exists('guis_nouki', $_POST)) {
            $val = isset($_POST['guis_nouki']) ? trim($_POST['guis_nouki']) : '';
            if ($val !== '') {
                $parsed = $this->normalize_datetime_with_default($val, '18:00');
                if ($parsed !== null) {
                    $data['guis_nouki'] = $parsed;
                } else {
                    $nullDatetimeFields[] = 'guis_nouki';
                }
            } else {
                $nullDatetimeFields[] = 'guis_nouki';
            }
        }
        if (array_key_exists('guis_nouki_status', $_POST)) {
            $data['guis_nouki_status'] = trim((string)$_POST['guis_nouki_status']);
        }
        if (array_key_exists('actual_end_date', $_POST)) {
            $val = isset($_POST['actual_end_date']) ? trim($_POST['actual_end_date']) : '';
            if ($val !== '') {
                $parsed = $this->normalize_datetime_with_default($val, '18:00');
                if ($parsed !== null) {
                    $data['actual_end_date'] = $parsed;
                } else {
                    $nullDatetimeFields[] = 'actual_end_date';
                }
            } else {
                $nullDatetimeFields[] = 'actual_end_date';
            }
        }

        // Auto-set progress to 100 when status is being set to completed
        if (isset($data['status']) && $data['status'] === 'completed') {
            $data['progress'] = 100;
        }

        $nullScalarFields = [];
        if (array_key_exists('customer_id', $_POST)) {
            $customerId = trim((string)$_POST['customer_id']);
            if ($customerId !== '' && $customerId !== '0') {
                $data['customer_id'] = intval($customerId);
            } else {
                $nullScalarFields[] = 'customer_id';
            }
        }
        if (array_key_exists('guis_receiver', $_POST)) {
            $guisReceiver = trim((string)$_POST['guis_receiver']);
            if ($guisReceiver !== '') {
                $data['guis_receiver'] = $this->validateUTF8MB4($guisReceiver);
            } else {
                $nullScalarFields[] = 'guis_receiver';
            }
        }
        
        try {
        $versionedUpdate = $this->performVersionedProjectUpdate($id, $data, $expectedVersion);
        if (!$versionedUpdate['ok']) {
            return $versionedUpdate['response'];
        }
        $result = true;
        $newProjectVersion = $versionedUpdate['version'];
        
        // Handle NULL datetime fields separately
        if ($result && !empty($nullDatetimeFields)) {
            $setParts = [];
            foreach ($nullDatetimeFields as $field) {
                $setParts[] = sprintf("`%s` = NULL", $this->escape($field));
            }
            if (!empty($setParts)) {
                $query = sprintf("UPDATE %s SET %s WHERE id = %d", $this->table, implode(', ', $setParts), $id);
                $this->query($query);
            }
        }
        if ($result && !empty($nullScalarFields)) {
            $setParts = [];
            foreach ($nullScalarFields as $field) {
                $setParts[] = sprintf("`%s` = NULL", $this->escape($field));
            }
            if (!empty($setParts)) {
                $query = sprintf("UPDATE %s SET %s WHERE id = %d", $this->table, implode(', ', $setParts), $id);
                $this->query($query);
            }
        }
        } catch (Exception $e) {
            error_log('Project update error: ' . $e->getMessage());
            return ['status' => 'error', 'error' => 'Database error: ' . $e->getMessage()];
        }

        // Khi tổng tiền dự án thay đổi: tự động sửa giá 2 bản vẽ mặc định (chỉ dự án con)
        if ($result && isset($data['amount'])) {
            $oldAmount = isset($old['amount']) ? floatval($old['amount']) : 0;
            $newAmount = floatval($data['amount']);
            $isChild = !empty($old['parent_project_id']) && (int)$old['parent_project_id'] > 0;
            if ($isChild && (abs($oldAmount - $newAmount) > 0.0001)) {
                require_once __DIR__ . '/drawing.php';
                $drawingModel = new Drawing();
                $drawingModel->autoCalculateAllDrawingPricesForProject($id);
            }
        }

        // Cập nhật người được gán task「お客様との連絡・調整・納品対応」theo GUIS 受付者 (dự án con)
        if ($result && !empty($old['parent_project_id']) && (int) $old['parent_project_id'] > 0
            && array_key_exists('guis_receiver', $_POST)) {
            $this->syncChildProjectDefaultContactTaskGuisAssignee($id);
        }

      
      
        $exist_managers = $this->getMembers(['project_id' => $id, 'role' => 'manager']);
        $exist_members = $this->getMembers(['project_id' => $id, 'role' => 'member']);
        $managers = [];
        $members = [];
        if ($result && isset($_POST['managers'])) {
            $managers = array_filter(array_map('intval', explode(',', $_POST['managers'])));
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
                $should_log = in_array($user_id, $new_members);
                $this->addMember($id, $user_id, $new_users[$user_id]['userid'] ?? '', 'member', $should_log);
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

            // Khi danh sách managers rỗng, xóa toàn bộ manager của dự án
            if (empty($managers) && !empty($exist_managers_ids)) {
                $this->query("DELETE FROM " . DB_PREFIX . "project_members WHERE project_id = " . intval($id) . " AND role = 'manager'");
                $this->notifyMemberRemoved($data['project_number'], $id, $data['name'], array_column($exist_managers, 'userid'), 'manager');
            } else {

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
                $should_log = in_array($user_id, $new_managers);
                $this->addMember($id, $user_id, $new_users[$user_id]['userid'] ?? '', 'manager', $should_log);
            }
            if (!empty($new_managers)) {
                $this->notifyMemberAdded($data['project_number'], $id, $data['name'], array_column($new_users, 'userid'), 'manager');
            }
            if (!empty($removed_managers)) {
                $this->notifyMemberRemoved($data['project_number'], $id, $data['name'], array_column($removed_users, 'userid'), 'manager');
            }
            }
        }
        if ($result) {
            $this->logProjectUpdateByField($id, $data, $old, $nullDatetimeFields);
            $departmentId = isset($data['department_id']) ? intval($data['department_id']) : intval($old['department_id'] ?? 0);
            $projectName = isset($data['name']) ? $data['name'] : ($old['name'] ?? '');

            // Use userid (string) consistently for notification recipients.
            $department_managers = $departmentId > 0 ? $this->getDepartmentManagers($departmentId) : [];
            $project_managers = $this->getMembers(['project_id' => $id, 'role' => 'manager']);
            $project_managers_ids = array_column($project_managers, 'userid');

            $managerIds = array_merge($project_managers_ids, $department_managers);
            $managerIds = array_unique($managerIds);
         
            if (isset($data['caily_nouki_status']) && $data['caily_nouki_status'] !== $old['caily_nouki_status'] && $data['caily_nouki_status'] == '納品済み') {
               $this->notifyProjectCailyNouhinUpdated($id, $projectName, $this->getUserRealname(), $data['caily_nouki_status'], $managerIds);
            }
            if (isset($data['guis_nouki_status']) && $data['guis_nouki_status'] !== $old['guis_nouki_status'] && $data['guis_nouki_status'] == '納品済み') {
                $this->notifyProjectGuisNoukiUpdated($id, $projectName, $this->getUserRealname(), $data['guis_nouki_status'], $managerIds);
            }
            return ['status' => 'success', 'message' => 'Project updated successfully', 'version' => $newProjectVersion];
        } else {
            return ['status' => 'error', 'message' => 'Update failed'];
        }
    }

    /**
     * Normalize datetime for comparison so that "2026-01-30 09:00:00" and "2026-01-30 09:00" are equal.
     * Returns comparable string (Y-m-d H:i:s) or '' for empty.
     */
    private function normalizeDatetimeForCompare($val) {
        if ($val === null || $val === '') {
            return '';
        }
        $s = trim((string)$val);
        if ($s === '' || preg_match('/^0000-00-00/', $s)) {
            return '';
        }
        $ts = strtotime($s);
        return ($ts !== false) ? date('Y-m-d H:i:s', $ts) : $s;
    }

    /**
     * Extract raw value from a custom field item.
     */
    private function getCustomFieldItemValue($item) {
        if ($item === null) {
            return '';
        }
        if (is_scalar($item)) {
            return trim((string)$item);
        }
        if (is_array($item) && array_key_exists('value', $item)) {
            $v = $item['value'];
            return is_scalar($v) ? trim((string)$v) : '';
        }
        return '';
    }

    /**
     * Extract type from a custom field item.
     */
    private function getCustomFieldItemType($item) {
        if (!is_array($item) || !isset($item['type'])) {
            return '';
        }
        return trim((string)$item['type']);
    }

    /**
     * Compare custom field values (datetime normalized to avoid format-only diffs).
     */
    private function customFieldValuesEqual($old, $new) {
        $oldVal = $this->getCustomFieldItemValue($old);
        $newVal = $this->getCustomFieldItemValue($new);
        $type = $this->getCustomFieldItemType($new);
        if ($type === '') {
            $type = $this->getCustomFieldItemType($old);
        }
        if ($type === 'datetime') {
            return $this->normalizeDatetimeForCompare($oldVal) === $this->normalizeDatetimeForCompare($newVal);
        }
        return $oldVal === $newVal;
    }

    /**
     * Rút giá trị hiển thị từ custom field (object có 'value' hoặc scalar), không trả về JSON.
     */
    private function customFieldValueToDisplay($val) {
        $raw = $this->getCustomFieldItemValue($val);
        if ($raw === '') {
            return '';
        }
        $type = $this->getCustomFieldItemType($val);
        if ($type === 'datetime') {
            $norm = $this->normalizeDatetimeForCompare($raw);
            if ($norm !== '') {
                $ts = strtotime($norm);
                return ($ts !== false) ? date('Y/m/d H:i', $ts) : $raw;
            }
        }
        return $raw;
    }

    /**
     * Log each changed field separately; append " [AI]" when update was triggered by AI.
     * Chỉ ghi log khi dữ liệu thực sự thay đổi (datetime được chuẩn hóa để so sánh).
     */
    private function logProjectUpdateByField($project_id, $data, $old, $nullDatetimeFields = []) {
        $aiLabel = (isset($_POST['_ai_triggered']) && $_POST['_ai_triggered']) ? ' [AI]' : '';
        $datetimeFields = ['start_date', 'end_date', 'actual_end_date', 'caily_nouki', 'guis_nouki'];
        $fieldLabels = [
            'name' => '名前を変更',
            'description' => '説明を変更',
            'building_branch' => '建物支店を変更',
            'building_size' => '建物規模を変更',
            'building_type' => '建物タイプを変更',
            'building_number' => '建物番号を変更',
            'project_number' => '案件番号を変更',
            'status' => 'ステータスを変更',
            'project_order_type' => '受注形態を変更',
            'priority' => '優先度を変更',
            'estimate_status' => '見積状況を変更',
            'estimate_date' => '見積日を変更',
            'estimate_number' => '見積番号を変更',
            'invoice_status' => '請求状況を変更',
            'invoice_date' => '請求日を変更',
            'invoice_amount' => '請求金額を変更',
            'invoice_number' => '請求番号を変更',
            'payment_status' => '入金状況を変更',
            'payment_date' => '入金日を変更',
            'payment_amount' => '入金額を変更',
            'receipt_number' => '領収書番号を変更',
            'payment_note' => '決済備考を変更',
            'tags' => 'タグを変更',
            'amount' => '総額を変更',
            'teams' => 'チームを変更',
            'progress' => '進捗を変更',
            'department_id' => '部門を変更',
            'parent_project_id' => '親案件を変更',
            // 'is_kadai' => '課題を変更',
            // 'department_custom_fields_set_id' => 'カスタムフィールドセットを変更',
            'custom_fields' => 'custom_fieldsを変更',
            'start_date' => '開始日を変更',
            'end_date' => '終了日を変更',
            'tantou' => '担当を変更',
            'caily_nouki' => 'CAILY納期を変更',
            'guis_nouki' => 'GUIS納期を変更',
            'actual_end_date' => '実終了日を変更',
            'caily_nouki_status' => 'CAILY納期状況を変更',
            'guis_nouki_status' => 'GUIS納期状況を変更',
        ];
        $skipKeys = ['updated_at', 'updated_by'];
        foreach ($data as $key => $newVal) {
            if (in_array($key, $skipKeys, true)) {
                continue;
            }
            $oldVal = isset($old[$key]) ? $old[$key] : '';
            $oldStr = is_scalar($oldVal) ? (string)$oldVal : json_encode($oldVal);
            $newStr = is_scalar($newVal) ? (string)$newVal : json_encode($newVal);
            
            // custom_fields: so sánh theo label ổn định để tránh lệch khi thứ tự mảng thay đổi
            if ($key === 'custom_fields') {
                $oldArr = is_string($oldVal) ? (json_decode($oldVal, true) ?: []) : (is_array($oldVal) ? $oldVal : []);
                $newArr = is_string($newVal) ? (json_decode($newVal, true) ?: []) : (is_array($newVal) ? $newVal : []);

                $oldByLabel = [];
                foreach ($oldArr as $idx => $item) {
                    $label = '';
                    if (is_array($item) && isset($item['label'])) {
                        $label = trim((string)$item['label']);
                    }
                    if ($label === '') {
                        $label = '__index_' . (string)$idx;
                    }
                    $oldByLabel[$label] = $item;
                }

                $newByLabel = [];
                foreach ($newArr as $idx => $item) {
                    $label = '';
                    if (is_array($item) && isset($item['label'])) {
                        $label = trim((string)$item['label']);
                    }
                    if ($label === '') {
                        $label = '__index_' . (string)$idx;
                    }
                    $newByLabel[$label] = $item;
                }

                $allLabels = array_unique(array_merge(array_keys($oldByLabel), array_keys($newByLabel)));
                foreach ($allLabels as $fieldLabel) {
                    $o = array_key_exists($fieldLabel, $oldByLabel) ? $oldByLabel[$fieldLabel] : null;
                    $n = array_key_exists($fieldLabel, $newByLabel) ? $newByLabel[$fieldLabel] : null;
                    if (!$this->customFieldValuesEqual($o, $n)) {
                        $oldDisplay = $this->customFieldValueToDisplay($o);
                        $newDisplay = $this->customFieldValueToDisplay($n);
                        $labelDisplay = (is_array($n) && isset($n['label'])) ? trim((string)$n['label']) : ((is_array($o) && isset($o['label'])) ? trim((string)$o['label']) : '');
                        if ($labelDisplay === '' && strpos($fieldLabel, '__index_') !== 0) {
                            $labelDisplay = $fieldLabel;
                        }
                        $note = $labelDisplay . 'を変更';
                        $this->logProjectAction($project_id, 'updated', $note . $aiLabel, $oldDisplay ? $oldDisplay : 'null', $newDisplay ? $newDisplay : 'null');
                    }
                }
                continue;
            }
            
            // Nếu là teams, convert IDs thành names
            if ($key === 'teams') {
                $oldStr = $this->convertTeamIdsToNames($oldStr);
                $newStr = $this->convertTeamIdsToNames($newStr);
            }

            if ($key === 'status') {
                if ($oldStr !== $newStr) {
                    $this->logProjectAction($project_id, 'status_changed', 'ステータス変更' . $aiLabel, $oldStr, $newStr);
                }
                continue;
            }
            
            $changed = false;
            if (in_array($key, $datetimeFields, true)) {
                $oldNorm = $this->normalizeDatetimeForCompare($oldVal);
                $newNorm = $this->normalizeDatetimeForCompare($newVal);
                $changed = ($oldNorm !== $newNorm);
            } else {
                $changed = ($oldStr !== $newStr);
            }
            if ($changed) {
                $note = isset($fieldLabels[$key]) ? $fieldLabels[$key] : $key . 'を変更';
                // Không lưu nội dung origin/thay đổi cho một số trường (vd: description)
                $noValueLogFields = ['description'];
                $v1 = in_array($key, $noValueLogFields, true) ? '' : $oldStr;
                $v2 = in_array($key, $noValueLogFields, true) ? '' : $newStr;
                $this->logProjectAction($project_id, 'updated', $note . $aiLabel, $v1, $v2);
            }
        }
        foreach ($nullDatetimeFields as $field) {
            $oldVal = isset($old[$field]) ? $old[$field] : '';
            if ($this->normalizeDatetimeForCompare($oldVal) === '') {
                continue; // Cũ đã rỗng, không coi là thay đổi
            }
            $note = isset($fieldLabels[$field]) ? $fieldLabels[$field] : $field . 'を変更';
            $this->logProjectAction($project_id, 'updated', $note . $aiLabel, (string)$oldVal, '');
        }
    }


    function delete() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'error' => 'No project id'];
        // Phase 4.1 – Permission check for project delete (AI-triggered or any caller)
        if (!$this->canUserEditProject($id)) {
            return ['status' => 'error', 'error' => 'Forbidden', 'http_status' => 403];
        }
        $old = $this->getById($id);
        $result = $this->query_update(['status' => 'cancelled'], ['id' => $id]);
        if ($result) {
            $this->logProjectAction($id, 'deleted', '案件を削除', $old['status'], 'cancelled');
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

    /**
     * Add a member to project. project_members: user_id = numeric (user.id), userid = string (user.userid).
     * @param int $project_id
     * @param int $user_id user.id (numeric), not userid string
     * @param string $username user.userid (string login), for project_members.userid
     */
    function addMember($project_id, $user_id, $username, $role = 'member', $log = true) {
        if(!$user_id) return false;
        // If username is not provided, get it from user_id
        if (!$username) {
            $user = $this->fetchOne("SELECT userid FROM " . DB_PREFIX . "user WHERE id = " . intval($user_id));
            if (!$user || !$user['userid']) {
                return false;
            }
            $username = $user['userid'];
        }
        
        // Check if member already exists
        $existing = $this->fetchOne(
            "SELECT id FROM " . DB_PREFIX . "project_members " .
            "WHERE project_id = " . intval($project_id) . " " .
            "AND user_id = " . intval($user_id)
        );
        if ($existing) {
            return false;
        }

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
        
        if ($result && $log) {
            // Lấy tên user để log chi tiết hơn
            $user = $this->fetchOne("SELECT realname FROM " . DB_PREFIX . "user WHERE id = " . intval($user_id));
            $userName = isset($user['realname']) ? $user['realname'] : '';
            
            // Xác định là AI hay manual
            $isAi = (isset($_POST['_ai_triggered']) && $_POST['_ai_triggered']) ? true : false;
            $sourceLabel = $isAi ? '[AI]' : '[手動]';
            
            // Format note với tên user và role
            $roleLabel = ($role === 'manager') ? 'マネージャー' : 'メンバー';
            $note = $userName ? "メンバー追加 $sourceLabel: {$userName} ({$roleLabel})" : "メンバー追加 $sourceLabel: {$roleLabel}";
            
            $this->logProjectAction($project_id, 'member_added', $note, '', '');
        }
        
        return $result;
    }
    
    function addMemberApi($params = null) {
        // Get parameters from $_POST or $params array
        if (is_array($params)) {
            $project_id = isset($params['project_id']) ? intval($params['project_id']) : (isset($_POST['project_id']) ? intval($_POST['project_id']) : 0);
            $user_id = isset($params['user_id']) ? intval($params['user_id']) : (isset($_POST['user_id']) ? intval($_POST['user_id']) : 0);
            $role = isset($params['role']) ? $params['role'] : (isset($_POST['role']) ? $_POST['role'] : 'member');
        } else {
            $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
            $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
            $role = isset($_POST['role']) ? $_POST['role'] : 'member';
        }
        
        if(!$project_id || !$user_id) {
            return [
                'status' => 'error',
                'message' => 'Project ID and User ID are required'
            ];
        }
        // Phase 4.3 – Permission check for project member/team assignment
        // if (!$this->canUserEditProject($project_id)) {
        //     return ['status' => 'error', 'message' => 'Forbidden', 'http_status' => 403];
        // }
        
        // Get project to check department
        $project = $this->fetchOne("SELECT department_id FROM " . DB_PREFIX . "projects WHERE id = " . intval($project_id));
        if (!$project) {
            return [
                'status' => 'error',
                'message' => 'Project not found'
            ];
        }
        
        // Check if project has a department
        if (!$project['department_id']) {
            return [
                'status' => 'error',
                'message' => 'Project does not have a department assigned'
            ];
        }
        
        // Get username from user_id
        $user = $this->fetchOne("SELECT userid FROM " . DB_PREFIX . "user WHERE id = " . intval($user_id));
        if (!$user || !$user['userid']) {
            return [
                'status' => 'error',
                'message' => 'User not found'
            ];
        }
        $username = $user['userid'];
        
        // Check if user to be added is in the same department as the project
        $userToAddDepartmentCheck = $this->fetchOne(
            "SELECT * FROM " . DB_PREFIX . "user_department ud " .
            "WHERE ud.department_id = " . intval($project['department_id']) . " " .
            "AND ud.userid = '" . $this->escape($username) . "' LIMIT 1"
        );
        
        // Allow admin to add members regardless of department
        $isAdmin = $_SESSION['authority'] == 'administrator';
        
        // Check if user to be added is in the same department (unless admin)
        if (!$isAdmin && !$userToAddDepartmentCheck) {
            return [
                'status' => 'error',
                'message' => '同じ部署のユーザーのみ参加できます'
            ];
        }
        
        // Check if member already exists; allow changing role (member <-> manager)
        $existing = $this->fetchOne(
            "SELECT id, role FROM " . DB_PREFIX . "project_members " .
            "WHERE project_id = " . intval($project_id) . " " .
            "AND user_id = " . intval($user_id)
        );
        if ($existing) {
            $currentRole = isset($existing['role']) ? $existing['role'] : 'member';
            $newRole = ($role === 'manager') ? 'manager' : 'member';
            if ($currentRole === $newRole) {
                return [
                    'status' => 'success',
                    'message' => 'User already has this role'
                ];
            }
            $result = $this->updateMemberRole($project_id, $user_id, $newRole);
            return $result
                ? ['status' => 'success', 'message' => 'Role updated to ' . $newRole]
                : ['status' => 'error', 'message' => 'Failed to update role'];
        }

        // Call the original addMember method
        $result = $this->addMember($project_id, $user_id, $username, $role);
        
        if ($result) {
            return [
                'status' => 'success',
                'message' => 'Member added successfully'
            ];
        }
        
        return [
            'status' => 'error',
            'message' => 'Failed to add member'
        ];
    }

    /**
     * Update a project member's role (member <-> manager).
     * @param int $project_id
     * @param int $user_id user.id (numeric)
     * @param string $role 'member' or 'manager'
     */
    function updateMemberRole($project_id, $user_id, $role) {
        $role = ($role === 'manager') ? 'manager' : 'member';
        $this->table = DB_PREFIX . 'project_members';
        
        // Lấy role cũ và tên user trước khi update
        $existing = $this->fetchOne(
            "SELECT pm.role, u.realname " .
            "FROM " . DB_PREFIX . "project_members pm " .
            "LEFT JOIN " . DB_PREFIX . "user u ON pm.user_id = u.id " .
            "WHERE pm.project_id = " . intval($project_id) . " AND pm.user_id = " . intval($user_id)
        );
        $oldRole = isset($existing['role']) ? $existing['role'] : '';
        $userName = isset($existing['realname']) ? $existing['realname'] : '';
        
        $result = $this->query_update(
            ['role' => $role],
            ['project_id' => intval($project_id), 'user_id' => intval($user_id)]
        );
        $this->table = DB_PREFIX . 'projects';
        if ($result) {
            // Xác định là AI hay manual
            $isAi = (isset($_POST['_ai_triggered']) && $_POST['_ai_triggered']) ? true : false;
            $sourceLabel = $isAi ? '[AI]' : '';
            
            // Format note với tên user và role
            $roleLabel = ($role === 'manager') ? 'マネージャー' : 'メンバー';
            $oldRoleLabel = ($oldRole === 'manager') ? 'マネージャー' : 'メンバー';
            $note = $userName ? "権限変更 $sourceLabel: {$userName} ({$oldRoleLabel} → {$roleLabel})" : "権限変更 $sourceLabel: {$oldRoleLabel} → {$roleLabel}";
            
            $this->logProjectAction($project_id, 'member_role_updated', $note, $oldRole, $role);
        }
        return $result;
    }

    /**
     * Remove a member from project. $user_id = numeric id (user.id), not userid string.
     * project_members: user_id = numeric, userid = string login.
     */
    function removeMember($project_id, $user_id) {
        // Lấy thông tin user và role trước khi xóa để log
        $existing = $this->fetchOne(
            "SELECT pm.role, u.realname " .
            "FROM " . DB_PREFIX . "project_members pm " .
            "LEFT JOIN " . DB_PREFIX . "user u ON pm.user_id = u.id " .
            "WHERE pm.project_id = " . intval($project_id) . " AND pm.user_id = " . intval($user_id)
        );
        $role = isset($existing['role']) ? $existing['role'] : '';
        $userName = isset($existing['realname']) ? $existing['realname'] : '';
        
        $this->table = DB_PREFIX . 'project_members';
        $result = $this->query_delete(['project_id' => intval($project_id), 'user_id' => intval($user_id)]);
        $this->table = DB_PREFIX . 'projects'; // Reset table back to projects
        
        if ($result) {
            // Xác định là AI hay manual
            $isAi = (isset($_POST['_ai_triggered']) && $_POST['_ai_triggered']) ? true : false;
            $sourceLabel = $isAi ? '[AI]' : '';
            
            // Format note với tên user và role
            $roleLabel = ($role === 'manager') ? 'マネージャー' : 'メンバー';
            $note = $userName ? "メンバー削除 $sourceLabel: {$userName} ({$roleLabel})" : "メンバー削除 $sourceLabel: {$roleLabel}";
            
            $this->logProjectAction($project_id, 'member_removed', $note, '', '');
        }
        
        return $result;
    }

    function getById($params = null) {
        // Handle both direct ID parameter and params array from API
        if (is_array($params)) {
            $id = isset($params['id']) ? $params['id'] : 0;
        } else {
            $id = $params;
        }
        
        $user_id = $_SESSION['id'];
        
        $projectId = intval($id);
        $query = sprintf(
            "SELECT p.*, d.name as department_name,
            c.name as contact_name, c.company_name, c.branch as branch_name, c.category_id as category_id
            FROM {$this->table} p 
            LEFT JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id
            LEFT JOIN " . DB_PREFIX . "customer c ON c.id = SUBSTRING_INDEX(p.customer_id, ',', 1)
            WHERE p.id = %d",
            $projectId
        );
        
        $project = $this->fetchOne($query);
        
        if ($project) {
            $this->attachProjectDetailAggregates($project, $user_id);
            $project['quotation_status'] = $this->getQuotationStatus($id);
            $project['version'] = isset($project['version']) ? intval($project['version']) : 1;
        }
        
        return $project;
    }
    
    /**
     * Toggle favorite status for a project
     */
    function toggleFavorite($params = null) {
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $user_id = $_SESSION['id'];
        
        if (!$project_id || !$user_id) {
            return array(
                'status' => 'error',
                'message' => 'Invalid parameters'
            );
        }
        
        // Check if favorite exists
        $checkQuery = sprintf(
            "SELECT id FROM " . DB_PREFIX . "project_favorites 
             WHERE project_id = %d AND user_id = %d",
            $project_id,
            $user_id
        );
        $existing = $this->fetchOne($checkQuery);
        
        if ($existing) {
            // Remove favorite
            $deleteQuery = sprintf(
                "DELETE FROM " . DB_PREFIX . "project_favorites 
                 WHERE project_id = %d AND user_id = %d",
                $project_id,
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
                "INSERT INTO " . DB_PREFIX . "project_favorites (project_id, user_id, created_at) 
                 VALUES (%d, %d, NOW())",
                $project_id,
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
            "DELETE FROM " . DB_PREFIX . "project_favorites 
             WHERE user_id = %d",
            $user_id
        );
        $this->query($deleteQuery);
        
        return array(
            'status' => 'success',
            'message' => 'すべてのお気に入りを削除しました'
        );
    }

    function updateProgress($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $progress = isset($_POST['progress']) ? intval($_POST['progress']) : 0;
        if (!$id) return false;

        // Lấy giá trị cũ để ghi log
        $old = $this->getById($id);
        $oldProgress = isset($old['progress']) ? $old['progress'] : null;

        $data = array(
            'progress' => $progress,
            'updated_by' => $_SESSION['userid'],
            'updated_at' => date('Y-m-d H:i:s')
        );
        $result = $this->query_update($data, ['id' => $id]);

        // Ghi project log nếu tiến độ thay đổi
        if ($result && $oldProgress !== null && (int)$oldProgress !== (int)$progress) {
            $this->logProjectAction(
                $id,
                'progress_updated',
                '進捗率変更',
                (string)$oldProgress,
                (string)$progress
            );
        }

        return $result;
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

    /**
     * Update project status from AI/API (checks canUserEditProject).
     * @param int $project_id
     * @param string $status draft|open|confirming|quotation|contract|in_progress|completed|paused|cancelled
     * @return array ['status'=>'success'|'error', 'message'|'error'=>...]
     */
    public function updateStatusForAi($project_id, $status) {
        $project_id = intval($project_id);
        $status = trim((string) $status);
        $allowed = ['draft', 'open', 'confirming', 'quotation', 'contract', 'in_progress', 'completed', 'paused', 'cancelled'];
        if ($project_id <= 0 || !in_array($status, $allowed, true)) {
            return ['status' => 'error', 'error' => 'Invalid project_id or status'];
        }
        if (!$this->canUserEditProject($project_id)) {
            return ['status' => 'error', 'error' => 'Forbidden', 'http_status' => 403];
        }
        $proj = $this->getById($project_id);
        if (!$proj) {
            return ['status' => 'error', 'error' => 'Project not found'];
        }
        $_POST['id'] = $project_id;
        $_POST['status'] = $status;
        $_POST['name'] = isset($proj['name']) ? $proj['name'] : '';
        $_POST['project_number'] = isset($proj['project_number']) ? $proj['project_number'] : '';
        return $this->updateStatus(null);
    }

    /**
     * Update only 受注形態 (project_order_type) for a project. Used by AI execute_action.
     * @param int $project_id
     * @param string $project_order_type e.g. 修正, 契約図, 新規, その他
     */
    public function updateProjectOrderTypeForAi($project_id, $project_order_type) {
        $project_id = intval($project_id);
        $project_order_type = trim((string) $project_order_type);
        if ($project_id <= 0) {
            return ['status' => 'error', 'error' => 'Invalid project_id'];
        }
        if (!$this->canUserEditProject($project_id)) {
            return ['status' => 'error', 'error' => 'Forbidden', 'http_status' => 403];
        }
        $proj = $this->getById($project_id);
        if (!$proj) {
            return ['status' => 'error', 'error' => 'Project not found'];
        }
        $this->table = DB_PREFIX . 'projects';
        $ok = $this->query_update(
            [
                'project_order_type' => $project_order_type,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $_SESSION['userid']
            ],
            ['id' => $project_id]
        );
        return $ok ? ['status' => 'success', 'message' => '受注形態を更新しました'] : ['status' => 'error', 'error' => 'Update failed'];
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
        
        // Save previous status when cancelling
        if ($status === 'cancelled' && $old['status'] !== 'cancelled') {
            $data['previous_status'] = $old['status'];
        }
        
        // Cập nhật actual_start_date nếu chuyển sang in_progress
        if ($status == 'in_progress') {
            $project = $this->getById($id);
            if (!$project['actual_start_date']) {
                $data['actual_start_date'] = date('Y-m-d H:i:s');
            }
        }
        
        // Auto-set progress to 100 if status is completed
        if ($status == 'completed') {
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
        
        if ($result) {
            return ['status' => 'success', 'message' => 'Status updated successfully'];
        } else {
            return ['status' => 'error', 'message' => 'Failed to update status'];
        }
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
        
        // Get thread_id filter if provided
        $thread_id = isset($_GET['thread_id']) ? intval($_GET['thread_id']) : null;
        
        $whereClause = "c.project_id = " . intval($project_id);
        if ($thread_id !== null && $thread_id > 0) {
            $whereClause .= " AND c.thread_id = " . intval($thread_id);
        }
        // If thread_id is null or 0, get all comments (for backward compatibility and search)
        
        $query = sprintf(
            "SELECT c.*, u.realname as user_name, u.user_image
            FROM " . DB_PREFIX . "comments c 
            LEFT JOIN " . DB_PREFIX . "user u ON c.user_id = u.userid 
            WHERE %s
            ORDER BY c.created_at DESC
            LIMIT %d OFFSET %d",
            $whereClause,
            intval($per_page),
            intval($offset)
        );
        
        $comments = $this->fetchAll($query);
        $this->attachCommentLikeAggregates($comments);
        
        return $comments;
    }
    
    // Get latest comment info for polling (without Firebase)
    function getLatestCommentInfo() {
        $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
        $since = isset($_GET['since']) ? $_GET['since'] : null; // Timestamp or comment ID to check from
        
        if (!$project_id) return ['latest_comment_id' => null, 'latest_comment_at' => null, 'thread_id' => null];
        
        $whereClause = "c.project_id = " . intval($project_id);
        
        // If since is provided, only get comments after that time/ID
        if ($since) {
            // Check if since is a timestamp or comment ID
            if (is_numeric($since) && strlen($since) > 10) {
                // It's a timestamp
                $sinceDate = date('Y-m-d H:i:s', intval($since) / 1000);
                $whereClause .= " AND c.created_at > '" . $this->quote($sinceDate) . "'";
            } else {
                // It's a comment ID
                $whereClause .= " AND c.id > " . intval($since);
            }
        }
        
        $query = sprintf(
            "SELECT c.id, c.thread_id, c.created_at
            FROM " . DB_PREFIX . "comments c 
            WHERE %s
            ORDER BY c.created_at DESC, c.id DESC
            LIMIT 1",
            $whereClause
        );
        
        $latest = $this->fetchOne($query);
        
        if ($latest) {
            return [
                'latest_comment_id' => $latest['id'],
                'latest_comment_at' => $latest['created_at'],
                'thread_id' => $latest['thread_id']
            ];
        }
        
        return ['latest_comment_id' => null, 'latest_comment_at' => null, 'thread_id' => null];
    }

    // Thread management methods
    function createThread() {
        $data = $_POST;
        $project_id = isset($data['project_id']) ? intval($data['project_id']) : 0;
        $title = isset($data['title']) ? trim($data['title']) : '';
        $user_id = isset($data['user_id']) ? $data['user_id'] : '';
        
        if (!$project_id || !$title || !$user_id) {
            return ['success' => false, 'message' => '必要な情報が不足しています'];
        }
        
        $threadData = array(
            'project_id' => $project_id,
            'title' => $title,
            'created_by' => $user_id,
            'created_at' => date('Y-m-d H:i:s')
        );
        
        $this->table = DB_PREFIX . 'comment_threads';
        $result = $this->query_insert($threadData);
        $this->table = DB_PREFIX . 'projects';
        
        if ($result) {
            return ['success' => true, 'id' => $result, 'message' => 'スレッドを作成しました'];
        }
        return ['success' => false, 'message' => 'スレッドの作成に失敗しました'];
    }
    
    function getThreads() {
        $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
        $user_id = isset($_SESSION['userid']) ? $_SESSION['userid'] : '';
        
        if (!$project_id) return [];
        
        $query = sprintf(
            "SELECT t.*, u.realname as creator_name, u.user_image as creator_image
            FROM " . DB_PREFIX . "comment_threads t
            LEFT JOIN " . DB_PREFIX . "user u ON t.created_by = u.userid
            WHERE t.project_id = %d",
            intval($project_id)
        );
        
        $threads = $this->fetchAll($query);
        
        if (empty($threads)) {
            return [];
        }

        $this->attachThreadListAggregates($threads);

        $threadIds = array_map('intval', array_column($threads, 'id'));
        $idsList = implode(',', $threadIds);
        $userEsc = $this->quote($user_id);

        $unreadMap = [];
        $unreadRows = $this->fetchAll(sprintf(
            "SELECT c.thread_id, COUNT(*) as unread_count
             FROM %scomments c
             LEFT JOIN %scomment_thread_reads r
               ON r.thread_id = c.thread_id AND r.user_id = '%s'
             WHERE c.thread_id IN (%s)
               AND (r.last_read_comment_id IS NULL OR r.last_read_comment_id = 0 OR c.id > r.last_read_comment_id)
             GROUP BY c.thread_id",
            DB_PREFIX,
            DB_PREFIX,
            $userEsc,
            $idsList
        ));
        foreach ($unreadRows as $row) {
            $unreadMap[(int)$row['thread_id']] = intval($row['unread_count']);
        }

        foreach ($threads as &$thread) {
            $tid = (int)$thread['id'];
            if ($thread['last_comment_id']) {
                $thread['unread_count'] = $unreadMap[$tid] ?? 0;
            } else {
                $thread['unread_count'] = 0;
            }
            
            // Strip HTML tags from last comment content for preview
            if ($thread['last_comment_content']) {
                $thread['last_comment_preview'] = $this->stripHtmlTags($thread['last_comment_content'], 100);
            } else {
                $thread['last_comment_preview'] = '';
            }
        }
        unset($thread);

        usort($threads, function ($a, $b) {
            $ta = !empty($a['last_comment_at']) ? $a['last_comment_at'] : ($a['created_at'] ?? '');
            $tb = !empty($b['last_comment_at']) ? $b['last_comment_at'] : ($b['created_at'] ?? '');
            return strcmp($tb, $ta);
        });
        
        return $threads;
    }

    private function attachThreadListAggregates(array &$threads) {
        if (empty($threads)) {
            return;
        }
        $threadIds = array_values(array_filter(array_map('intval', array_column($threads, 'id')), function ($id) {
            return $id > 0;
        }));
        if (empty($threadIds)) {
            return;
        }
        $idsList = implode(',', $threadIds);

        $countMap = [];
        $countRows = $this->fetchAll(sprintf(
            "SELECT thread_id, COUNT(*) as comment_count, MAX(created_at) as last_comment_at
             FROM %scomments WHERE thread_id IN (%s) GROUP BY thread_id",
            DB_PREFIX,
            $idsList
        ));
        foreach ($countRows as $row) {
            $countMap[(int)$row['thread_id']] = $row;
        }

        $lastMap = [];
        $lastRows = $this->fetchAll(sprintf(
            "SELECT c.thread_id, c.id as last_comment_id, c.content as last_comment_content,
                    c.user_id as last_comment_user_id, c.created_at as last_comment_at, u.realname as last_comment_user_name
             FROM %scomments c
             INNER JOIN (
                 SELECT thread_id, MAX(id) as max_id FROM %scomments WHERE thread_id IN (%s) GROUP BY thread_id
             ) lm ON c.id = lm.max_id
             LEFT JOIN %suser u ON c.user_id = u.userid",
            DB_PREFIX,
            DB_PREFIX,
            $idsList,
            DB_PREFIX
        ));
        foreach ($lastRows as $row) {
            $lastMap[(int)$row['thread_id']] = $row;
        }

        foreach ($threads as &$thread) {
            $tid = (int)$thread['id'];
            $counts = $countMap[$tid] ?? null;
            $last = $lastMap[$tid] ?? null;
            $thread['comment_count'] = $counts ? (int)$counts['comment_count'] : 0;
            if ($last) {
                $thread['last_comment_id'] = (int)$last['last_comment_id'];
                $thread['last_comment_content'] = $last['last_comment_content'];
                $thread['last_comment_user_id'] = $last['last_comment_user_id'];
                $thread['last_comment_user_name'] = $last['last_comment_user_name'];
                $thread['last_comment_at'] = $last['last_comment_at'];
            } else {
                $thread['last_comment_id'] = null;
                $thread['last_comment_content'] = null;
                $thread['last_comment_user_id'] = null;
                $thread['last_comment_user_name'] = null;
                $thread['last_comment_at'] = $counts['last_comment_at'] ?? null;
            }
        }
        unset($thread);
    }
    
    // Helper function to strip HTML and get preview
    private function stripHtmlTags($html, $maxLength = 100) {
        // Remove HTML tags
        $text = strip_tags($html);
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        // Truncate if too long
        if (mb_strlen($text) > $maxLength) {
            $text = mb_substr($text, 0, $maxLength) . '...';
        }
        return $text;
    }
    
    function getCommentsByThread() {
        $thread_id = isset($_GET['thread_id']) ? intval($_GET['thread_id']) : 0;
        if (!$thread_id) return [];
        
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
        $offset = ($page - 1) * $per_page;
        
        $query = sprintf(
            "SELECT c.*, u.realname as user_name, u.user_image
            FROM " . DB_PREFIX . "comments c 
            LEFT JOIN " . DB_PREFIX . "user u ON c.user_id = u.userid 
            WHERE c.thread_id = %d 
            ORDER BY c.created_at DESC
            LIMIT %d OFFSET %d",
            intval($thread_id),
            intval($per_page),
            intval($offset)
        );
        
        $comments = $this->fetchAll($query);
        $this->attachCommentLikeAggregates($comments);
        
        return $comments;
    }
    
    function searchComments() {
        $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
        $search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
        
        if (!$project_id || !$search_term) return [];
        
        // Encode search term to HTML entities to match database content
        // Convert spaces to &nbsp; and encode other special characters
        $encoded_search = htmlentities($search_term, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Also replace spaces with &nbsp; entity
        $encoded_search = str_replace(' ', '&nbsp;', $encoded_search);
        
        // Escape both original and encoded versions for SQL
        $escaped_original = $this->quote($search_term);
        $escaped_encoded = $this->quote($encoded_search);
        
        $pattern_original = '%' . $escaped_original . '%';
        $pattern_encoded = '%' . $escaped_encoded . '%';
        
        // Search in both original and encoded formats
        $query = sprintf(
            "SELECT c.*, u.realname as user_name, u.user_image, t.title as thread_title, t.id as thread_id
            FROM " . DB_PREFIX . "comments c 
            LEFT JOIN " . DB_PREFIX . "user u ON c.user_id = u.userid
            LEFT JOIN " . DB_PREFIX . "comment_threads t ON c.thread_id = t.id
            WHERE c.project_id = %d 
            AND (c.content LIKE '%s' OR c.content LIKE '%s' OR t.title LIKE '%s')
            ORDER BY c.created_at DESC
            LIMIT 100",
            intval($project_id),
            $pattern_original,
            $pattern_encoded,
            $pattern_original
        );
        
        $comments = $this->fetchAll($query);
        $this->attachCommentLikeAggregates($comments);
        
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
        
        // Add thread_id if provided
        if (isset($data['thread_id']) && $data['thread_id']) {
            $commentData['thread_id'] = intval($data['thread_id']);
        }
        
        $this->table = DB_PREFIX . 'comments';
        $result = $this->query_insert($commentData);
        $this->table = DB_PREFIX . 'projects'; // Reset table back to projects
        
        // Send mention notifications if comment was added successfully
        if ($result) {
            $threadId = isset($data['thread_id']) && $data['thread_id'] ? intval($data['thread_id']) : null;
            
            // Send mention notifications (only for mentions)
            $this->sendMentionNotifications($data['project_id'], $data['content'], $data['user_id'], $result, $threadId);
            
            // Note: We removed Firebase notification - using polling instead
            // No need to send Firebase notification for every comment
            
            return [
                'success' => true, 
                'message' => 'Comment added successfully',
                'comment_id' => $result,
                'thread_id' => $threadId
            ];
        }
        return ['success' => false, 'message' => 'Comment addition failed'];
    }
    
    // Mark thread as read (update last_read_comment_id)
    function markThreadAsRead() {
        $thread_id = isset($_POST['thread_id']) ? intval($_POST['thread_id']) : 0;
        $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : (isset($_SESSION['userid']) ? $_SESSION['userid'] : '');
        $last_comment_id = isset($_POST['last_comment_id']) ? intval($_POST['last_comment_id']) : 0;
        
        if (!$thread_id || !$user_id || !$last_comment_id) {
            return ['success' => false, 'message' => 'Invalid parameters'];
        }
        
        // Check if record exists
        $existing = $this->fetchOne(sprintf(
            "SELECT id FROM " . DB_PREFIX . "comment_thread_reads 
             WHERE thread_id = %d AND user_id = '%s'",
            intval($thread_id),
            $this->quote($user_id)
        ));
        
        $this->table = DB_PREFIX . 'comment_thread_reads';
        
        if ($existing) {
            // Update existing record - use direct query because user_id is string
            $query = sprintf(
                "UPDATE " . DB_PREFIX . "comment_thread_reads 
                 SET last_read_comment_id = %d 
                 WHERE thread_id = %d AND user_id = '%s'",
                intval($last_comment_id),
                intval($thread_id),
                $this->quote($user_id)
            );
            $result = $this->query($query);
        } else {
            // Insert new record
            $result = $this->query_insert([
                'thread_id' => $thread_id,
                'user_id' => $user_id,
                'last_read_comment_id' => $last_comment_id
            ]);
        }
        
        $this->table = DB_PREFIX . 'projects';
        
        return ['success' => (bool)$result];
    }
    
    // Get comment info including thread_id by comment ID
    function getCommentInfo() {
        $comment_id = isset($_GET['comment_id']) ? intval($_GET['comment_id']) : 0;
        if (!$comment_id) {
            return ['success' => false, 'message' => 'Invalid comment ID'];
        }
        
        $query = sprintf(
            "SELECT c.id, c.thread_id, c.project_id, c.user_id, c.content, c.created_at,
                    t.title as thread_title
            FROM " . DB_PREFIX . "comments c
            LEFT JOIN " . DB_PREFIX . "comment_threads t ON c.thread_id = t.id
            WHERE c.id = %d
            LIMIT 1",
            intval($comment_id)
        );
        
        $comment = $this->fetchOne($query);
        
        if ($comment) {
            return [
                'success' => true,
                'comment' => $comment
            ];
        }
        
        return ['success' => false, 'message' => 'Comment not found'];
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
    private function sendMentionNotifications($projectId, $content, $commentUserId, $commentId, $threadId = null) {
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

                $titleJa = '#'.$projectId.': 案件でメンションされました';
                $messageJa = sprintf('%sさんが案件「%s」であなたをメンションしました', 
                    $_SESSION['realname'], 
                    $project['name']
                );
                $titleVi = '#'.$projectId.': Dự án có bình luận mới';
                $messageVi = sprintf('%s đã nhắc đến bạn trong dự án「%s」', $_SESSION['realname'], $project['name']);
                $payload = [
                    'event' => 'project_mention',
                    'title' => $titleJa,
                    'message' => $messageJa,
                    'data' => [
                        'project_id' => $projectId,
                        'project_name' => $project['name'],
                        'comment_id' => $commentId,
                        'comment_content' => $content,
                        'commenter_id' => $commentUserId,
                        'commenter_name' => $_SESSION['realname'],
                        'avatar' => $this->getUserImage(),
                        'url' => "/project/detail.php?id=$projectId#comment-$commentId",
                        'title_ja' => $titleJa,
                        'message_ja' => $messageJa,
                        'title_vi' => $titleVi,
                        'message_vi' => $messageVi,
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

    /**
     * Business document view: project_director_stat / view / edit, or department manager.
     */
    public function canUserViewBusinessDocuments($project_id) {
        $project_id = intval($project_id);
        if ($project_id <= 0) {
            return false;
        }
        if (isset($_SESSION['authority']) && $_SESSION['authority'] === 'administrator') {
            return true;
        }
        $project = $this->fetchOne("SELECT department_id FROM " . $this->table . " WHERE id = " . $project_id);
        if (!$project || !isset($project['department_id'])) {
            return false;
        }
        $dept_id = intval($project['department_id']);
        $current_userid = isset($_SESSION['userid']) ? $this->escape($_SESSION['userid']) : '';
        if ($dept_id <= 0 || !$current_userid) {
            return false;
        }
        $row = $this->fetchOne(sprintf(
            "SELECT project_manager, project_director, project_director_stat, project_director_view, project_director_edit
            FROM %suser_department
            WHERE department_id = %d AND userid = '%s'",
            DB_PREFIX,
            $dept_id,
            $current_userid
        ));
        if (!$row) {
            return false;
        }
        if ((int)($row['project_manager'] ?? 0) === 1) {
            return true;
        }
        return (int)($row['project_director_stat'] ?? 0) === 1
            || (int)($row['project_director_view'] ?? 0) === 1
            || (int)($row['project_director_edit'] ?? 0) === 1
            || (int)($row['project_director'] ?? 0) === 1;
    }

    /**
     * Business document edit: project_director_edit or department manager.
     */
    public function canUserEditBusinessDocuments($project_id) {
        $project_id = intval($project_id);
        if ($project_id <= 0) {
            return false;
        }
        if (isset($_SESSION['authority']) && $_SESSION['authority'] === 'administrator') {
            return true;
        }
        $project = $this->fetchOne("SELECT department_id FROM " . $this->table . " WHERE id = " . $project_id);
        if (!$project || !isset($project['department_id'])) {
            return false;
        }
        $dept_id = intval($project['department_id']);
        $current_userid = isset($_SESSION['userid']) ? $this->escape($_SESSION['userid']) : '';
        if ($dept_id <= 0 || !$current_userid) {
            return false;
        }
        $row = $this->fetchOne(sprintf(
            "SELECT project_manager, project_director_edit
            FROM %suser_department
            WHERE department_id = %d AND userid = '%s'",
            DB_PREFIX,
            $dept_id,
            $current_userid
        ));
        if (!$row) {
            return false;
        }
        if ((int)($row['project_manager'] ?? 0) === 1) {
            return true;
        }
        return (int)($row['project_director_edit'] ?? 0) === 1;
    }

    function updateProjectStatus($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'error' => 'No project id'];

        if (!$this->canUserEditBusinessDocuments($id)) {
            return ['status' => 'error', 'error' => 'Forbidden', 'http_status' => 403];
        }
        
        // Lấy dữ liệu cũ để so sánh và ghi log
        $old = $this->getById($id);
        if (!$old) {
            return ['status' => 'error', 'error' => 'Project not found'];
        }

        $versionCheck = $this->assertProjectVersionMatches($old);
        if (!$versionCheck['ok']) {
            return $versionCheck['response'];
        }
        $expectedVersion = $versionCheck['expected_version'];
        
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
        if (isset($_POST['estimate_number'])) {
            $data['estimate_number'] = $_POST['estimate_number'];
        }
        if (isset($_POST['invoice_status'])) {
            $data['invoice_status'] = $_POST['invoice_status'];
        }
        if (array_key_exists('invoice_amount', $_POST)) {
            $data['invoice_amount'] = $_POST['invoice_amount'] !== '' ? floatval($_POST['invoice_amount']) : 0;
        }
        if (isset($_POST['invoice_number'])) {
            $data['invoice_number'] = $_POST['invoice_number'];
        }
        if (isset($_POST['payment_status'])) {
            $data['payment_status'] = $_POST['payment_status'];
        }
        if (array_key_exists('payment_amount', $_POST)) {
            $data['payment_amount'] = $_POST['payment_amount'] !== '' ? floatval($_POST['payment_amount']) : 0;
        }
        if (isset($_POST['receipt_number'])) {
            $data['receipt_number'] = $_POST['receipt_number'];
        }
        if (isset($_POST['payment_note'])) {
            $data['payment_note'] = $_POST['payment_note'];
        }

        $nullDatetimeFields = [];
        $businessDatetimeFields = [
            'estimate_date' => '18:00',
            'invoice_date' => '18:00',
            'payment_date' => '18:00',
        ];
        foreach ($businessDatetimeFields as $field => $defaultTime) {
            if (!array_key_exists($field, $_POST)) {
                continue;
            }
            $val = trim((string)$_POST[$field]);
            if ($val === '') {
                $nullDatetimeFields[] = $field;
                continue;
            }
            $parsed = $this->normalize_datetime_with_default($val, $defaultTime);
            if ($parsed !== null) {
                $data[$field] = $parsed;
            } else {
                $nullDatetimeFields[] = $field;
            }
        }
        
        $versionedUpdate = $this->performVersionedProjectUpdate($id, $data, $expectedVersion);
        if (!$versionedUpdate['ok']) {
            return $versionedUpdate['response'];
        }
        $result = true;
        $newProjectVersion = $versionedUpdate['version'];

        if ($result && !empty($nullDatetimeFields)) {
            $setParts = [];
            foreach ($nullDatetimeFields as $field) {
                $setParts[] = sprintf("`%s` = NULL", $this->escape($field));
            }
            $query = sprintf("UPDATE %s SET %s WHERE id = %d", $this->table, implode(', ', $setParts), $id);
            $this->query($query);
        }
        
        if ($result) {
            $this->logBusinessDocumentChanges($id, $old, $data, $nullDatetimeFields);

            return ['status' => 'success', 'version' => $newProjectVersion];
        } else {
            return ['status' => 'error', 'error' => 'Update failed'];
        }
    }

    private function logBusinessDocumentChanges($project_id, array $old, array $data, array $nullDatetimeFields = []) {
        $new = array_merge($old, $data);
        foreach ($nullDatetimeFields as $field) {
            $new[$field] = null;
        }

        $fields = [
            'amount' => ['action' => 'amount_updated', 'note' => '見積金額を変更', 'numeric' => true],
            'estimate_status' => ['action' => 'estimate_status_updated', 'note' => '見積状況を変更'],
            'estimate_date' => ['action' => 'estimate_date_updated', 'note' => '見積日を変更'],
            'estimate_number' => ['action' => 'estimate_number_updated', 'note' => '見積番号を変更'],
            'invoice_status' => ['action' => 'invoice_status_updated', 'note' => '請求状況を変更'],
            'invoice_date' => ['action' => 'invoice_date_updated', 'note' => '請求日を変更'],
            'invoice_amount' => ['action' => 'invoice_amount_updated', 'note' => '請求金額を変更', 'numeric' => true],
            'invoice_number' => ['action' => 'invoice_number_updated', 'note' => '請求番号を変更'],
            'payment_status' => ['action' => 'payment_status_updated', 'note' => '入金状況を変更'],
            'payment_date' => ['action' => 'payment_date_updated', 'note' => '入金日を変更'],
            'payment_amount' => ['action' => 'payment_amount_updated', 'note' => '入金額を変更', 'numeric' => true],
            'receipt_number' => ['action' => 'receipt_number_updated', 'note' => '領収書番号を変更'],
            'payment_note' => ['action' => 'payment_note_updated', 'note' => '決済備考を変更'],
        ];

        $datetimeFields = ['estimate_date', 'invoice_date', 'payment_date'];

        foreach ($fields as $field => $meta) {
            if (!array_key_exists($field, $data) && !in_array($field, $nullDatetimeFields, true)) {
                continue;
            }
            $oldVal = $old[$field] ?? null;
            $newVal = $new[$field] ?? null;
            if (!empty($meta['numeric'])) {
                if ((float)$oldVal === (float)$newVal) {
                    continue;
                }
            } elseif (in_array($field, $datetimeFields, true)) {
                if ($this->normalizeDatetimeForCompare($oldVal) === $this->normalizeDatetimeForCompare($newVal)) {
                    continue;
                }
            } elseif (trim((string)$oldVal) === trim((string)$newVal)) {
                continue;
            }
            $this->logProjectAction(
                $project_id,
                $meta['action'],
                $meta['note'],
                $oldVal !== null && $oldVal !== '' ? (string)$oldVal : '',
                $newVal !== null && $newVal !== '' ? (string)$newVal : ''
            );
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
     * Hàm tiện ích để gửi thông báo khi tạo dự án mới
     */
    function notifyProjectCreated($projectId, $projectName, $userIds = null) {

        // Get project information to get department_id
        $project = $this->getById($projectId);
        $departmentId = $project ? $project['department_id'] : 0;
        
        // Get department managers if department is specified
        $managerIds = [];
        if ($departmentId > 0) {
            $managerIds = $this->getDepartmentManagers($departmentId);
        }
        
        // Combine member IDs and manager IDs, remove duplicates
        $allUserIds = [];
        if ($userIds) {
            $allUserIds = array_merge($allUserIds, $userIds);
        }
        if (!empty($managerIds)) {
            $allUserIds = array_merge($allUserIds, $managerIds);
        }
        $allUserIds = array_unique($allUserIds);

        $projectName = strlen($projectName) > 15 ? substr($projectName, 0, 15) . '...' : $projectName;
        
        // Title / message đa ngôn ngữ
        $titleJa = '#'.$projectId.': 新しい案件が作成されました';
        $messageJa = sprintf('%sが案件「%s」を作成しました', $this->getUserRealname(), $projectName);
        $titleVi = '#'.$projectId.': Dự án mới đã được tạo';
        $messageVi = sprintf('%s đã tạo dự án「%s」', $this->getUserRealname(), $projectName);
        
        $params = [
            'event' => 'project_created',
            // Tiêu đề/mô tả mặc định (JA) để backend và client cũ sử dụng
            'title' => $titleJa,
            'message' => $messageJa,
            'project_id' => $projectId,
            'user_ids' => $allUserIds,
            'data' => [
                'project_name' => $projectName,
                'action' => 'created',
                'avatar' => $this->getUserImage(),
                'url' => "/project/detail.php?id=$projectId",
                // Đa ngôn ngữ cho frontend (notification.js chọn theo i18n)
                'title_ja' => $titleJa,
                'message_ja' => $messageJa,
                'title_vi' => $titleVi,
                'message_vi' => $messageVi,
                'is_important' => 1,
            ],
            'type' => 'project'
        ];
        
        return $this->sendProjectNotification($params);
    }
    
    /**
     * Hàm tiện ích để gửi thông báo khi cập nhật dự án
     */
    function notifyProjectUpdated($projectId, $projectName, $changes = []) {
        // Title / message đa ngôn ngữ
        $titleJa = '#'.$projectId.': 案件が更新されました';
        $messageJa = sprintf('案件「%s」が更新されました', $projectName);
        $titleVi =  '#'.$projectId.': Dự án đã được cập nhật';
        $messageVi = sprintf('Dự án「%s」đã được cập nhật', $projectName);
        
        $params = [
            'event' => 'project_updated',
            'title' => $titleJa,
            'message' => $messageJa,
            'project_id' => $projectId,
            'data' => [
                'project_name' => $projectName,
                'changes' => $changes,
                'action' => 'updated',
                'title_ja' => $titleJa,
                'message_ja' => $messageJa,
                'title_vi' => $titleVi,
                'message_vi' => $messageVi,
            ],
            'url' => "/project/detail.php?id=$projectId",
            'type' => 'project'
        ];
        
        return $this->sendProjectNotification($params);
    }

    /**
     * Hàm tiện ích để gửi thông báo khi cập nhật dự án
     */
    function notifyProjectCailyNouhinUpdated($projectId, $projectName, $updatedBy,  $cailyNoukiStatus, $recipientIds = []) {
        // Title / message đa ngôn ngữ
        $titleJa = '#'.$projectId.': 案件「'.$projectName.'」 CAILY納品済み';
        $messageJa = sprintf('%sがCAILY納品状況を「%s」に更新しました', $updatedBy, $cailyNoukiStatus);
        $titleVi =  '#'.$projectId.': Dự án「'.$projectName.'」 CAILY Đã giao';
        $messageVi = sprintf('%s đã cập nhật trạng thái giao hàng CAILY thành「%s」', $updatedBy, $cailyNoukiStatus);
        
        $params = [
            'event' => 'project_caily_nouki_updated',
            'title' => $titleJa,
            'message' => $messageJa,
            'project_id' => $projectId,
            'user_ids' => $recipientIds,
            'data' => [
                'project_name' => $projectName,
                'action' => 'updated',
                'title_ja' => $titleJa,
                'message_ja' => $messageJa,
                'title_vi' => $titleVi,
                'message_vi' => $messageVi,
                'is_important' => 1,
                'avatar' => $this->getUserImage(),
                'url' => "/project/detail.php?id=$projectId",
            ],
            'type' => 'project',
        ];
        
        return $this->sendProjectNotification($params);
    }

    function notifyProjectGuisNoukiUpdated($projectId, $projectName, $updatedBy,  $guisNoukiStatus, $recipientIds = []) {
        // Title / message đa ngôn ngữ
        $titleJa = '#'.$projectId.': 案件「'.$projectName.'」 GUIS納品済み';
        $messageJa = sprintf('%sがGUIS納品状況を「%s」に更新しました', $updatedBy, $guisNoukiStatus);
        $titleVi =  '#'.$projectId.': Dự án「'.$projectName.'」 GUIS Đã giao';
        $messageVi = sprintf('%s đã cập nhật trạng thái giao hàng GUIS thành「%s」', $updatedBy, $guisNoukiStatus);
        
        $params = [
            'event' => 'project_guis_nouki_updated',
            'title' => $titleJa,
            'message' => $messageJa,
            'project_id' => $projectId,
            'user_ids' => $recipientIds,
            'data' => [
                'project_name' => $projectName,
                'action' => 'updated',
                'title_ja' => $titleJa,
                'message_ja' => $messageJa,
                'title_vi' => $titleVi,
                'message_vi' => $messageVi,
                'is_important' => 1,
                'avatar' => $this->getUserImage(),
                'url' => "/project/detail.php?id=$projectId",
            ],
            'type' => 'project',
        ];
        
        return $this->sendProjectNotification($params);
    }
    
    
    /**
     * Map project status key to Japanese label for logs/notifications.
     */
    private function getProjectStatusLabelsJa() {
        return [
            'draft' => '受付',
            'open' => '納期検討',
            'confirming' => '仮受',
            'quotation' => '見積',
            'contract' => '請負',
            'in_progress' => '進行中',
            'completed' => '完了',
            'paused' => '一時停止',
            'cancelled' => '中止',
            'deleted' => '削除'
        ];
    }

    private function getProjectStatusLabelJa($status) {
        $labels = $this->getProjectStatusLabelsJa();
        $key = is_scalar($status) ? trim((string)$status) : '';
        return $key !== '' && isset($labels[$key]) ? $labels[$key] : $key;
    }

    /**
     * Hàm tiện ích để gửi thông báo khi thay đổi trạng thái dự án
     */
    function notifyProjectStatusChanged($projectNumber, $projectId, $projectName, $newStatus, $memberIds) {
        $projectName = strlen($projectName) > 15 ? substr($projectName, 0, 15) . '...' : $projectName;
        $statusLabelsJa = $this->getProjectStatusLabelsJa();

        $statusLabelsVi = [
            'draft' => 'Nháp',
            'open' => 'Đánh giá kì hạn',
            'confirming' => 'Tạm nhận',
            'quotation' => 'Báo giá',
            'contract' => 'Hợp đồng',
            'in_progress' => 'Đang tiến hành',
            'completed' => 'Hoàn thành',
            'paused' => 'Tạm dừng',
            'cancelled' => 'Hủy bỏ',
            'deleted' => 'Đã xóa'
        ];
        
        $newStatusLabelJa = $statusLabelsJa[$newStatus] ?? $newStatus;
        $newStatusLabelVi = $statusLabelsVi[$newStatus] ?? $newStatus;

        $isImportant = in_array($newStatus, ['completed', 'cancelled', 'deleted', 'in_progress', 'paused']) ? 1 : 0;
        
        $titleJa = '#'.$projectId.': ステータスが変更されました';
        $messageJa = sprintf('%sが案件「%s」のステータスを「%s」に変更しました', $this->getUserRealname(), $projectName, $newStatusLabelJa);
        $titleVi = '#'.$projectId.': Trạng thái đã được thay đổi';
        $messageVi = sprintf('%s đã thay đổi trạng thái của dự án「%s」thành「%s」', $this->getUserRealname(), $projectName, $newStatusLabelVi);
        
        $params = [
            'event' => 'project_status_changed',
            'title' => $titleJa,
            'message' => $messageJa,
            'project_id' => $projectId,
            'user_ids' => $memberIds,
            'data' => [
                'project_name' => $projectName,
                'project_number' => $projectNumber,
                'action' => 'status_changed',
                'avatar' => $this->getUserImage(),
                'url' => "/project/detail.php?id=$projectId",
                'title_ja' => $titleJa,
                'message_ja' => $messageJa,
                'title_vi' => $titleVi,
                'message_vi' => $messageVi,
                'is_important' => $isImportant,
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
        $projectName = strlen($projectName) > 15 ? substr($projectName, 0, 15) . '...' : $projectName;
        
        $roleLabelJa = $role === 'manager' ? 'マネージャー' : 'メンバー';
        $roleLabelVi = $role === 'manager' ? 'Quản lý' : 'Thành viên';
        
        $titleJa = '#'.$projectId.': メンバーが追加されました';
        $messageJa = sprintf('%sがあなたを案件「%s」に%sを追加しました', $this->getUserRealname(), $projectName, $roleLabelJa);
        $titleVi = '#'.$projectId.': Thành viên đã được thêm';
        $messageVi = sprintf('%s đã thêm bạn làm %s trong dự án「%s」', $this->getUserRealname(), $roleLabelVi, $projectName);
        
        $params = [
            'event' => 'project_member_added',
            'title' => $titleJa,
            'message' => $messageJa,
            'project_id' => $projectId,
            'user_ids' => $memberIds,
            'data' => [
                'project_name' => $projectName,
                'avatar' => $this->getUserImage(),
                'role' => $role,
                'role_label' => $roleLabelJa,
                'action' => 'member_added',
                'url' => "/project/detail.php?id=$projectId",
                'title_ja' => $titleJa,
                'message_ja' => $messageJa,
                'title_vi' => $titleVi,
                'message_vi' => $messageVi,
                'is_important' => $role === 'manager' ? 1 : 0,
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
        $projectName = strlen($projectName) > 15 ? substr($projectName, 0, 15) . '...' : $projectName;
        $roleLabel = $role === 'manager' ? 'マネージャー' : 'メンバー';
        $roleLabelVi = $role === 'manager' ? 'Quản lý' : 'Thành viên';
        $titleJa = '#'.$projectId.': メンバーが削除されました';
        $messageJa = sprintf('%sがあなたを案件「%s」から%sを削除しました', $this->getUserRealname(), $projectName, $roleLabel);
        $titleVi = '#'.$projectId.': Thành viên đã được xóa';
        $messageVi = sprintf('%s đã xóa bạn khỏi %s của dự án「%s」', $this->getUserRealname(), $roleLabelVi, $projectName);
        $params = [
            'event' => 'project_member_removed',
            'project_id' => $projectId,
            'user_ids' => $memberIds,
            'title' => $titleJa,
            'message' => $messageJa,
            'data' => [
                'project_name' => $projectName,
                'avatar' => $this->getUserImage(),
                'role' => $role,
                'role_label' => $roleLabel,
                'action' => 'member_removed',
                'url' => "",
                'title_ja' => $titleJa,
                'message_ja' => $messageJa,
                'title_vi' => $titleVi,
                'message_vi' => $messageVi,
            ],
            'type' => 'project'
        ];
        
        return $this->sendProjectNotification($params);
    }
    
    /**
     * Hàm tiện ích để gửi thông báo khi có comment mới
     */
    function notifyNewComment($projectId, $projectName, $commentId, $commentContent, $excludeUserId = null) {

        $projectName = strlen($projectName) > 15 ? substr($projectName, 0, 15) . '...' : $projectName;
        $titleJa = '案件に新しいコメントがあります';
        $messageJa = sprintf('案件「%s」に新しいコメントが追加されました', $projectName);
        $titleVi = 'Dự án có bình luận mới';
        $messageVi = sprintf('Dự án「%s」có bình luận mới', $projectName);
        $params = [
            'event' => 'project_comment_added',
            'title' => $titleJa,
            'message' => $messageJa,
            'project_id' => $projectId,
            'exclude_user_ids' => $excludeUserId ? [$excludeUserId] : [],
            'data' => [
                'project_name' => $projectName,
                'comment_id' => $commentId,
                'comment_content' => substr($commentContent, 0, 100) . (strlen($commentContent) > 100 ? '...' : ''),
                'action' => 'comment_added',
                'avatar' => $this->getUserImage(),
                'title_ja' => $titleJa,
                'message_ja' => $messageJa,
                'title_vi' => $titleVi,
                'message_vi' => $messageVi,
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

    /**
     * Convert comma-separated team IDs to comma-separated team names.
     * @param string $teamIds Comma-separated team IDs (e.g. "1,2,3" or "")
     * @return string Comma-separated team names (e.g. "Team A, Team B, Team C" or "")
     */
    private function convertTeamIdsToNames($teamIds) {
        if (empty($teamIds) || trim($teamIds) === '') {
            return '';
        }
        $ids = array_filter(array_map('intval', explode(',', $teamIds)));
        if (empty($ids)) {
            return '';
        }
        $idsStr = implode(',', $ids);
        $query = "SELECT GROUP_CONCAT(name SEPARATOR ', ') as team_names FROM " . DB_PREFIX . "team WHERE id IN (" . $idsStr . ")";
        $result = $this->fetchOne($query);
        return isset($result['team_names']) ? $result['team_names'] : '';
    }

    // Ghi log hành động dự án (xóa thành viên, xóa manager, thay đổi team, v.v.)
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
        try {
            $this->table = DB_PREFIX . 'project_logs';
            $this->query_insert($data);
            $this->table = DB_PREFIX . 'projects'; // reset lại table
        } catch (Exception $e) {
            error_log('Project log insert failed: ' . $e->getMessage() . ' [project_id=' . $project_id . ', action=' . $action . ']');
            $this->table = DB_PREFIX . 'projects';
        }
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
            "SELECT f.*, u.realname as created_by_name
             FROM " . DB_PREFIX . "project_folders f
             LEFT JOIN " . DB_PREFIX . "user u ON f.created_by = u.userid
             WHERE f.project_id = %d AND %s
             ORDER BY f.name ASC",
            $project_id,
            $folder_id ? "f.parent_folder_id = $folder_id" : "f.parent_folder_id IS NULL"
        );
        $folders = $this->fetchAll($folderQuery);
        $this->attachFolderListAggregates($folders);
        
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
        $folder_id = (int)$folder_id;
        if ($folder_id <= 0) {
            return [];
        }
        $start = $this->fetchOne(sprintf(
            "SELECT project_id FROM %sproject_folders WHERE id = %d",
            DB_PREFIX,
            $folder_id
        ));
        if (!$start || empty($start['project_id'])) {
            return [];
        }
        $allFolders = $this->fetchAll(sprintf(
            "SELECT id, name, parent_folder_id FROM %sproject_folders WHERE project_id = %d",
            DB_PREFIX,
            (int)$start['project_id']
        ));
        $folderById = [];
        foreach ($allFolders as $folder) {
            $folderById[(int)$folder['id']] = $folder;
        }
        return $this->buildFolderBreadcrumbsFromMap($folder_id, $folderById);
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
        // Check if info mode is requested
        if (isset($_GET['info']) && $_GET['info'] == '1') {
            return $this->getAttachmentInfo();
        }
        
        // Check if direct download is requested (bypass detail page)
        if (isset($_GET['download']) && $_GET['download'] == '1') {
            $this->serveSecureFile(true); // force download
            return;
        }
        
        // Check if inline view is explicitly requested
        if (isset($_GET['inline']) && $_GET['inline'] == '1') {
            $this->serveSecureFile(false); // inline view
            return;
        }
        
        // Default: redirect to detail page
        $file_id = isset($_GET['file_id']) ? intval($_GET['file_id']) : 0;
        if ($file_id) {
            header('Location: ' . ROOT . 'project/file-view.php?file_id=' . $file_id);
            exit;
        }
        
        // Fallback: serve file inline if no file_id
        $this->serveSecureFile(false);
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
                "SELECT a.*, p.name as project_name, u.realname as uploaded_by_name
                 FROM " . DB_PREFIX . "project_attachments a
                 LEFT JOIN " . DB_PREFIX . "projects p ON a.project_id = p.id
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
                "SELECT f.*, p.name as project_name, u.realname as created_by_name
                 FROM " . DB_PREFIX . "project_folders f
                 LEFT JOIN " . DB_PREFIX . "projects p ON f.project_id = p.id
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
        $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
        
        // If no folder_id, download all files in project (root)
        if ($folder_id === null) {
            if (!$project_id) {
                http_response_code(400);
                die('プロジェクトIDが指定されていません。');
            }
            return $this->downloadProjectZip($project_id);
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
            "SELECT f.*, p.name as project_name
             FROM " . DB_PREFIX . "project_folders f
             LEFT JOIN " . DB_PREFIX . "projects p ON f.project_id = p.id
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
    
    // Download all attachments in a project as ZIP (root level)
    private function downloadProjectZip($project_id) {
        // Check if ZipArchive class is available
        if (!class_exists('ZipArchive')) {
            // Try to use shell command as fallback (if available)
            if (function_exists('shell_exec') && !empty(shell_exec('which zip'))) {
                return $this->downloadProjectZipShell($project_id);
            } else {
                http_response_code(500);
                die('ZIP機能を使用するにはPHPのzip拡張機能が必要です。サーバー管理者に連絡してください。');
            }
        }
        
        // Get project info
        $projectQuery = sprintf(
            "SELECT * FROM " . DB_PREFIX . "projects WHERE id = %d",
            $project_id
        );
        $project = $this->fetchOne($projectQuery);
        
        if (!$project) {
            http_response_code(404);
            die('プロジェクトが見つかりません。');
        }
        
        // Create zip file
        $zipFileName = tempnam(sys_get_temp_dir(), 'project_') . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            http_response_code(500);
            die('ZIPファイルの作成に失敗しました。');
        }
        
        // Add root files (files without folder_id)
        $rootFilesQuery = sprintf(
            "SELECT * FROM " . DB_PREFIX . "project_attachments WHERE project_id = %d AND folder_id IS NULL",
            $project_id
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
            "SELECT * FROM " . DB_PREFIX . "project_folders WHERE project_id = %d AND parent_folder_id IS NULL",
            $project_id
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
        $encodedFileName = rawurlencode($project['name'] . '_attachments.zip');
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
    
    // Fallback method for project zip using shell command
    private function downloadProjectZipShell($project_id) {
        // Get project info
        $projectQuery = sprintf(
            "SELECT * FROM " . DB_PREFIX . "projects WHERE id = %d",
            $project_id
        );
        $project = $this->fetchOne($projectQuery);
        
        if (!$project) {
            http_response_code(404);
            die('プロジェクトが見つかりません。');
        }
        
        // Create temporary directory
        $tempDir = sys_get_temp_dir() . '/zip_' . uniqid();
        mkdir($tempDir, 0755, true);
        
        // Copy root files
        $rootFilesQuery = sprintf(
            "SELECT * FROM " . DB_PREFIX . "project_attachments WHERE project_id = %d AND folder_id IS NULL",
            $project_id
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
            "SELECT * FROM " . DB_PREFIX . "project_folders WHERE project_id = %d AND parent_folder_id IS NULL",
            $project_id
        );
        $rootFolders = $this->fetchAll($rootFoldersQuery);
        
        foreach ($rootFolders as $folder) {
            $this->copyFolderToTemp($tempDir, $folder['id'], $folder['name']);
        }
        
        // Create zip using shell command
        $zipFileName = sys_get_temp_dir() . '/project_' . $project_id . '_' . time() . '.zip';
        $command = "cd " . escapeshellarg($tempDir) . " && zip -r " . escapeshellarg($zipFileName) . " . 2>&1";
        $output = shell_exec($command);
        
        // Clean up temp directory
        $this->deleteDirectory($tempDir);
        
        if (!file_exists($zipFileName)) {
            http_response_code(500);
            die('ZIPファイルの作成に失敗しました。');
        }
        
        // Send zip file
        $encodedFileName = rawurlencode($project['name'] . '_attachments.zip');
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
            "SELECT f.*, p.name as project_name
             FROM " . DB_PREFIX . "project_folders f
             LEFT JOIN " . DB_PREFIX . "projects p ON f.project_id = p.id
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
        
        // For text-based content, ensure UTF-8 charset so Japanese/Vietnamese display correctly
        $contentType = $mimeType;
        if (
            strpos($mimeType, 'text/') === 0 ||
            stripos($mimeType, 'html') !== false ||
            in_array($mimeType, ['application/json', 'application/javascript', 'application/xml'])
        ) {
            $contentType .= '; charset=UTF-8';
        }
        
        // Set headers
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . $fileSize);
        
        // Encode filename safely for Japanese characters
        $encodedFileName = rawurlencode($fileName);
        $dispositionType = $forceDownload ? 'attachment' : 'inline';
        header(
            sprintf(
                'Content-Disposition: %s; filename="%s"; filename*=UTF-8\'\'%s',
                $dispositionType,
                $encodedFileName,
                $encodedFileName
            )
        );
        
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
     * Generate project_number for a child project based on parent_project.
     * Format: [parent_project.project_number]-01, -02, ...
     * @param int $parent_project_id
     * @return string
     */
    function generateProjectNumberForParent($parent_project_id) {
        $parent_project_id = intval($parent_project_id);
        if ($parent_project_id <= 0) {
            return 'P-01';
        }
        $parent = $this->fetchOne(sprintf(
            "SELECT project_number FROM " . DB_PREFIX . "parent_projects WHERE id = %d LIMIT 1",
            $parent_project_id
        ));
        $parent_number = isset($parent['project_number']) ? trim($parent['project_number']) : '';
        if ($parent_number === '') {
            return 'P-01';
        }
        $like_pattern = str_replace(array('%', '_'), array('\\%', '\\_'), $parent_number) . '-%';
        $this->table = DB_PREFIX . 'projects';
        $query = sprintf(
            "SELECT project_number FROM " . DB_PREFIX . "projects WHERE parent_project_id = %d AND project_number LIKE '%s'",
            $parent_project_id,
            $this->quote($like_pattern)
        );
        $result = $this->fetchAll($query);
        $maxNumber = 0;
        foreach ($result as $row) {
            if (!empty($row['project_number'])) {
                $suffix = substr($row['project_number'], strlen($parent_number) + 1);
                if (preg_match('/^(\d+)$/', trim($suffix), $matches)) {
                    $num = intval($matches[1]);
                    if ($num > $maxNumber) {
                        $maxNumber = $num;
                    }
                }
            }
        }
        $nextNumber = $maxNumber + 1;
        return $parent_number . '-' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Normalize date/datetime from POST: if value is date-only (YYYY-MM-DD) append default time; else parse with strtotime.
     * Start date → 09:00, deadline/end date → 18:00.
     */
    private function normalize_datetime_with_default($value, $defaultTime) {
        $value = trim($value ?? '');
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value . ' ' . $defaultTime;
        }
        $ts = strtotime($value);
        return $ts !== false ? date('Y-m-d H:i', $ts) : null;
    }

    /**
     * Parse date input to datetime Y-m-d H:i:s (GMT+9 / Asia/Tokyo). Same logic as ParentProject::parse_request_date_to_datetime.
     * Supports: "Mon Jun 30 22:00:00 GMT+07:00 2025" (JS Date → JST), "2025/07/09" (Y/m/d), "7/9" (m/d), "07/09/2025" (m/d/Y).
     * @param string $defaultTime e.g. '09:00:00' for start_date, '18:00:00' for end/nouki
     */
    private function parse_date_to_datetime($input, $year, $defaultTime = '00:00:00') {
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
                return sprintf('%04d-%02d-%02d %s', $y, $m, $d, $defaultTime);
            }
        }
        if (count($parts) === 2) {
            $m = (int) $parts[0];
            $d = (int) $parts[1];
            $y = (int) $year;
            if ($m >= 1 && $m <= 12 && $d >= 1 && $d <= 31 && checkdate($m, $d, $y)) {
                return sprintf('%04d-%02d-%02d %s', $y, $m, $d, $defaultTime);
            }
        }
        return null;
    }

    /**
     * Find project by name, customer_id, project_order_type. Returns row with id or null.
     */
    function get_by_name_customer_order_type($name, $customer_id, $project_order_type, $end_date) {
        $name = trim($name ?? '');
        $customer_id = intval($customer_id);
        $project_order_type = trim($project_order_type ?? '');
        if ($name === '') {
            return null;
        }
        $query = sprintf(
            "SELECT id FROM %s WHERE TRIM(name) = '%s' AND customer_id = %d AND TRIM(COALESCE(project_order_type,'')) = '%s'",
            $this->table,
            $this->quote($name),
            $customer_id,
            $this->quote($project_order_type)
        );
        if ($end_date !== null) {
            $query .= sprintf(
                " AND end_date = '%s'",
                $this->quote($end_date)
            );
        }
        $query .= " LIMIT 1";
        return $this->fetchOne($query);
    }

    /**
     * Public API (no login): insert or update child project.
     * Params: parent_project_id, name, status (default completed), start_date, end_date, tantou (CAILY), caily_nouki, guis_nouki, created_by (admin), progress, project_order_type, department_id (default 5), customer_id.
     * If name + customer_id + project_order_type exists then update, else insert. On insert generate project_number via generateProjectNumberForParent(parent_project_id).
     */
    function upsert_project_public($params) {
        $hash = array('status' => 'error', 'message_code' => '', 'id' => null);
        $name = isset($params['name']) ? trim($params['name']) : '';
        $customer_id = isset($params['customer_id']) ? intval($params['customer_id']) : 0;
        $project_order_type = isset($params['project_order_type']) ? trim($params['project_order_type']) : '';
        if ($name === '') {
            $hash['message_code'] = 'name is required';
            return $hash;
        }
        $parent_project_id = isset($params['parent_project_id']) ? intval($params['parent_project_id']) : 0;
        if ($parent_project_id <= 0) {
            $hash['message_code'] = 'parent_project_id is required';
            return $hash;
        }
       
        $status = isset($params['status']) && trim($params['status'] ?? '') !== '' ? trim($params['status']) : 'completed';
        $department_id = isset($params['department_id']) && $params['department_id'] !== '' && $params['department_id'] !== null
            ? intval($params['department_id']) : 5;
        $progress = isset($params['progress']) && $params['progress'] !== '' && $params['progress'] !== null
            ? intval($params['progress']) : 0;
        $tantou = (isset($params['tantou']) && in_array(trim($params['tantou']), ['CAILY', 'GUIS'], true)) ? trim($params['tantou']) : 'CAILY';
        $created_by = 'admin';
        $amount = (isset($params['amount']) && $params['amount'] !== '' && $params['amount'] !== null)
            ? floatval($params['amount']) : 0;
        $description = isset($params['description']) ? trim($params['description']) : '';
        $year = date('Y');

       
        $start_date = $this->parse_date_to_datetime(isset($params['start_date']) ? $params['start_date'] : '', $year, '09:00:00');
        $end_date = $this->parse_date_to_datetime(isset($params['end_date']) ? $params['end_date'] : '', $year, '18:00:00');
        $caily_nouki = $this->parse_date_to_datetime(isset($params['caily_nouki']) ? $params['caily_nouki'] : '', $year, '18:00:00');
        $guis_nouki = $this->parse_date_to_datetime(isset($params['guis_nouki']) ? $params['guis_nouki'] : '', $year, '18:00:00');
       
       
        $existing = $this->get_by_name_customer_order_type($name, $customer_id, $project_order_type, $end_date);
        
        
        if ($existing && !empty($existing['id'])) {
            $data = array(
                'name' => $name,
                'description' => $description,
                'status' => $status,
                'tantou' => $tantou,
                'progress' => $progress,
                'project_order_type' => $project_order_type,
                'department_id' => $department_id,
                'amount' => $amount,
                'updated_by' => $created_by,
                'updated_at' => date('Y-m-d H:i:s'),
            );
            if ($parent_project_id > 0) {
                $data['parent_project_id'] = $parent_project_id;
            }
            if ($customer_id > 0) {
                $data['customer_id'] = $customer_id;
            }
            if ($start_date !== null) {
                $data['start_date'] = $start_date;
            }
            if ($end_date !== null) {
                $data['end_date'] = $end_date;
            }
            if ($caily_nouki !== null) {
                $data['caily_nouki'] = $caily_nouki;
            }
            if ($guis_nouki !== null) {
                $data['guis_nouki'] = $guis_nouki;
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
        $project_number = $this->generateProjectNumberForParent($parent_project_id);
        $data = array(
            'project_number' => $project_number,
            'name' => $name,
            'description' => $description,
            'status' => $status,
            'priority' => 'medium',
            'department_id' => $department_id,
            'progress' => $progress,
            'project_order_type' => $project_order_type,
            'tantou' => $tantou,
            'created_by' => $created_by,
            'created_at' => date('Y-m-d H:i:s'),
            'is_kadai' => 0,
            'amount' => $amount,
            'estimate_status' => '未発行',
            'invoice_status' => '未発行',
            'payment_status' => '未入金',
        );
        if ($parent_project_id > 0) {
            $data['parent_project_id'] = $parent_project_id;
        }
        if ($customer_id > 0) {
            $data['customer_id'] = $customer_id;
        }
        if ($start_date !== null) {
            $data['start_date'] = $start_date;
        }
        if ($end_date !== null) {
            $data['end_date'] = $end_date;
        }
        if ($caily_nouki !== null) {
            $data['caily_nouki'] = $caily_nouki;
        }
        if ($guis_nouki !== null) {
            $data['guis_nouki'] = $guis_nouki;
        }
        $new_id = $this->query_insert($data);
        if ($new_id) {
            $this->createChildProjectDefaultTasks($new_id, $data);
            $hash['status'] = 'success';
            $hash['message_code'] = 'created';
            $hash['id'] = (int) $new_id;
        } else {
            $hash['message_code'] = 'insert failed';
        }
        return $hash;
    }

    /**
     * Tạo 2 task mặc định (+ bản vẽ đồng bộ) khi tạo dự án con.
     */
    private function createChildProjectDefaultTasks($project_id, $data) {
        if (empty($data['parent_project_id']) || (int) $data['parent_project_id'] <= 0) {
            return;
        }

        $amount = isset($data['amount']) ? floatval($data['amount']) : 0;
        $createdBy = $this->resolveProjectCreatorUserId($data);

        if ($createdBy > 0) {
            $this->addMember($project_id, $createdBy, null, 'member', false);
        }

        if (!class_exists('Task')) {
            require_once __DIR__ . '/task.php';
        }
        $guisReceiverUserId = $this->resolveChildProjectGuisReceiverUserId($data);
        $taskModel = new Task();
        $taskModel->createDefaultTasksForProject($project_id, array(
            'amount' => $amount,
            'created_by' => $createdBy,
            'guis_receiver_user_id' => $guisReceiverUserId,
        ));
    }

    /**
     * Resolve GUIS 受付者 numeric user id for child project (own guis_receiver or parent building).
     */
    private function resolveChildProjectGuisReceiverUserId($data, $projectId = 0) {
        $guisReceiverUserid = '';
        if (is_array($data) && !empty($data['guis_receiver'])) {
            $guisReceiverUserid = trim((string) $data['guis_receiver']);
        }

        if ($guisReceiverUserid === '' && intval($projectId) > 0) {
            return $this->resolveChildProjectGuisReceiverUserIdFromDb($projectId);
        }

        if ($guisReceiverUserid === '' && is_array($data) && !empty($data['parent_project_id'])) {
            $parentId = intval($data['parent_project_id']);
            $parent = $this->fetchOne(sprintf(
                "SELECT guis_receiver FROM %sparent_projects WHERE id = %d LIMIT 1",
                DB_PREFIX,
                $parentId
            ));
            if ($parent && !empty($parent['guis_receiver'])) {
                $guisReceiverUserid = trim((string) $parent['guis_receiver']);
            }
        }

        return $this->resolveUseridToNumericId($guisReceiverUserid);
    }

    /**
     * Resolve GUIS 受付者 numeric user id for a project (project or parent building).
     */
    function getGuisReceiverNumericUserId($projectId) {
        return $this->resolveChildProjectGuisReceiverUserIdFromDb(intval($projectId));
    }

    private function resolveChildProjectGuisReceiverUserIdFromDb($projectId) {
        $projectId = intval($projectId);
        if ($projectId <= 0) {
            return 0;
        }
        $row = $this->fetchOne(sprintf(
            "SELECT p.guis_receiver, pp.guis_receiver AS parent_guis_receiver
             FROM %sprojects p
             LEFT JOIN %sparent_projects pp ON pp.id = p.parent_project_id
             WHERE p.id = %d LIMIT 1",
            DB_PREFIX,
            DB_PREFIX,
            $projectId
        ));
        if (!$row) {
            return 0;
        }
        $guisReceiverUserid = '';
        if (!empty($row['guis_receiver'])) {
            $guisReceiverUserid = trim((string) $row['guis_receiver']);
        } elseif (!empty($row['parent_guis_receiver'])) {
            $guisReceiverUserid = trim((string) $row['parent_guis_receiver']);
        }
        return $this->resolveUseridToNumericId($guisReceiverUserid);
    }

    private function syncChildProjectDefaultContactTaskGuisAssignee($projectId) {
        $guisUserId = $this->resolveChildProjectGuisReceiverUserIdFromDb($projectId);
        if ($guisUserId <= 0) {
            return;
        }
        if (!class_exists('Task')) {
            require_once __DIR__ . '/task.php';
        }
        $taskModel = new Task();
        $taskModel->assignDefaultContactTaskToUser($projectId, $guisUserId);
    }

    private function resolveUseridToNumericId($userid) {
        $userid = trim((string) $userid);
        if ($userid === '') {
            return 0;
        }
        $user = $this->fetchOne(sprintf(
            "SELECT id FROM %suser WHERE userid = '%s' LIMIT 1",
            DB_PREFIX,
            $this->quote($userid)
        ));
        return ($user && !empty($user['id'])) ? intval($user['id']) : 0;
    }

    /**
     * Resolve numeric user id of the child project creator (projects.created_by stores userid string).
     */
    private function resolveProjectCreatorUserId($data = null) {
        if (isset($_SESSION['id']) && intval($_SESSION['id']) > 0) {
            return intval($_SESSION['id']);
        }
        if (isset($_SESSION['user_id']) && intval($_SESSION['user_id']) > 0) {
            return intval($_SESSION['user_id']);
        }

        $userid = '';
        if (is_array($data) && !empty($data['created_by'])) {
            $userid = trim((string) $data['created_by']);
        } elseif (isset($_SESSION['userid'])) {
            $userid = trim((string) $_SESSION['userid']);
        }

        if ($userid === '') {
            return 0;
        }

        $user = $this->fetchOne(sprintf(
            "SELECT id FROM %suser WHERE userid = '%s' LIMIT 1",
            DB_PREFIX,
            $this->quote($userid)
        ));
        if ($user && !empty($user['id'])) {
            return intval($user['id']);
        }

        return 0;
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

    /**
     * Update project amount
     * @param array $params Array containing 'id' and 'amount'
     * @return array Status response
     */
    function updateAmount($params = null) {
        try {
            // Get parameters
            $id = isset($_POST['id']) ? intval($_POST['id']) : (isset($params['id']) ? intval($params['id']) : 0);
            $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : (isset($params['amount']) ? floatval($params['amount']) : 0);
            
            // Validate input
            if (!$id) {
                return ['status' => 'error', 'message' => 'Project ID is required'];
            }
            
            if ($amount < 0) {
                return ['status' => 'error', 'message' => 'Amount cannot be negative'];
            }
            
            // Check if project exists
            $existingProject = $this->getById($id);
            if (!$existingProject) {
                return ['status' => 'error', 'message' => 'Project not found'];
            }
            
            // Prepare update data
            $data = array(
                'amount' => $amount,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $_SESSION['userid']
            );
            
            // Perform update
            $result = $this->query_update($data, ['id' => $id]);
            
            if ($result) {
                return [
                    'status' => 'success', 
                    'message' => 'Project amount updated successfully',
                    'data' => [
                        'id' => $id,
                        'amount' => $amount,
                        'updated_at' => $data['updated_at']
                    ]
                ];
            } else {
                return ['status' => 'error', 'message' => 'Failed to update project amount'];
            }
            
        } catch (Exception $e) {
            error_log('Error in updateAmount: ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Confirm a kadai project (change is_kadai from 1 to 0)
     * @param array $params Array containing 'id'
     * @return array Status response
     */
    function confirm($params = null) {
        try {
            // Get parameters
            $id = isset($_POST['id']) ? intval($_POST['id']) : (isset($params['id']) ? intval($params['id']) : 0);
            
            // Validate input
            if (!$id) {
                return ['status' => 'error', 'message' => 'ID is required'];
            }
            
            // Check if project exists
            $existingProject = $this->getById($id);
            if (!$existingProject) {
                return ['status' => 'error', 'message' => 'Project not found'];
            }
            
            // Check if project is currently a kadai project
            if ($existingProject['is_kadai'] != 1) {
                return ['status' => 'error', 'message' => 'This project is not a kadai project'];
            }
            
            // Prepare update data
            $data = array(
                'is_kadai' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $_SESSION['userid']
            );
            
            // Perform update
            $result = $this->query_update($data, ['id' => $id]);
            
            if ($result) {
                // Log the confirm action
                $this->logProjectAction($id, 'confirmed', '案件承認', '', '');
                
                return [
                    'status' => 'success', 
                    'message' => 'Project confirmed successfully',
                    'data' => [
                        'id' => $id,
                        'is_kadai' => 0,
                        'updated_at' => $data['updated_at']
                    ]
                ];
            } else {
                return ['status' => 'error', 'message' => 'Failed to confirm project'];
            }
            
        } catch (Exception $e) {
            error_log('Error in confirm: ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Get quotation status for a project
     * @param int $project_id The project ID
     * @return string The quotation status
     */
    function getQuotationStatus($project_id) {
        try {
            $query = sprintf(
                "SELECT q.status 
                 FROM " . DB_PREFIX . "quotations q 
                 WHERE FIND_IN_SET(%d, q.selected_child_project_ids) > 0 
                 AND q.status NOT IN ('キャンセル', '却下')
                 ORDER BY 
                    CASE q.status 
                        WHEN '承認済み' THEN 1
                        WHEN '発行済み' THEN 2 
                        WHEN '調整' THEN 3
                        WHEN '下書き' THEN 4
                        ELSE 5
                    END ASC,
                    q.updated_at DESC
                 LIMIT 1",
                intval($project_id)
            );
            
            $result = $this->fetchOne($query);
            
            if ($result && !empty($result['status'])) {
                return $result['status'];
            }
            
            return '未発行';
            
        } catch (Exception $e) {
            error_log('Error getting quotation status: ' . $e->getMessage());
            return '未発行';
        }
    }

    /**
     * Command palette: quick search child projects (id, name, customer).
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
            'p.name',
            'CAST(p.id AS CHAR)',
            'pc.company_name',
            'pc.branch',
            'pc.name',
            'pp_c.company_name',
            'pp_c.branch',
            'pp_c.name',
            'pp.construction_number',
            'pp.company_name',
            'pp.branch_name',
        ], 'p.id');
        if ($searchWhere !== '') {
            $whereArr[] = $searchWhere;
        }
        $where = 'WHERE ' . implode(' AND ', $whereArr);
        $customerJoin = $this->getProjectListCustomerJoinSql();
        $query = sprintf(
            "SELECT p.id, p.name, p.project_number, p.project_order_type,
                    %s AS company_name,
                    %s AS branch_name,
                    pp.construction_number as parent_construction_number
             FROM %sprojects p
             LEFT JOIN %sparent_projects pp ON p.parent_project_id = pp.id
             %s
             %s
             ORDER BY p.updated_at DESC
             LIMIT 10",
            $this->sqlEffectiveCompanyName(),
            $this->sqlEffectiveBranchName(),
            DB_PREFIX,
            DB_PREFIX,
            $customerJoin,
            $where
        );
        return $this->fetchAll($query);
    }
}

?>