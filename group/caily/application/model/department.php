<?php

class Department extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'departments';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'name' => array(),
            'description' => array(),
            'can_project' => array(),
            'note_labels' => array(),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search')),
            'is_active' => array()
        );
        $this->connect();
        $this->ensureProjectViewEndDateColumn();
        $this->ensureNoteLabelsColumn();
        $this->ensureDefaultTasksTable();
        $this->ensureProjectListColumnDefaultsTable();
    }

    /**
     * プロジェクト権限: 期限日閲覧 (project_view_end_date)
     */
    private function ensureProjectViewEndDateColumn() {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;
        $table = DB_PREFIX . 'user_department';
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->quote($table) . "' "
            . "AND COLUMN_NAME = 'project_view_end_date'"
        );
        if (!empty($row['cnt'])) {
            return;
        }
        $this->query(
            "ALTER TABLE `{$table}` ADD COLUMN `project_view_end_date` tinyint(1) NOT NULL DEFAULT 0 "
            . "COMMENT 'プロジェクト権限: 期限日閲覧' AFTER `project_comment`"
        );
    }

    /**
     * メモラベル (note_labels) — JSON array of {name, color}
     */
    private function ensureNoteLabelsColumn() {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;
        $table = $this->table;
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->quote($table) . "' "
            . "AND COLUMN_NAME = 'note_labels'"
        );
        if (!empty($row['cnt'])) {
            return;
        }
        $this->query(
            "ALTER TABLE `{$table}` ADD COLUMN `note_labels` TEXT NULL DEFAULT NULL "
            . "COMMENT 'メモラベル JSON [{name,color}]' AFTER `can_project`"
        );
    }

    /**
     * Normalize note_labels to [{name, color}, ...]
     */
    private function normalizeNoteLabels($raw) {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        $seen = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = isset($item['name']) ? trim((string)$item['name']) : '';
            if ($name === '' || mb_strlen($name) > 100) {
                continue;
            }
            if (isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $color = isset($item['color']) ? trim((string)$item['color']) : '#6c757d';
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                $color = '#6c757d';
            }
            $out[] = ['name' => $name, 'color' => $color];
        }
        return $out;
    }

    private function encodeNoteLabels($raw) {
        $normalized = $this->normalizeNoteLabels($raw);
        if (empty($normalized)) {
            return null;
        }
        return json_encode($normalized, JSON_UNESCAPED_UNICODE);
    }

    private function decodeNoteLabelsForResponse($value) {
        return $this->normalizeNoteLabels($value);
    }

    private function parseNoteLabelsFromRequest() {
        if (isset($_POST['note_labels'])) {
            $raw = $_POST['note_labels'];
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    return $this->encodeNoteLabels($decoded);
                }
            }
            if (is_array($raw)) {
                return $this->encodeNoteLabels($raw);
            }
        }
        return null;
    }

    function list() {
        $query = sprintf(
            "SELECT c.*
            FROM {$this->table} c
            WHERE c.is_active = 1
            ORDER BY c.id ASC"
        );
        $rows = $this->fetchAll($query);
        $this->attachDepartmentListAggregates($rows);
        return $rows;
    }

    private function attachDepartmentListAggregates(array &$rows) {
        if (empty($rows)) {
            return;
        }
        $deptIds = array_values(array_filter(array_map('intval', array_column($rows, 'id')), function ($id) {
            return $id > 0;
        }));
        if (empty($deptIds)) {
            return;
        }
        $idsList = implode(',', $deptIds);

        $projectMap = [];
        $projectRows = $this->fetchAll(sprintf(
            "SELECT department_id, COUNT(*) as project_count FROM %sprojects WHERE department_id IN (%s) GROUP BY department_id",
            DB_PREFIX,
            $idsList
        ));
        foreach ($projectRows as $row) {
            $projectMap[(int)$row['department_id']] = (int)$row['project_count'];
        }

        $employeeMap = [];
        $employeeRows = $this->fetchAll(sprintf(
            "SELECT department_id, COUNT(*) as num_employees FROM %suser_department WHERE department_id IN (%s) GROUP BY department_id",
            DB_PREFIX,
            $idsList
        ));
        foreach ($employeeRows as $row) {
            $employeeMap[(int)$row['department_id']] = (int)$row['num_employees'];
        }

        foreach ($rows as &$row) {
            $deptId = (int)$row['id'];
            $row['project_count'] = $projectMap[$deptId] ?? 0;
            $row['num_employees'] = $employeeMap[$deptId] ?? 0;
            $row['note_labels'] = $this->decodeNoteLabelsForResponse(
                isset($row['note_labels']) ? $row['note_labels'] : null
            );
        }
        unset($row);
    }

    function listByUser() {
        if($_SESSION['authority'] != 'administrator'){
            $query = sprintf( 
                "SELECT DISTINCT c.*
                FROM {$this->table} c
                JOIN " . DB_PREFIX . "user_department ud ON c.id = ud.department_id
                WHERE ud.userid = '%s' AND c.can_project = 1 AND c.is_active = 1
                ORDER BY c.id ASC",
                $_SESSION['userid']
            );
        } else {
            $query = sprintf(
                "SELECT c.*
                FROM {$this->table} c
                WHERE c.is_active = 1 AND c.can_project = 1
                ORDER BY c.id ASC"
            );
        }
        
        $rows = $this->fetchAll($query);
        foreach ($rows as &$row) {
            $row['note_labels'] = $this->decodeNoteLabelsForResponse(
                isset($row['note_labels']) ? $row['note_labels'] : null
            );
        }
        unset($row);
        return $rows;
    }

    function list_department() {
        $query = sprintf(
            "SELECT c.*
            FROM {$this->table} c
            WHERE c.is_active = 1
            ORDER BY c.id ASC"
        );
        return $this->fetchAll($query);
    }

    private static function departmentPermissionFields() {
        return [
            'project_manager',
            'project_director_stat',
            'project_director_view',
            'project_director_edit',
            'project_add',
            'project_edit',
            'project_delete',
            'project_comment',
            'project_view_end_date',
            'task_view',
            'task_add',
            'task_edit',
            'task_delete',
        ];
    }

    private function normalizeDepartmentPermissionRow(array $row) {
        $perm = [];
        foreach (self::departmentPermissionFields() as $field) {
            $perm[$field] = (int) ($row[$field] ?? 0);
        }
        return $perm;
    }

    private function buildDepartmentPermissionFromPost($userid) {
        $perm = [];
        foreach (self::departmentPermissionFields() as $field) {
            $perm[$field] = (isset($_POST[$field][$userid]) && $_POST[$field][$userid] == 'true') ? 1 : 0;
        }
        return $perm;
    }

    private function getDepartmentMembersPermissionMap($departmentId) {
        $fields = implode(', ', self::departmentPermissionFields());
        $rows = $this->fetchAll(sprintf(
            "SELECT userid, %s FROM %suser_department WHERE department_id = %d",
            $fields,
            DB_PREFIX,
            intval($departmentId)
        ));
        $map = [];
        foreach ($rows as $row) {
            $map[$row['userid']] = $this->normalizeDepartmentPermissionRow($row);
        }
        return $map;
    }

    /**
     * Userids whose department membership or permissions changed (for session invalidation).
     */
    private function collectDepartmentPermissionChangeUserids($departmentId, array $newMemberUserids) {
        $oldMap = $this->getDepartmentMembersPermissionMap($departmentId);
        $changed = [];

        foreach ($oldMap as $userid => $oldPerm) {
            if (!in_array($userid, $newMemberUserids, true)) {
                $changed[] = $userid;
            }
        }

        foreach ($newMemberUserids as $userid) {
            $newPerm = $this->buildDepartmentPermissionFromPost($userid);
            if (!isset($oldMap[$userid])) {
                $changed[] = $userid;
            } elseif ($oldMap[$userid] !== $newPerm) {
                $changed[] = $userid;
            }
        }

        return array_values(array_unique($changed));
    }

    function add() {
        $data = array(
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'can_project' => $_POST['can_project'],
            'note_labels' => $this->parseNoteLabelsFromRequest(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );
        $department_id = $this->query_insert($data);
        
        // Add department members
        if (isset($_POST['members']) && is_array($_POST['members'])) {
            foreach ($_POST['members'] as $user_id) {
                $member_data = array_merge(
                    [
                        'department_id' => $department_id,
                        'userid' => $user_id,
                    ],
                    $this->buildDepartmentPermissionFromPost($user_id)
                );
                $this->query_insert($member_data, DB_PREFIX . 'user_department');
            }
        }

        if (isset($_POST['members']) && is_array($_POST['members'])) {
            $this->invalidateUserLoginByUserids($_POST['members']);
        }
        
        return $department_id;
    }

    function edit() {
        $id = $_GET['id'];
        $newMemberUserids = (isset($_POST['members']) && is_array($_POST['members'])) ? $_POST['members'] : [];
        $changedUserids = $this->collectDepartmentPermissionChangeUserids($id, $newMemberUserids);
        $data = array(
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'can_project' => $_POST['can_project'],
            'note_labels' => $this->parseNoteLabelsFromRequest(),
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        // Update department info
        $this->query_update($data, ['id' => $id]);
        
        // Update department members
        $this->query("DELETE FROM " . DB_PREFIX . "user_department WHERE department_id = " . intval($id));
        if (isset($_POST['members']) && is_array($_POST['members'])) {
            foreach ($_POST['members'] as $user_id) {
                $member_data = array_merge(
                    [
                        'department_id' => $id,
                        'userid' => $user_id,
                    ],
                    $this->buildDepartmentPermissionFromPost($user_id)
                );
                $this->query_insert($member_data, DB_PREFIX . 'user_department');
            }
        }

        $this->invalidateUserLoginByUserids($changedUserids);
        
        return true;
    }

    function delete() {
        $id = $_GET['id'];
        $this->invalidateUserLoginForDepartmentId($id);
        // Check if category is in use
        $query = sprintf(
            "SELECT COUNT(*) as count FROM " . DB_PREFIX . "projects WHERE department_id = %d",
            intval($id)
        );
        $result = $this->fetchOne($query);
        
        if ($result['count'] > 0) {
            throw new Exception('この部署は使用中のため削除できません。');
        }
        $query = sprintf("DELETE FROM groupware_user_department WHERE department_id = %d", intval($id));
        $this->query($query);
        return $this->query_update(['is_active' => 0], ['id' => $id]);
    }

    function get() {
        $id = $_GET['id'];
        $query = sprintf(
            "SELECT c.*
            FROM {$this->table} c
            WHERE c.id = %d",
            intval($id)
        );
        $department = $this->fetchOne($query);
        if ($department) {
            $rows = [$department];
            $this->attachDepartmentListAggregates($rows);
            $department = $rows[0];
        }
        
        // Get department members with their permissions
        if ($department) {
            $query = sprintf(
                "SELECT ud.userid, u.realname as user_name,
                ud.project_manager, ud.project_director, ud.project_director_stat, ud.project_director_view, ud.project_director_edit,
                ud.project_add, ud.project_edit, ud.project_delete, ud.project_comment, ud.project_view_end_date,
                ud.task_view, ud.task_add, ud.task_edit, ud.task_delete
                FROM " . DB_PREFIX . "user_department ud
                LEFT JOIN " . DB_PREFIX . "user u ON u.userid = ud.userid
                WHERE ud.department_id = %d AND (u.is_suspend = 0 OR u.is_suspend IS NULL)",
                intval($id)
            );
            $department['members'] = $this->fetchAll($query);
            $department['note_labels'] = $this->decodeNoteLabelsForResponse(
                isset($department['note_labels']) ? $department['note_labels'] : null
            );
        }
        
        return $department;
    }

    function get_users() {
        $department_id = $_GET['department_id'];
        $query = sprintf(
            "SELECT u.id, u.realname as user_name, u.userid as user_id
            FROM " . DB_PREFIX . "user u
            JOIN " . DB_PREFIX . "user_department ud ON u.userid = ud.userid
            WHERE ud.department_id = %d
            AND (u.is_suspend = 0 OR u.is_suspend IS NULL)
            ORDER BY u.id ASC",
            intval($department_id)
        );
        return $this->fetchAll($query);
    }

    function get_user_permissions() {
        if (($_SESSION['authority'] ?? '') === 'administrator') {
            return [[
                'project_manager' => 1,
                'project_director' => 1,
                'project_director_stat' => 1,
                'project_director_view' => 1,
                'project_director_edit' => 1,
                'project_add' => 1,
                'project_edit' => 1,
                'project_delete' => 1,
                'project_comment' => 1,
                'project_view_end_date' => 1,
                'department_id' => 0,
            ]];
        }
        $userid = $_SESSION['userid'];
        $query = sprintf(
            "SELECT ud.*, d.name as department_name, d.id as department_id
            FROM " . DB_PREFIX . "user_department ud
            JOIN " . DB_PREFIX . "departments d ON ud.department_id = d.id
            WHERE ud.userid = '%s'",
            $userid
        );
        return $this->fetchAll($query);
    }

    function get_user_permission_by_department() {
        $userid = $_SESSION['userid'];
        $department_id = $_GET['department_id'];
        $query = sprintf(
            "SELECT ud.*
            FROM " . DB_PREFIX . "user_department ud
            WHERE ud.userid = '%s' AND ud.department_id = %d",
            $userid,
            intval($department_id)
        );
        return $this->fetchOne($query);
    }

    // --- Custom Fields Management ---
    private function customFieldsTable() {
        return 'department_custom_fields';
    }

    private function _normalizeCustomFields($fields) {
        if (!isset($fields) || !is_array($fields)) {
            return [];
        }
        $normalized = [];
        foreach ($fields as $f) {
            $opts = [];
            if (isset($f['options'])) {
                if (is_array($f['options'])) {
                    $opts = $f['options'];
                } else {
                    // UI nhập カンマ区切り; cũng hỗ trợ xuống dòng
                    $raw = str_replace(["\r\n", "\r"], "\n", (string)$f['options']);
                    if (strpos($raw, "\n") !== false) {
                        $parts = explode("\n", $raw);
                    } else {
                        $parts = explode(',', $raw);
                    }
                    $opts = array_values(array_filter(array_map('trim', $parts), function ($v) {
                        return $v !== '';
                    }));
                }
            }
            $normalized[] = [
                'label'   => isset($f['label']) ? (string)$f['label'] : '',
                'type'    => isset($f['type']) && $f['type'] !== '' ? (string)$f['type'] : 'text',
                'options' => $opts,
                // Thuộc tính one_row: lưu boolean (true nếu trường chiếm trọn 1 hàng / col-12)
                'one_row' => !empty($f['one_row']) ? 1 : 0
            ];
        }
        return $normalized;
    }

    private function invalidateCustomFieldsCache() {
        $userId = isset($_SESSION['userid']) ? (string)$_SESSION['userid'] : '';
        if ($userId === '') {
            return;
        }
        if (!class_exists('ApiCache', false)) {
            require_once dirname(__DIR__) . '/library/ApiCache.php';
        }
        // getCustomFields từng được cache theo user — xóa cache user hiện tại sau khi ghi
        ApiCache::invalidateUser($userId);
    }

    function saveCustomFields() {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return ['success' => false, 'message' => 'Invalid JSON'];
        }
        $table = $this->customFieldsTable();
        $department_id = isset($data['department_id']) ? intval($data['department_id']) : 0;
        $id = intval($data['id']);
        $name = isset($data['name']) ? $data['name'] : '';
        $fieldsRaw = isset($data['fields']) ? $data['fields'] : [];
        $fields = json_encode($this->_normalizeCustomFields($fieldsRaw), JSON_UNESCAPED_UNICODE);

        try {
            $affected = $this->query_update(
                ['department_id' => $department_id, 'fields' => $fields, 'name' => $name],
                ['id' => $id],
                $table
            );
        } catch (Exception $e) {
            return ['success' => false, 'message' => '更新に失敗しました。', 'error' => $e->getMessage()];
        }
        if ($affected === false || $affected < 0) {
            return ['success' => false, 'message' => '更新に失敗しました。'];
        }
        if ($affected === 0) {
            return ['success' => false, 'message' => '対象レコードが見つかりません。'];
        }
        $this->invalidateCustomFieldsCache();
        return ['success' => true];
    }

    function removeCustomFields() {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['id'])) {
            return ['success' => false, 'message' => 'Invalid JSON'];
        }
        $id = intval($data['id']);
        try {
            $this->query_delete(['id' => $id], $this->customFieldsTable());
        } catch (Exception $e) {
            return ['success' => false, 'message' => '削除に失敗しました。', 'error' => $e->getMessage()];
        }
        $this->invalidateCustomFieldsCache();
        return ['success' => true];
    }

    function addCustomFields() {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return ['success' => false, 'message' => 'Invalid JSON'];
        }
        $table = $this->customFieldsTable();
        $department_id = intval($data['department_id']);
        $name = isset($data['name']) ? $data['name'] : '';
        $fieldsRaw = isset($data['fields']) ? $data['fields'] : [];
        $fields = json_encode($this->_normalizeCustomFields($fieldsRaw), JSON_UNESCAPED_UNICODE);
        try {
            $insertId = $this->query_insert(
                ['department_id' => $department_id, 'fields' => $fields, 'name' => $name],
                $table
            );
        } catch (Exception $e) {
            return ['success' => false, 'message' => '登録に失敗しました。', 'error' => $e->getMessage()];
        }
        if (!$insertId) {
            return ['success' => false, 'message' => '登録に失敗しました。'];
        }
        $this->invalidateCustomFieldsCache();
        return ['success' => true, 'id' => $insertId];
    }

    function getCustomFields() {
        $rows = $this->fetchAll("SELECT * FROM " . $this->customFieldsTable() . " ORDER BY id ASC");
        $result = [];
        foreach ($rows as $row) {
            $fields = json_decode($row['fields'], true);
            if (!is_array($fields)) {
                $fields = [];
            }
            // Chuẩn hóa options về string (カンマ区切り) cho UI
            foreach ($fields as &$field) {
                if (isset($field['options']) && is_array($field['options'])) {
                    $field['options'] = implode(',', $field['options']);
                } elseif (!isset($field['options'])) {
                    $field['options'] = '';
                }
            }
            unset($field);
            $result[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'department_id' => $row['department_id'],
                'fields' => $fields
            ];
        }
        return $result;
    }

    function getAll() {
        $query = sprintf(
            "SELECT id, name FROM %s WHERE is_active = 1 ORDER BY name",
            $this->table
        );
        return $this->fetchAll($query);
    }

    // --- Default Tasks Management (per department) ---

    private function defaultTasksTable() {
        return 'department_default_tasks';
    }

    private function ensureDefaultTasksTable() {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;
        $table = $this->defaultTasksTable();
        try {
            $row = $this->fetchOne(
                "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.TABLES "
                . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->quote($table) . "'"
            );
            if (!empty($row['cnt'])) {
                return;
            }
            $this->query(
                "CREATE TABLE IF NOT EXISTS `{$table}` ("
                . "`id` INT NOT NULL AUTO_INCREMENT,"
                . "`department_id` INT NOT NULL,"
                . "`tasks` TEXT NOT NULL COMMENT 'JSON array of default task definitions',"
                . "`updated_at` DATETIME NULL DEFAULT NULL,"
                . "`created_at` DATETIME NULL DEFAULT NULL,"
                . "PRIMARY KEY (`id`),"
                . "UNIQUE KEY `uk_department_default_tasks_department` (`department_id`)"
                . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (Exception $e) {
            error_log('ensureDefaultTasksTable failed: ' . $e->getMessage());
        }
    }

    /**
     * Built-in defaults used when a department has no saved config.
     */
    /** Only this title gets default GUIS assignee + progress 100%. */
    public static function getDefaultContactTaskTitle() {
        return 'お客様との連絡・調整・納品対応';
    }

    public function getBuiltinDefaultTaskDefinitions() {
        $contactTitle = self::getDefaultContactTaskTitle();
        return array(
            array(
                'title' => $contactTitle,
                'price_pct' => 15,
                'task_kind' => '連絡',
                'assign_guis_receiver' => 1,
                'status' => 'completed',
                'progress' => 100,
                'drawing_count' => 1,
            ),
            array(
                'title' => '全図面のチェック・確認作業',
                'price_pct' => 20,
                'task_kind' => 'チェック',
                'assign_guis_receiver' => 0,
                'status' => 'todo',
                'progress' => 0,
                'drawing_count' => 1,
            ),
        );
    }

    private function normalizeDefaultTaskDefinitions($tasks) {
        if (!is_array($tasks)) {
            return array();
        }
        $contactTitle = self::getDefaultContactTaskTitle();
        $normalized = array();
        $seenTitles = array();
        foreach ($tasks as $t) {
            if (!is_array($t)) {
                continue;
            }
            $title = isset($t['title']) ? trim((string) $t['title']) : '';
            if ($title === '' || isset($seenTitles[$title])) {
                continue;
            }
            $seenTitles[$title] = true;
            $pricePct = isset($t['price_pct']) ? floatval($t['price_pct']) : 0;
            if ($pricePct < 0) {
                $pricePct = 0;
            }
            if ($pricePct > 100) {
                $pricePct = 100;
            }
            $isContact = ($title === $contactTitle);
            // Only contact task: default assignee + progress 100%. Others: no assignee, progress 0.
            $status = $isContact ? 'completed' : 'todo';
            $progress = $isContact ? 100 : 0;
            $drawingCount = isset($t['drawing_count']) ? intval($t['drawing_count']) : 1;
            if ($drawingCount < 0) {
                $drawingCount = 0;
            }
            $normalized[] = array(
                'title' => $title,
                'price_pct' => round($pricePct, 2),
                'task_kind' => isset($t['task_kind']) ? trim((string) $t['task_kind']) : '',
                'assign_guis_receiver' => $isContact ? 1 : 0,
                'status' => $status,
                'progress' => $progress,
                'drawing_count' => $drawingCount,
            );
        }
        return $normalized;
    }

    /**
     * Resolve default task definitions for a department (saved config or builtin).
     */
    public function resolveDefaultTaskDefinitions($departmentId) {
        $departmentId = intval($departmentId);
        if ($departmentId > 0) {
            $this->ensureDefaultTasksTable();
            $row = $this->fetchOne(sprintf(
                "SELECT tasks FROM %s WHERE department_id = %d LIMIT 1",
                $this->defaultTasksTable(),
                $departmentId
            ));
            if ($row && isset($row['tasks']) && $row['tasks'] !== '') {
                $decoded = json_decode($row['tasks'], true);
                $normalized = $this->normalizeDefaultTaskDefinitions($decoded);
                if (!empty($normalized)) {
                    return $normalized;
                }
            }
        }
        return $this->getBuiltinDefaultTaskDefinitions();
    }

    /**
     * List all department default-task configs (admin UI).
     */
    function getDefaultTasks() {
        $this->ensureDefaultTasksTable();
        $departmentId = 0;
        if (isset($_GET['department_id'])) {
            $departmentId = intval($_GET['department_id']);
        } elseif (isset($_POST['department_id'])) {
            $departmentId = intval($_POST['department_id']);
        }
        if ($departmentId > 0) {
            return array(
                'department_id' => $departmentId,
                'tasks' => $this->resolveDefaultTaskDefinitions($departmentId),
                'is_builtin' => !$this->hasSavedDefaultTasks($departmentId),
            );
        }

        $rows = $this->fetchAll(
            "SELECT d.id AS department_id, d.name AS department_name, t.id, t.tasks, t.updated_at "
            . "FROM " . $this->table . " d "
            . "LEFT JOIN " . $this->defaultTasksTable() . " t ON t.department_id = d.id "
            . "WHERE d.is_active = 1 "
            . "ORDER BY d.name ASC"
        );
        $result = array();
        foreach ($rows as $row) {
            $deptId = intval($row['department_id']);
            $hasSaved = !empty($row['id']);
            $tasks = $hasSaved
                ? $this->normalizeDefaultTaskDefinitions(json_decode($row['tasks'], true))
                : $this->getBuiltinDefaultTaskDefinitions();
            if ($hasSaved && empty($tasks)) {
                $tasks = $this->getBuiltinDefaultTaskDefinitions();
                $hasSaved = false;
            }
            $result[] = array(
                'id' => $hasSaved ? intval($row['id']) : null,
                'department_id' => $deptId,
                'department_name' => isset($row['department_name']) ? $row['department_name'] : '',
                'tasks' => $tasks,
                'task_count' => count($tasks),
                'is_builtin' => !$hasSaved,
                'updated_at' => $hasSaved ? ($row['updated_at'] ?? null) : null,
            );
        }
        return $result;
    }

    private function hasSavedDefaultTasks($departmentId) {
        $departmentId = intval($departmentId);
        if ($departmentId <= 0) {
            return false;
        }
        $this->ensureDefaultTasksTable();
        $row = $this->fetchOne(sprintf(
            "SELECT id FROM %s WHERE department_id = %d LIMIT 1",
            $this->defaultTasksTable(),
            $departmentId
        ));
        return $row && !empty($row['id']);
    }

    function saveDefaultTasks() {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return array('success' => false, 'message' => 'Invalid JSON');
        }
        $departmentId = isset($data['department_id']) ? intval($data['department_id']) : 0;
        if ($departmentId <= 0) {
            return array('success' => false, 'message' => '部署を選択してください');
        }
        $tasks = $this->normalizeDefaultTaskDefinitions(isset($data['tasks']) ? $data['tasks'] : array());
        if (empty($tasks)) {
            return array('success' => false, 'message' => 'タスクを1件以上設定してください');
        }
        $sumPct = 0;
        foreach ($tasks as $t) {
            $sumPct += floatval($t['price_pct']);
        }
        if ($sumPct > 100.0001) {
            return array('success' => false, 'message' => '比率の合計は100%以下にしてください');
        }

        $this->ensureDefaultTasksTable();
        $table = $this->defaultTasksTable();
        $now = date('Y-m-d H:i:s');
        $payload = json_encode($tasks, JSON_UNESCAPED_UNICODE);
        $existing = $this->fetchOne(sprintf(
            "SELECT id FROM %s WHERE department_id = %d LIMIT 1",
            $table,
            $departmentId
        ));
        try {
            if ($existing && !empty($existing['id'])) {
                $ok = $this->query_update(
                    array(
                        'tasks' => $payload,
                        'updated_at' => $now,
                    ),
                    array('id' => intval($existing['id'])),
                    $table
                );
                if ($ok === false) {
                    return array('success' => false, 'message' => '更新に失敗しました。');
                }
                return array('success' => true, 'id' => intval($existing['id']));
            }
            $insertId = $this->query_insert(
                array(
                    'department_id' => $departmentId,
                    'tasks' => $payload,
                    'created_at' => $now,
                    'updated_at' => $now,
                ),
                $table
            );
            if (!$insertId) {
                return array('success' => false, 'message' => '登録に失敗しました。');
            }
            return array('success' => true, 'id' => $insertId);
        } catch (Exception $e) {
            return array('success' => false, 'message' => '保存に失敗しました。', 'error' => $e->getMessage());
        }
    }

    function removeDefaultTasks() {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return array('success' => false, 'message' => 'Invalid JSON');
        }
        $departmentId = isset($data['department_id']) ? intval($data['department_id']) : 0;
        $id = isset($data['id']) ? intval($data['id']) : 0;
        $this->ensureDefaultTasksTable();
        try {
            if ($id > 0) {
                $this->query_delete(array('id' => $id), $this->defaultTasksTable());
            } elseif ($departmentId > 0) {
                $this->query_delete(array('department_id' => $departmentId), $this->defaultTasksTable());
            } else {
                return array('success' => false, 'message' => '対象が指定されていません');
            }
        } catch (Exception $e) {
            return array('success' => false, 'message' => '削除に失敗しました。', 'error' => $e->getMessage());
        }
        return array('success' => true);
    }

    // --- Project list column defaults (per department) ---

    private function projectListColumnDefaultsTable() {
        return 'department_project_list_columns';
    }

    private function ensureProjectListColumnDefaultsTable() {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;
        $table = $this->projectListColumnDefaultsTable();
        try {
            $row = $this->fetchOne(
                "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.TABLES "
                . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->quote($table) . "'"
            );
            if (!empty($row['cnt'])) {
                return;
            }
            $this->query(
                "CREATE TABLE IF NOT EXISTS `{$table}` ("
                . "`id` INT NOT NULL AUTO_INCREMENT,"
                . "`department_id` INT NOT NULL,"
                . "`config` MEDIUMTEXT NOT NULL COMMENT 'JSON: visibility/order/widths',"
                . "`updated_by` VARCHAR(50) NULL DEFAULT NULL,"
                . "`updated_at` DATETIME NULL DEFAULT NULL,"
                . "`created_at` DATETIME NULL DEFAULT NULL,"
                . "PRIMARY KEY (`id`),"
                . "UNIQUE KEY `uk_dept_project_list_columns` (`department_id`)"
                . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (Exception $e) {
            error_log('ensureProjectListColumnDefaultsTable failed: ' . $e->getMessage());
        }
    }

    private function normalizeProjectListColumnConfig($raw) {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : array();
        }
        if (!is_array($raw)) {
            return null;
        }
        $visibility = array();
        if (isset($raw['visibility']) && is_array($raw['visibility'])) {
            foreach ($raw['visibility'] as $key => $val) {
                $k = trim((string) $key);
                if ($k === '') {
                    continue;
                }
                $visibility[$k] = !empty($val) ? 1 : 0;
            }
        }
        $order = array();
        if (isset($raw['order']) && is_array($raw['order'])) {
            foreach ($raw['order'] as $key) {
                $k = trim((string) $key);
                if ($k === '' || in_array($k, $order, true)) {
                    continue;
                }
                $order[] = $k;
            }
        }
        $widths = array();
        if (isset($raw['widths']) && is_array($raw['widths'])) {
            foreach ($raw['widths'] as $key => $val) {
                $k = trim((string) $key);
                $w = intval($val);
                if ($k === '' || $w < 40) {
                    continue;
                }
                if ($w > 800) {
                    $w = 800;
                }
                $widths[$k] = $w;
            }
        }
        if (!$visibility && !$order && !$widths) {
            return null;
        }
        return array(
            'visibility' => $visibility,
            'order' => $order,
            'widths' => $widths,
        );
    }

    function getProjectListColumnDefaults() {
        $departmentId = 0;
        if (isset($_GET['department_id'])) {
            $departmentId = intval($_GET['department_id']);
        } elseif (isset($_POST['department_id'])) {
            $departmentId = intval($_POST['department_id']);
        }
        if ($departmentId <= 0) {
            return array('success' => false, 'message' => 'department_id required', 'config' => null);
        }
        $this->ensureProjectListColumnDefaultsTable();
        $row = $this->fetchOne(sprintf(
            "SELECT id, config, updated_at, updated_by FROM %s WHERE department_id = %d LIMIT 1",
            $this->projectListColumnDefaultsTable(),
            $departmentId
        ));
        if (!$row || empty($row['config'])) {
            return array(
                'success' => true,
                'department_id' => $departmentId,
                'config' => null,
                'is_set' => false,
            );
        }
        $config = $this->normalizeProjectListColumnConfig($row['config']);
        return array(
            'success' => true,
            'department_id' => $departmentId,
            'id' => intval($row['id']),
            'config' => $config,
            'is_set' => $config !== null,
            'updated_at' => isset($row['updated_at']) ? $row['updated_at'] : null,
            'updated_by' => isset($row['updated_by']) ? $row['updated_by'] : null,
        );
    }

    function saveProjectListColumnDefaults() {
        if (!isset($_SESSION['authority']) || $_SESSION['authority'] !== 'administrator') {
            return array('success' => false, 'message' => '権限がありません', 'http_status' => 403);
        }
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return array('success' => false, 'message' => 'Invalid JSON');
        }
        $departmentId = isset($data['department_id']) ? intval($data['department_id']) : 0;
        if ($departmentId <= 0) {
            return array('success' => false, 'message' => '部署を選択してください');
        }
        $config = $this->normalizeProjectListColumnConfig(isset($data['config']) ? $data['config'] : $data);
        if ($config === null) {
            return array('success' => false, 'message' => '列設定が空です');
        }
        $this->ensureProjectListColumnDefaultsTable();
        $table = $this->projectListColumnDefaultsTable();
        $now = date('Y-m-d H:i:s');
        $userid = isset($_SESSION['userid']) ? (string) $_SESSION['userid'] : '';
        $payload = json_encode($config, JSON_UNESCAPED_UNICODE);
        $existing = $this->fetchOne(sprintf(
            "SELECT id FROM %s WHERE department_id = %d LIMIT 1",
            $table,
            $departmentId
        ));
        try {
            if ($existing && !empty($existing['id'])) {
                $ok = $this->query_update(
                    array(
                        'config' => $payload,
                        'updated_at' => $now,
                        'updated_by' => $userid,
                    ),
                    array('id' => intval($existing['id'])),
                    $table
                );
                if ($ok === false) {
                    return array('success' => false, 'message' => '更新に失敗しました。');
                }
                return array('success' => true, 'id' => intval($existing['id']));
            }
            $insertId = $this->query_insert(
                array(
                    'department_id' => $departmentId,
                    'config' => $payload,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'updated_by' => $userid,
                ),
                $table
            );
            if (!$insertId) {
                return array('success' => false, 'message' => '登録に失敗しました。');
            }
            return array('success' => true, 'id' => $insertId);
        } catch (Exception $e) {
            return array('success' => false, 'message' => '保存に失敗しました。', 'error' => $e->getMessage());
        }
    }

    function removeProjectListColumnDefaults() {
        if (!isset($_SESSION['authority']) || $_SESSION['authority'] !== 'administrator') {
            return array('success' => false, 'message' => '権限がありません', 'http_status' => 403);
        }
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return array('success' => false, 'message' => 'Invalid JSON');
        }
        $departmentId = isset($data['department_id']) ? intval($data['department_id']) : 0;
        if ($departmentId <= 0) {
            return array('success' => false, 'message' => '部署を選択してください');
        }
        $this->ensureProjectListColumnDefaultsTable();
        try {
            $this->query_delete(
                array('department_id' => $departmentId),
                $this->projectListColumnDefaultsTable()
            );
        } catch (Exception $e) {
            return array('success' => false, 'message' => '削除に失敗しました。', 'error' => $e->getMessage());
        }
        return array('success' => true);
    }
} 