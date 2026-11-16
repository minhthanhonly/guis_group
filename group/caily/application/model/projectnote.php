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
            'board_col' => array(),
            'board_row' => array(),
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
    /** メモボード (案件詳細): number of grid columns; a note sits in one cell (board_col, board_row) */
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

    /** bg_color + visibility + board_col / board_row columns (メモボード) */
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
            . "AND COLUMN_NAME IN ('bg_color', 'visibility', 'board_col', 'board_row')"
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
        if (empty($have['board_col'])) {
            $this->query(
                "ALTER TABLE `{$table}` ADD COLUMN `board_col` TINYINT DEFAULT NULL "
                . "COMMENT 'メモボード列 (1-3)' AFTER `visibility`"
            );
        }
        if (empty($have['board_row'])) {
            $this->query(
                "ALTER TABLE `{$table}` ADD COLUMN `board_row` INT DEFAULT NULL "
                . "COMMENT 'メモボード行 (1-)' AFTER `board_col`"
            );
        }
    }

    /* ---------- メモボード: free placement on a 3-column grid, shared by everyone ----------
     * A board is a map cell index => note id, index = (row - 1) * BOARD_COLS + (col - 1).
     * Empty cells are allowed; two notes never share a cell. Every note of the project takes
     * part (also notes the current user cannot see) so the layout is the same for everyone.
     */

    /** Serialise board writes per project (two people dragging at once). */
    private function lockBoard($projectId) {
        $row = $this->fetchOne(sprintf("SELECT GET_LOCK('%s', 5) AS l", $this->quote($this->table . '_board_' . intval($projectId))));
        return !empty($row['l']);
    }

    private function unlockBoard($projectId) {
        $this->fetchOne(sprintf("SELECT RELEASE_LOCK('%s') AS l", $this->quote($this->table . '_board_' . intval($projectId))));
    }

    /**
     * Current board of a project. Notes without a (valid, free) cell are appended after the last
     * occupied cell — ピン（全員） first, then sort_order, newest first (the old list order).
     * Returns array(cells, original rows by id).
     */
    private function readBoard($projectId) {
        $rows = $this->fetchAll(sprintf(
            "SELECT id, board_col, board_row, sort_order FROM {$this->table} WHERE project_id = %d "
            . "ORDER BY is_important DESC, sort_order ASC, created_at DESC, id DESC",
            intval($projectId)
        ));
        $cells = array();
        $pending = array();
        $orig = array();
        $placed = array();
        foreach ((array)$rows as $r) {
            $id = intval($r['id']);
            $orig[$id] = $r;
            $c = $r['board_col'] === null ? 0 : intval($r['board_col']);
            $rw = $r['board_row'] === null ? 0 : intval($r['board_row']);
            if ($c >= 1 && $c <= self::BOARD_COLS && $rw >= 1) {
                $placed[] = array('idx' => ($rw - 1) * self::BOARD_COLS + ($c - 1), 'id' => $id);
            } else {
                $pending[] = $id;
            }
        }
        // stable: earlier cell first, so a duplicate cell keeps one note and defers the other
        usort($placed, function ($a, $b) {
            return $a['idx'] === $b['idx'] ? 0 : ($a['idx'] < $b['idx'] ? -1 : 1);
        });
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
        return array($cells, $orig);
    }

    /** Write changed cells; sort_order follows reading order (row, col) so lists elsewhere match. */
    private function saveBoard($cells, $orig) {
        ksort($cells);
        $rank = 0;
        foreach ($cells as $idx => $id) {
            $col = ($idx % self::BOARD_COLS) + 1;
            $row = intdiv($idx, self::BOARD_COLS) + 1;
            $o = isset($orig[$id]) ? $orig[$id] : null;
            if (!$o || $o['board_col'] === null || intval($o['board_col']) !== $col
                || $o['board_row'] === null || intval($o['board_row']) !== $row
                || intval($o['sort_order']) !== $rank) {
                $this->query(sprintf(
                    "UPDATE {$this->table} SET board_col = %d, board_row = %d, sort_order = %d WHERE id = %d",
                    $col, $row, $rank, $id
                ));
            }
            $rank++;
        }
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

    private static function boardRemove(&$cells, $id) {
        $at = array_search($id, $cells, true);
        if ($at !== false) {
            unset($cells[$at]);
        }
    }

    /** Give every note a cell (notes created before the board, or after a 一覧 reorder). */
    private function ensureBoardLayout($projectId) {
        $projectId = intval($projectId);
        if (!$projectId) {
            return;
        }
        $missing = $this->fetchOne(sprintf(
            "SELECT COUNT(*) AS c FROM {$this->table} WHERE project_id = %d AND (board_col IS NULL OR board_row IS NULL)",
            $projectId
        ));
        if (empty($missing['c'])) {
            return;
        }
        $locked = $this->lockBoard($projectId);
        list($cells, $orig) = $this->readBoard($projectId);
        $this->saveBoard($cells, $orig);
        if ($locked) {
            $this->unlockBoard($projectId);
        }
    }

    /** ピン（全員） just turned on: the note goes to the first cell, the others shift on. */
    private function pinToFront($projectId, $noteId) {
        $projectId = intval($projectId);
        $noteId = intval($noteId);
        if (!$projectId || !$noteId) {
            return;
        }
        $locked = $this->lockBoard($projectId);
        list($cells, $orig) = $this->readBoard($projectId);
        self::boardRemove($cells, $noteId);
        self::boardInsert($cells, 0, $noteId);
        $this->saveBoard($cells, $orig);
        if ($locked) {
            $this->unlockBoard($projectId);
        }
    }

    /** Cells of the notes the current user can see: array(note id => array(col, row)). */
    private function visibleBoardPositions($projectId) {
        $rows = $this->fetchAll(sprintf(
            "SELECT n.id, n.board_col, n.board_row FROM {$this->table} n WHERE n.project_id = %d AND " . $this->currentVisibilitySql('n'),
            intval($projectId)
        ));
        $out = array();
        foreach ((array)$rows as $r) {
            $out[intval($r['id'])] = array(intval($r['board_col']), intval($r['board_row']));
        }
        return $out;
    }

    /**
     * Drag on the メモボード: put a note in cell (col, row). An occupied cell pushes its note
     * (and the chain behind it) one cell on. POST: project_id, note_id, col (1-3), row (1-).
     */
    function moveOnBoard($params = null) {
        $projectId = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $noteId = isset($_POST['note_id']) ? intval($_POST['note_id']) : 0;
        $col = isset($_POST['col']) ? intval($_POST['col']) : 0;
        $row = isset($_POST['row']) ? intval($_POST['row']) : 0;
        if (!$projectId || !$noteId) {
            return ['status' => 'error', 'error' => 'project_id and note_id required'];
        }
        $note = $this->getById($noteId); // visibility-checked
        if (!$note || intval($note['project_id']) !== $projectId) {
            return ['status' => 'error', 'error' => 'Note not found'];
        }
        $col = max(1, min(self::BOARD_COLS, $col));
        $locked = $this->lockBoard($projectId);
        list($cells, $orig) = $this->readBoard($projectId);
        self::boardRemove($cells, $noteId);
        $maxRow = $cells ? intdiv(max(array_keys($cells)), self::BOARD_COLS) + 1 : 0;
        $row = max(1, min($maxRow + 1, $row));
        self::boardInsert($cells, ($row - 1) * self::BOARD_COLS + ($col - 1), $noteId);
        $this->saveBoard($cells, $orig);
        if ($locked) {
            $this->unlockBoard($projectId);
        }
        return ['status' => 'success', 'positions' => $this->visibleBoardPositions($projectId)];
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
        $result = $this->query_insert($data);
        if ($result) {
            $pinSelf = isset($_POST['is_pinned_self']) ? intval($_POST['is_pinned_self']) : 0;
            if ($pinSelf) {
                $this->setPersonalPin(intval($result), true);
            }
            $this->ensureBoardLayout($projectId);
            if (!empty($data['is_important'])) {
                $this->pinToFront($projectId, intval($result));
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
        
        // ピン（全員） and ピン（自分） are independent (a note can have both; ピン（自分） shows first)
        $wantSelf = isset($_POST['is_pinned_self']) ? (intval($_POST['is_pinned_self']) === 1) : null;

        $result = $this->query_update($data, ['id' => $id]);
        if ($result) {
            if ($wantSelf !== null) {
                $this->setPersonalPin($id, $wantSelf);
            }
            if (!empty($data['is_important']) && empty($note['is_important'])) {
                $this->pinToFront(intval($note['project_id']), $id);
            }
            return ['status' => 'success'];
        } else {
            return ['status' => 'error', 'error' => 'Failed to update note'];
        }
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
        // ピン（全員） stays as it is — both pins can be on
        $this->setPersonalPin($id, $nowPinned);
        return [
            'status' => 'success',
            'is_pinned_self' => $nowPinned ? 1 : 0,
            'is_important' => !empty($note['is_important']) ? 1 : 0
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
            // personal pins (anyone's ピン（自分）) are kept
            if ($next === 1) {
                $this->pinToFront(intval($note['project_id']), $id);
            }
            return [
                'status' => 'success',
                'is_important' => $next,
                'is_pinned_self' => !empty($note['is_pinned_self']) ? 1 : 0
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
        if ($ord > 0) {
            // 案件一覧 reorder: re-pack the メモボード in the new order on next load
            $this->query(sprintf(
                "UPDATE {$this->table} SET board_col = NULL, board_row = NULL WHERE project_id = %d",
                $projectId
            ));
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
        
        $this->ensureBoardLayout($project_id);
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
