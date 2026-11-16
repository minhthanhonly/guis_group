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
            'pinned_at' => array(),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search'))
        );
        $this->connect();
        $this->ensureLabelColumn();
        $this->ensureSortOrderColumn();
        $this->ensureBoardColumns();
        $this->ensureUserBoardTable();
    }

    /** 背景色 palette keys (Keep style). '' = default (white). */
    const BG_COLORS = array('coral', 'peach', 'sand', 'mint', 'sage', 'fog', 'storm', 'dusk', 'blossom', 'clay', 'chalk');
    /** 公開範囲: all = 全員, members = 案件メンバー (+ author), self = 自分のみ */
    const VISIBILITIES = array('all', 'members', 'self');
    /** メモボード (案件詳細): number of grid columns */
    const BOARD_COLS = 3;

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

    /** bg_color + visibility + pinned_at columns (メモボード) */
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
            . "AND COLUMN_NAME IN ('bg_color', 'visibility', 'pinned_at')"
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
        if (empty($have['pinned_at'])) {
            $this->query(
                "ALTER TABLE `{$table}` ADD COLUMN `pinned_at` DATETIME DEFAULT NULL "
                . "COMMENT 'ピン（全員）にした日時' AFTER `visibility`"
            );
        }
    }

    /* ---------- メモボード: each user arranges their own board ----------
     * ピン（全員） notes form a band at the top, in the user's own order (pin_order);
     * newly pinned notes (no pin_order yet) come first, newest pin first.
     * Other notes sit on a 3-column grid below, one note per cell (board_col, board_row),
     * empty cells allowed; notes without a cell are appended after the last occupied one.
     * Only notes the user can see take part.
     */

    private function userBoardTable() {
        return DB_PREFIX . 'project_note_user_board';
    }

    private function ensureUserBoardTable() {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;
        $table = $this->userBoardTable();
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
            . "`project_id` INT NOT NULL,"
            . "`note_id` INT NOT NULL,"
            . "`user_id` VARCHAR(64) NOT NULL COMMENT 'userid string',"
            . "`board_col` TINYINT DEFAULT NULL COMMENT '列 (1-3)、ピン以外',"
            . "`board_row` INT DEFAULT NULL COMMENT '行 (1-)、ピン以外',"
            . "`pin_order` INT DEFAULT NULL COMMENT 'ピン（全員）の並び順',"
            . "`updated_at` DATETIME NOT NULL,"
            . "PRIMARY KEY (`id`),"
            . "UNIQUE KEY `uniq_note_user` (`note_id`, `user_id`),"
            . "KEY `idx_project_user` (`project_id`, `user_id`)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='メモボード 個人の配置'"
        );
    }

    /** Visible notes of a project with the current user's placement (default order first). */
    private function userBoardRows($projectId) {
        return $this->fetchAll(sprintf(
            "SELECT n.id, n.is_important, n.pinned_at, b.board_col, b.board_row, b.pin_order
            FROM {$this->table} n
            LEFT JOIN " . $this->userBoardTable() . " b ON b.note_id = n.id AND b.user_id = '%s'
            WHERE n.project_id = %d AND " . $this->currentVisibilitySql('n') . "
            ORDER BY n.sort_order ASC, n.created_at DESC, n.id DESC",
            $this->quote($this->currentUserid()),
            intval($projectId)
        ));
    }

    /** array('pinned' => note ids in band order, 'cells' => cell index => note id, 'appended' => ids given a cell just now) */
    private function computeUserBoard($rows) {
        $fresh = array();
        $ordered = array();
        $placed = array();
        $pending = array();
        foreach ((array)$rows as $i => $r) {
            $id = intval($r['id']);
            if (!empty($r['is_important'])) {
                if ($r['pin_order'] === null) {
                    $fresh[] = array('id' => $id, 'at' => (string)$r['pinned_at']);
                } else {
                    $ordered[] = array('id' => $id, 'o' => intval($r['pin_order']), 'i' => $i);
                }
                continue;
            }
            $c = $r['board_col'] === null ? 0 : intval($r['board_col']);
            $rw = $r['board_row'] === null ? 0 : intval($r['board_row']);
            if ($c >= 1 && $c <= self::BOARD_COLS && $rw >= 1) {
                $placed[] = array('idx' => ($rw - 1) * self::BOARD_COLS + ($c - 1), 'id' => $id, 'i' => $i);
            } else {
                $pending[] = $id;
            }
        }
        usort($fresh, function ($a, $b) {
            return $a['at'] === $b['at'] ? $b['id'] - $a['id'] : strcmp($b['at'], $a['at']);
        });
        usort($ordered, function ($a, $b) {
            return $a['o'] === $b['o'] ? $a['i'] - $b['i'] : $a['o'] - $b['o'];
        });
        $pinned = array();
        foreach (array_merge($fresh, $ordered) as $p) {
            $pinned[] = $p['id'];
        }
        usort($placed, function ($a, $b) {
            return $a['idx'] === $b['idx'] ? $a['i'] - $b['i'] : $a['idx'] - $b['idx'];
        });
        $cells = array();
        foreach ($placed as $p) {
            if (isset($cells[$p['idx']])) {
                $pending[] = $p['id'];
            } else {
                $cells[$p['idx']] = $p['id'];
            }
        }
        if ($pending) {
            $next = $cells ? max(array_keys($cells)) + 1 : 0;
            foreach ($pending as $id) {
                $cells[$next++] = $id;
            }
        }
        return array('pinned' => $pinned, 'cells' => $cells, 'appended' => $pending);
    }

    /** Upsert the current user's rows: $values = note id => array(column => int). */
    private function saveUserBoard($projectId, $values) {
        if (!$values) {
            return;
        }
        $uid = $this->quote($this->currentUserid());
        $now = $this->quote(date('Y-m-d H:i:s'));
        $cols = array_keys(reset($values));
        $tuples = array();
        foreach ($values as $noteId => $v) {
            $vals = array();
            foreach ($cols as $c) {
                $vals[] = $v[$c] === null ? 'NULL' : intval($v[$c]);
            }
            $tuples[] = sprintf("(%d, %d, '%s', %s, '%s')", intval($projectId), intval($noteId), $uid, implode(', ', $vals), $now);
        }
        $updates = array();
        foreach ($cols as $c) {
            $updates[] = "`{$c}` = VALUES(`{$c}`)";
        }
        $updates[] = '`updated_at` = VALUES(`updated_at`)';
        $this->query(
            "INSERT INTO " . $this->userBoardTable() . " (`project_id`, `note_id`, `user_id`, `" . implode('`, `', $cols) . "`, `updated_at`) VALUES "
            . implode(', ', $tuples) . " ON DUPLICATE KEY UPDATE " . implode(', ', $updates)
        );
    }

    /** Add board_col / board_row (grid) and pin_rank (band) of the current user to each note row. */
    private function attachUserBoard($projectId, &$notes) {
        $board = $this->computeUserBoard($this->userBoardRows($projectId));
        // notes that just got a cell (new / unpinned / first visit) keep it from now on
        if ($board['appended'] && $this->currentUserid() !== '') {
            $appended = array_flip($board['appended']);
            $values = array();
            foreach ($board['cells'] as $idx => $id) {
                if (isset($appended[$id])) {
                    $values[$id] = array('board_col' => ($idx % self::BOARD_COLS) + 1, 'board_row' => intdiv($idx, self::BOARD_COLS) + 1);
                }
            }
            $this->saveUserBoard($projectId, $values);
        }
        $pos = array();
        foreach ($board['pinned'] as $rank => $id) {
            $pos[$id] = array(null, null, $rank);
        }
        foreach ($board['cells'] as $idx => $id) {
            $pos[$id] = array(($idx % self::BOARD_COLS) + 1, intdiv($idx, self::BOARD_COLS) + 1, null);
        }
        foreach ($notes as &$n) {
            $p = isset($pos[intval($n['id'])]) ? $pos[intval($n['id'])] : array(null, null, null);
            $n['board_col'] = $p[0];
            $n['board_row'] = $p[1];
            $n['pin_rank'] = $p[2];
        }
        unset($n);
    }

    /** Put $id at $idx; an occupant moves one cell on, and so on until an empty cell takes the last one. */
    private static function boardInsert(&$cells, $idx, $id) {
        $carry = $id;
        while (isset($cells[$idx])) {
            $next = $cells[$idx];
            $cells[$idx] = $carry;
            $carry = $next;
            $idx++;
        }
        $cells[$idx] = $carry;
    }

    /** ピン（全員） turned on: the note goes first in everyone's band. */
    private function markPinned($noteId) {
        $noteId = intval($noteId);
        $this->query(sprintf("UPDATE {$this->table} SET pinned_at = '%s' WHERE id = %d", $this->quote(date('Y-m-d H:i:s')), $noteId));
        $this->query(sprintf("UPDATE " . $this->userBoardTable() . " SET pin_order = NULL WHERE note_id = %d", $noteId));
    }

    /** ピン（全員） turned off: the note goes to the end of everyone's grid. */
    private function markUnpinned($noteId) {
        $noteId = intval($noteId);
        $this->query(sprintf("UPDATE {$this->table} SET pinned_at = NULL WHERE id = %d", $noteId));
        $this->query(sprintf(
            "UPDATE " . $this->userBoardTable() . " SET board_col = NULL, board_row = NULL, pin_order = NULL WHERE note_id = %d",
            $noteId
        ));
    }

    /**
     * Drag on the メモボード (own board): put a note that is not ピン（全員） in grid cell (col, row).
     * An occupied cell pushes its note — and the chain behind it — one cell on.
     * POST: project_id, note_id, col (1-3), row (1-). Returns the user's grid: note id => [col, row].
     */
    function moveOnBoard($params = null) {
        $projectId = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $noteId = isset($_POST['note_id']) ? intval($_POST['note_id']) : 0;
        $col = isset($_POST['col']) ? intval($_POST['col']) : 0;
        $row = isset($_POST['row']) ? intval($_POST['row']) : 0;
        if (!$projectId || !$noteId || $this->currentUserid() === '') {
            return ['status' => 'error', 'error' => 'project_id and note_id required'];
        }
        $note = $this->getById($noteId); // visibility-checked
        if (!$note || intval($note['project_id']) !== $projectId) {
            return ['status' => 'error', 'error' => 'Note not found'];
        }
        if (!empty($note['is_important'])) {
            return ['status' => 'error', 'error' => 'ピン（全員）のメモはピンの中でのみ並べ替えできます'];
        }
        $board = $this->computeUserBoard($this->userBoardRows($projectId));
        $cells = $board['cells'];
        $at = array_search($noteId, $cells, true);
        if ($at !== false) {
            unset($cells[$at]);
        }
        $col = max(1, min(self::BOARD_COLS, $col));
        $maxRow = $cells ? intdiv(max(array_keys($cells)), self::BOARD_COLS) + 1 : 0;
        $row = max(1, min($maxRow + 1, $row));
        self::boardInsert($cells, ($row - 1) * self::BOARD_COLS + ($col - 1), $noteId);
        $values = array();
        $positions = array();
        foreach ($cells as $idx => $id) {
            $c = ($idx % self::BOARD_COLS) + 1;
            $r = intdiv($idx, self::BOARD_COLS) + 1;
            $values[$id] = array('board_col' => $c, 'board_row' => $r);
            $positions[$id] = array($c, $r);
        }
        $this->saveUserBoard($projectId, $values);
        return ['status' => 'success', 'positions' => $positions];
    }

    /**
     * Reorder the ピン（全員） band of the own board. POST: project_id, note_ids (JSON, pinned notes in
     * the new order; pinned notes left out keep their relative order after them).
     */
    function reorderPinned($params = null) {
        $projectId = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        if (!$projectId || $this->currentUserid() === '') {
            return ['status' => 'error', 'error' => 'project_id required'];
        }
        $raw = isset($_POST['note_ids']) ? $_POST['note_ids'] : '';
        $ids = is_string($raw) ? json_decode($raw, true) : $raw;
        $ids = array_values(array_filter(array_map('intval', is_array($ids) ? $ids : array())));
        $current = $this->computeUserBoard($this->userBoardRows($projectId))['pinned'];
        $order = array();
        foreach ($ids as $id) {
            if (in_array($id, $current, true) && !in_array($id, $order, true)) {
                $order[] = $id;
            }
        }
        foreach ($current as $id) {
            if (!in_array($id, $order, true)) {
                $order[] = $id;
            }
        }
        $values = array();
        foreach ($order as $rank => $id) {
            $values[$id] = array('pin_order' => $rank);
        }
        $this->saveUserBoard($projectId, $values);
        return ['status' => 'success', 'order' => $order];
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
        $result = $this->query_insert($data);
        if ($result) {
            if (!empty($data['is_important'])) {
                $this->markPinned(intval($result));
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
        
        $result = $this->query_update($data, ['id' => $id]);
        if ($result) {
            if (!empty($data['is_important']) && empty($note['is_important'])) {
                $this->markPinned($id);
            } elseif (empty($data['is_important']) && !empty($note['is_important'])) {
                $this->markUnpinned($id);
            }
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'error' => 'Failed to update note'];
        }
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
            if ($next === 1) {
                $this->markPinned($id);
            } else {
                $this->markUnpinned($id);
            }
            return ['status' => 'success', 'is_important' => $next];
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
        
        $result = $this->query_delete(['id' => $id]);
        if ($result) {
            $this->query(sprintf("DELETE FROM %s WHERE note_id = %d", $this->userBoardTable(), $id));
        }
        if ($result) {
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'error' => 'Failed to delete note'];
        }
    }

    function getById($id) {
        $query = sprintf(
            "SELECT n.*, u.realname
            FROM {$this->table} n
            LEFT JOIN " . DB_PREFIX . "user u ON n.user_id = u.userid
            WHERE n.id = %d AND " . $this->currentVisibilitySql('n'),
            intval($id)
        );
        return $this->fetchOne($query);
    }

    function getByProject($project_id) {
        $query = sprintf(
            "SELECT n.*, u.realname
            FROM {$this->table} n
            LEFT JOIN " . DB_PREFIX . "user u ON n.user_id = u.userid
            WHERE n.project_id = %d AND " . $this->currentVisibilitySql('n') . "
            ORDER BY n.is_important DESC, n.sort_order ASC, n.created_at DESC",
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
        $this->attachUserBoard($project_id, $notes);
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
