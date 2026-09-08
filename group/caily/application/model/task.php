<?php

class Task extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'tasks';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'project_id' => array('type' => 'int'),
            'parent_id' => array('type' => 'int'),
            'title' => array(),
            'description' => array(),
            'status' => array(),
            'priority' => array(),
            'task_kind' => array(),
            'drawing_count' => array('type' => 'int'),
            'note' => array(),
            'assigned_to' => array('type' => 'int'),
            'created_by' => array('type' => 'int'),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search')),
            'due_date' => array(),
            'progress' => array('type' => 'int'),
            'category_id' => array('type' => 'int'),
            'estimated_hours' => array('type' => 'float'),
            'actual_hours' => array('type' => 'float'),
            'position' => array('type' => 'int')
        );
        $this->connect();
    }

    /**
     * If value is date-only (YYYY-MM-DD) append default time; else parse with strtotime or d/m format.
     * Start date → 09:00, due date (期限) → 18:00.
     * Supports Vietnamese d/m format: 10/2 = 2 Feb, 15/2 = 15 Feb (day/month).
     */
    private function normalize_task_kind($value) {
        $allowed = array('新規作成', '修正(エラー)', '修正(変更)', 'チェック', '連絡', '検討', '相談・会議');
        $value = trim((string) $value);
        if ($value === '新規') {
            $value = '新規作成';
        }
        return in_array($value, $allowed, true) ? $value : '';
    }

    private function normalize_estimated_hours($value) {
        if ($value === '' || $value === null) {
            return 0;
        }
        return max(0, round(floatval($value), 2));
    }

    /** Time entry hours may be negative (adjustment / correction). */
    private function normalize_time_entry_hours($value) {
        if ($value === '' || $value === null) {
            return 0;
        }
        return round(floatval($value), 2);
    }

    private function getCompletedTimeEntryHoursSum($taskId) {
        $taskId = intval($taskId);
        if ($taskId <= 0) {
            return 0;
        }
        $row = $this->fetchOne(sprintf(
            "SELECT COALESCE(SUM(hours), 0) AS total
             FROM %stime_entries
             WHERE task_id = %d AND end_time IS NOT NULL",
            DB_PREFIX,
            $taskId
        ));
        return round(floatval($row['total'] ?? 0), 2);
    }

    private function getLastTimeEntryTimesForAdjustment($taskId, $ownerUserId = null) {
        $taskId = intval($taskId);
        if ($taskId <= 0) {
            return null;
        }
        $ownerFilter = '';
        if ($ownerUserId !== null && $ownerUserId !== '') {
            $ownerFilter = sprintf(
                " AND user_id = '%s'",
                $this->quote((string) $ownerUserId)
            );
        }
        $row = $this->fetchOne(sprintf(
            "SELECT start_time, end_time
             FROM %stime_entries
             WHERE task_id = %d
               AND end_time IS NOT NULL
               AND (description IS NULL OR description NOT LIKE '工数調整%%')
               %s
             ORDER BY end_time DESC, id DESC
             LIMIT 1",
            DB_PREFIX,
            $taskId,
            $ownerFilter
        ));
        if (!$row || empty($row['end_time'])) {
            return null;
        }
        return array(
            'start_time' => $row['start_time'] ?? $row['end_time'],
            'end_time' => $row['end_time'],
        );
    }

    private function getTaskAssigneeUserids($taskId, $taskRow = null) {
        $taskId = intval($taskId);
        if ($taskId <= 0) {
            return array();
        }

        $rows = $this->fetchAll(sprintf(
            "SELECT u.userid
             FROM %stask_assignees ta
             INNER JOIN %suser u ON u.id = ta.user_id
             WHERE ta.task_id = %d
             ORDER BY ta.user_id ASC",
            DB_PREFIX,
            DB_PREFIX,
            $taskId
        ));
        $userids = array();
        foreach ($rows as $row) {
            $uid = trim((string) ($row['userid'] ?? ''));
            if ($uid !== '') {
                $userids[] = $uid;
            }
        }
        if (!empty($userids)) {
            return $userids;
        }

        if ($taskRow === null) {
            $taskRow = $this->getById($taskId);
        }
        if (!$taskRow || empty($taskRow['assigned_to'])) {
            return array();
        }
        return $this->convertIdsToUserIds($taskRow['assigned_to']);
    }

    /**
     * Resolve time_entries.user_id for 工数調整: assignee(s), not the editor when different.
     */
    private function resolveTimeEntryOwnerUserId($taskId, $taskRow = null) {
        $sessionUserid = isset($_SESSION['userid']) ? (string) $_SESSION['userid'] : '';
        $assignees = $this->getTaskAssigneeUserids($taskId, $taskRow);
        if (empty($assignees)) {
            if ($sessionUserid !== '') {
                return $sessionUserid;
            }
            return $this->resolveTimerUserId();
        }

        if ($sessionUserid !== '') {
            foreach ($assignees as $uid) {
                if (strcasecmp($uid, $sessionUserid) === 0) {
                    return $uid;
                }
            }
        }

        $quotedAssignees = array();
        foreach ($assignees as $uid) {
            $quotedAssignees[] = sprintf(
                "'%s'",
                $this->quote($uid)
            );
        }
        $row = $this->fetchOne(sprintf(
            "SELECT te.user_id
             FROM %stime_entries te
             WHERE te.task_id = %d
               AND te.end_time IS NOT NULL
               AND te.user_id IN (%s)
             GROUP BY te.user_id
             ORDER BY COALESCE(SUM(te.hours), 0) DESC, te.user_id ASC
             LIMIT 1",
            DB_PREFIX,
            $taskId,
            implode(',', $quotedAssignees)
        ));
        if ($row && !empty($row['user_id'])) {
            return (string) $row['user_id'];
        }

        return $assignees[0];
    }

    private function buildHoursAdjustmentDescription() {
        $editorName = trim((string) ($_SESSION['realname'] ?? ''));
        if ($editorName === '') {
            $editorName = trim((string) ($_SESSION['userid'] ?? ''));
        }
        if ($editorName === '') {
            return '工数調整';
        }
        return sprintf('工数調整 (編集者: %s)', $editorName);
    }

    private function parseAdjustmentDateTime($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return date('Y-m-d H:i:s');
        }
        // Prefer explicit formats from the overview/task UI (avoid ambiguous strtotime slash parsing).
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})[ T](\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $m)) {
            return sprintf(
                '%04d-%02d-%02d %02d:%02d:%02d',
                (int) $m[1],
                (int) $m[2],
                (int) $m[3],
                (int) $m[4],
                (int) $m[5],
                isset($m[6]) && $m[6] !== '' ? (int) $m[6] : 0
            );
        }
        if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})\s+(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $m)) {
            return sprintf(
                '%04d-%02d-%02d %02d:%02d:%02d',
                (int) $m[1],
                (int) $m[2],
                (int) $m[3],
                (int) $m[4],
                (int) $m[5],
                isset($m[6]) && $m[6] !== '' ? (int) $m[6] : 0
            );
        }
        $normalized = $this->normalize_datetime_with_default($value, date('H:i'));
        if (!$normalized) {
            return date('Y-m-d H:i:s');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized)) {
            return $normalized . ':00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $normalized)) {
            return $normalized;
        }
        $ts = strtotime($normalized);
        return $ts !== false ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
    }

    private function insertTimeEntryHoursAdjustment($taskId, $hoursDelta, $taskRow = null, $adjustmentAt = null) {
        $taskId = intval($taskId);
        $hoursDelta = round(floatval($hoursDelta), 2);
        if ($taskId <= 0 || abs($hoursDelta) < 0.005) {
            return true;
        }

        $userId = $this->resolveTimeEntryOwnerUserId($taskId, $taskRow);
        if ($userId === '') {
            $userId = isset($_SESSION['userid']) ? (string) $_SESSION['userid'] : '';
        }
        if ($userId === '') {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $entryTime = $this->parseAdjustmentDateTime($adjustmentAt);
        $prevTable = $this->table;
        $this->table = DB_PREFIX . 'time_entries';
        $entryId = $this->query_insert(array(
            'task_id' => $taskId,
            'user_id' => $userId,
            'start_time' => $entryTime,
            'end_time' => $entryTime,
            'hours' => $hoursDelta,
            'description' => $this->buildHoursAdjustmentDescription(),
            'created_at' => $now,
            'updated_at' => $now,
        ));
        $this->table = $prevTable;
        return !empty($entryId);
    }

    private function syncTaskEstimatedHoursFromTimeEntries($taskId) {
        $taskId = intval($taskId);
        $sum = $this->normalize_estimated_hours($this->getCompletedTimeEntryHoursSum($taskId));
        $prevTable = $this->table;
        $this->table = DB_PREFIX . 'tasks';
        $this->query_update(
            array(
                'estimated_hours' => $sum,
                'updated_at' => date('Y-m-d H:i:s'),
            ),
            array('id' => $taskId)
        );
        $this->table = $prevTable;
        return $sum;
    }

    /**
     * Set task 工数 to $targetHours by inserting a time_entries adjustment for the delta.
     */
    private function applyTaskHoursTarget($taskId, $targetHours, $taskRow = null, $adjustmentAt = null) {
        $taskId = intval($taskId);
        $targetHours = $this->normalize_estimated_hours($targetHours);
        if ($taskId <= 0) {
            return $targetHours;
        }
        $currentSum = $this->getCompletedTimeEntryHoursSum($taskId);
        $this->insertTimeEntryHoursAdjustment($taskId, round($targetHours - $currentSum, 2), $taskRow, $adjustmentAt);
        return $this->syncTaskEstimatedHoursFromTimeEntries($taskId);
    }

    private function attachTimeEntryHours(array &$tasks) {
        if (empty($tasks)) {
            return;
        }
        $ids = array();
        foreach ($tasks as $task) {
            $tid = intval($task['id'] ?? 0);
            if ($tid > 0) {
                $ids[$tid] = $tid;
            }
        }
        if (empty($ids)) {
            return;
        }
        $rows = $this->fetchAll(sprintf(
            "SELECT task_id, COALESCE(SUM(hours), 0) AS total
             FROM %stime_entries
             WHERE task_id IN (%s) AND end_time IS NOT NULL
             GROUP BY task_id",
            DB_PREFIX,
            implode(',', $ids)
        ));
        $map = array();
        foreach ($rows as $row) {
            $map[intval($row['task_id'])] = round(floatval($row['total'] ?? 0), 2);
        }
        foreach ($tasks as &$task) {
            $tid = intval($task['id'] ?? 0);
            if (isset($map[$tid])) {
                $task['estimated_hours'] = $this->normalize_estimated_hours($map[$tid]);
            }
        }
        unset($task);
    }

    private function getDrawingModel() {
        if (!class_exists('Drawing')) {
            require_once DIR_MODEL . 'drawing.php';
        }
        return new Drawing();
    }

    /**
     * Sync linked project_drawings from task fields (status, assignees, drawing_count).
     * Drawing::syncFromTask also maintains completed_at when status is completed.
     */
    private function syncTaskDrawingsForTask($taskId, $taskRow = null, $options = array()) {
        $taskId = intval($taskId);
        if ($taskId <= 0) {
            return;
        }
        if ($taskRow === null) {
            $taskRow = $this->getById($taskId);
        }
        if (!$taskRow) {
            return;
        }
        $taskRow['id'] = $taskId;
        $drawingModel = $this->getDrawingModel();
        $syncOptions = $options;
        unset($syncOptions['recalc_prices']);
        $drawingModel->syncFromTask($taskRow, $syncOptions);
        if (!empty($options['recalc_prices'])) {
            $projectId = isset($taskRow['project_id']) ? intval($taskRow['project_id']) : 0;
            if ($projectId > 0) {
                $drawingModel->autoCalculateAllDrawingPricesForProject($projectId);
            }
        }
    }

    private function getDefaultTaskTitlesWithAutoDrawingLink() {
        return array(
            'お客様との連絡・調整・納品対応',
            '全図面のチェック・確認作業',
        );
    }

    private function isDefaultTaskTitleWithAutoDrawingLink($title) {
        $title = trim((string) $title);
        return in_array($title, $this->getDefaultTaskTitlesWithAutoDrawingLink(), true);
    }

    private function getDefaultTaskKindByTitle($title) {
        $map = array(
            'お客様との連絡・調整・納品対応' => '連絡',
            '全図面のチェック・確認作業' => 'チェック',
        );
        $title = trim((string) $title);
        return isset($map[$title]) ? $map[$title] : '';
    }

    private function applyDefaultTaskKindToData(&$data) {
        $kind = $this->getDefaultTaskKindByTitle(isset($data['title']) ? $data['title'] : '');
        if ($kind !== '') {
            $data['task_kind'] = $kind;
        }
    }

    private function applyDefaultTaskDrawingLinkToData(&$data) {
        $title = isset($data['title']) ? trim((string) $data['title']) : '';
        if (!$this->isDefaultTaskTitleWithAutoDrawingLink($title)) {
            return;
        }
        $drawingCount = isset($data['drawing_count']) ? max(0, intval($data['drawing_count'])) : 0;
        if ($drawingCount <= 0) {
            $data['drawing_count'] = 1;
        }
    }

    private function ensureDefaultTaskDrawingLinkOnList(&$task) {
        if (!$this->isDefaultTaskTitleWithAutoDrawingLink(isset($task['title']) ? $task['title'] : '')) {
            return;
        }
        $drawingCount = isset($task['drawing_count']) ? max(0, intval($task['drawing_count'])) : 0;
        if ($drawingCount > 0) {
            return;
        }
        $taskId = isset($task['id']) ? intval($task['id']) : 0;
        if ($taskId <= 0) {
            return;
        }
        $data = array(
            'drawing_count' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        );
        $this->query_update($data, array('id' => $taskId));
        $merged = array_merge($task, $data);
        $merged['id'] = $taskId;
        $this->syncTaskDrawingsForTask($taskId, $merged, array('recalc_prices' => true));
        $task['drawing_count'] = 1;
    }

    private function taskHasLinkedDrawings($taskRow, $oldRow = null) {
        $newDrawingCount = isset($taskRow['drawing_count']) ? max(0, intval($taskRow['drawing_count'])) : 0;
        $oldDrawingCount = ($oldRow && isset($oldRow['drawing_count'])) ? max(0, intval($oldRow['drawing_count'])) : 0;
        if ($newDrawingCount > 0 || $oldDrawingCount > 0) {
            return true;
        }
        $taskId = isset($taskRow['id']) ? intval($taskRow['id']) : 0;
        if ($taskId <= 0) {
            return false;
        }
        $drawingModel = $this->getDrawingModel();
        $count = intval($drawingModel->fetchCount(
            $drawingModel->table,
            sprintf('WHERE task_id = %d', $taskId)
        ));
        return $count > 0;
    }

    /**
     * Recalculate drawing prices only when task is completed, or when leaving completed status.
     */
    private function shouldRecalcDrawingPricesForTask($taskRow, $oldRow = null) {
        if (!$taskRow || !is_array($taskRow)) {
            return false;
        }

        if ($this->isDefaultTaskTitleWithAutoDrawingLink(isset($taskRow['title']) ? $taskRow['title'] : '')) {
            return true;
        }
        if ($oldRow && $this->isDefaultTaskTitleWithAutoDrawingLink(isset($oldRow['title']) ? $oldRow['title'] : '')) {
            return true;
        }

        if (!$this->taskHasLinkedDrawings($taskRow, $oldRow)) {
            return false;
        }

        $status = isset($taskRow['status']) ? (string) $taskRow['status'] : '';
        $oldStatus = ($oldRow && isset($oldRow['status'])) ? (string) $oldRow['status'] : '';

        if ($oldStatus === 'completed' && $status !== 'completed') {
            return true;
        }

        return $status === 'completed';
    }

    /**
     * Bootstrap default tasks (and task-linked drawings) for a new child project.
     */
    function createDefaultTasksForProject($projectId, $options = array()) {
        $projectId = intval($projectId);
        if ($projectId <= 0) {
            return false;
        }

        $amount = isset($options['amount']) ? floatval($options['amount']) : 0;
        $createdBy = isset($options['created_by']) ? intval($options['created_by']) : 0;
        if ($createdBy <= 0 && isset($_SESSION['id'])) {
            $createdBy = intval($_SESSION['id']);
        } elseif ($createdBy <= 0 && isset($_SESSION['user_id'])) {
            $createdBy = intval($_SESSION['user_id']);
        }
        $guisReceiverUserId = isset($options['guis_receiver_user_id']) ? intval($options['guis_receiver_user_id']) : 0;
        $contactTaskAssignee = $guisReceiverUserId > 0 ? $guisReceiverUserId : 0;

        $defaults = array(
            array(
                'title' => 'お客様との連絡・調整・納品対応',
                'task_kind' => '連絡',
                'assigned_to' => $contactTaskAssignee > 0 ? (string) $contactTaskAssignee : null,
                'status' => 'completed',
                'progress' => 100,
                'drawing_count' => 1,
                'price_pct' => 0.15,
            ),
            array(
                'title' => '全図面のチェック・確認作業',
                'task_kind' => 'チェック',
                'assigned_to' => null,
                'status' => 'todo',
                'progress' => 0,
                'drawing_count' => 1,
                'price_pct' => 0.20,
            ),
        );

        $created = array();
        $skipped = array();

        if (!class_exists('Project')) {
            require_once DIR_MODEL . 'project.php';
        }
        $projectModel = new Project();
        $drawingModel = $this->getDrawingModel();
        $now = date('Y-m-d H:i:s');
        $posRow = $this->fetchOne(sprintf(
            "SELECT COALESCE(MAX(position), 0) AS max_pos FROM %s WHERE project_id = %d",
            $this->table,
            $projectId
        ));
        $nextPosition = intval(isset($posRow['max_pos']) ? $posRow['max_pos'] : 0);

        foreach ($defaults as $def) {
            $existing = $this->fetchOne(sprintf(
                "SELECT id FROM %s WHERE project_id = %d AND title = '%s' LIMIT 1",
                $this->table,
                $projectId,
                $this->quote($def['title'])
            ));
            if ($existing && !empty($existing['id'])) {
                $skipped[] = $def['title'];
                continue;
            }

            $nextPosition++;
            $data = array(
                'project_id' => $projectId,
                'parent_id' => null,
                'title' => $def['title'],
                'description' => '',
                'status' => $def['status'],
                'priority' => 'medium',
                'task_kind' => isset($def['task_kind']) ? $def['task_kind'] : '',
                'drawing_count' => $def['drawing_count'],
                'estimated_hours' => 0,
                'note' => '',
                'assigned_to' => $def['assigned_to'],
                'created_by' => $createdBy > 0 ? $createdBy : null,
                'progress' => $def['progress'],
                'position' => $nextPosition,
                'created_at' => $now,
                'updated_at' => $now,
            );

            if (!empty($data['assigned_to'])) {
                $assignedIds = array_filter(array_map('intval', explode(',', (string) $data['assigned_to'])));
                foreach ($assignedIds as $uid) {
                    if ($uid > 0) {
                        $projectModel->addMember($projectId, $uid, null, 'member', true);
                    }
                }
            }

            $taskId = $this->query_insert($data);
            if (!$taskId) {
                continue;
            }
            $created[] = $def['title'];

            if (!empty($data['assigned_to'])) {
                $this->syncTaskAssignees($taskId, $data['assigned_to']);
                if ($def['title'] === 'お客様との連絡・調整・納品対応') {
                    $this->acknowledgeTaskAssignees($taskId, $data['assigned_to']);
                }
            }

            $data['id'] = $taskId;
            $drawingModel->syncFromTask($data, array('skip_price_recalc' => true));

            if ($amount > 0 && isset($def['price_pct']) && isset($def['status']) && $def['status'] === 'completed') {
                $price = round($amount * $def['price_pct'], 2);
                $drawing = $this->fetchOne(sprintf(
                    "SELECT id FROM %sproject_drawings WHERE task_id = %d ORDER BY id ASC LIMIT 1",
                    DB_PREFIX,
                    intval($taskId)
                ));
                if ($drawing && !empty($drawing['id'])) {
                    $drawingModel->query_update(
                        array('price' => $price, 'updated_at' => date('Y-m-d H:i:s')),
                        array('id' => intval($drawing['id']))
                    );
                }
            }
        }

        if ($amount > 0) {
            $drawingModel->autoCalculateAllDrawingPricesForProject($projectId);
        }

        return array(
            'created' => $created,
            'skipped' => $skipped,
        );
    }

    /**
     * Create bootstrap default tasks that are not already on the project (task page action).
     */
    function createMissingDefaultTasks() {
        $projectId = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        if ($projectId <= 0) {
            return array('status' => 'error', 'message' => 'project_id required');
        }
        if (!$this->canUserAddTask($projectId)) {
            return array('status' => 'error', 'message' => 'Forbidden', 'http_status' => 403);
        }

        if (!class_exists('Project')) {
            require_once DIR_MODEL . 'project.php';
        }
        $projectModel = new Project();
        $project = $this->fetchOne(sprintf(
            "SELECT id, amount FROM %sprojects WHERE id = %d LIMIT 1",
            DB_PREFIX,
            $projectId
        ));
        if (!$project || empty($project['id'])) {
            return array('status' => 'error', 'message' => 'Project not found');
        }

        $createdBy = 0;
        if (isset($_SESSION['id'])) {
            $createdBy = intval($_SESSION['id']);
        } elseif (isset($_SESSION['user_id'])) {
            $createdBy = intval($_SESSION['user_id']);
        }

        $result = $this->createDefaultTasksForProject($projectId, array(
            'amount' => isset($project['amount']) ? floatval($project['amount']) : 0,
            'created_by' => $createdBy,
            'guis_receiver_user_id' => $projectModel->getGuisReceiverNumericUserId($projectId),
        ));

        $created = isset($result['created']) ? $result['created'] : array();
        $skipped = isset($result['skipped']) ? $result['skipped'] : array();

        if (empty($created)) {
            return array(
                'status' => 'success',
                'created' => $created,
                'skipped' => $skipped,
                'message' => '既定タスクは既にすべて存在します',
            );
        }

        return array(
            'status' => 'success',
            'created' => $created,
            'skipped' => $skipped,
            'message' => count($created) . '件の既定タスクを追加しました',
        );
    }

    /**
     * Gán task mặc định「お客様との連絡・調整・納品対応」cho GUIS 受付者 (khi sửa dự án con).
     */
    function assignDefaultContactTaskToUser($projectId, $userId) {
        $projectId = intval($projectId);
        $userId = intval($userId);
        if ($projectId <= 0 || $userId <= 0) {
            return false;
        }

        $title = 'お客様との連絡・調整・納品対応';
        $task = $this->fetchOne(sprintf(
            "SELECT id FROM %s WHERE project_id = %d AND title = '%s' LIMIT 1",
            $this->table,
            $projectId,
            $this->quote($title)
        ));
        if (!$task || empty($task['id'])) {
            return false;
        }

        $taskId = intval($task['id']);
        $assignedTo = (string) $userId;
        $this->query_update(
            array(
                'assigned_to' => $assignedTo,
                'updated_at' => date('Y-m-d H:i:s'),
            ),
            array('id' => $taskId)
        );

        if (!class_exists('Project')) {
            require_once DIR_MODEL . 'project.php';
        }
        $projectModel = new Project();
        $projectModel->addMember($projectId, $userId, null, 'member', true);
        $this->syncTaskAssignees($taskId, $assignedTo);
        $this->acknowledgeTaskAssignees($taskId, $assignedTo);

        $mergedTask = $this->getById($taskId);
        if ($mergedTask) {
            $mergedTask['assigned_to'] = $assignedTo;
            $this->syncTaskDrawingsForTask($taskId, $mergedTask);
        }
        return true;
    }

    private function get_default_task_kind_for_project($project_id) {
        $project_id = intval($project_id);
        if ($project_id <= 0) {
            return '新規作成';
        }
        $project = $this->fetchOne(
            "SELECT project_order_type FROM " . DB_PREFIX . "projects WHERE id = " . $project_id
        );
        $orderType = isset($project['project_order_type']) ? (string) $project['project_order_type'] : '';
        if ($orderType !== '' && mb_strpos($orderType, '修正') !== false) {
            return '修正(エラー)';
        }
        return '新規作成';
    }

    private function normalize_datetime_with_default($value, $defaultTime) {
        $value = trim($value ?? '');
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value . ' ' . $defaultTime;
        }
        // Định dạng d/m hoặc d/m/y (tiếng Việt: ngày/tháng)
        if (preg_match('/^(\d{1,2})\/(\d{1,2})(?:\/(\d{2,4}))?$/', $value, $m)) {
            $d = (int) $m[1];
            $mo = (int) $m[2];
            $y = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : (int) date('Y');
            if ($y < 100) {
                $y += 2000;
            }
            if ($d >= 1 && $d <= 31 && $mo >= 1 && $mo <= 12 && checkdate($mo, $d, $y)) {
                return sprintf('%04d-%02d-%02d %s', $y, $mo, $d, $defaultTime);
            }
        }
        $ts = strtotime($value);
        return $ts !== false ? date('Y-m-d H:i', $ts) : null;
    }

    function list($params = null) {
        $whereArr = [];
        
        // Handle both direct parameters and params array from API
        if (is_array($params)) {
            if (isset($params['project_id'])) {
                $whereArr[] = sprintf("t.project_id = %d", intval($params['project_id']));
            }
            
            if (isset($params['parent_id'])) {
                $whereArr[] = sprintf("t.parent_id = %d", intval($params['parent_id']));
            } else if (!isset($params['include_subtasks'])) {
                $whereArr[] = "t.parent_id IS NULL";
            }
            $include_subtasks = isset($params['include_subtasks']);
        } else {
            if (isset($_GET['project_id'])) {
                $whereArr[] = sprintf("t.project_id = %d", intval($_GET['project_id']));
            }
            
            if (isset($_GET['parent_id'])) {
                $whereArr[] = sprintf("t.parent_id = %d", intval($_GET['parent_id']));
            } else if (!isset($_GET['include_subtasks'])) {
                $whereArr[] = "t.parent_id IS NULL";
            }
            $include_subtasks = isset($_GET['include_subtasks']);
        }
        
        $where = !empty($whereArr) ? "WHERE " . implode(" AND ", $whereArr) : "";
        
        // Current user for reaction info
        $current_user_id = isset($_SESSION['userid']) ? $_SESSION['userid'] : '';
        
        $current_user_id_number = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
        
        $query = sprintf(
            "SELECT t.*, p.name as project_name,
            u.realname as assigned_to_name, u.user_image as assigned_to_user_image, u.userid as assigned_to_userid, u.user_ruby as assigned_to_user_ruby,
            u_creator.realname as created_by_name, u_creator.user_image as created_by_user_image,
            u_creator.userid as created_by_userid, u_creator.user_ruby as created_by_user_ruby
            FROM {$this->table} t 
            LEFT JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id 
            LEFT JOIN " . DB_PREFIX . "user u ON t.assigned_to = u.id 
            LEFT JOIN " . DB_PREFIX . "user u_creator ON t.created_by = u_creator.id
            %s
            ORDER BY t.position, t.created_at DESC",
            $where
        );
        
        $tasks = $this->fetchAll($query);
        $this->attachTaskListAggregates($tasks, $current_user_id);
        
        // Load acknowledgements for all tasks
        $taskIds = array_map(function($task) { return $task['id']; }, $tasks);
        $acknowledgementsMap = [];
        if (!empty($taskIds)) {
            $ackQuery = sprintf(
                "SELECT task_id, user_id, acknowledged, acknowledged_at 
                FROM " . DB_PREFIX . "task_assignees 
                WHERE task_id IN (%s)",
                implode(',', array_map('intval', $taskIds))
            );
            $ackRows = $this->fetchAll($ackQuery);
            foreach ($ackRows as $ack) {
                $taskId = (int)$ack['task_id'];
                $uid = (int)$ack['user_id'];
                if (!isset($acknowledgementsMap[$taskId])) {
                    $acknowledgementsMap[$taskId] = [];
                }
                $acknowledgementsMap[$taskId][(string)$uid] = [
                    'acknowledged' => intval($ack['acknowledged']),
                    'acknowledged_at' => $ack['acknowledged_at']
                ];
            }
        }
        
        $unreadMap = [];
        $subtasksByParent = [];
        if ($include_subtasks && !empty($taskIds)) {
            $unreadMap = $this->batchTaskUnreadCommentCounts($taskIds, $current_user_id);
            $parentIdsWithSubtasks = [];
            foreach ($tasks as $task) {
                if (!empty($task['subtask_count']) && (int)$task['subtask_count'] > 0) {
                    $parentIdsWithSubtasks[] = (int)$task['id'];
                }
            }
            if (!empty($parentIdsWithSubtasks)) {
                $subtasksByParent = $this->batchGetSubtasks($parentIdsWithSubtasks);
            }
        }

        if ($include_subtasks) {
            foreach ($tasks as &$task) {
                $tid = (int)$task['id'];
                $task['unread_count'] = $unreadMap[$tid] ?? 0;
                if (!empty($task['subtask_count']) && (int)$task['subtask_count'] > 0) {
                    $task['subtasks'] = $subtasksByParent[$tid] ?? [];
                }
                $task['acknowledgements'] = $acknowledgementsMap[$tid] ?? [];
            }
            unset($task);
        } else {
            foreach ($tasks as &$task) {
                $task['acknowledgements'] = $acknowledgementsMap[$task['id']] ?? [];
            }
            unset($task);
        }

        return $tasks;
    }

    function getSubtasks($parent_id) {
        $map = $this->batchGetSubtasks([intval($parent_id)]);
        return $map[intval($parent_id)] ?? [];
    }

    private function attachTaskListAggregates(array &$tasks, $currentUserId) {
        if (empty($tasks)) {
            return;
        }
        $taskIds = array_values(array_filter(array_map('intval', array_column($tasks, 'id')), function ($id) {
            return $id > 0;
        }));
        if (empty($taskIds)) {
            return;
        }
        $idsList = implode(',', $taskIds);
        $currentUserId = (string)$currentUserId;

        $subtaskMap = $this->fetchSubtaskCountMap($taskIds);

        $reactionStats = [];
        $reactionRows = $this->fetchAll(sprintf(
            "SELECT tr.task_id, tr.type, tr.user_id, tr.note, u.realname
             FROM %stask_reactions tr
             LEFT JOIN %suser u ON tr.user_id = u.userid
             WHERE tr.task_id IN (%s)",
            DB_PREFIX,
            DB_PREFIX,
            $idsList
        ));
        foreach ($reactionRows as $row) {
            $tid = (int)$row['task_id'];
            if (!isset($reactionStats[$tid])) {
                $reactionStats[$tid] = [
                    'like_count' => 0,
                    'dislike_count' => 0,
                    'liked_by_names' => [],
                    'disliked_by_names' => [],
                    'current_user_reaction' => null,
                    'current_user_reaction_note' => null,
                ];
            }
            $type = $row['type'] ?? '';
            if ($type === 'like') {
                $reactionStats[$tid]['like_count']++;
                if (!empty($row['realname'])) {
                    $reactionStats[$tid]['liked_by_names'][] = $row['realname'];
                }
            } elseif ($type === 'dislike') {
                $reactionStats[$tid]['dislike_count']++;
                if (!empty($row['realname'])) {
                    $reactionStats[$tid]['disliked_by_names'][] = $row['realname'];
                }
            }
            if ($currentUserId !== '' && isset($row['user_id']) && (string)$row['user_id'] === $currentUserId) {
                $reactionStats[$tid]['current_user_reaction'] = $type;
                $reactionStats[$tid]['current_user_reaction_note'] = $row['note'] ?? null;
            }
        }

        foreach ($tasks as &$task) {
            $tid = (int)$task['id'];
            $task['subtask_count'] = $subtaskMap[$tid] ?? 0;
            $stats = $reactionStats[$tid] ?? null;
            if ($stats) {
                $task['like_count'] = $stats['like_count'];
                $task['dislike_count'] = $stats['dislike_count'];
                $task['liked_by_names'] = $stats['liked_by_names'];
                $task['disliked_by_names'] = $stats['disliked_by_names'];
                $task['current_user_reaction'] = $stats['current_user_reaction'];
                $task['current_user_reaction_note'] = $stats['current_user_reaction_note'];
            } else {
                $task['like_count'] = 0;
                $task['dislike_count'] = 0;
                $task['liked_by_names'] = [];
                $task['disliked_by_names'] = [];
                $task['current_user_reaction'] = null;
                $task['current_user_reaction_note'] = null;
            }
        }
        unset($task);

        $this->attachTimeEntryHours($tasks);
        $this->attachActiveTimerFlags($tasks);
    }

    private function fetchActiveTimerTaskIdSet(array $taskIds = null) {
        $whereParts = array('te.end_time IS NULL');
        if ($taskIds !== null) {
            $taskIds = array_values(array_filter(array_map('intval', $taskIds), function ($id) {
                return $id > 0;
            }));
            if (empty($taskIds)) {
                return array();
            }
            $whereParts[] = 'te.task_id IN (' . implode(',', $taskIds) . ')';
        }

        $rows = $this->fetchAll(sprintf(
            'SELECT DISTINCT te.task_id FROM %stime_entries te WHERE %s',
            DB_PREFIX,
            implode(' AND ', $whereParts)
        ));
        if (!is_array($rows)) {
            return array();
        }

        $set = array();
        foreach ($rows as $row) {
            $set[intval($row['task_id'])] = true;
        }
        return $set;
    }

    private function fetchAllActiveTimerTaskIds() {
        return array_map('intval', array_keys($this->fetchActiveTimerTaskIdSet(null)));
    }

    private function attachActiveTimerFlags(array &$tasks) {
        if (empty($tasks)) {
            return;
        }

        $taskIds = array_values(array_filter(array_map('intval', array_column($tasks, 'id')), function ($id) {
            return $id > 0;
        }));
        $activeSet = $this->fetchActiveTimerTaskIdSet($taskIds);
        foreach ($tasks as &$task) {
            $tid = intval($task['id']);
            $task['timer_active'] = !empty($activeSet[$tid]);
        }
        unset($task);
    }

    private function appendActiveTimerTaskIds(array $response) {
        $response['active_task_ids'] = $this->fetchAllActiveTimerTaskIds();
        $response['server_now'] = time();
        return $response;
    }

    private function fetchSubtaskCountMap(array $taskIds) {
        $taskIds = array_values(array_filter(array_map('intval', $taskIds), function ($id) {
            return $id > 0;
        }));
        if (empty($taskIds)) {
            return [];
        }
        $idsList = implode(',', $taskIds);
        $subtaskMap = [];
        $subtaskRows = $this->fetchAll(sprintf(
            "SELECT parent_id, COUNT(*) as subtask_count FROM %s WHERE parent_id IN (%s) GROUP BY parent_id",
            $this->table,
            $idsList
        ));
        foreach ($subtaskRows as $row) {
            $subtaskMap[(int)$row['parent_id']] = (int)$row['subtask_count'];
        }
        return $subtaskMap;
    }

    private function batchGetSubtasks(array $parentIds) {
        $parentIds = array_values(array_filter(array_map('intval', $parentIds), function ($id) {
            return $id > 0;
        }));
        if (empty($parentIds)) {
            return [];
        }
        $idsList = implode(',', $parentIds);
        $query = sprintf(
            "SELECT t.*, u.realname as assigned_to_name, u.user_image as assigned_to_user_image, u.userid as assigned_to_userid
            FROM {$this->table} t
            LEFT JOIN %suser u ON t.assigned_to = u.id
            WHERE t.parent_id IN (%s)
            ORDER BY t.parent_id, t.position, t.created_at ASC",
            DB_PREFIX,
            $idsList
        );
        $rows = $this->fetchAll($query);
        $byParent = [];
        foreach ($rows as $row) {
            $pid = (int)$row['parent_id'];
            if (!isset($byParent[$pid])) {
                $byParent[$pid] = [];
            }
            $byParent[$pid][] = $row;
        }
        return $byParent;
    }

    private function batchTaskUnreadCommentCounts(array $taskIds, $userId) {
        $taskIds = array_values(array_filter(array_map('intval', $taskIds), function ($id) {
            return $id > 0;
        }));
        if (empty($taskIds) || $userId === '') {
            return [];
        }
        $idsList = implode(',', $taskIds);
        $userEsc = $this->quote($userId);
        $rows = $this->fetchAll(sprintf(
            "SELECT c.task_id, COUNT(*) as unread_count
             FROM %scomments c
             LEFT JOIN %scomment_reads r
               ON r.task_id = c.task_id AND r.user_id = '%s'
             WHERE c.task_id IN (%s)
               AND c.user_id != '%s'
               AND (r.read_at IS NULL OR c.created_at > r.read_at)
             GROUP BY c.task_id",
            DB_PREFIX,
            DB_PREFIX,
            $userEsc,
            $idsList,
            $userEsc
        ));
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['task_id']] = (int)$row['unread_count'];
        }
        return $map;
    }

    function add() {
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        if ($project_id <= 0) {
            return ['status' => 'error', 'message' => 'project_id required'];
        }
        if (!$this->canUserAddTask($project_id)) {
            return ['status' => 'error', 'message' => 'Forbidden', 'http_status' => 403];
        }
        if (!class_exists('Project')) {
            require_once DIR_MODEL . 'project.php';
        }
        $projectModel = new Project();
        $dueDate = isset($_POST['due_date']) && trim((string)$_POST['due_date']) !== '' ? $this->normalize_datetime_with_default($_POST['due_date'], '18:00') : null;
        $startDate = isset($_POST['start_date']) && trim((string)$_POST['start_date']) !== '' ? $this->normalize_datetime_with_default($_POST['start_date'], '09:00') : null;
        $title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
        $taskKind = $this->normalize_task_kind(isset($_POST['task_kind']) ? $_POST['task_kind'] : '');
        if ($taskKind === '') {
            $taskKind = $this->get_default_task_kind_for_project($project_id);
        }
        $defaultKind = $this->getDefaultTaskKindByTitle($title);
        if ($defaultKind !== '') {
            $taskKind = $defaultKind;
        }
        $drawingCount = isset($_POST['drawing_count']) ? max(0, intval($_POST['drawing_count'])) : 0;
        if (!$this->userCanEditTaskDrawing($project_id, $taskKind)) {
            $drawingCount = 0;
        }
        $estimatedHours = $this->normalize_estimated_hours(isset($_POST['estimated_hours']) ? $_POST['estimated_hours'] : 0);
        $data = array(
            'project_id' => $_POST['project_id'],
            'parent_id' => isset($_POST['parent_id']) && $_POST['parent_id'] ? $_POST['parent_id'] : null,
            'title' => $_POST['title'],
            'description' => isset($_POST['description']) ? $_POST['description'] : '',
            'status' => isset($_POST['status']) ? $_POST['status'] : 'todo',
            'priority' => isset($_POST['priority']) ? $_POST['priority'] : 'medium',
            'task_kind' => $taskKind,
            'drawing_count' => $drawingCount,
            'estimated_hours' => $estimatedHours,
            'note' => isset($_POST['note']) ? (string) $_POST['note'] : '',
            'assigned_to' => isset($_POST['assigned_to']) ? $_POST['assigned_to'] : null,
            'created_by' => isset($_POST['created_by']) ? $_POST['created_by'] : $_SESSION['user_id'],
            // 'category_id' => isset($_POST['category_id']) ? $_POST['category_id'] : null,
            // 'actual_hours' => isset($_POST['actual_hours']) ? $_POST['actual_hours'] : 0,
            'progress' => (isset($_POST['progress']) && $_POST['progress'] !== '' && $_POST['progress'] !== null) ? intval($_POST['progress']) : 0,
            'position' => isset($_POST['position']) ? $_POST['position'] : 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );
        if ($dueDate !== null) {
            $data['due_date'] = $dueDate;
        }
        if ($startDate !== null) {
            $data['start_date'] = $startDate;
        }

        $this->applyDefaultTaskKindToData($data);
        $this->applyDefaultTaskDrawingLinkToData($data);

        // Nếu user được phân công chưa là thành viên dự án thì tự động thêm vào dự án (role = member)
        if (!empty($data['assigned_to'])) {
            $assignedIds = array_filter(array_map('intval', explode(',', (string)$data['assigned_to'])));
            foreach ($assignedIds as $uid) {
                if ($uid > 0) {
                    $projectModel->addMember($project_id, $uid, null, 'member', true);
                }
            }
        }

        $task_id = $this->query_insert($data);
        // if ($task_id && $data['project_id']) {
        //     $this->updateProjectProgress($data['project_id']);
        // }
        if($task_id){
            $this->logTaskAction($task_id, 'created', 'タスク作成', '', '');
            
            // Sync task_assignees table when task is created
            if (!empty($data['assigned_to'])) {
                $this->syncTaskAssignees($task_id, $data['assigned_to']);
            }
            
            // Send notification to assigned users if task is assigned
            if (!empty($data['assigned_to']) && $data['project_id']) {
                // Get project information
                $project = $this->fetchOne(
                    "SELECT project_number, name FROM " . DB_PREFIX . "projects WHERE id = " . intval($data['project_id'])
                );
                
                if ($project) {
                    $projectNumber = $project['project_number'] ?? '';
                    $projectName = $project['name'] ?? '';
                    $assignedUserIds = $data['assigned_to'];
                    
                    $this->notifyTaskCreated($task_id, $data['title'], $data['project_id'], $projectNumber, $projectName, $assignedUserIds);
                }
            }

            $data['id'] = $task_id;
            $syncOptions = array();
            if ($this->shouldRecalcDrawingPricesForTask($data)) {
                $syncOptions['recalc_prices'] = true;
            }
            $this->syncTaskDrawingsForTask($task_id, $data, $syncOptions);

            if ($estimatedHours > 0) {
                $this->applyTaskHoursTarget($task_id, $estimatedHours, $data);
            }
            
            return [
                'status' => 'success',
                'task_id' => $task_id
            ];
        }
        
        return [
            'status' => 'error'
        ];
    }

    function edit() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) {
            return ['status' => 'error', 'message' => 'Task id required'];
        }
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        // If project_id not provided, resolve from task (only task id is required for update)
        if (!$project_id) {
            $existing = $this->getById($id);
            if ($existing && !empty($existing['project_id'])) {
                $project_id = (int) $existing['project_id'];
                $_POST['project_id'] = $project_id;
            }
        }
        if (!$project_id) {
            return ['status' => 'error', 'message' => 'Task not found'];
        }
        // Phase 4.2 – Permission check: user can edit task only if they can edit the project
        if (!class_exists('Project')) {
            require_once DIR_MODEL . 'project.php';
        }
        $projectModel = new Project();
        if (!$projectModel->canUserEditProject($project_id) && !$this->checkPermission($project_id, $id)) {
            return ['status' => 'error', 'message' => 'Forbidden', 'http_status' => 403];
        }
        
        $old = $this->getById($id);
        if (!$old) {
            return ['status' => 'error', 'message' => 'Task not found'];
        }
        $title = (isset($_POST['title']) && trim((string)$_POST['title']) !== '') ? trim($_POST['title']) : (isset($old['title']) ? $old['title'] : '');
        $description = isset($_POST['description']) ? $_POST['description'] : (isset($old['description']) ? $old['description'] : '');
        $status = isset($_POST['status']) ? $_POST['status'] : (isset($old['status']) ? $old['status'] : 'new');
        $priority = isset($_POST['priority']) ? $_POST['priority'] : (isset($old['priority']) ? $old['priority'] : 'medium');
        $assignedTo = isset($_POST['assigned_to']) ? $_POST['assigned_to'] : (isset($old['assigned_to']) ? $old['assigned_to'] : null);
        $dueDate = (isset($_POST['due_date']) && trim((string)$_POST['due_date']) !== '') ? $this->normalize_datetime_with_default($_POST['due_date'], '18:00') : null;
        $startDate = (isset($_POST['start_date']) && trim((string)$_POST['start_date']) !== '') ? $this->normalize_datetime_with_default($_POST['start_date'], '09:00') : null;
        if (array_key_exists('task_kind', $_POST)) {
            $taskKind = $this->normalize_task_kind($_POST['task_kind']);
            if ($taskKind === '') {
                $taskKind = isset($old['task_kind']) && $old['task_kind'] !== '' ? $old['task_kind'] : $this->get_default_task_kind_for_project($project_id);
            }
        } else {
            $taskKind = isset($old['task_kind']) && $old['task_kind'] !== '' ? $old['task_kind'] : $this->get_default_task_kind_for_project($project_id);
        }
        $defaultKind = $this->getDefaultTaskKindByTitle($title);
        if ($defaultKind !== '') {
            $taskKind = $defaultKind;
        }
        if (array_key_exists('drawing_count', $_POST)) {
            if ($projectModel->canUserEditProject($project_id) || $this->userCanEditTaskDrawing($project_id, $taskKind)) {
                $drawingCount = max(0, intval($_POST['drawing_count']));
            } else {
                $drawingCount = isset($old['drawing_count']) ? max(0, intval($old['drawing_count'])) : 0;
            }
        } else {
            $drawingCount = isset($old['drawing_count']) ? max(0, intval($old['drawing_count'])) : 0;
        }
        $estimatedHours = array_key_exists('estimated_hours', $_POST)
            ? $this->normalize_estimated_hours($_POST['estimated_hours'])
            : (isset($old['estimated_hours']) ? $this->normalize_estimated_hours($old['estimated_hours']) : 0);
        $data = array(
            'project_id' => $_POST['project_id'],
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'priority' => $priority,
            'task_kind' => $taskKind,
            'drawing_count' => $drawingCount,
            'estimated_hours' => $estimatedHours,
            'note' => array_key_exists('note', $_POST)
                ? (string) $_POST['note']
                : (isset($old['note']) ? (string) $old['note'] : ''),
            'assigned_to' => $assignedTo,
            'actual_hours' => isset($_POST['actual_hours']) ? $_POST['actual_hours'] : (isset($old['actual_hours']) ? $old['actual_hours'] : 0),
            'updated_at' => date('Y-m-d H:i:s')
        );
        if ($dueDate !== null) {
            $data['due_date'] = $dueDate;
        }
        if ($startDate !== null) {
            $data['start_date'] = $startDate;
        }

        if (isset($_POST['position'])) {
            $data['position'] = $_POST['position'];
        }
        if (isset($_POST['progress'])) {
            $data['progress'] = ($_POST['progress'] !== '' && $_POST['progress'] !== null) ? intval($_POST['progress']) : (isset($old['progress']) ? (int)$old['progress'] : 0);
        }
        // Khi trạng thái là completed thì tự động cập nhật tiến độ 100%
        if ($status === 'completed') {
            $data['progress'] = 100;
        }
        if (isset($_POST['parent_id'])) {
            $data['parent_id'] = ($_POST['parent_id'] !== '' && $_POST['parent_id'] !== null) ? intval($_POST['parent_id']) : (isset($old['parent_id']) ? $old['parent_id'] : null);
        }
        $this->applyDefaultTaskDrawingLinkToData($data);
        $result = $this->query_update($data, ['id' => $id]);
        
        // if ($result && $task['parent_id']) {
        //     $this->updateParentTaskProgress($task['parent_id']);
        // }
        // if ($result && $task['project_id']) {
        //     $this->updateProjectProgress($task['project_id']);
        // }
        if($result){
            // Log các trường thay đổi chính
            $fields = ['title','description','status','priority','task_kind','drawing_count','estimated_hours','note','due_date','start_date','progress'];
            $labels = [
                'title' => 'タスク名',
                'description' => '説明',
                'status' => 'ステータス',
                'priority' => '優先度',
                'task_kind' => '種別',
                'drawing_count' => '図面数',
                'estimated_hours' => '工数',
                'note' => 'メモ',
                'assigned_to' => '担当者',
                'due_date' => '期限日',
                'start_date' => '開始日',
                'progress' => '進捗',
            ];
            foreach ($fields as $f) {
                $oldVal = $old[$f] ?? '';
                $newVal = $data[$f] ?? '';
                if ($oldVal != $newVal) {
                    if ($f === 'priority') {
                        $this->logTaskAction($id, 'priority_updated', '優先度変更', $oldVal, $newVal);
                    } else if ($f === 'status') {
                        $this->logTaskAction($id, 'status_changed', 'ステータス変更', $oldVal, $newVal);
                    } else if ($f === 'progress') {
                        $this->logTaskAction($id, 'progress_updated', '進捗変更', $oldVal, $newVal);
                    } else {
                        $this->logTaskAction($id, 'updated', $labels[$f].'を変更', $oldVal, $newVal);
                    }
                }
            }
            
            // Check if assigned_to changed and send notifications
            $oldAssignedTo = $old['assigned_to'] ?? '';
            $newAssignedTo = $data['assigned_to'] ?? '';
            if ($oldAssignedTo != $newAssignedTo && !empty($newAssignedTo) && $data['project_id']) {
                // Log assigned_to change
                $this->logTaskAction($id, 'assigned', '担当者変更', $oldAssignedTo, $newAssignedTo);
                
                // Get project information
                $project = $this->fetchOne(
                    "SELECT project_number, name FROM " . DB_PREFIX . "projects WHERE id = " . intval($data['project_id'])
                );
                
                if ($project) {
                    $projectNumber = $project['project_number'] ?? '';
                    $projectName = $project['name'] ?? '';
                    $taskTitle = $data['title'] ?? $old['title'] ?? '';
                    
                    // Send notification to newly assigned users
                    $this->notifyTaskAssigneeChanged($id, $taskTitle, $data['project_id'], $projectNumber, $projectName, $newAssignedTo, $oldAssignedTo);
                }
            } else if ($oldAssignedTo != $newAssignedTo) {
                // Log even if no notification is sent
                $this->logTaskAction($id, 'assigned', '担当者変更', $oldAssignedTo, $newAssignedTo);
            }

            $mergedTask = array_merge($old, $data);
            $mergedTask['id'] = $id;
            $syncOptions = array();
            $oldDrawingCount = isset($old['drawing_count']) ? max(0, intval($old['drawing_count'])) : 0;
            $oldStatus = isset($old['status']) ? (string) $old['status'] : '';
            $statusChanged = $oldStatus !== (string) $status;
            $drawingCountChanged = $oldDrawingCount !== $drawingCount;
            $isDefaultTask = $this->isDefaultTaskTitleWithAutoDrawingLink($mergedTask['title'] ?? '')
                || $this->isDefaultTaskTitleWithAutoDrawingLink($old['title'] ?? '');
            if ($isDefaultTask || $drawingCountChanged || $statusChanged) {
                if ($this->shouldRecalcDrawingPricesForTask($mergedTask, $old)) {
                    $syncOptions['recalc_prices'] = true;
                }
            }
            $this->syncTaskDrawingsForTask($id, $mergedTask, $syncOptions);

            if (array_key_exists('estimated_hours', $_POST)) {
                $this->applyTaskHoursTarget($id, $estimatedHours, $mergedTask);
            }

            return [
                'status' => 'success'
            ];
        }
        return [
            'status' => 'error'
        ];
    }

    function delete() {
        $id = isset($_POST['id']) ? $_POST['id'] : 0;
        if(!$id){
            return [
                'status' => 'error',
                'message' => 'タスクIDが指定されていません'
            ];
        }
        $old = $this->getById($id);
        if (!$old || !isset($old['project_id'])) {
            return [
                'status' => 'error',
                'message' => 'タスクが見つかりません'
            ];
        }
        $projectId = (int) $old['project_id'];
        $savedGetProjectId = isset($_GET['project_id']) ? $_GET['project_id'] : null;
        $_GET['project_id'] = $projectId;
        $permission = $this->getPermission();
        if ($savedGetProjectId !== null) {
            $_GET['project_id'] = $savedGetProjectId;
        } else {
            unset($_GET['project_id']);
        }
        $canDelete = !empty($permission['can_manage_project']);
        if (!$canDelete && !empty($permission['rule']['task_delete'])) {
            $currentUserId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
            $createdBy = isset($old['created_by']) ? (int) $old['created_by'] : 0;
            $canDelete = ($createdBy > 0 && $createdBy === $currentUserId);
        }
        if (!$canDelete) {
            return [
                'status' => 'error',
                'message' => 'このタスクを削除する権限がありません。作成者のみ削除できます。'
            ];
        }
        $query = sprintf(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE parent_id = %d",
            intval($id)
        );
        $subtasks = $this->fetchOne($query)['count'];
        
        if ($subtasks > 0) {
            return [
                'status' => 'error',
                'message' => 'このタスクにはサブタスクが存在するため、削除できません。'
            ];
        }
        $this->getDrawingModel()->deleteDrawingsForTask($id, $old);

        $result = $this->query_delete(['id' => $id]);
       
        if($result){
            $query = sprintf(
                "DELETE FROM " . DB_PREFIX . "comments WHERE task_id = %d",
                intval($id)
            );
            $this->query($query);

            $query = sprintf(
                "DELETE FROM " . DB_PREFIX . "task_links WHERE source_task_id = %d OR target_task_id = %d",
                intval($id),
                intval($id)
            );
            $this->query($query);

            $query = sprintf(
                "DELETE FROM " . DB_PREFIX . "task_assignees WHERE task_id = %d",
                intval($id)
            );
            $this->query($query);

            $query = sprintf(
                "DELETE FROM " . DB_PREFIX . "task_logs WHERE task_id = %d",
                intval($id)
            );
            $this->query($query);

            $query = sprintf(
                "DELETE FROM " . DB_PREFIX . "task_reactions WHERE task_id = %d",
                intval($id)
            );
            $this->query($query);

           // $this->logTaskAction($id, 'deleted', 'タスク削除', $old['status'], 'deleted');

            $assignedUserIds = $this->convertIdsToUserIds($old['assigned_to']);
            $this->notifyTaskDeleted($id, $old['title'], $projectId, $assignedUserIds);

            if ($this->isDefaultTaskTitleWithAutoDrawingLink($old['title'] ?? '') && $projectId > 0) {
                $this->getDrawingModel()->autoCalculateAllDrawingPricesForProject($projectId);
            }

            return [
                'status' => 'success'
            ];
        }
        return [
            'status' => 'error'
        ];
    }

    function notifyTaskDeleted($taskId, $taskTitle, $projectId, $assignedUserIds) {
        $taskTitle = strlen($taskTitle) > 15 ? substr($taskTitle, 0, 15) . '...' : $taskTitle;
        $titleJa = '#'.$projectId.': タスクが削除されました';
        $messageJa = sprintf('%sがタスク#%s「%s」を削除しました', $this->getUserRealname(), $taskId, $taskTitle);
        $titleVi = '#'.$projectId.': Task đã được xóa';
        $messageVi = sprintf('%s đã xóa task #%s「%s」', $this->getUserRealname(), $taskId,  $taskTitle);
        $params = [
            'event' => 'task_deleted',
            'title' => $titleJa,
            'message' => $messageJa,
            'project_id' => $projectId,
            'task_id' => $taskId,
            'user_ids' => $assignedUserIds,
            'data' => [
                'task_title' => $taskTitle,
                'project_id' => $projectId,
                'avatar' => $this->getUserImageUrl(),
                'action' => 'task_deleted',
                'url' => "/project/task.php?project_id=$projectId",
                'title_ja' => $titleJa,
                'message_ja' => $messageJa,
                'title_vi' => $titleVi,
                'message_vi' => $messageVi,
            ],
        ];
        return $this->sendTaskNotification($params);
    }


    function updateStatus() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $status = isset($_POST['status']) ? $_POST['status'] : '';
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        
        if (!$id || !$status) {
            return ['status' => 'error', 'message' => 'Missing required parameters'];
        }
        
        if (!$this->checkPermission($project_id, $id)) {
            return ['status' => 'error', 'message' => 'このタスクを更新する権限がありません'];
        }
        
        $old = $this->getById($id);
        $data = array(
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        );
        // Khi chuyển trạng thái sang completed thì tự động cập nhật tiến độ 100%
        if ($status === 'completed') {
            $data['progress'] = 100;
        }

        $result = $this->query_update($data, ['id' => $id]);

        if ($result < 0) {
            return ['status' => 'error', 'message' => 'Update failed'];
        }

        $this->logTaskAction($id, 'status_changed', 'ステータス変更', $old['status'], $status);
        if ($status === 'completed' && (isset($old['progress']) ? (int)$old['progress'] : 0) != 100) {
            $this->logTaskAction($id, 'progress_updated', '進捗変更', isset($old['progress']) ? $old['progress'] : 0, 100);
        }
        $mergedTask = array_merge($old, $data);
        $mergedTask['id'] = $id;
        $syncOptions = array();
        if ($this->shouldRecalcDrawingPricesForTask($mergedTask, $old)) {
            $syncOptions['recalc_prices'] = true;
        }
        $this->syncTaskDrawingsForTask($id, $mergedTask, $syncOptions);

        $response = array('status' => 'success');
        if ($status === 'completed' || $status === 'cancelled') {
            $userId = $this->resolveTimerUserId();
            $activeEntryId = 0;
            if ($userId !== '') {
                $active = $this->fetchActiveTaskTimerRow($userId);
                if ($active && intval($active['task_id']) === $id) {
                    $activeEntryId = intval($active['id']);
                }
            }

            $stopResults = $this->stopAllActiveTaskTimersForTask($id);
            if ($activeEntryId > 0) {
                foreach ($stopResults as $stopResult) {
                    if (
                        isset($stopResult['status'], $stopResult['entry_id'])
                        && $stopResult['status'] === 'success'
                        && intval($stopResult['entry_id']) === $activeEntryId
                    ) {
                        $response['stopped_timer'] = array(
                            'task_id' => $stopResult['task_id'],
                            'hours_added' => $stopResult['hours_added'],
                            'estimated_hours' => $stopResult['estimated_hours'],
                        );
                        break;
                    }
                }
            }
        }

        return $this->appendActiveTimerTaskIds($response);
    }

    function updateProgress() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $progress = isset($_POST['progress']) ? intval($_POST['progress']) : 0;
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        
        if (!$id) return ['status' => 'error', 'message' => 'Missing task id'];
        
        if (!$this->checkPermission($project_id, $id)) {
            return ['status' => 'error', 'message' => 'このタスクを更新する権限がありません'];
        }
        
        $old = $this->getById($id);
        $data = array(
            'progress' => $progress,
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        $result = $this->query_update($data, ['id' => $id]);
        
        if ($result) {
            $this->logTaskAction($id, 'progress_updated', '進捗変更', $old['progress'], $progress);
            $mergedTask = array_merge($old, $data);
            $mergedTask['id'] = $id;
            if ($this->shouldRecalcDrawingPricesForTask($mergedTask, $old)) {
                $projectId = isset($old['project_id']) ? intval($old['project_id']) : 0;
                if ($projectId > 0) {
                    $this->getDrawingModel()->autoCalculateAllDrawingPricesForProject($projectId);
                }
            }
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'message' => 'Update failed'];
        }
    }

    /**
     * Toggle task link to drawings list (drawing_count 0 = off, >0 = on).
     * Project managers or project members.
     */
    function updateDrawingLink() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        if ($id <= 0 || $project_id <= 0) {
            return ['status' => 'error', 'message' => 'Missing required parameters'];
        }
        $old = $this->getById($id);
        if (!$old || intval($old['project_id']) !== $project_id) {
            return ['status' => 'error', 'message' => 'Task not found'];
        }
        if (!$this->userCanEditTaskDrawing($project_id, isset($old['task_kind']) ? $old['task_kind'] : '')) {
            return ['status' => 'error', 'message' => 'Forbidden', 'http_status' => 403];
        }
        $linked = isset($_POST['linked']) && (string) $_POST['linked'] === '1';
        if ($linked) {
            $drawingCount = isset($_POST['drawing_count']) ? max(1, intval($_POST['drawing_count'])) : max(1, intval($old['drawing_count']));
        } else {
            $drawingCount = 0;
        }
        $data = array(
            'drawing_count' => $drawingCount,
            'updated_at' => date('Y-m-d H:i:s'),
        );
        $result = $this->query_update($data, ['id' => $id]);
        // mysqli_affected_rows() is 0 when values are unchanged (e.g. duplicate change+blur requests).
        if ($result < 0) {
            return ['status' => 'error', 'message' => 'Update failed'];
        }
        if (intval($old['drawing_count']) !== $drawingCount) {
            $this->logTaskAction($id, 'updated', '図面数を変更', $old['drawing_count'], $drawingCount);
        }
        $mergedTask = array_merge($old, $data);
        $mergedTask['id'] = $id;
        $syncOptions = array();
        if ($this->shouldRecalcDrawingPricesForTask($mergedTask, $old)) {
            $syncOptions['recalc_prices'] = true;
        }
        $this->syncTaskDrawingsForTask($id, $mergedTask, $syncOptions);
        return ['status' => 'success', 'drawing_count' => $drawingCount];
    }

    function updateAssignee() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $assigned_to = isset($_POST['assigned_to']) ? $_POST['assigned_to'] : '';
        // $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        
        if (!$id) return ['status' => 'error', 'message' => 'Missing task id'];
       
        $data = array(
            'assigned_to' => $assigned_to,
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        $result = $this->query_update($data, ['id' => $id]);
        
        if ($result) {
            // Sync task_assignees table
            $this->syncTaskAssignees($id, $assigned_to);
            $task = $this->getById($id);
            if ($task) {
                $syncOptions = array();
                if ($this->shouldRecalcDrawingPricesForTask($task)) {
                    $syncOptions['recalc_prices'] = true;
                }
                $this->syncTaskDrawingsForTask($id, $task, $syncOptions);
            }
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'message' => 'Update failed'];
        }
    }
    
    /**
     * Mark assignees as acknowledged (受領済み) without requiring manual 受領 action.
     */
    private function acknowledgeTaskAssignees($taskId, $assignedToCsv) {
        $taskId = intval($taskId);
        if ($taskId <= 0 || trim((string) $assignedToCsv) === '') {
            return;
        }
        $userIds = array_filter(array_map('intval', explode(',', (string) $assignedToCsv)));
        foreach ($userIds as $userId) {
            if ($userId <= 0) {
                continue;
            }
            $this->query(sprintf(
                "INSERT INTO %stask_assignees (task_id, user_id, acknowledged, acknowledged_at)
                VALUES (%d, %d, 1, NOW())
                ON DUPLICATE KEY UPDATE acknowledged = 1, acknowledged_at = NOW(), updated_at = NOW()",
                DB_PREFIX,
                $taskId,
                $userId
            ));
        }
    }

    /**
     * Sync task_assignees table when assigned_to changes
     */
    function syncTaskAssignees($task_id, $assigned_to_csv) {
        // Get current assignees from task_assignees table
        $currentAssignees = $this->fetchAll(
            "SELECT user_id FROM " . DB_PREFIX . "task_assignees WHERE task_id = " . intval($task_id)
        );
        $currentUserIds = array_map(function($row) { return intval($row['user_id']); }, $currentAssignees);
        
        // Parse new assigned_to CSV
        $newUserIds = [];
        if (!empty($assigned_to_csv)) {
            $parts = explode(',', $assigned_to_csv);
            foreach ($parts as $part) {
                $userId = intval(trim($part));
                if ($userId > 0) {
                    $newUserIds[] = $userId;
                }
            }
        }
        
        // Remove assignees that are no longer assigned
        $toRemove = array_diff($currentUserIds, $newUserIds);
        if (!empty($toRemove)) {
            $this->query(
                "DELETE FROM " . DB_PREFIX . "task_assignees 
                WHERE task_id = " . intval($task_id) . " 
                AND user_id IN (" . implode(',', $toRemove) . ")"
            );
        }
        
        // Add new assignees (if not exists). If assignee is current user (self-assigned), default acknowledged = 1
        $current_user_id = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
        $toAdd = array_diff($newUserIds, $currentUserIds);
        foreach ($toAdd as $userId) {
            $is_self = ($current_user_id > 0 && intval($userId) === $current_user_id);
            $ack = $is_self ? 1 : 0;
            $ackAt = $is_self ? "NOW()" : "NULL";
            $this->query(
                "INSERT INTO " . DB_PREFIX . "task_assignees (task_id, user_id, acknowledged, acknowledged_at) 
                VALUES (" . intval($task_id) . ", " . intval($userId) . ", " . $ack . ", " . $ackAt . ")
                ON DUPLICATE KEY UPDATE task_id = task_id"
            );
        }
    }
    
    /**
     * Acknowledge task assignment
     */
    function acknowledgeTask() {
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $user_id = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
        
        if (!$task_id || !$user_id) {
            return ['status' => 'error', 'message' => 'Missing required parameters'];
        }
        
        // Verify user is assigned to this task
        $task = $this->getById($task_id);
        if (!$task) {
            return ['status' => 'error', 'message' => 'Task not found'];
        }
        
        // Check if user is in assigned_to
        $assignedToArray = !empty($task['assigned_to']) ? explode(',', $task['assigned_to']) : [];
        $isAssigned = false;
        foreach ($assignedToArray as $assignedId) {
            if (intval(trim($assignedId)) == $user_id) {
                $isAssigned = true;
                break;
            }
        }
        
        if (!$isAssigned) {
            return ['status' => 'error', 'message' => 'Bạn không được giao việc này'];
        }
        
        // Update or insert acknowledgement
        $query = sprintf(
            "INSERT INTO " . DB_PREFIX . "task_assignees (task_id, user_id, acknowledged, acknowledged_at) 
            VALUES (%d, %d, 1, NOW())
            ON DUPLICATE KEY UPDATE 
                acknowledged = 1, 
                acknowledged_at = NOW(),
                updated_at = NOW()",
            intval($task_id),
            intval($user_id)
        );
        
        $result = $this->query($query);
        
        if ($result) {
            $this->logTaskAction($task_id, 'acknowledged', 'Đã nhận việc', null, $user_id);
            return ['status' => 'success', 'message' => 'Đã đánh dấu nhận việc thành công'];
        } else {
            return ['status' => 'error', 'message' => 'Failed to acknowledge task'];
        }
    }
    
    /**
     * Get task acknowledgements for a specific task
     */
    function getTaskAcknowledgements($task_id) {
        $query = sprintf(
            "SELECT ta.*, u.realname as user_name, u.userid
            FROM " . DB_PREFIX . "task_assignees ta
            LEFT JOIN " . DB_PREFIX . "user u ON ta.user_id = u.id
            WHERE ta.task_id = %d
            ORDER BY ta.acknowledged DESC, ta.acknowledged_at DESC",
            intval($task_id)
        );
        return $this->fetchAll($query);
    }



    function updateDescription() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $description = isset($_POST['description']) ? $_POST['description'] : '';
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        
        if (!$id || !$description) {
            return ['status' => 'error', 'message' => 'Missing required parameters'];
        }
        
        if (!$this->checkPermission($project_id, $id)) {
            return ['status' => 'error', 'message' => 'このタスクを更新する権限がありません'];
        }
        
        $data = array(
            'description' => $description,
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        $result = $this->query_update($data, ['id' => $id]);
        
        if ($result) {
            $this->logTaskAction($id, 'description_updated', '説明変更');
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'message' => 'Update failed'];
        }
    }
    

    function updateEstimatedHours() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;

        if ($id <= 0) {
            return ['status' => 'error', 'message' => 'Missing task id'];
        }

        if (!$this->checkPermission($project_id, $id)) {
            return ['status' => 'error', 'message' => 'このタスクを更新する権限がありません'];
        }

        $old = $this->getById($id);
        if (!$old) {
            return ['status' => 'error', 'message' => 'Task not found'];
        }

        $estimatedHours = $this->normalize_estimated_hours(isset($_POST['estimated_hours']) ? $_POST['estimated_hours'] : 0);
        $oldHours = $this->getCompletedTimeEntryHoursSum($id);
        if ($oldHours <= 0) {
            $oldHours = isset($old['estimated_hours']) ? $this->normalize_estimated_hours($old['estimated_hours']) : 0;
        }
        $adjustmentAt = isset($_POST['adjustment_at']) ? $_POST['adjustment_at'] : null;
        $estimatedHours = $this->applyTaskHoursTarget($id, $estimatedHours, $old, $adjustmentAt);

        if ($oldHours != $estimatedHours) {
            $this->logTaskAction($id, 'updated', '工数を変更', $oldHours, $estimatedHours);
        }

        return ['status' => 'success', 'estimated_hours' => $estimatedHours];
    }

    /**
     * Admin-only: update one time_entries row (start/end/hours) and resync task 工数.
     */
    function updateTimeEntryByAdmin() {
        if (!isset($_SESSION['authority']) || $_SESSION['authority'] !== 'administrator') {
            return [
                'status' => 'error',
                'message' => '権限がありません',
                'http_status' => 403,
            ];
        }

        $entryId = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($entryId <= 0) {
            return ['status' => 'error', 'message' => 'Missing time entry id'];
        }

        $entry = $this->fetchOne(sprintf(
            "SELECT * FROM %stime_entries WHERE id = %d LIMIT 1",
            DB_PREFIX,
            $entryId
        ));
        if (!$entry) {
            return ['status' => 'error', 'message' => 'Time entry not found'];
        }
        if (empty($entry['end_time'])) {
            return ['status' => 'error', 'message' => '作業計測中のエントリは編集できません'];
        }

        $taskId = intval($entry['task_id'] ?? 0);
        if ($taskId <= 0) {
            return ['status' => 'error', 'message' => 'Task not found'];
        }

        $hours = $this->normalize_time_entry_hours(isset($_POST['hours']) ? $_POST['hours'] : ($entry['hours'] ?? 0));

        $hasStart = isset($_POST['start_time']) && trim((string) $_POST['start_time']) !== '';
        $hasEnd = isset($_POST['end_time']) && trim((string) $_POST['end_time']) !== '';
        $hasAdjustment = isset($_POST['adjustment_at']) && trim((string) $_POST['adjustment_at']) !== '';

        if ($hasStart || $hasEnd) {
            $startTime = $hasStart
                ? $this->parseAdjustmentDateTime($_POST['start_time'])
                : (string) ($entry['start_time'] ?? date('Y-m-d H:i:s'));
            $endTime = $hasEnd
                ? $this->parseAdjustmentDateTime($_POST['end_time'])
                : (string) ($entry['end_time'] ?? $startTime);
        } elseif ($hasAdjustment) {
            $entryTime = $this->parseAdjustmentDateTime($_POST['adjustment_at']);
            $startTime = $entryTime;
            $endTime = $entryTime;
        } else {
            $startTime = (string) ($entry['start_time'] ?? date('Y-m-d H:i:s'));
            $endTime = (string) ($entry['end_time'] ?? $startTime);
        }

        $now = date('Y-m-d H:i:s');

        $prevTable = $this->table;
        $this->table = DB_PREFIX . 'time_entries';
        $updateResult = $this->query_update(
            array(
                'start_time' => $startTime,
                'end_time' => $endTime,
                'hours' => $hours,
                'updated_at' => $now,
            ),
            array('id' => $entryId)
        );
        $this->table = $prevTable;

        if ($updateResult < 0) {
            return ['status' => 'error', 'message' => 'Update failed'];
        }

        $estimatedHours = $this->syncTaskEstimatedHoursFromTimeEntries($taskId);
        $this->logTaskAction(
            $taskId,
            'updated',
            '作業時間エントリを変更',
            isset($entry['hours']) ? $this->normalize_time_entry_hours($entry['hours']) : 0,
            $hours
        );

        return [
            'status' => 'success',
            'entry' => array(
                'id' => $entryId,
                'task_id' => $taskId,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'hours' => $hours,
            ),
            'estimated_hours' => $estimatedHours,
        ];
    }

    function updateNote() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;

        if ($id <= 0) {
            return ['status' => 'error', 'message' => 'Missing task id'];
        }

        if (!$project_id) {
            $existing = $this->getById($id);
            if ($existing && !empty($existing['project_id'])) {
                $project_id = intval($existing['project_id']);
            }
        }

        if (!$this->canUserEditTaskNote($project_id, $id)) {
            return [
                'status' => 'error',
                'message' => 'このタスクのメモを更新する権限がありません',
                'http_status' => 403,
            ];
        }

        $old = $this->getById($id);
        if (!$old) {
            return ['status' => 'error', 'message' => 'Task not found'];
        }

        $note = array_key_exists('note', $_POST) ? (string) $_POST['note'] : (isset($old['note']) ? (string) $old['note'] : '');
        $data = array(
            'note' => $note,
            'updated_at' => date('Y-m-d H:i:s'),
        );

        $result = $this->query_update($data, ['id' => $id]);
        if ($result < 0) {
            return ['status' => 'error', 'message' => 'Update failed'];
        }

        $oldNote = isset($old['note']) ? (string) $old['note'] : '';
        if ($oldNote !== $note) {
            $this->logTaskAction($id, 'updated', 'メモを変更', $oldNote, $note);
        }

        return ['status' => 'success'];
    }

    private function canUserEditTaskNote($projectId, $taskId) {
        $projectId = intval($projectId);
        $taskId = intval($taskId);
        if ($projectId <= 0 || $taskId <= 0) {
            return false;
        }

        $perm = $this->resolveProjectTaskPermissions($projectId);
        if (!$perm || empty($perm['is_member'])) {
            return false;
        }

        if (!empty($perm['can_manage_project'])) {
            return true;
        }

        $task = $this->getById($taskId);
        if (!$task || intval($task['project_id']) !== $projectId) {
            return false;
        }

        return $this->isCurrentUserAssignedToTask($task);
    }

    private function userCanEditTaskDrawing($projectId, $taskKind) {
        $projectId = intval($projectId);
        if ($projectId <= 0) {
            return false;
        }

        $perm = $this->resolveProjectTaskPermissions($projectId);
        return $perm && !empty($perm['is_member']);
    }

    private function resolveTimerUserId() {
        return isset($_SESSION['userid']) ? (string) $_SESSION['userid'] : '';
    }

    private function isCurrentUserAssignedToTask($task) {
        if (!$task || empty($task['assigned_to'])) {
            return false;
        }

        $currentUserId = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
        if ($currentUserId <= 0) {
            return false;
        }

        $assignedIds = array_map('intval', array_filter(array_map('trim', explode(',', $task['assigned_to']))));
        return in_array($currentUserId, $assignedIds, true);
    }

    private function fetchActiveTaskTimerRow($userId) {
        if ($userId === '') {
            return null;
        }

        $query = sprintf(
            "SELECT te.*, t.title AS task_title, t.project_id, t.estimated_hours, p.name AS project_name
            FROM %stime_entries te
            INNER JOIN %stasks t ON te.task_id = t.id
            LEFT JOIN %sprojects p ON t.project_id = p.id
            WHERE te.user_id = '%s' AND te.end_time IS NULL
            ORDER BY te.id DESC
            LIMIT 1",
            DB_PREFIX,
            DB_PREFIX,
            DB_PREFIX,
            $userId
        );

        return $this->fetchOne($query);
    }

    private function fetchActiveTaskTimerRowsByTaskId($taskId) {
        $taskId = intval($taskId);
        if ($taskId <= 0) {
            return array();
        }

        $query = sprintf(
            "SELECT te.*, t.title AS task_title, t.project_id, t.estimated_hours, p.name AS project_name
            FROM %stime_entries te
            INNER JOIN %stasks t ON te.task_id = t.id
            LEFT JOIN %sprojects p ON t.project_id = p.id
            WHERE te.task_id = %d AND te.end_time IS NULL
            ORDER BY te.id ASC",
            DB_PREFIX,
            DB_PREFIX,
            DB_PREFIX,
            $taskId
        );

        $rows = $this->fetchAll($query);
        return is_array($rows) ? $rows : array();
    }

    private function stopAllActiveTaskTimersForTask($taskId) {
        $rows = $this->fetchActiveTaskTimerRowsByTaskId($taskId);
        $results = array();
        foreach ($rows as $row) {
            $results[] = $this->stopActiveTaskTimerEntry($row);
        }
        return $results;
    }

    private function formatActiveTimerPayload($row) {
        if (!$row) {
            return null;
        }

        $startTime = isset($row['start_time']) ? $row['start_time'] : '';
        $startTimestamp = ($startTime !== '' && $startTime !== null) ? intval(strtotime($startTime)) : 0;
        $serverNow = time();
        $elapsedSeconds = ($startTimestamp > 0) ? max(0, $serverNow - $startTimestamp) : 0;

        return array(
            'id' => intval($row['id']),
            'task_id' => intval($row['task_id']),
            'task_title' => isset($row['task_title']) ? $row['task_title'] : '',
            'project_id' => intval($row['project_id']),
            'project_name' => isset($row['project_name']) ? $row['project_name'] : '',
            'start_time' => $startTime,
            'start_timestamp' => $startTimestamp,
            'elapsed_seconds' => $elapsedSeconds,
            'estimated_hours' => isset($row['estimated_hours'])
                ? $this->normalize_estimated_hours($row['estimated_hours'])
                : 0,
        );
    }

    function getActiveTaskTimer() {
        $userId = $this->resolveTimerUserId();
        if ($userId === '') {
            return array('status' => 'success', 'active' => null, 'server_now' => time());
        }

        $row = $this->fetchActiveTaskTimerRow($userId);
        return $this->appendActiveTimerTaskIds(array(
            'status' => 'success',
            'active' => $row ? $this->formatActiveTimerPayload($row) : null,
        ));
    }

    private function stopActiveTaskTimerEntry($active) {
        if (!$active || empty($active['id'])) {
            return array('status' => 'error', 'message' => '進行中の作業計測がありません');
        }

        $taskId = intval($active['task_id']);
        $task = $this->getById($taskId);
        if (!$task) {
            return array('status' => 'error', 'message' => 'Task not found');
        }

        $endTime = date('Y-m-d H:i:s');
        $startTs = strtotime($active['start_time']);
        $endTs = strtotime($endTime);
        $elapsedSeconds = max(0, $endTs - $startTs);
        $hours = $this->normalize_estimated_hours($elapsedSeconds / 3600);

        $this->table = DB_PREFIX . 'time_entries';
        $updateResult = $this->query_update(
            array(
                'end_time' => $endTime,
                'hours' => $hours,
                'updated_at' => $endTime,
            ),
            array('id' => intval($active['id']))
        );
        $this->table = DB_PREFIX . 'tasks';

        if ($updateResult < 0) {
            return array('status' => 'error', 'message' => '作業計測の終了に失敗しました');
        }

        $task = $this->getById($taskId);
        if (!$task) {
            return array('status' => 'error', 'message' => 'Task not found');
        }

        $oldHours = $this->normalize_estimated_hours(isset($task['estimated_hours']) ? $task['estimated_hours'] : 0);
        $newHours = $this->syncTaskEstimatedHoursFromTimeEntries($taskId);

        if ($oldHours != $newHours) {
            $this->logTaskAction($taskId, 'updated', '作業時間を計測', $oldHours, $newHours);
        }

        return array(
            'status' => 'success',
            'entry_id' => intval($active['id']),
            'task_id' => $taskId,
            'hours_added' => $hours,
            'estimated_hours' => $newHours,
        );
    }

    function startTaskTimer() {
        $taskId = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;

        if ($taskId <= 0) {
            return array('status' => 'error', 'message' => 'Missing task id');
        }

        $task = $this->getById($taskId);
        if (!$task) {
            return array('status' => 'error', 'message' => 'Task not found');
        }

        if (!$this->isCurrentUserAssignedToTask($task)) {
            return array('status' => 'error', 'message' => 'このタスクの担当者のみ作業時間を計測できます');
        }

        $userId = $this->resolveTimerUserId();
        if ($userId === '') {
            return array('status' => 'error', 'message' => 'ログインが必要です');
        }

        $stoppedPreviousTask = null;
        $active = $this->fetchActiveTaskTimerRow($userId);
        if ($active) {
            $payload = $this->formatActiveTimerPayload($active);
            if (intval($active['task_id']) === $taskId) {
                return array(
                    'status' => 'success',
                    'active' => $payload,
                    'message' => 'すでに計測中です',
                );
            }

            $stopResult = $this->stopActiveTaskTimerEntry($active);
            if ($stopResult['status'] !== 'success') {
                return $stopResult;
            }

            $stoppedPreviousTask = array(
                'task_id' => intval($stopResult['task_id']),
                'hours_added' => $stopResult['hours_added'],
                'estimated_hours' => $stopResult['estimated_hours'],
            );
        }

        $startTime = date('Y-m-d H:i:s');
        $this->table = DB_PREFIX . 'time_entries';
        $entryId = $this->query_insert(array(
            'task_id' => $taskId,
            'user_id' => $userId,
            'start_time' => $startTime,
            'created_at' => $startTime,
        ));
        $this->table = DB_PREFIX . 'tasks';

        if (!$entryId) {
            return array('status' => 'error', 'message' => '作業計測の開始に失敗しました');
        }

        $this->logTaskAction($taskId, 'updated', '作業時間の計測を開始', '', $startTime);
        $activeRow = $this->fetchActiveTaskTimerRow($userId);

        $response = array(
            'status' => 'success',
            'active' => $this->formatActiveTimerPayload($activeRow),
        );
        if ($stoppedPreviousTask) {
            $response['stopped_previous_task'] = $stoppedPreviousTask;
        }

        return $this->appendActiveTimerTaskIds($response);
    }

    function stopTaskTimer() {
        $requestedTaskId = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $userId = $this->resolveTimerUserId();

        if ($userId === '') {
            return array('status' => 'error', 'message' => 'ログインが必要です');
        }

        $active = $this->fetchActiveTaskTimerRow($userId);
        if (!$active) {
            return array('status' => 'error', 'message' => '進行中の作業計測がありません');
        }

        $taskId = intval($active['task_id']);

        if ($requestedTaskId > 0 && $requestedTaskId !== $taskId) {
            return array(
                'status' => 'error',
                'message' => '別のタスクで作業計測中です',
                'active' => $this->formatActiveTimerPayload($active),
            );
        }

        $task = $this->getById($taskId);
        if (!$task) {
            return array('status' => 'error', 'message' => 'Task not found');
        }

        if (!$this->isCurrentUserAssignedToTask($task)) {
            return array('status' => 'error', 'message' => 'このタスクの担当者のみ作業時間を計測できます');
        }

        $result = $this->stopActiveTaskTimerEntry($active);
        if ($result['status'] !== 'success') {
            return $result;
        }

        $result['active'] = null;
        return $this->appendActiveTimerTaskIds($result);
    }

    function updatePriority() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $priority = isset($_POST['priority']) ? $_POST['priority'] : '';
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        
        if (!$id || !$priority) {
            return ['status' => 'error', 'message' => 'Missing required parameters'];
        }
        
        if (!$this->checkPermission($project_id, $id)) {
            return ['status' => 'error', 'message' => 'このタスクを更新する権限がありません'];
        }
        
        $old = $this->getById($id);
        $data = array(
            'priority' => $priority,
            'updated_at' => date('Y-m-d H:i:s')
        );

        $result = $this->query_update($data, ['id' => $id]);
        if ($result) {
            $this->logTaskAction($id, 'priority_updated', '優先度変更', $old['priority'], $priority);
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'message' => 'Update failed'];
        }
    }

    function updateParentTaskProgress($parent_id) {
        $query = sprintf(
            "UPDATE {$this->table} 
            SET progress = (
                SELECT COALESCE(AVG(progress), 0) 
                FROM {$this->table} 
                WHERE parent_id = %d
            ),
            updated_at = '%s'
            WHERE id = %d",
            intval($parent_id),
            date('Y-m-d H:i:s'),
            intval($parent_id)
        );
        return $this->query($query);
    }

    function updateProjectProgress($project_id) {
        $query = sprintf(
            "UPDATE " . DB_PREFIX . "projects 
            SET progress = (
                SELECT COALESCE(AVG(progress), 0) 
                FROM {$this->table} 
                WHERE project_id = %d AND parent_id IS NULL
            ),
            updated_at = '%s'
            WHERE id = %d",
            intval($project_id),
            date('Y-m-d H:i:s'),
            intval($project_id)
        );
       
        return $this->query($query);
    }

    function getTimeEntries($taskId) {
        $query = sprintf(
            "SELECT te.*, u.realname as user_name 
            FROM " . DB_PREFIX . "time_entries te 
            LEFT JOIN " . DB_PREFIX . "user u ON te.user_id = u.id 
            WHERE te.task_id = %d 
            ORDER BY te.start_time DESC",
            intval($taskId)
        );
        return $this->fetchAll($query);
    }

    function getComments() {
        // Handle both direct parameter and GET parameter
        $taskId = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;
        
        if (!$taskId) {
            return [];
        }
        
        // Get pagination parameters
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
        $offset = ($page - 1) * $per_page;
        
        $query = sprintf(
            "SELECT c.*, u.realname as user_name, u.user_image
            FROM " . DB_PREFIX . "comments c 
            LEFT JOIN " . DB_PREFIX . "user u ON c.user_id = u.userid 
            WHERE c.task_id = %d 
            ORDER BY c.created_at DESC
            LIMIT %d OFFSET %d",
            intval($taskId),
            intval($per_page),
            intval($offset)
        );
        
        $comments = $this->fetchAll($query);
        $this->attachCommentLikeAggregates($comments);
        
        return $comments;
    }

    function addTimeEntry($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
                $this->table = DB_PREFIX . 'time_entries';        $result = $this->query_insert($data);        $this->table = DB_PREFIX . 'tasks';
        return $result;
    }

    function updateTimeEntry($data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->table = DB_PREFIX . 'time_entries';
        $result = $this->query_update($data, ['task_id' => $data['task_id']]);
        $this->table = DB_PREFIX . 'tasks';
        return $result;
    }

    function addComment() {
        $data = $_POST;
        
        // Get task_id from the request
        $task_id = isset($data['task_id']) ? intval($data['task_id']) : 0;
        if (!$task_id) {
            return ['success' => false, 'message' => 'タスクIDが指定されていません'];
        }
        
        // Get task to get project_id
        $task = $this->getById($task_id);
        if (!$task) {
            return ['success' => false, 'message' => 'タスクが見つかりません'];
        }
        
        $project_id = $task['project_id'];
        
        $commentData = array(
            'task_id' => $task_id,
            'user_id' => $data['user_id'],
            'content' => $data['content'],
            'created_at' => date('Y-m-d H:i:s')
        );
        
        $this->table = DB_PREFIX . 'comments';
        $result = $this->query_insert($commentData);
        $this->table = DB_PREFIX . 'tasks'; // Reset table back to tasks
        
        // Send mention notifications if comment was added successfully
        if ($result && $project_id) {
            $this->sendMentionNotifications($project_id, $data['content'], $data['user_id'], $result, $task_id);
        }
        
        return ['success' => (bool)$result, 'id' => $result];
    }

    function getById($params = null) {
        // Handle both direct ID parameter and params array from API
        if (is_array($params)) {
            $id = isset($params['id']) ? $params['id'] : 0;
        } else {
            $id = $params;
        }

        $query = sprintf(
            "SELECT t.*, p.name as project_name, u.realname as assigned_to_name 
            FROM {$this->table} t 
            LEFT JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id 
            LEFT JOIN " . DB_PREFIX . "user u ON t.assigned_to = u.id 
            WHERE t.id = %d",
            intval($id)
        );
        return $this->fetchOne($query);
    }

    function getByProject($params = null) {
        // Handle both direct project_id parameter and params array from API
        if (is_array($params)) {
            $project_id = isset($params['project_id']) ? intval($params['project_id']) : 0;
        } else {
            $project_id = intval($params);
        }
        
        if (!$project_id) {
            return [];
        }
        
        $query = sprintf(
            "SELECT t.*, u.realname as assigned_to_name
            FROM {$this->table} t 
            LEFT JOIN " . DB_PREFIX . "user u ON t.assigned_to = u.id 
            WHERE t.project_id = %d 
            ORDER BY t.position, t.created_at ASC",
            $project_id
        );
        $tasks = $this->fetchAll($query);
        if (!empty($tasks)) {
            $subtaskMap = $this->fetchSubtaskCountMap(array_column($tasks, 'id'));
            foreach ($tasks as &$task) {
                $task['subtask_count'] = $subtaskMap[(int)$task['id']] ?? 0;
            }
            unset($task);
            $this->attachTimeEntryHours($tasks);
        }
        return $tasks;
    }

    private function checkPermission($projectId, $taskId = null) {
        $project = null;
        $project = $this->fetchOne(
            "SELECT * FROM " . DB_PREFIX . "projects p" .  " WHERE p.id = " . intval($projectId)
        );
        
        $task = $this->getById($taskId);

        if (!$project || !$task) return false;
        
        $currentUserIdNumber = $_SESSION['id'];
        $currentUserId = $_SESSION['userid'];
        $isAssigned = $this->isCurrentUserAssignedToTask($task);
        $isProjectManager = $_SESSION['authority'] == 'administrator';
       
        
        // If not manager by projects.manager_id, check groupware_project_members
        if (!$isProjectManager) {
            $memberCheck = $this->fetchOne(
                "SELECT COUNT(*) as count FROM " . DB_PREFIX . "project_members " .
                "WHERE project_id = " . intval($task['project_id']) . " " .
                "AND user_id = '" . $currentUserIdNumber . "' " .
                "AND role = 'manager'"
            );
            $isProjectManager = ($memberCheck && $memberCheck['count'] > 0);
        }
        if(!$isProjectManager){
            $departmentCheck = $this->fetchOne(
                "SELECT COUNT(*) as count FROM " . DB_PREFIX . "user_department ud " .
                "WHERE ud.department_id = " . intval($project['department_id']) . " " .
                "AND ud.userid = '" . $currentUserId . "' AND ud.project_manager = 1"
            );
            $isProjectManager = ($departmentCheck && $departmentCheck['count'] > 0);
        }

        $isTaskCreator = $task['created_by'] == $currentUserIdNumber;
        
        return $isAssigned || $isProjectManager || $isTaskCreator;
    }


    /**
     * Phase 2.3 – Task list/read for AI context.
     * Returns tasks for a project only if the current user can view the project (via Project::canUserEditProject).
     *
     * @param int $project_id
     * @param array $options ['limit' => int, 'include_subtasks' => bool]
     * @return array
     */
    public function getForAiContext($project_id, $options = []) {
        $project_id = intval($project_id);
        if ($project_id <= 0) {
            return [];
        }
        if (!class_exists('Project')) {
            require_once DIR_MODEL . 'project.php';
        }
        $projectModel = new Project();
        if (!$projectModel->canUserEditProject($project_id)) {
            return [];
        }
        $limit = isset($options['limit']) ? min(100, max(1, intval($options['limit']))) : 50;
        $include_subtasks = !empty($options['include_subtasks']);
        $whereArr = ["t.project_id = " . $project_id];
        if (!$include_subtasks) {
            $whereArr[] = "t.parent_id IS NULL";
        }
        $where = "WHERE " . implode(" AND ", $whereArr);
        $fields = "t.id, t.project_id, t.title, t.status, t.assigned_to, t.due_date, t.progress, t.parent_id, t.position, u.realname as assigned_to_name";
        $query = "SELECT " . $fields . " FROM " . $this->table . " t LEFT JOIN " . DB_PREFIX . "user u ON t.assigned_to = u.id " . $where . " ORDER BY t.position, t.created_at DESC LIMIT " . $limit;
        return $this->fetchAll($query);
    }

    /**
     * Phase 2.4 – Task statistics for AI context.
     * Returns task count by status and overdue count for projects the user can see (same visibility as Project::getForAiContext).
     *
     * @param int|null $department_id optional filter by project department
     * @return array ['by_status' => [...], 'overdue_count' => int]
     */
    public function getStatsForAiContext($department_id = null) {
        $user_id = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
        $is_admin = (isset($_SESSION['authority']) && $_SESSION['authority'] === 'administrator');
        $permJoin = "";
        $permWhere = "";
        if (!$is_admin && $user_id) {
            $permJoin = " INNER JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id ";
            $permWhere = sprintf(
                " AND (p.created_by = %d OR EXISTS (SELECT 1 FROM " . DB_PREFIX . "project_members pm WHERE pm.project_id = p.id AND pm.user_id = %d))",
                $user_id,
                $user_id
            );
        } else {
            $permJoin = " INNER JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id ";
        }
        $deptWhere = ($department_id !== null && $department_id > 0) ? sprintf(" AND p.department_id = %d", $department_id) : "";
        $baseWhere = "WHERE p.status != 'deleted'" . $permWhere . $deptWhere;

        $by_status = $this->fetchAll(
            "SELECT t.status, COUNT(*) as count FROM " . $this->table . " t " . $permJoin . $baseWhere . " GROUP BY t.status"
        );
        $overdue = $this->fetchOne(
            "SELECT COUNT(*) as c FROM " . $this->table . " t " . $permJoin . $baseWhere .
            " AND t.due_date IS NOT NULL AND t.due_date < NOW() AND t.status NOT IN ('completed','cancelled')"
        );
        return [
            'by_status' => $by_status ?: [],
            'overdue_count' => isset($overdue['c']) ? (int)$overdue['c'] : 0
        ];
    }

    /**
     * Matches task.php UI: can_manage_project || is_member || user_department.task_add.
     */
    private function canUserAddTask($projectId) {
        $perm = $this->resolveProjectTaskPermissions($projectId);
        if (!$perm) {
            return false;
        }
        if (!empty($perm['can_manage_project']) || !empty($perm['is_member'])) {
            return true;
        }
        $rule = isset($perm['rule']) ? $perm['rule'] : null;
        return $rule && isset($rule['task_add']) && (int)$rule['task_add'] === 1;
    }

    private function resolveProjectTaskPermissions($projectId) {
        $projectId = intval($projectId);
        if ($projectId <= 0) {
            return false;
        }
        $project = $this->fetchOne(
            "SELECT * FROM " . DB_PREFIX . "projects p WHERE p.id = " . $projectId
        );
        if (!$project) {
            return false;
        }
        $departmentCheck = null;
        $currentUserIdNumber = $_SESSION['id'];
        $currentUserId = $_SESSION['userid'];
        $isAdmin = $_SESSION['authority'] == 'administrator';
        $isDepartmentManager = false;
        $isProjectManager = false;
        $isMember = false;

        if (!$isAdmin) {
            $memberCheck = $this->fetchOne(
                "SELECT COUNT(*) as count FROM " . DB_PREFIX . "project_members " .
                "WHERE project_id = " . $projectId . " " .
                "AND user_id = '" . $currentUserIdNumber . "' " .
                "AND role = 'manager'"
            );
            $isProjectManager = ($memberCheck && $memberCheck['count'] > 0);
        }
        if (!$isAdmin) {
            $memberCheck = $this->fetchOne(
                "SELECT COUNT(*) as count FROM " . DB_PREFIX . "project_members " .
                "WHERE project_id = " . $projectId . " " .
                "AND user_id = '" . $currentUserIdNumber . "' " .
                "AND role = 'member'"
            );
            $isMember = ($memberCheck && $memberCheck['count'] > 0);
        }
        if (!$isAdmin) {
            $departmentCheck = $this->fetchOne(
                "SELECT * FROM " . DB_PREFIX . "user_department ud " .
                "WHERE ud.department_id = " . intval($project['department_id']) . " " .
                "AND ud.userid = '" . $currentUserId . "' LIMIT 1"
            );
            $isDepartmentManager = ($departmentCheck && $departmentCheck['project_manager'] == 1);
        } else {
            $departmentCheck = [
                'project_manager' => 1,
                'project_director' => 1,
                'project_director_stat' => 1,
                'project_director_view' => 1,
                'project_director_edit' => 1,
                'project_view_end_date' => 1,
            ];
        }

        $isTeamLeader = false;
        if (!$isAdmin) {
            $teamLeaderCheck = $this->fetchOne(
                "SELECT COUNT(*) as count FROM " . DB_PREFIX . "team_members " .
                "WHERE user_id = " . intval($currentUserIdNumber) . " AND leader = 1"
            );
            $isTeamLeader = ($teamLeaderCheck && $teamLeaderCheck['count'] > 0);
        }

        $isCreator = false;
        if (!$isAdmin && isset($project['created_by']) && isset($_SESSION['userid'])) {
            $isCreator = strval($project['created_by']) === strval($_SESSION['userid']);
        }

        $isInDepartment = false;
        if (!$isAdmin && $departmentCheck) {
            $isInDepartment = true;
        }

        $isProjectDirector = false;
        if ($departmentCheck) {
            $isProjectDirector = (
                ($departmentCheck['project_director'] ?? 0) == 1
                || ($departmentCheck['project_director_stat'] ?? 0) == 1
                || ($departmentCheck['project_director_view'] ?? 0) == 1
                || ($departmentCheck['project_director_edit'] ?? 0) == 1
            );
        }

        return [
            'is_team_leader' => $isTeamLeader,
            'is_member' => $isAdmin || $isProjectManager || $isDepartmentManager || $isProjectDirector || $isMember || $isCreator,
            'is_director' => $isAdmin || $isProjectManager || $isDepartmentManager || $isProjectDirector,
            'can_manage_project' => $isAdmin || $isProjectManager || $isDepartmentManager,
            'can_manage_department' => $isAdmin || $isDepartmentManager,
            'is_creator' => $isCreator,
            'is_in_department' => $isInDepartment,
            'rule' => $departmentCheck
        ];
    }

    function getPermission() {
        $projectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
        return $this->resolveProjectTaskPermissions($projectId);
    }

    function updateOrder() {
        $taskIds = json_decode($_POST['task_ids'], true);
        $projectId = $_POST['project_id'];
        $draggedTaskId = isset($_POST['dragged_task_id']) ? intval($_POST['dragged_task_id']) : null;
        $newParentId = isset($_POST['new_parent_id']) ? (empty($_POST['new_parent_id']) ? null : intval($_POST['new_parent_id'])) : null;
        
        // Update positions
        $position = 0;
        foreach ($taskIds as $taskId) {
            $this->query_update(
                ['position' => $position],
                ['id' => $taskId, 'project_id' => $projectId]
            );
            $position++;
        }
        
        // Update parent_id for the dragged task if provided
        if ($draggedTaskId && $newParentId !== null) {
            // Check for circular reference
            if ($newParentId && $this->isDescendant($newParentId, $draggedTaskId)) {
                return [
                    'success' => false,
                    'message' => '循環参照を防ぐため、この操作は許可されていません'
                ];
            }
            
            // Update the parent_id
            $data = [
                'parent_id' => $newParentId,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $this->query_update($data, ['id' => $draggedTaskId]);
        }
        
        return [
            'success' => true,
            'message' => 'タスク順序が更新されました'
        ];
    }

    function setParent() {
        $taskId = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $parentId = isset($_POST['parent_id']) ? (empty($_POST['parent_id']) ? null : intval($_POST['parent_id'])) : null;
        
        if (!$taskId) {
            return [
                'success' => false,
                'message' => 'タスクIDが指定されていません'
            ];
        }
        
        // Get current task info
        $task = $this->getById($taskId);
        if (!$task) {
            return [
                'success' => false,
                'message' => 'タスクが見つかりません'
            ];
        }
        
        // If setting a parent, check for circular reference
        if ($parentId) {
            if ($taskId == $parentId) {
                return [
                    'success' => false,
                    'message' => '自分自身を親タスクに設定することはできません'
                ];
            }
            
            // Check if parent exists and is in the same project
            $parentTask = $this->getById($parentId);
            if (!$parentTask) {
                return [
                    'success' => false,
                    'message' => '親タスクが見つかりません'
                ];
            }
            
            if ($parentTask['project_id'] != $task['project_id']) {
                return [
                    'success' => false,
                    'message' => '異なるプロジェクトのタスクを親に設定することはできません'
                ];
            }
            
            // Check for circular reference (parent would become child of current task)
            if ($this->isDescendant($parentId, $taskId)) {
                return [
                    'success' => false,
                    'message' => '循環参照を防ぐため、この操作は許可されていません'
                ];
            }
        }
        
        // Update the task's parent_id
        $data = [
            'parent_id' => $parentId,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $result = $this->query_update($data, ['id' => $taskId]);
        
        if ($result) {
            // Update parent task progress if setting a parent
            // if ($parentId) {
            //     $this->updateParentTaskProgress($parentId);
            // }
            
            // // Update project progress
            // $this->updateProjectProgress($task['project_id']);
            
            return [
                'success' => true,
                'message' => $parentId ? 'サブタスクが作成されました' : 'サブタスクが解除されました'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'タスクの更新に失敗しました'
            ];
        }
    }
    
    private function isDescendant($taskId, $potentialAncestorId) {
        // Check if taskId is a descendant of potentialAncestorId using iterative approach
        $currentId = $taskId;
        $maxDepth = 10; // Prevent infinite loops
        $depth = 0;
        
        while ($currentId && $depth < $maxDepth) {
            $query = sprintf(
                "SELECT parent_id FROM {$this->table} WHERE id = %d",
                intval($currentId)
            );
            
            $result = $this->fetchOne($query);
            if (!$result || !$result['parent_id']) {
                break; // No parent found
            }
            
            $currentId = $result['parent_id'];
            $depth++;
            
            // Check if we found the potential ancestor
            if ($currentId == $potentialAncestorId) {
                return true;
            }
        }
        
        return false;
    }

    function updateTaskDate() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : null;
        $due_date = isset($_POST['due_date']) ? $_POST['due_date'] : null;
        $progress = isset($_POST['progress']) ? intval($_POST['progress']) : null;
        
        if (!$id) {
            return [
                'success' => false,
                'message' => 'タスクIDが指定されていません'
            ];
        }
        
        $data = [
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($start_date !== null) {
            $data['start_date'] = $this->normalize_datetime_with_default($start_date, '09:00');
        }
        if ($due_date !== null) {
            $data['due_date'] = $this->normalize_datetime_with_default($due_date, '18:00');
        }
        if ($progress !== null) {
            $data['progress'] = $progress;
        }
        
        $result = $this->query_update($data, ['id' => $id]);
        
        if ($result) {
            return [
                'success' => true,
                'message' => 'タスクが更新されました'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'タスクの更新に失敗しました'
            ];
        }
    }

    function updateTaskParent() {
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $parent_id = isset($_POST['parent_id']) ? (empty($_POST['parent_id']) ? null : intval($_POST['parent_id'])) : null;
        
        if (!$task_id) {
            return [
                'success' => false,
                'message' => 'タスクIDが指定されていません'
            ];
        }
        
        // Get current task info
        $task = $this->getById($task_id);
        if (!$task) {
            return [
                'success' => false,
                'message' => 'タスクが見つかりません'
            ];
        }
        
        // If setting a parent, check for circular reference
        if ($parent_id) {
            if ($task_id == $parent_id) {
                return [
                    'success' => false,
                    'message' => '自分自身を親タスクに設定することはできません'
                ];
            }
            
            // Check if parent exists and is in the same project
            $parentTask = $this->getById($parent_id);
            if (!$parentTask) {
                return [
                    'success' => false,
                    'message' => '親タスクが見つかりません'
                ];
            }
            
            if ($parentTask['project_id'] != $task['project_id']) {
                return [
                    'success' => false,
                    'message' => '異なるプロジェクトのタスクを親に設定することはできません'
                ];
            }
            
            // Check for circular reference (parent would become child of current task)
            if ($this->isDescendant($parent_id, $task_id)) {
                return [
                    'success' => false,
                    'message' => '循環参照を防ぐため、この操作は許可されていません'
                ];
            }
        }
        
        // Update the task's parent_id
        $data = [
            'parent_id' => $parent_id,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $result = $this->query_update($data, ['id' => $task_id]);
        
        if ($result) {
            return [
                'success' => true,
                'message' => $parent_id ? 'サブタスクが作成されました' : 'サブタスクが解除されました'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'タスクの更新に失敗しました'
            ];
        }
    }

    // Lấy tất cả links của project
    function getLinksByProject($params = null) {
        $project_id = is_array($params) ? intval($params['project_id']) : intval($params);
        if (!$project_id) return [];
        $query = "SELECT l.* FROM " . DB_PREFIX . "task_links l
                  WHERE l.project_id = $project_id";
        return $this->fetchAll($query);
    }

    // Thêm link
    function addTaskLink() {
        $project_id = intval($_POST['project_id']);
        $source = intval($_POST['source_task_id']);
        $target = intval($_POST['target_task_id']);
        $type = isset($_POST['link_type']) ? $_POST['link_type'] : '0';
        if (!$source || !$target) return ['success' => false, 'message' => 'Thiếu thông tin'];
        $data = [
            'source_task_id' => $source,
            'project_id' => $project_id,
            'target_task_id' => $target,
            'link_type' => $type,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $this->table = DB_PREFIX . 'task_links';
        $result = $this->query_insert($data);
        $this->table = DB_PREFIX . 'tasks';
        return $result ? ['success' => true] : ['success' => false];
    }

    // Xóa link
    function deleteTaskLink() {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'message' => 'No link id'];
        $this->table = DB_PREFIX . 'task_links';
        $result = $this->query_delete(['id' => $id]);
        $this->table = DB_PREFIX . 'tasks';
        if ($result) {
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'message' => 'Delete failed'];
        }
    }

    // Ghi log hành động task
    private function logTaskAction($task_id, $action, $note = '', $value1 = '', $value2 = '') {
        $user_id = $_SESSION['userid'] ?? '';
        $username = $_SESSION['realname'] ?? '';
        $data = [
            'task_id' => $task_id,
            'user_id' => $user_id,
            'username' => $username,
            'action' => $action,
            'note' => $note,
            'value1' => $value1,
            'value2' => $value2,
            'time' => date('Y-m-d H:i:s')
        ];
        $this->table = DB_PREFIX . 'task_logs';
        $this->query_insert($data);
        $this->table = DB_PREFIX . 'tasks'; // reset lại table
    }

    // Lấy lịch sử hành động của task
    function getLogs($params = null) {
        $task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;
        if (!$task_id) return [];

        $query = sprintf(
            "SELECT l.*, u.realname, u.user_image FROM " . DB_PREFIX . "task_logs l
            LEFT JOIN " . DB_PREFIX . "user u ON l.user_id = u.userid
            WHERE l.task_id = %d ORDER BY l.time DESC",
            $task_id
        );
        $logs = $this->fetchAll($query);
        return $logs;
    }
    
    function toggleLike() {
        $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
        $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : '';
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        $name = isset($_POST['name']) ? $_POST['name'] : '';
        
        if (!$comment_id || !$user_id) {
            return ['success' => false, 'message' => 'Invalid parameters'];
        }
        
        $this->table = DB_PREFIX . 'comment_likes';
        
        if ($action === 'like') {
            // Check if already liked
            $existing = $this->fetchOne(sprintf(
                "SELECT * FROM %s WHERE comment_id = %d AND user_id = '%s'",
                $this->table,
                $comment_id,
                $this->quote($user_id)
            ));
            
            if (!$existing) {
                $data = [
                    'comment_id' => $comment_id,
                    'user_id' => $user_id,
                    'name' => $name ?: $_SESSION['realname'] ?? 'Unknown',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $this->query_insert($data);
            }
        } else {
            // Unlike
            $result = $this->query(sprintf(
                "DELETE FROM " . DB_PREFIX . "comment_likes 
                 WHERE comment_id = %d AND user_id = '%s'",
                $comment_id, $user_id
            ));
        }
        
        // Get updated like count and names
        $query = sprintf(
            "SELECT COUNT(*) as like_count,
            GROUP_CONCAT(name) as liked_by_names
            FROM " . DB_PREFIX . "comment_likes
            WHERE comment_id = %d",
            $comment_id
        );
        
        $result = $this->fetchOne($query);
        $this->table = DB_PREFIX . 'tasks'; // Reset table
        
        return [
            'success' => true,
            'like_count' => intval($result['like_count']),
            'liked_by_names' => $result['liked_by_names'] ? explode(',', $result['liked_by_names']) : []
        ];
    }

    /**
     * Toggle like/dislike reaction for a task (with optional note)
     */
    function toggleTaskReaction() {
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $type = isset($_POST['type']) ? $_POST['type'] : '';
        $note = isset($_POST['note']) ? trim($_POST['note']) : '';
        $selected_reasons = isset($_POST['selected_reasons']) ? trim($_POST['selected_reasons']) : '';
        $custom_note = isset($_POST['custom_note']) ? trim($_POST['custom_note']) : '';
        $user_id = isset($_SESSION['userid']) ? $_SESSION['userid'] : '';
        
        if (!$task_id || !$user_id || !in_array($type, ['like', 'dislike'])) {
            return ['success' => false, 'message' => 'Invalid parameters'];
        }
        
        $this->table = DB_PREFIX . 'task_reactions';
        
        // Check existing reaction for this user & task
        $existing = $this->fetchOne(sprintf(
            "SELECT * FROM %s WHERE task_id = %d AND user_id = '%s'",
            $this->table,
            $task_id,
            $this->quote($user_id)
        ));
        
        $now = date('Y-m-d H:i:s');
        $is_delete = isset($_POST['delete']) && $_POST['delete'] == '1';
        
        // Prepare note data: store structured data as JSON if selected_reasons or custom_note provided
        $noteData = $note; // Default to combined note for backward compatibility
        if ($selected_reasons || $custom_note) {
            $selectedReasonsArray = $selected_reasons ? explode(',', $selected_reasons) : [];
            $selectedReasonsArray = array_filter(array_map('trim', $selectedReasonsArray));
            $noteData = json_encode([
                'selected_reasons' => $selectedReasonsArray,
                'custom_note' => $custom_note,
                'combined' => $note // Keep combined text for display
            ], JSON_UNESCAPED_UNICODE);
        }
        
        if ($is_delete && $existing) {
            // Delete reaction
            $this->query(sprintf(
                "DELETE FROM %s WHERE id = %d",
                $this->table,
                $existing['id']
            ));
        } else if ($existing) {
            // Update type & note
            $data = [
                'type' => $type,
                'note' => $noteData,
                'updated_at' => $now
            ];
            $this->query_update($data, ['id' => $existing['id']]);
        } else if (!$is_delete) {
            // Insert new reaction
            $data = [
                'task_id' => $task_id,
                'user_id' => $user_id,
                'type' => $type,
                'note' => $noteData,
                'created_at' => $now,
                'updated_at' => $now
            ];
            $this->query_insert($data);
        }
        
        // Recalculate like/dislike counts
        $countQuery = sprintf(
            "SELECT 
                SUM(CASE WHEN type = 'like' THEN 1 ELSE 0 END) as like_count,
                SUM(CASE WHEN type = 'dislike' THEN 1 ELSE 0 END) as dislike_count
            FROM " . DB_PREFIX . "task_reactions
            WHERE task_id = %d",
            $task_id
        );
        $counts = $this->fetchOne($countQuery);
        
        // Get current user's reaction after update/delete
        $current_reaction = null;
        if (!$is_delete) {
            $current_reaction = $type;
        } else {
            $current_reaction = null;
        }
        
        $this->table = DB_PREFIX . 'tasks'; // Reset table
        
        return [
            'success' => true,
            'task_id' => $task_id,
            'like_count' => intval($counts['like_count'] ?? 0),
            'dislike_count' => intval($counts['dislike_count'] ?? 0),
            'current_user_reaction' => $current_reaction
        ];
    }
    
    /**
     * Get task reaction details (for editing existing reaction)
     */
    function getTaskReaction() {
        $task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;
        $user_id = isset($_SESSION['userid']) ? $_SESSION['userid'] : '';
        
        if (!$task_id || !$user_id) {
            return ['success' => false, 'message' => 'Invalid parameters'];
        }
        
        $this->table = DB_PREFIX . 'task_reactions';
        $reaction = $this->fetchOne(sprintf(
            "SELECT * FROM %s WHERE task_id = %d AND user_id = '%s'",
            $this->table,
            $task_id,
            $this->quote($user_id)
        ));
        
        $this->table = DB_PREFIX . 'tasks'; // Reset table
        
        if ($reaction) {
            // Use null coalescing instead of logical OR to avoid boolean casting
            $note = isset($reaction['note']) ? $reaction['note'] : '';
            $selected_reasons = [];
            $custom_note = '';
            
            // Try to parse as JSON (new format)
            $decoded = json_decode($note, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $selected_reasons = isset($decoded['selected_reasons']) ? $decoded['selected_reasons'] : [];
                $custom_note = isset($decoded['custom_note']) ? $decoded['custom_note'] : '';
                // Use combined text if available, otherwise use custom_note
                $note = isset($decoded['combined']) ? $decoded['combined'] : $custom_note;
            } else {
                // Old format: plain text note
                $custom_note = $note;
            }
            
            return [
                'success' => true,
                'type' => $reaction['type'],
                'note' => $note,
                'selected_reasons' => $selected_reasons,
                'custom_note' => $custom_note
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Reaction not found'
        ];
    }
    
    // Detect mentions and send notifications
    private function sendMentionNotifications($projectId, $content, $commentUserId, $commentId, $taskId) {
        try {
            require_once(DIR_MODEL . 'NotificationService.php');
            $notiService = new NotificationService();

            $notiService->sendTaskCommentNotification($taskId, $commentId);

            // Extract mentioned users from content
            $mentionedUsers = $this->extractMentions($content);
            
            if (empty($mentionedUsers)) {
                return; // No mentions found
            }
            
            // Get project info for notification
            $project = $this->fetchOne(sprintf(
                "SELECT * FROM " . DB_PREFIX . "projects WHERE id = %d",
                intval($projectId)
            ));
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
                    'event' => 'task_mention',
                    'title' => 'タスクでメンションされました',
                    'message' => sprintf('%sさんがタスクでメンションしました', 
                        $_SESSION['realname']
                    ),
                    'data' => [
                        'project_id' => $projectId,
                        'project_name' => $project['name'],
                        'comment_id' => $commentId,
                        'comment_content' => $content,
                        'commenter_id' => $commentUserId,
                        'commenter_name' => $_SESSION['realname'],
                        'avatar' => $this->getUserImage(),
                        'url' => "/project/task.php?id=$projectId&task_id=$taskId#comment-$commentId"
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
    
    private function getUserImage() {
        return $_SESSION['user_image'] ?? '';
    }

    // Sửa comment task
    function updateComment() {
        $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
        $content = isset($_POST['content']) ? $_POST['content'] : '';
        $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : '';
        if (!$comment_id || !$user_id) {
            return ['success' => false, 'message' => 'Invalid parameters'];
        }
        $this->table = DB_PREFIX . 'comments';
        $comment = $this->fetchOne("SELECT * FROM " . DB_PREFIX . "comments WHERE id = $comment_id");
        if (!$comment) {
            $this->table = DB_PREFIX . 'tasks';
            return ['success' => false, 'message' => 'Comment not found'];
        }
        // Chỉ cho phép sửa nếu là chủ comment hoặc admin
        if ($_SESSION['authority'] != 'administrator' && $comment['user_id'] != $user_id) {
            $this->table = DB_PREFIX . 'tasks';
            return ['success' => false, 'message' => 'Permission denied'];
        }
        $data = [
            'content' => $content,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $result = $this->query_update($data, ['id' => $comment_id]);
        $this->table = DB_PREFIX . 'tasks';
        return $result ? ['success' => true] : ['success' => false, 'message' => 'Update failed'];
    }

    // Xóa comment task
    function deleteComment() {
        $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
        $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : '';
        if (!$comment_id || !$user_id) {
            return ['success' => false, 'message' => 'Invalid parameters'];
        }
        $this->table = DB_PREFIX . 'comments';
        $comment = $this->fetchOne("SELECT * FROM " . DB_PREFIX . "comments WHERE id = $comment_id");
        if (!$comment) {
            $this->table = DB_PREFIX . 'tasks';
            return ['success' => false, 'message' => 'Comment not found'];
        }
        // Chỉ cho phép xóa nếu là chủ comment hoặc admin
        if ($_SESSION['authority'] != 'administrator' && $comment['user_id'] != $user_id) {
            $this->table = DB_PREFIX . 'tasks';
            return ['success' => false, 'message' => 'Permission denied'];
        }
        $result = $this->query_delete(['id' => $comment_id]);
        $this->table = DB_PREFIX . 'tasks';
        return $result ? ['success' => true] : ['success' => false, 'message' => 'Delete failed'];
    }

   
    // API: Lấy số comment chưa đọc của 1 task cho user hiện tại dựa vào thời gian truy cập
    function getTaskUnreadCommentCount($task_id = null) {
        if(!$task_id) {
            $task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;
        }
        $user_id = $_SESSION['userid'] ?? '';
        if (!$task_id || !$user_id) return ['unread_count' => 0];
        // Lấy thời gian đã đọc gần nhất
        $sqlRead = sprintf(
            "SELECT read_at FROM %scomment_reads WHERE task_id = %d AND user_id = '%s' ORDER BY read_at DESC LIMIT 1",
            DB_PREFIX,
            $task_id,
            $this->quote($user_id)
        );
        $rowRead = $this->fetchOne($sqlRead);
        $read_at = $rowRead && !empty($rowRead['read_at']) ? $rowRead['read_at'] : null;
        if ($read_at) {
            // Đếm số comment mới hơn thời gian đã đọc
            $sql = "SELECT COUNT(*) as unread_count FROM " . DB_PREFIX . "comments WHERE user_id != '" . $this->quote($user_id) . "' AND task_id = $task_id AND created_at > '" . $this->quote($read_at) . "'";
        } else {
            // Nếu chưa từng đọc, trả về tổng số comment
            $sql = "SELECT COUNT(*) as unread_count FROM " . DB_PREFIX . "comments WHERE user_id != '" . $this->quote($user_id) . "' AND task_id = $task_id";
        }
        $row = $this->fetchOne($sql);
        return ['unread_count' => intval($row['unread_count'] ?? 0)];
    }

    // Khi truy cập task, update hoặc insert read_at = NOW()
    function markTaskCommentsAsRead() {
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $user_id = $_SESSION['userid'] ?? '';
        if (!$task_id || !$user_id) return ['success' => false, 'message' => 'Invalid parameters'];
        $now = date('Y-m-d H:i:s');
        $table = DB_PREFIX . 'comment_reads';
        $sql = sprintf(
            "SELECT id FROM %s WHERE task_id = %d AND user_id = '%s'",
            $table,
            $task_id,
            $this->quote($user_id)
        );
        $row = $this->fetchOne($sql);
        if ($row && isset($row['id'])) {
            $update = sprintf(
                "UPDATE %s SET read_at = '%s' WHERE id = %d",
                $table,
                $now,
                intval($row['id'])
            );
            $this->query($update);
        } else {
            $insert = sprintf(
                "INSERT INTO %s (task_id, user_id, read_at) VALUES (%d, '%s', '%s')",
                $table,
                $task_id,
                $this->quote($user_id),
                $now
            );
            $this->query($insert);
        }
        return ['success' => true];
    }

    /**
     * Send task notification
     */
    function sendTaskNotification($params = null) {
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
                'data' => [],
                'url' => '',
                'priority' => 'normal',
                'type' => 'task'
            ];
            
            $params = array_merge($defaultParams, $params);
            
            // Get target users
            $targetUserIds = [];
            
            // Direct user IDs
            if (!empty($params['user_ids'])) {
                $targetUserIds = array_merge($targetUserIds, $params['user_ids']);
            }
            
            // Remove duplicates and current user
            $targetUserIds = array_unique($targetUserIds);
            $targetUserIds = array_diff($targetUserIds, [$_SESSION['userid']]);
            
            if (empty($targetUserIds)) {
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
            
            return $result ? true : false;
            
        } catch (Exception $e) {
            error_log('Error sending task notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user realname for notifications
     */
    function getUserRealname() {
        if (isset($_SESSION['lastname']) && $_SESSION['lastname'] != '') {
            return $_SESSION['lastname'] . 'さん';
        }
        return (isset($_SESSION['realname']) ? $_SESSION['realname'] : 'Unknown') . 'さん';
    }

    /**
     * Get user image URL for notifications
     */
    private function getUserImageUrl() {
        $userImage = $this->getUserImage();
        if ($userImage && $userImage != '') {
            return '/assets/upload/avatar/' . $userImage;
        }
        return '/assets/img/avatars/1.png';
    }

    /**
     * Convert user IDs (numeric id) to userid (string)
     */
    private function convertIdsToUserIds($ids) {
        if (empty($ids)) {
            return [];
        }
        
        // Convert to array if string
        if (is_string($ids)) {
            $ids = array_filter(array_map('trim', explode(',', $ids)));
        }
        
        if (empty($ids)) {
            return [];
        }
        
        // Convert to integers and filter
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids);
        
        if (empty($ids)) {
            return [];
        }
        
        // Query user table to get userid from id
        $placeholders = str_repeat('%d,', count($ids) - 1) . '%d';
        $query = sprintf(
            "SELECT userid FROM " . DB_PREFIX . "user WHERE id IN ($placeholders)",
            ...$ids
        );
        
        $users = $this->fetchAll($query);
        
        if (empty($users)) {
            return [];
        }
        
        return array_column($users, 'userid');
    }

    /**
     * Notify users when a task is created and assigned
     */
    function notifyTaskCreated($taskId, $taskTitle, $projectId, $projectNumber, $projectName, $assignedUserIds) {
        if (empty($assignedUserIds)) {
            return false;
        }
        
        // Convert numeric IDs to userid strings
        $userIds = $this->convertIdsToUserIds($assignedUserIds);
        
        if (empty($userIds)) {
            return false;
        }

        $titleJa = '#'.$projectId.': タスクが作成されました';
        $messageJa = sprintf('%sがあなたにタスク#%s「%s」を割り当てました', $this->getUserRealname(), $taskId, $taskTitle);
        $titleVi = '#'.$projectId.': Task đã được tạo';
        $messageVi = sprintf('%s đã gán task #%s「%s」cho bạn', $this->getUserRealname(), $taskId, $taskTitle);
        $params = [
            'event' => 'task_created',
            'title' => $titleJa,
            'message' => $messageJa,
            'project_id' => $projectId,
            'task_id' => $taskId,
            'user_ids' => $userIds,
            'data' => [
                'task_title' => $taskTitle,
                'project_name' => $projectName,
                'project_number' => $projectNumber,
                'avatar' => $this->getUserImageUrl(),
                'action' => 'task_created',
                'url' => "/project/task.php?project_id=$projectId",
                'title_ja' => $titleJa,
                'message_ja' => $messageJa,
                'title_vi' => $titleVi,
                'message_vi' => $messageVi,
            ],
            'type' => 'task',
            'priority' => 'normal'
        ];
        
        return $this->sendTaskNotification($params);
    }

    /**
     * Notify users when task assignees are changed
     */
    function notifyTaskAssigneeChanged($taskId, $taskTitle, $projectId, $projectNumber, $projectName, $newAssignedUserIds, $oldAssignedUserIds = []) {
        // Convert to arrays if strings
        if (is_string($newAssignedUserIds)) {
            $newAssignedUserIds = array_filter(array_map('trim', explode(',', $newAssignedUserIds)));
        }
        if (is_string($oldAssignedUserIds)) {
            $oldAssignedUserIds = array_filter(array_map('trim', explode(',', $oldAssignedUserIds)));
        }
        
        // Find newly assigned IDs (numeric IDs in new but not in old)
        $newlyAssignedIds = array_diff($newAssignedUserIds, $oldAssignedUserIds);
        
        if (empty($newlyAssignedIds)) {
            return false;
        }
        
        // Convert only newly assigned IDs to userid strings
        $newlyAssigned = $this->convertIdsToUserIds($newlyAssignedIds);
        
        if (empty($newlyAssigned)) {
            return false;
        }

        $taskTitle = strlen($taskTitle) > 15 ? substr($taskTitle, 0, 15) . '...' : $taskTitle;
        $projectName = strlen($projectName) > 15 ? substr($projectName, 0, 15) . '...' : $projectName;
        $titleJa = '#'.$projectNumber.': タスクが割り当てられました';
        $messageJa = sprintf('%sがあなたにタスク#%s「%s」を割り当てました', $this->getUserRealname(), $taskId, $taskTitle);
        $titleVi = '#'.$projectNumber.': Task đã được gán';
        $messageVi = sprintf('%s đã gán task #%s「%s」cho bạn', $this->getUserRealname(), $taskId, $taskTitle);
        $params = [
            'event' => 'task_assigned',
            'title' => $titleJa,
            'message' => $messageJa,
            'project_id' => $projectId,
            'task_id' => $taskId,
            'user_ids' => array_values($newlyAssigned),
            'data' => [
                'task_title' => $taskTitle,
                'project_name' => $projectName,
                'project_number' => $projectNumber,
                'avatar' => $this->getUserImageUrl(),
                'action' => 'task_assigned',
                'url' => "/project/task.php?project_id=$projectId",
                'title_ja' => $titleJa,
                'message_ja' => $messageJa,
                'title_vi' => $titleVi,
                'message_vi' => $messageVi,
            ],
            'type' => 'task',
            'priority' => 'normal'
        ];

        //notify removeed 
        $removedAssignees = array_diff($oldAssignedUserIds, $newAssignedUserIds);
        if (!empty($removedAssignees)) {
            $removedAssignees = $this->convertIdsToUserIds($removedAssignees);
            $this->notifyTaskAssigneeRemoved($taskId, $taskTitle, $projectId, $projectNumber, $projectName, $removedAssignees);
        }
        
        return $this->sendTaskNotification($params);
    }   

    function notifyTaskAssigneeRemoved($taskId, $taskTitle, $projectId, $projectNumber, $projectName, $removedAssignees) {
        $taskTitle = strlen($taskTitle) > 15 ? substr($taskTitle, 0, 15) . '...' : $taskTitle;
        $titleJa = '#'.$projectId.': タスクが割り当て解除されました';
        $messageJa = sprintf('%sがあなたのタスク「%s」を割り当て解除しました', $this->getUserRealname(), $taskTitle);
        $titleVi = '#'.$projectId.': Task đã hủy gán';
        $messageVi = sprintf('%s đã hủy gán task「%s」', $this->getUserRealname(), $taskTitle);
        $params = [
            'event' => 'task_assignee_removed',
            'title' => $titleJa,
            'message' => $messageJa,
            'project_id' => $projectId,
            'task_id' => $taskId,
            'user_ids' => $removedAssignees,
            'data' => [
                'task_title' => $taskTitle,
                'project_name' => $projectName,
                'project_number' => $projectNumber,
                'avatar' => $this->getUserImageUrl(),
                'action' => 'task_assignee_removed',
                'url' => "/project/task.php?project_id=$projectId",
                'title_ja' => $titleJa,
                'message_ja' => $messageJa,
                'title_vi' => $titleVi,
                'message_vi' => $messageVi,
            ],
            'type' => 'task',
            'priority' => 'normal'
        ];
        return $this->sendTaskNotification($params);
    }

    /**
     * Overview of all tasks grouped by department / team / user
     *
     * Returns:
     * - departments: [{id, name}]
     * - teams:       [{id, name, department_id}]
     * - users:       [{id, userid, realname, department_id, department_name, teams:[{id,name}]}]
     * - tasks:       [{
     *                    id, title, status, priority, project_id, project_number, project_name,
     *                    department_id, department_name, assigned_to_ids:[]
     *                 }]
     * - unassigned_users: same structure as users (no tasks assigned)
     */
    function listOverview($params = null) {
        // Optional filters - support both $params array and $_GET
        if (is_array($params)) {
            $department_id     = isset($params['department_id']) ? intval($params['department_id']) : 0;
            $team_id           = isset($params['team_id']) ? intval($params['team_id']) : 0;
            $user_id           = isset($params['user_id']) ? intval($params['user_id']) : 0;
            $exclude_completed = isset($params['exclude_completed']) ? intval($params['exclude_completed']) : 0;
            $created_month     = isset($params['created_month']) ? trim((string)$params['created_month']) : '';
        } else {
            $department_id     = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
            $team_id           = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;
            $user_id           = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
            $exclude_completed = isset($_GET['exclude_completed']) ? intval($_GET['exclude_completed']) : 0;
            $created_month     = isset($_GET['created_month']) ? trim((string)$_GET['created_month']) : '';
        }

        // 1. Load departments
        // Hiển thị cùng thứ tự như department.php (Department::list): is_active = 1, ORDER BY id ASC
        $departments = $this->fetchAll(
            "SELECT id, name 
             FROM " . DB_PREFIX . "departments 
             WHERE is_active = 1
             ORDER BY id ASC"
        );

        // 2. Load teams
        $teamWhere = "WHERE t.is_active = 1";
        if ($department_id > 0) {
            $teamWhere .= " AND t.department_id = " . intval($department_id);
        }
        $teams = $this->fetchAll(
            "SELECT t.id, t.name, t.department_id 
             FROM " . DB_PREFIX . "team t
             $teamWhere
             ORDER BY t.department_id ASC, t.name ASC"
        );

        // 3. Load users with department & team info
        $userWhereArr = ["(u.is_suspend = 0 OR u.is_suspend IS NULL OR u.is_suspend = '')"];
        if ($department_id > 0) {
            $userWhereArr[] = "ud.department_id = " . intval($department_id);
        }
        if ($team_id > 0) {
            $userWhereArr[] = "tm.team_id = " . intval($team_id);
        }
        // Do not filter users dropdown by selected user_id (user_id only filters tasks).
        $userWhere = "WHERE " . implode(" AND ", $userWhereArr);

        $userRows = $this->fetchAll(
            "SELECT 
                u.id,
                u.userid,
                u.realname,
                u.user_image,
                ud.department_id,
                d.name AS department_name,
                tm.team_id,
                t.name AS team_name
             FROM " . DB_PREFIX . "user u
             LEFT JOIN " . DB_PREFIX . "user_department ud ON ud.userid = u.userid
             LEFT JOIN " . DB_PREFIX . "departments d ON ud.department_id = d.id
             LEFT JOIN " . DB_PREFIX . "team_members tm ON tm.user_id = u.id
             LEFT JOIN " . DB_PREFIX . "team t ON tm.team_id = t.id AND t.is_active = 1
             $userWhere
             ORDER BY u.id ASC"
        );

        // Aggregate user data (one row per user, with teams array)
        $users = [];
        foreach ($userRows as $row) {
            $uid = $row['id'];
            if (!isset($users[$uid])) {
                $users[$uid] = [
                    'id' => $uid,
                    'userid' => $row['userid'],
                    'realname' => $row['realname'],
                    'user_image' => isset($row['user_image']) ? $row['user_image'] : '',
                    'department_id' => $row['department_id'],
                    'department_name' => $row['department_name'],
                    'teams' => []
                ];
            }
            if (!empty($row['team_id'])) {
                // Avoid duplicate team entries
                $exists = false;
                foreach ($users[$uid]['teams'] as $t) {
                    if ($t['id'] == $row['team_id']) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $users[$uid]['teams'][] = [
                        'id' => $row['team_id'],
                        'name' => $row['team_name']
                    ];
                }
            }
        }

        // 4. Load tasks with project & department info
        $taskWhereArr = ["1=1"];
        if ($department_id > 0) {
            $taskWhereArr[] = "p.department_id = " . intval($department_id);
        }
        // Filter by user (internal user id in assigned_to CSV)
        if ($user_id > 0) {
            $taskWhereArr[] = "FIND_IN_SET(" . intval($user_id) . ", t.assigned_to) > 0";
        }
        // Loại bỏ completed & cancelled từ phía DB nếu được yêu cầu
        if ($exclude_completed) {
            $taskWhereArr[] = "t.status NOT IN ('completed','cancelled')";
        }
        if ($created_month !== '' && preg_match('/^\d{4}-\d{2}$/', $created_month)) {
            $taskWhereArr[] = "DATE_FORMAT(t.created_at, '%Y-%m') = '" . $this->quote($created_month) . "'";
        }
        $taskWhere = "WHERE " . implode(" AND ", $taskWhereArr);
        // We do not filter by team here because team is derived from users
        $taskRows = $this->fetchAll(
            "SELECT 
                t.id,
                t.project_id,
                t.title,
                t.status,
                t.priority,
                t.task_kind,
                t.drawing_count,
                t.estimated_hours,
                t.note,
                t.assigned_to,
                t.created_by,
                t.due_date,
                t.start_date,
                t.progress,
                p.id AS project_id,
                p.name AS project_name,
                pp.construction_number AS project_construction_number,
                p.department_id,
                p.end_date AS project_end_date,
                d.name AS department_name,
                u_creator.realname AS created_by_name,
                u_creator.user_image AS created_by_user_image
             FROM " . DB_PREFIX . "tasks t
             LEFT JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id
             LEFT JOIN " . DB_PREFIX . "parent_projects pp ON p.parent_project_id = pp.id
             LEFT JOIN " . DB_PREFIX . "departments d ON p.department_id = d.id
             LEFT JOIN " . DB_PREFIX . "user u_creator ON t.created_by = u_creator.id
             $taskWhere
             ORDER BY p.department_id ASC, t.project_id ASC, t.position ASC, t.created_at DESC"
        );

        $taskIds = array_map(function($r) { return $r['id']; }, $taskRows);
        $acknowledgementsMap = [];
        if (!empty($taskIds)) {
            $ackQuery = sprintf(
                "SELECT task_id, user_id, acknowledged, acknowledged_at FROM " . DB_PREFIX . "task_assignees WHERE task_id IN (%s)",
                implode(',', array_map('intval', $taskIds))
            );
            $ackRows = $this->fetchAll($ackQuery);
            foreach ($ackRows as $ack) {
                $tid = (int)$ack['task_id'];
                $uid = (int)$ack['user_id'];
                if (!isset($acknowledgementsMap[$tid])) {
                    $acknowledgementsMap[$tid] = [];
                }
                $acknowledgementsMap[$tid][(string)$uid] = [
                    'acknowledged' => (int)$ack['acknowledged'],
                    'acknowledged_at' => $ack['acknowledged_at']
                ];
            }
        }

        $tasks = [];
        $assignedUserIdSet = [];
        foreach ($taskRows as $row) {
            // Parse assigned_to (internal user IDs, comma separated)
            $assignedIds = [];
            if (!empty($row['assigned_to'])) {
                $parts = explode(',', $row['assigned_to']);
                foreach ($parts as $part) {
                    $id = intval(trim($part));
                    if ($id > 0) {
                        $assignedIds[] = $id;
                        $assignedUserIdSet[$id] = true;
                    }
                }
            }

            $projectIdForDrawing = isset($row['project_id']) ? intval($row['project_id']) : 0;
            $taskKindForDrawing = isset($row['task_kind']) ? $row['task_kind'] : '';

            $tasks[] = [
                'id' => $row['id'],
                'project_id' => $row['project_id'],
                'project_number' => isset($row['project_number']) ? $row['project_number'] : null,
                'project_name' => $row['project_name'],
                'project_construction_number' => isset($row['project_construction_number']) ? $row['project_construction_number'] : '',
                'title' => $row['title'],
                'status' => $row['status'],
                'priority' => $row['priority'],
                'task_kind' => isset($row['task_kind']) ? $row['task_kind'] : '',
                'drawing_count' => isset($row['drawing_count']) ? intval($row['drawing_count']) : 0,
                'can_edit_drawing' => $this->userCanEditTaskDrawing($projectIdForDrawing, $taskKindForDrawing),
                'estimated_hours' => isset($row['estimated_hours']) ? $this->normalize_estimated_hours($row['estimated_hours']) : 0,
                'note' => isset($row['note']) ? $row['note'] : '',
                'assigned_to' => isset($row['assigned_to']) ? $row['assigned_to'] : '',
                'due_date' => $row['due_date'],
                'start_date' => $row['start_date'],
                'progress' => $row['progress'],
                'department_id' => $row['department_id'],
                'department_name' => $row['department_name'],
                'project_end_date' => isset($row['project_end_date']) ? $row['project_end_date'] : null,
                'assigned_to_ids' => $assignedIds,
                'created_by' => isset($row['created_by']) ? $row['created_by'] : null,
                'created_by_name' => isset($row['created_by_name']) ? $row['created_by_name'] : null,
                'created_by_user_image' => isset($row['created_by_user_image']) ? $row['created_by_user_image'] : null,
                'acknowledgements' => $acknowledgementsMap[$row['id']] ?? []
            ];
        }

        $this->attachTimeEntryHours($tasks);
        $this->attachActiveTimerFlags($tasks);

        // 5. Determine unassigned users (no active tasks: exclude completed & cancelled)
        // Build set of users that have at least one active task
        $activeAssignedUserIdSet = [];
        $activeWhereArr = ["1=1", "t.status NOT IN ('completed','cancelled')"];
        if ($department_id > 0) {
            $activeWhereArr[] = "p.department_id = " . intval($department_id);
        }
        $activeWhere = "WHERE " . implode(" AND ", $activeWhereArr);
        $activeRows = $this->fetchAll(
            "SELECT t.assigned_to
             FROM " . DB_PREFIX . "tasks t
             LEFT JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id
             $activeWhere"
        );
        foreach ($activeRows as $row) {
            if (!empty($row['assigned_to'])) {
                $parts = explode(',', $row['assigned_to']);
                foreach ($parts as $part) {
                    $id = intval(trim($part));
                    if ($id > 0) {
                        $activeAssignedUserIdSet[$id] = true;
                    }
                }
            }
        }

        // Unassigned users: no active task assigned, and must belong to at least one team
        $unassigned_users = [];
        foreach ($users as $u) {
            $uid = $u['id'];
            if (!isset($activeAssignedUserIdSet[$uid])) {
                $hasTeam = !empty($u['teams']) && is_array($u['teams']);
                if ($hasTeam) {
                    $unassigned_users[] = $u;
                }
            }
        }

        // 6. Ensure users list includes all assignees and creators from tasks (for avatar/name display)
        $displayUserIds = $assignedUserIdSet;
        foreach ($taskRows as $row) {
            if (!empty($row['created_by'])) {
                $displayUserIds[(int)$row['created_by']] = true;
            }
        }
        $missingUserIds = array_diff(array_keys($displayUserIds), array_keys($users));
        if (!empty($missingUserIds)) {
            $placeholders = implode(',', array_map('intval', $missingUserIds));
            $extraRows = $this->fetchAll(
                "SELECT u.id, u.userid, u.realname, u.user_image,
                        (SELECT ud.department_id FROM " . DB_PREFIX . "user_department ud WHERE ud.userid = u.userid LIMIT 1) AS department_id,
                        (SELECT d.name FROM " . DB_PREFIX . "user_department ud2
                         LEFT JOIN " . DB_PREFIX . "departments d ON d.id = ud2.department_id
                         WHERE ud2.userid = u.userid LIMIT 1) AS department_name
                 FROM " . DB_PREFIX . "user u
                 WHERE u.id IN ($placeholders)"
            );
            foreach ($extraRows as $row) {
                $uid = (int)$row['id'];
                if (!isset($users[$uid])) {
                    $users[$uid] = [
                        'id' => $uid,
                        'userid' => $row['userid'],
                        'realname' => $row['realname'],
                        'user_image' => isset($row['user_image']) ? $row['user_image'] : '',
                        'department_id' => $row['department_id'],
                        'department_name' => $row['department_name'],
                        'teams' => []
                    ];
                }
            }
        }

        // Re-index users array (drop numeric keys)
        $usersList = array_values($users);

        return $this->appendActiveTimerTaskIds([
            'departments' => $departments,
            'teams' => $teams,
            'users' => $usersList,
            'tasks' => $tasks,
            'unassigned_users' => $unassigned_users
        ]);
    }

    /**
     * Weekly tasks with time_entries for one user (Mon-Sun, Asia/Tokyo).
     * Non-PM users are forced to their own userid.
     */
    function listWeeklyTasks() {
        $canSelectUser = !empty($_SESSION['isProjectManager']);
        $sessionNumericId = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
        $requestedUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        $targetNumericId = $canSelectUser && $requestedUserId > 0 ? $requestedUserId : $sessionNumericId;

        if ($targetNumericId <= 0) {
            return [
                'status' => 'error',
                'message' => 'ユーザーが指定されていません',
                'can_select_user' => $canSelectUser,
                'week_start' => null,
                'week_end' => null,
                'user' => null,
                'tasks' => []
            ];
        }

        $user = $this->fetchOne(sprintf(
            "SELECT id, userid, realname FROM %suser WHERE id = %d LIMIT 1",
            DB_PREFIX,
            $targetNumericId
        ));
        if (!$user || empty($user['userid'])) {
            return [
                'status' => 'error',
                'message' => 'ユーザーが見つかりません',
                'can_select_user' => $canSelectUser,
                'week_start' => null,
                'week_end' => null,
                'user' => null,
                'tasks' => []
            ];
        }

        $tz = new DateTimeZone('Asia/Tokyo');
        $weekStartParam = isset($_GET['week_start']) ? trim((string) $_GET['week_start']) : '';
        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStartParam)) {
                $monday = new DateTime($weekStartParam, $tz);
            } else {
                $monday = new DateTime('now', $tz);
            }
        } catch (Exception $e) {
            $monday = new DateTime('now', $tz);
        }
        $dow = intval($monday->format('N'));
        if ($dow !== 1) {
            $monday->modify('-' . ($dow - 1) . ' days');
        }
        $monday->setTime(0, 0, 0);
        $sunday = clone $monday;
        $sunday->modify('+6 days');
        $weekStart = $monday->format('Y-m-d');
        $weekEnd = $sunday->format('Y-m-d');

        $useridEsc = $this->quote($user['userid']);
        $userJoin = "CONVERT(u.userid USING utf8mb4) = CONVERT(te.user_id USING utf8mb4)";
        $weekStartQ = "'" . $this->quote($weekStart) . "'";
        $weekEndQ = "'" . $this->quote($weekEnd) . "'";

        // Tasks that have at least one entry for this user in the selected week.
        $weekTaskRows = $this->fetchAll(sprintf(
            "SELECT DISTINCT te.task_id
             FROM %stime_entries te
             WHERE te.user_id = '%s'
               AND (
                    (te.end_time IS NOT NULL AND DATE(te.end_time) BETWEEN %s AND %s)
                    OR (te.end_time IS NULL AND DATE(te.start_time) BETWEEN %s AND %s)
               )",
            DB_PREFIX,
            $useridEsc,
            $weekStartQ,
            $weekEndQ,
            $weekStartQ,
            $weekEndQ
        ));

        // Also include tasks with no time_entries at all if their due_date is in this week.
        $dueOnlyTaskRows = $this->fetchAll(sprintf(
            "SELECT DISTINCT t.id AS task_id
             FROM %stasks t
             LEFT JOIN %stask_assignees ta ON ta.task_id = t.id
             WHERE t.due_date IS NOT NULL
               AND DATE(t.due_date) BETWEEN %s AND %s
               AND (
                    ta.user_id = %d
                    OR FIND_IN_SET('%d', COALESCE(t.assigned_to, '')) > 0
               )
               AND NOT EXISTS (
                    SELECT 1
                    FROM %stime_entries te2
                    WHERE te2.task_id = t.id
               )",
            DB_PREFIX,
            DB_PREFIX,
            $weekStartQ,
            $weekEndQ,
            $targetNumericId,
            $targetNumericId,
            DB_PREFIX
        ));

        $taskIds = [];
        foreach ($weekTaskRows as $row) {
            $tid = intval($row['task_id'] ?? 0);
            if ($tid > 0) {
                $taskIds[$tid] = $tid;
            }
        }
        foreach ($dueOnlyTaskRows as $row) {
            $tid = intval($row['task_id'] ?? 0);
            if ($tid > 0) {
                $taskIds[$tid] = $tid;
            }
        }
        $taskIds = array_values($taskIds);

        if (empty($taskIds)) {
            return [
                'status' => 'success',
                'can_select_user' => $canSelectUser,
                'week_start' => $weekStart,
                'week_end' => $weekEnd,
                'user' => [
                    'id' => intval($user['id']),
                    'userid' => $user['userid'],
                    'realname' => $user['realname']
                ],
                'tasks' => []
            ];
        }

        $idList = implode(',', array_map('intval', $taskIds));

        $taskRows = $this->fetchAll(sprintf(
            "SELECT
                t.id,
                t.title,
                t.status,
                t.priority,
                t.task_kind,
                t.estimated_hours,
                t.assigned_to,
                t.project_id,
                t.progress,
                t.due_date,
                t.note,
                p.name AS project_name,
                p.department_id,
                p.end_date AS project_end_date,
                pp.construction_number AS project_construction_number
             FROM %stasks t
             LEFT JOIN %sprojects p ON t.project_id = p.id
             LEFT JOIN %sparent_projects pp ON p.parent_project_id = pp.id
             WHERE t.id IN (%s)
             ORDER BY t.id ASC",
            DB_PREFIX,
            DB_PREFIX,
            DB_PREFIX,
            $idList
        ));

        $allEntryRows = $this->fetchAll(sprintf(
            "SELECT
                te.id AS entry_id,
                te.task_id,
                te.user_id,
                te.start_time,
                te.end_time,
                te.hours,
                te.description,
                u.realname AS entry_user_name
             FROM %stime_entries te
             LEFT JOIN %suser u ON %s
             WHERE te.task_id IN (%s)
             ORDER BY te.start_time DESC, te.id DESC",
            DB_PREFIX,
            DB_PREFIX,
            $userJoin,
            $idList
        ));

        $tasksById = [];
        foreach ($taskRows as $row) {
            $taskId = intval($row['id']);
            $assignedIds = [];
            if (!empty($row['assigned_to'])) {
                foreach (explode(',', $row['assigned_to']) as $part) {
                    $id = intval(trim($part));
                    if ($id > 0) {
                        $assignedIds[] = $id;
                    }
                }
            }
            $tasksById[$taskId] = [
                'id' => $taskId,
                'project_id' => isset($row['project_id']) ? intval($row['project_id']) : 0,
                'project_name' => $row['project_name'] ?? '',
                'project_construction_number' => $row['project_construction_number'] ?? '',
                'title' => $row['title'] ?? '',
                'status' => $row['status'] ?? '',
                'priority' => $row['priority'] ?? '',
                'task_kind' => $row['task_kind'] ?? '',
                'estimated_hours' => $this->normalize_estimated_hours($row['estimated_hours'] ?? 0),
                'progress' => isset($row['progress']) ? intval($row['progress']) : 0,
                'due_date' => $row['due_date'] ?? null,
                'note' => $row['note'] ?? '',
                'department_id' => $row['department_id'] ?? null,
                'project_end_date' => $row['project_end_date'] ?? null,
                'assigned_to_ids' => $assignedIds,
                'week_hours' => 0,
                'time_entries' => []
            ];
        }

        foreach ($allEntryRows as $row) {
            $taskId = intval($row['task_id']);
            if ($taskId <= 0 || !isset($tasksById[$taskId])) {
                continue;
            }

            $hours = round(floatval($row['hours'] ?? 0), 2);
            $endTime = $row['end_time'] ?? null;
            $entryUserId = (string) ($row['user_id'] ?? '');
            $inWeek = false;
            if (!empty($endTime)) {
                $endDate = substr((string) $endTime, 0, 10);
                $inWeek = ($endDate >= $weekStart && $endDate <= $weekEnd);
            }

            $tasksById[$taskId]['time_entries'][] = [
                'id' => intval($row['entry_id']),
                'start_time' => $row['start_time'] ?? null,
                'end_time' => $endTime,
                'hours' => $hours,
                'description' => $row['description'] ?? '',
                'user_name' => $row['entry_user_name'] ?? '',
                'user_id' => $entryUserId,
                'running' => empty($endTime),
                'in_week' => $inWeek
            ];

            if ($inWeek && strcasecmp($entryUserId, $user['userid']) === 0) {
                $tasksById[$taskId]['week_hours'] = round($tasksById[$taskId]['week_hours'] + $hours, 2);
            }
        }

        return [
            'status' => 'success',
            'can_select_user' => $canSelectUser,
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'user' => [
                'id' => intval($user['id']),
                'userid' => $user['userid'],
                'realname' => $user['realname']
            ],
            'tasks' => array_values($tasksById)
        ];
    }
}