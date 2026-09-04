<?php

class Department extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'departments';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'name' => array(),
            'description' => array(),
            'can_project' => array(),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search')),
            'is_active' => array()
        );
        $this->connect();
        $this->ensureProjectViewEndDateColumn();
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
        
        return $this->fetchAll($query);
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
} 