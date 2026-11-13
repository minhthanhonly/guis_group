<?php

class ProjectNote extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'project_notes';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'project_id' => array(),
            'user_id' => array(),
            'title' => array(),
            'content' => array(),
            'is_important' => array(),
            'needs_confirmation' => array(),
            'display_column' => array(),
            'label' => array(),
            'sort_order' => array(),
            'bg_color' => array(),
            'visibility' => array(),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search'))
        );
        $this->connect();
        $this->ensureLabelColumn();
        $this->ensureSortOrderColumn();
        $this->ensureBoardColumns();
        $this->ensurePersonalPinTable();
    }

    /** 背景色 palette keys (Keep style). '' = default (white). */
    const BG_COLORS = array('coral', 'peach', 'sand', 'mint', 'sage', 'fog', 'storm', 'dusk', 'blossom', 'clay', 'chalk');
    /** 公開範囲: all = 全員, members = 案件メンバー (+ author), self = 自分のみ */
    const VISIBILITIES = array('all', 'members', 'self');

    /**
     * SQL condition: the note (alias $alias) is visible to the given user. No admin exception.
     * $escapedUserid / $authId must come from the caller's session; $escapedUserid already escaped.
     */
    public static function visibilitySql($alias, $escapedUserid, $authId) {
        $a = preg_replace('/[^a-z0-9_]/i', '', $alias);
        $authId = intval($authId);
        $cond = "COALESCE({$a}.visibility, 'all') = 'all'";
        if ($escapedUserid !== '') {
            $cond .= " OR {$a}.user_id = '{$escapedUserid}'";
        }
        if ($authId > 0) {
            $cond .= " OR ({$a}.visibility = 'members' AND EXISTS (SELECT 1 FROM " . DB_PREFIX . "project_members pmv"
                . " WHERE pmv.project_id = {$a}.project_id AND pmv.user_id = '{$authId}'))";
        }
        return '(' . $cond . ')';
    }

    /** Visibility condition for the current session user. */
    public function currentVisibilitySql($alias = 'n') {
        $authId = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
        return self::visibilitySql($alias, $this->quote($this->currentUserid()), $authId);
    }

    private function normalizeBgColor($raw) {
        $c = trim((string)$raw);
        return in_array($c, self::BG_COLORS, true) ? $c : '';
    }

    private function normalizeVisibility($raw) {
        $v = trim((string)$raw);
        return in_array($v, self::VISIBILITIES, true) ? $v : 'all';
    }

    /** bg_color + visibility columns (メモボード) */
    private function ensureBoardColumns() {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;
        $table = $this->table;
        $rows = $this->fetchAll(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->quote($table) . "' "
            . "AND COLUMN_NAME IN ('bg_color', 'visibility')"
        );
        $have = array();
        foreach ((array)$rows as $r) {
            $have[$r['COLUMN_NAME']] = true;
        }
        if (empty($have['bg_color'])) {
            $this->query(
                "ALTER TABLE `{$table}` ADD COLUMN `bg_color` varchar(20) DEFAULT NULL "
                . "COMMENT 'メモ背景色' AFTER `sort_order`"
            );
        }
        if (empty($have['visibility'])) {
            $this->query(
                "ALTER TABLE `{$table}` ADD COLUMN `visibility` varchar(10) NOT NULL DEFAULT 'all' "
                . "COMMENT '公開範囲 all/members/self' AFTER `bg_color`"
            );
        }
    }

    private function personalPinTable() {
        return DB_PREFIX . 'project_note_user_pins';
    }

    /**
     * メモラベル (label) — name string from department note_labels
     */
    private function ensureLabelColumn() {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;
        $table = $this->table;
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->quote($table) . "' "
            . "AND COLUMN_NAME = 'label'"
        );
        if (!empty($row['cnt'])) {
            return;
        }
        $this->query(
            "ALTER TABLE `{$table}` ADD COLUMN `label` varchar(100) DEFAULT NULL "
            . "COMMENT 'メモラベル' AFTER `display_column`"
        );
    }

    private function ensureSortOrderColumn() {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;
        $table = $this->table;
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->quote($table) . "' "
            . "AND COLUMN_NAME = 'sort_order'"
        );
        if (!empty($row['cnt'])) {
            return;
        }
        $this->query(
            "ALTER TABLE `{$table}` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 "
            . "COMMENT 'メモ表示順' AFTER `label`"
        );
    }

    /** Per-user pin (ピン（自分）) — note_id + userid */
    private function ensurePersonalPinTable() {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;
        $table = $this->personalPinTable();
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.TABLES "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->quote($table) . "'"
        );
        if (!empty($row['cnt'])) {
            return;
        }
        $this->query(
            "CREATE TABLE `{$table}` ("
            . "`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,"
            . "`note_id` INT NOT NULL,"
            . "`user_id` VARCHAR(64) NOT NULL COMMENT 'userid string',"
            . "`created_at` DATETIME NOT NULL,"
            . "PRIMARY KEY (`id`),"
            . "UNIQUE KEY `uniq_note_user` (`note_id`, `user_id`),"
            . "KEY `idx_user_id` (`user_id`)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='メモ個人ピン'"
        );
    }

    private function currentUserid() {
        return isset($_SESSION['userid']) ? (string)$_SESSION['userid'] : '';
    }

    private function canEditNote($note) {
        if (!$note) {
            return false;
        }
        if (isset($_SESSION['authority']) && $_SESSION['authority'] === 'administrator') {
            return true;
        }
        $currentUserId = $this->currentUserid();
        $currentUserAuthId = isset($_SESSION['id']) ? $_SESSION['id'] : null;
        if (strval($note['user_id']) === strval($currentUserId)) {
            return true;
        }
        if ($currentUserAuthId !== null) {
            $query = sprintf(
                "SELECT COUNT(*) as count FROM " . DB_PREFIX . "project_members 
                WHERE project_id = %d AND user_id = %s AND role = 'manager'",
                intval($note['project_id']),
                $this->quote($currentUserAuthId)
            );
            $result = $this->fetchOne($query);
            if ($result && $result['count'] > 0) {
                return true;
            }
        }
        $project = $this->fetchOne(
            "SELECT department_id FROM " . DB_PREFIX . "projects WHERE id = " . intval($note['project_id'])
        );
        if ($project && !empty($project['department_id'])) {
            $departmentCheck = $this->fetchOne(sprintf(
                "SELECT project_manager FROM " . DB_PREFIX . "user_department ud "
                . "WHERE ud.department_id = %d AND ud.userid = '%s' LIMIT 1",
                intval($project['department_id']),
                $this->quote($currentUserId)
            ));
            if ($departmentCheck && isset($departmentCheck['project_manager'])
                && intval($departmentCheck['project_manager']) === 1) {
                return true;
            }
        }
        return false;
    }

    private function nextSortOrder($projectId, $needsConfirmation) {
        $row = $this->fetchOne(sprintf(
            "SELECT COALESCE(MAX(sort_order), 0) AS max_ord FROM {$this->table} "
            . "WHERE project_id = %d AND needs_confirmation = %d",
            intval($projectId),
            intval($needsConfirmation)
        ));
        return intval(isset($row['max_ord']) ? $row['max_ord'] : 0) + 1;
    }

    private function defaultNoteTitle() {
        return 'メモ';
    }

    private function noteContentHasText($content) {
        $text = trim(strip_tags((string)$content));
        $text = str_replace("\xc2\xa0", ' ', $text);
        return $text !== '';
    }

    private function normalizeLabel($raw) {
        $label = trim((string)$raw);
        if ($label === '') {
            return '';
        }
        if (mb_strlen($label) > 100) {
            $label = mb_substr($label, 0, 100);
        }
        return $label;
    }

    /** Note content is Quill HTML that every viewer's browser renders: strip anything executable before saving. */
    private function cleanNoteContent($content) {
        require_once DIR_LIBRARY . 'HtmlSanitizer.php';
        return HtmlSanitizer::clean($content);
    }

    function create($params = null) {
        $projectId = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $needsConfirmation = isset($_POST['needs_confirmation']) ? intval($_POST['needs_confirmation']) : 0;
        $data = array(
            'project_id' => $projectId,
            'user_id' => $_SESSION['userid'],
            'title' => $this->defaultNoteTitle(),
            'content' => $this->cleanNoteContent(isset($_POST['content']) ? $_POST['content'] : ''),
            'is_important' => isset($_POST['is_important']) ? intval($_POST['is_important']) : 0,
            'needs_confirmation' => $needsConfirmation,
            'display_column' => isset($_POST['display_column']) ? trim((string)$_POST['display_column']) : '',
            'label' => isset($_POST['label']) ? $this->normalizeLabel($_POST['label']) : '',
            'sort_order' => $projectId ? $this->nextSortOrder($projectId, $needsConfirmation) : 0,
            'bg_color' => $this->normalizeBgColor(isset($_POST['bg_color']) ? $_POST['bg_color'] : ''),
            'visibility' => $this->normalizeVisibility(isset($_POST['visibility']) ? $_POST['visibility'] : 'all'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        if (!$data['project_id'] || !$this->noteContentHasText($data['content'])) {
            return ['status' => 'error', 'error' => 'Missing required fields'];
        }
        
        // 自分のみ: nobody else sees the note, so ピン（全員） has no meaning
        if ($data['visibility'] === 'self') {
            $data['is_important'] = 0;
        }
        // Mutual exclusivity: ピン（全員） wins over ピン（自分）
        if (!empty($data['is_important'])) {
            $_POST['is_pinned_self'] = 0;
        }
        $result = $this->query_insert($data);
        if ($result) {
            $pinSelf = isset($_POST['is_pinned_self']) ? intval($_POST['is_pinned_self']) : 0;
            if ($pinSelf && empty($data['is_important'])) {
                $this->setPersonalPin(intval($result), true);
            }
            return ['status' => 'success', 'id' => $result];
        } else {
            return ['status' => 'error', 'error' => 'Failed to create note'];
        }
    }

    function update($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'error' => 'No note id'];
        
        $note = $this->getById($id);
        if (!$note) {
            return ['status' => 'error', 'error' => 'Note not found'];
        }
        if (!$this->canEditNote($note)) {
            return ['status' => 'error', 'error' => 'Permission denied'];
        }
        
        $data = array(
            'content' => $this->cleanNoteContent(isset($_POST['content']) ? $_POST['content'] : ''),
            'is_important' => isset($_POST['is_important']) ? intval($_POST['is_important']) : 0,
            'needs_confirmation' => isset($_POST['needs_confirmation']) ? intval($_POST['needs_confirmation']) : 0,
            'display_column' => isset($_POST['display_column']) ? trim((string)$_POST['display_column']) : '',
            'label' => isset($_POST['label']) ? $this->normalizeLabel($_POST['label']) : '',
            'updated_at' => date('Y-m-d H:i:s')
        );
        if (isset($_POST['bg_color'])) {
            $data['bg_color'] = $this->normalizeBgColor($_POST['bg_color']);
        }
        if (isset($_POST['visibility'])) {
            $data['visibility'] = $this->normalizeVisibility($_POST['visibility']);
        }
        $effectiveVisibility = isset($data['visibility']) ? $data['visibility'] : $this->normalizeVisibility($note['visibility'] ?? 'all');
        if ($effectiveVisibility === 'self') {
            $data['is_important'] = 0; // ピン（全員） is meaningless on a 自分のみ note
        }
        
        if (!$this->noteContentHasText($data['content'])) {
            return ['status' => 'error', 'error' => 'Content is required'];
        }
        
        // Mutual exclusivity: only one of ピン（全員） / ピン（自分）
        $wantSelf = isset($_POST['is_pinned_self']) ? (intval($_POST['is_pinned_self']) === 1) : null;
        if (!empty($data['is_important'])) {
            $wantSelf = false;
        } elseif ($wantSelf === true) {
            $data['is_important'] = 0;
        }

        $result = $this->query_update($data, ['id' => $id]);
        if ($result) {
            if ($wantSelf !== null) {
                $this->setPersonalPin($id, $wantSelf);
            } elseif (!empty($data['is_important'])) {
                $this->clearAllPersonalPins($id);
            }
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'error' => 'Failed to update note'];
        }
    }

    /** Remove every user's personal pin for a note (used when pinning for everyone). */
    private function clearAllPersonalPins($noteId) {
        $noteId = intval($noteId);
        if (!$noteId) {
            return false;
        }
        $pinTable = $this->personalPinTable();
        return (bool)$this->query(sprintf(
            "DELETE FROM {$pinTable} WHERE note_id = %d",
            $noteId
        ));
    }

    private function setPersonalPin($noteId, $pinned) {
        $noteId = intval($noteId);
        $userid = $this->currentUserid();
        if (!$noteId || $userid === '') {
            return false;
        }
        $pinTable = $this->personalPinTable();
        if ($pinned) {
            $exists = $this->fetchOne(sprintf(
                "SELECT id FROM {$pinTable} WHERE note_id = %d AND user_id = '%s' LIMIT 1",
                $noteId,
                $this->quote($userid)
            ));
            if ($exists) {
                return true;
            }
            return (bool)$this->query(sprintf(
                "INSERT INTO {$pinTable} (note_id, user_id, created_at) VALUES (%d, '%s', '%s')",
                $noteId,
                $this->quote($userid),
                $this->quote(date('Y-m-d H:i:s'))
            ));
        }
        return (bool)$this->query(sprintf(
            "DELETE FROM {$pinTable} WHERE note_id = %d AND user_id = '%s'",
            $noteId,
            $this->quote($userid)
        ));
    }

    /** Toggle ピン（自分） for current user — no edit permission required. */
    function togglePersonalPin($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) {
            return ['status' => 'error', 'error' => 'No note id'];
        }
        $note = $this->getById($id);
        if (!$note) {
            return ['status' => 'error', 'error' => 'Note not found'];
        }
        $userid = $this->currentUserid();
        if ($userid === '') {
            return ['status' => 'error', 'error' => 'Not logged in'];
        }
        $pinTable = $this->personalPinTable();
        $exists = $this->fetchOne(sprintf(
            "SELECT id FROM {$pinTable} WHERE note_id = %d AND user_id = '%s' LIMIT 1",
            $id,
            $this->quote($userid)
        ));
        $nowPinned = empty($exists);
        // Mutual exclusivity: turning on ピン（自分） clears ピン（全員）
        if ($nowPinned && !empty($note['is_important'])) {
            if (!$this->canEditNote($note)) {
                return ['status' => 'error', 'error' => 'Permission denied'];
            }
            $cleared = $this->query_update(
                array('is_important' => 0, 'updated_at' => date('Y-m-d H:i:s')),
                array('id' => $id)
            );
            if (!$cleared) {
                return ['status' => 'error', 'error' => 'Failed to update'];
            }
        }
        $this->setPersonalPin($id, $nowPinned);
        return [
            'status' => 'success',
            'is_pinned_self' => $nowPinned ? 1 : 0,
            'is_important' => $nowPinned ? 0 : (!empty($note['is_important']) ? 1 : 0)
        ];
    }

    /** Toggle ピン（全員） = is_important. Requires edit permission. */
    function toggleImportant($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) {
            return ['status' => 'error', 'error' => 'No note id'];
        }
        $note = $this->getById($id);
        if (!$note) {
            return ['status' => 'error', 'error' => 'Note not found'];
        }
        if (!$this->canEditNote($note)) {
            return ['status' => 'error', 'error' => 'Permission denied'];
        }
        $next = empty($note['is_important']) ? 1 : 0;
        if ($next === 1 && ($note['visibility'] ?? 'all') === 'self') {
            return ['status' => 'error', 'error' => '自分のみのメモはピン（全員）にできません'];
        }
        $ok = $this->query_update(
            array('is_important' => $next, 'updated_at' => date('Y-m-d H:i:s')),
            array('id' => $id)
        );
        if ($ok) {
            // Mutual exclusivity: ピン（全員） clears all ピン（自分）
            if ($next === 1) {
                $this->clearAllPersonalPins($id);
            }
            return [
                'status' => 'success',
                'is_important' => $next,
                'is_pinned_self' => $next === 1 ? 0 : null
            ];
        }
        return ['status' => 'error', 'error' => 'Failed to update'];
    }

    /**
     * Reorder notes within a project (+ optional needs_confirmation bucket).
     * POST: project_id, note_ids (JSON array or comma-separated), needs_confirmation (optional).
     */
    function reorder($params = null) {
        $projectId = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        if (!$projectId) {
            return ['status' => 'error', 'error' => 'project_id required'];
        }
        $rawIds = isset($_POST['note_ids']) ? $_POST['note_ids'] : '';
        if (is_string($rawIds)) {
            $decoded = json_decode($rawIds, true);
            if (is_array($decoded)) {
                $ids = $decoded;
            } else {
                $ids = preg_split('/\s*,\s*/', trim($rawIds), -1, PREG_SPLIT_NO_EMPTY);
            }
        } elseif (is_array($rawIds)) {
            $ids = $rawIds;
        } else {
            $ids = array();
        }
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return ['status' => 'error', 'error' => 'note_ids required'];
        }

        $hasNeeds = array_key_exists('needs_confirmation', $_POST) && $_POST['needs_confirmation'] !== '';
        $needsConfirmation = $hasNeeds ? intval($_POST['needs_confirmation']) : null;

        $placeholders = implode(',', $ids);
        $whereExtra = $needsConfirmation !== null
            ? (' AND n.needs_confirmation = ' . intval($needsConfirmation))
            : '';
        $owned = $this->fetchAll(sprintf(
            "SELECT n.id FROM {$this->table} n WHERE n.project_id = %d AND n.id IN (%s)%s AND " . $this->currentVisibilitySql('n'),
            $projectId,
            $placeholders,
            $whereExtra
        ));
        $ownedSet = array();
        foreach ($owned as $row) {
            $ownedSet[intval($row['id'])] = true;
        }
        $ord = 0;
        foreach ($ids as $nid) {
            if (empty($ownedSet[$nid])) {
                continue;
            }
            $this->query_update(array('sort_order' => $ord), array('id' => $nid));
            $ord++;
        }
        return ['status' => 'success', 'count' => $ord];
    }

    function delete($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) return ['status' => 'error', 'error' => 'No note id'];
        
        $note = $this->getById($id);
        if (!$note) {
            return ['status' => 'error', 'error' => 'Note not found'];
        }
        if (!$this->canEditNote($note)) {
            return ['status' => 'error', 'error' => 'Permission denied'];
        }
        
        $this->query(sprintf(
            "DELETE FROM %s WHERE note_id = %d",
            $this->personalPinTable(),
            $id
        ));
        $result = $this->query_delete(['id' => $id]);
        if ($result) {
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'error' => 'Failed to delete note'];
        }
    }

    function getById($id) {
        $userid = $this->currentUserid();
        $pinTable = $this->personalPinTable();
        $query = sprintf(
            "SELECT n.*, u.realname,
                CASE WHEN pin.id IS NOT NULL THEN 1 ELSE 0 END AS is_pinned_self
            FROM {$this->table} n
            LEFT JOIN " . DB_PREFIX . "user u ON n.user_id = u.userid
            LEFT JOIN {$pinTable} pin ON pin.note_id = n.id AND pin.user_id = '%s'
            WHERE n.id = %d AND " . $this->currentVisibilitySql('n'),
            $this->quote($userid),
            intval($id)
        );
        return $this->fetchOne($query);
    }

    function getByProject($project_id) {
        $userid = $this->currentUserid();
        $pinTable = $this->personalPinTable();
        $query = sprintf(
            "SELECT n.*, u.realname,
                CASE WHEN pin.id IS NOT NULL THEN 1 ELSE 0 END AS is_pinned_self
            FROM {$this->table} n
            LEFT JOIN " . DB_PREFIX . "user u ON n.user_id = u.userid
            LEFT JOIN {$pinTable} pin ON pin.note_id = n.id AND pin.user_id = '%s'
            WHERE n.project_id = %d AND " . $this->currentVisibilitySql('n') . "
            ORDER BY is_pinned_self DESC, n.is_important DESC, n.sort_order ASC, n.created_at DESC",
            $this->quote($userid),
            intval($project_id)
        );
        return $this->fetchAll($query);
    }

    function list($params = null) {
        $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
        if (!$project_id) {
            return ['status' => 'error', 'error' => 'Project ID is required'];
        }
        
        $notes = $this->getByProject($project_id);
        return ['status' => 'success', 'data' => $notes];
    }

    function get($params = null) {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$id) {
            return ['status' => 'error', 'error' => 'Note ID is required'];
        }
        
        $note = $this->getById($id);
        if ($note) {
            return ['status' => 'success', 'data' => $note];
        } else {
            return ['status' => 'error', 'error' => 'Note not found'];
        }
    }
}
