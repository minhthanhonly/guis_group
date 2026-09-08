<?php

/**
 * Branch / locality technical specification rules (multi-department).
 * department_key: isho (意匠設計), setsubi (設備設計), extensible.
 */
class BranchSpec extends ApplicationModel {
    const DEPT_LABELS = array(
        'isho' => '意匠設計',
        'setsubi' => '設備設計',
    );

    const DEPT_NAME_ALIASES = array(
        'isho' => array('意匠設計', '意匠'),
        'setsubi' => array('設備設計', '設備', '技術課設備'),
    );

    const FEATURE_LABELS = array(
        'mb_water_heater' => 'MB内に給湯器設置',
        'fire_water_tank' => '消火用補給水槽',
        'steel_stairs' => '鉄骨階段',
        'gh_l_type' => 'GH・L型',
    );

    function __construct() {
        $this->table = DB_PREFIX . 'branch_spec_rules';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'department_key' => array(),
            'company_key' => array(),
            'priority' => array(),
            'match_branch' => array(),
            'match_prefecture' => array(),
            'match_city' => array(),
            'match_structure' => array(),
            'match_type1' => array(),
            'match_type2' => array(),
            'match_scale_pattern' => array(),
            'match_feature' => array(),
            'match_note' => array(),
            'title_ja' => array(),
            'title_vi' => array(),
            'content_ja' => array(),
            'content_vi' => array(),
            'apply_electrical' => array(),
            'apply_sanitary' => array(),
            'apply_architectural' => array(),
            'apply_wood' => array(),
            'apply_steel_rc' => array(),
            'requires_manual_confirm' => array(),
            'source_sheet' => array(),
            'is_active' => array(),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search')),
        );
        $this->connect();
        $this->ensureTables();
    }

    private function ensureTables() {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $this->query(
                "CREATE TABLE IF NOT EXISTS `{$this->table}` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `department_key` varchar(32) NOT NULL,
                  `company_key` varchar(255) DEFAULT '*',
                  `priority` int(11) DEFAULT 0,
                  `match_branch` varchar(500) DEFAULT NULL,
                  `match_prefecture` varchar(500) DEFAULT NULL,
                  `match_city` varchar(500) DEFAULT NULL,
                  `match_structure` varchar(20) DEFAULT 'any',
                  `match_type1` varchar(255) DEFAULT NULL,
                  `match_type2` varchar(255) DEFAULT NULL,
                  `match_scale_pattern` varchar(255) DEFAULT NULL,
                  `match_feature` varchar(64) DEFAULT NULL,
                  `match_note` varchar(500) DEFAULT NULL,
                  `title_ja` varchar(500) DEFAULT NULL,
                  `title_vi` varchar(500) DEFAULT NULL,
                  `content_ja` text,
                  `content_vi` text,
                  `apply_electrical` tinyint(1) DEFAULT 0,
                  `apply_sanitary` tinyint(1) DEFAULT 0,
                  `apply_architectural` tinyint(1) DEFAULT 0,
                  `apply_wood` tinyint(1) DEFAULT 1,
                  `apply_steel_rc` tinyint(1) DEFAULT 1,
                  `requires_manual_confirm` tinyint(1) DEFAULT 0,
                  `source_sheet` varchar(64) DEFAULT NULL,
                  `is_active` tinyint(1) DEFAULT 1,
                  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `idx_dept_active` (`department_key`, `is_active`),
                  KEY `idx_priority` (`priority`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (Exception $e) {
            error_log('BranchSpec ensureTables: ' . $e->getMessage());
        }
        try {
            $parentTable = DB_PREFIX . 'parent_projects';
            $col = $this->fetchOne("SHOW COLUMNS FROM `{$parentTable}` LIKE 'construction_city'");
            if (!$col) {
                $this->query(
                    "ALTER TABLE `{$parentTable}` ADD COLUMN `construction_city` varchar(255) DEFAULT NULL COMMENT '建築地（市区町村）' AFTER `construction_branch`"
                );
            }
            $col2 = $this->fetchOne("SHOW COLUMNS FROM `{$parentTable}` LIKE 'structure_type'");
            if (!$col2) {
                $this->query(
                    "ALTER TABLE `{$parentTable}` ADD COLUMN `structure_type` varchar(20) DEFAULT NULL COMMENT 'W|S_RC' AFTER `construction_city`"
                );
            }
            $col3 = $this->fetchOne("SHOW COLUMNS FROM `{$parentTable}` LIKE 'spec_features'");
            if (!$col3) {
                $this->query(
                    "ALTER TABLE `{$parentTable}` ADD COLUMN `spec_features` varchar(500) DEFAULT NULL COMMENT 'CSV feature keys' AFTER `structure_type`"
                );
            }
            $col4 = $this->fetchOne("SHOW COLUMNS FROM `{$this->table}` LIKE 'match_feature'");
            if (!$col4) {
                $this->query(
                    "ALTER TABLE `{$this->table}` ADD COLUMN `match_feature` varchar(64) DEFAULT NULL AFTER `match_scale_pattern`"
                );
            }
        } catch (Exception $e) {
            error_log('BranchSpec ensure parent/spec columns: ' . $e->getMessage());
        }
    }

    public static function featureLabels() {
        return self::FEATURE_LABELS;
    }

    public static function resolveFeatureKey($rule) {
        if (!empty($rule['match_feature'])) {
            return trim($rule['match_feature']);
        }
        $blob = (isset($rule['title_ja']) ? $rule['title_ja'] : '')
            . ' '
            . (isset($rule['match_note']) ? $rule['match_note'] : '')
            . ' '
            . (isset($rule['content_ja']) ? $rule['content_ja'] : '');
        if (mb_strpos($blob, 'MB内') !== false && mb_strpos($blob, '給湯') !== false) {
            return 'mb_water_heater';
        }
        if (mb_strpos($blob, '消火用補給水槽') !== false) {
            return 'fire_water_tank';
        }
        if (mb_strpos($blob, '鉄骨階段') !== false) {
            return 'steel_stairs';
        }
        if (mb_strpos($blob, 'GH') !== false || mb_strpos($blob, 'Ｌ型') !== false || mb_strpos($blob, 'L型') !== false) {
            return 'gh_l_type';
        }
        return '';
    }

    public static function structureFromParent($structureType, $scale) {
        $st = trim((string)$structureType);
        if ($st === 'W' || $st === 'wood' || $st === '木造') {
            return 'wood';
        }
        if ($st === 'S_RC' || $st === 'S・RC' || $st === 'steel_rc' || $st === 'RC') {
            return 'steel_rc';
        }
        return self::inferStructure($scale);
    }

    public static function normalizeBranch($value) {
        $s = preg_replace('/\s+/u', '', (string)$value);
        $s = preg_replace('/[（(].*?[）)]/u', '', $s);
        $s = str_replace(array('の案件', '案件', '支店'), '', $s);
        return trim($s, " ,、/");
    }

    public static function csvToList($csv) {
        if ($csv === null || $csv === '') {
            return array();
        }
        $parts = preg_split('/[,、\/]/u', (string)$csv);
        $out = array();
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return $out;
    }

    public static function inferStructure($scale) {
        $s = (string)$scale;
        if ($s === '') {
            return 'any';
        }
        $upper = mb_strtoupper($s, 'UTF-8');
        $isWood = (strpos($upper, 'W') !== false) || (mb_strpos($s, '木') !== false);
        $isRc = (strpos($upper, 'RC') !== false) || (strpos($upper, 'S') !== false && !$isWood)
            || (mb_strpos($s, '鉄') !== false);
        if ($isWood && $isRc) {
            return 'both';
        }
        if ($isWood) {
            return 'wood';
        }
        if ($isRc) {
            return 'steel_rc';
        }
        return 'any';
    }

    private function departmentKeyFromName($name) {
        $name = (string)$name;
        foreach (self::DEPT_NAME_ALIASES as $key => $aliases) {
            foreach ($aliases as $alias) {
                if ($name !== '' && mb_strpos($name, $alias) !== false) {
                    return $key;
                }
            }
        }
        return null;
    }

    function getDepartmentMeta($params = null) {
        return array(
            'status' => 'success',
            'data' => self::DEPT_LABELS,
            'features' => self::FEATURE_LABELS,
        );
    }

    function listRules($params = null) {
        return $this->list($params);
    }

    function list($params = null) {
        $this->seedIfEmpty();
        $dept = isset($_GET['department_key']) ? trim($_GET['department_key']) : (isset($params['department_key']) ? trim($params['department_key']) : '');
        $activeOnly = !isset($_GET['all']) || $_GET['all'] !== '1';
        $where = array('1=1');
        if ($dept !== '') {
            $where[] = "department_key = '" . $this->quote($dept) . "'";
        }
        if ($activeOnly) {
            $where[] = 'is_active = 1';
        }
        $sql = sprintf(
            "SELECT * FROM %s WHERE %s ORDER BY department_key ASC, priority ASC, id ASC",
            $this->table,
            implode(' AND ', $where)
        );
        $rows = $this->fetchAll($sql);
        if (!$rows) {
            $rows = array();
        }
        foreach ($rows as &$row) {
            // Normalize Excel marks for older rows
            if ((empty($row['apply_wood']) && empty($row['apply_steel_rc'])) || (!isset($row['apply_wood']))) {
                $ms = isset($row['match_structure']) ? $row['match_structure'] : 'any';
                if ($ms === 'wood') {
                    $row['apply_wood'] = 1;
                    $row['apply_steel_rc'] = 0;
                } elseif ($ms === 'steel_rc') {
                    $row['apply_wood'] = 0;
                    $row['apply_steel_rc'] = 1;
                } else {
                    $row['apply_wood'] = 1;
                    $row['apply_steel_rc'] = 1;
                }
            }
            if (empty($row['match_feature'])) {
                $row['match_feature'] = self::resolveFeatureKey($row);
            }
        }
        unset($row);
        return array('status' => 'success', 'data' => $rows);
    }

    function getById($params = null) {
        $id = is_array($params) ? (isset($params['id']) ? intval($params['id']) : 0) : intval($params);
        if (!$id && isset($_GET['id'])) {
            $id = intval($_GET['id']);
        }
        if (!$id) {
            return null;
        }
        return $this->fetchOne(sprintf("SELECT * FROM %s WHERE id = %d", $this->table, $id));
    }

    function create($params = null) {
        $data = $this->readRuleFromPost();
        if (empty($data['department_key'])) {
            return array('status' => 'error', 'error' => 'department_key is required');
        }
        if (trim((string)$data['content_ja']) === '' && trim((string)$data['title_ja']) === '') {
            return array('status' => 'error', 'error' => '仕様内容またはタイトルが必要です');
        }
        $data = $this->normalizeRuleApplyFlags($data);
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        try {
            $id = $this->query_insert($data);
            if ($id) {
                return array('status' => 'success', 'id' => $id);
            }
            return array('status' => 'error', 'error' => 'Failed to create');
        } catch (Exception $e) {
            return array('status' => 'error', 'error' => $e->getMessage());
        }
    }

    function update($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) {
            return array('status' => 'error', 'error' => 'id required');
        }
        $existing = $this->getById($id);
        if (!$existing) {
            return array('status' => 'error', 'error' => 'Rule not found');
        }
        $data = $this->readRuleFromPost();
        $data = $this->normalizeRuleApplyFlags($data);
        $data['updated_at'] = date('Y-m-d H:i:s');
        try {
            $this->query_update($data, array('id' => $id));
            return array('status' => 'success', 'id' => $id);
        } catch (Exception $e) {
            return array('status' => 'error', 'error' => $e->getMessage());
        }
    }

    private function normalizeRuleApplyFlags($data) {
        $wood = !empty($data['apply_wood']) ? 1 : 0;
        $rc = !empty($data['apply_steel_rc']) ? 1 : 0;
        // Prefer explicit wood/RC flags from Excel-like UI; derive match_structure
        if (isset($_POST['apply_wood']) || isset($_POST['apply_steel_rc'])) {
            if ($wood && $rc) {
                $data['match_structure'] = 'both';
            } elseif ($wood) {
                $data['match_structure'] = 'wood';
            } elseif ($rc) {
                $data['match_structure'] = 'steel_rc';
            } else {
                $data['match_structure'] = 'any';
                $wood = 1;
                $rc = 1;
            }
        } else {
            $ms = isset($data['match_structure']) ? $data['match_structure'] : 'any';
            if ($ms === 'wood') {
                $wood = 1;
                $rc = 0;
            } elseif ($ms === 'steel_rc') {
                $wood = 0;
                $rc = 1;
            } elseif ($ms === 'both') {
                $wood = 1;
                $rc = 1;
            } else {
                $wood = 1;
                $rc = 1;
            }
        }
        $data['apply_wood'] = $wood;
        $data['apply_steel_rc'] = $rc;
        if (empty($data['source_sheet'])) {
            $data['source_sheet'] = isset($data['department_key']) ? $data['department_key'] : '';
        }
        if (empty($data['match_note']) && !empty($data['match_branch'])) {
            $data['match_note'] = $data['match_branch'];
        }
        return $data;
    }

    function setActive($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 0;
        if (!$id) {
            return array('status' => 'error', 'error' => 'id required');
        }
        try {
            $this->query_update(
                array('is_active' => $active ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s')),
                array('id' => $id)
            );
            return array('status' => 'success');
        } catch (Exception $e) {
            return array('status' => 'error', 'error' => $e->getMessage());
        }
    }

    function delete($params = null) {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) {
            return array('status' => 'error', 'error' => 'id required');
        }
        $this->query(sprintf("DELETE FROM %s WHERE id = %d", $this->table, $id));
        return array('status' => 'success');
    }

    private function readRuleFromPost() {
        $bool = function ($key, $default = 0) {
            return isset($_POST[$key]) ? (intval($_POST[$key]) ? 1 : 0) : $default;
        };
        return array(
            'department_key' => isset($_POST['department_key']) ? trim($_POST['department_key']) : 'isho',
            'company_key' => isset($_POST['company_key']) ? trim($_POST['company_key']) : '*',
            'priority' => isset($_POST['priority']) ? intval($_POST['priority']) : 0,
            'match_branch' => isset($_POST['match_branch']) ? trim($_POST['match_branch']) : '',
            'match_prefecture' => isset($_POST['match_prefecture']) ? trim($_POST['match_prefecture']) : '',
            'match_city' => isset($_POST['match_city']) ? trim($_POST['match_city']) : '',
            'match_structure' => isset($_POST['match_structure']) ? trim($_POST['match_structure']) : 'any',
            'match_type1' => isset($_POST['match_type1']) ? trim($_POST['match_type1']) : '',
            'match_type2' => isset($_POST['match_type2']) ? trim($_POST['match_type2']) : '',
            'match_scale_pattern' => isset($_POST['match_scale_pattern']) ? trim($_POST['match_scale_pattern']) : '',
            'match_feature' => isset($_POST['match_feature']) ? trim($_POST['match_feature']) : '',
            'match_note' => isset($_POST['match_note']) ? trim($_POST['match_note']) : '',
            'title_ja' => isset($_POST['title_ja']) ? trim($_POST['title_ja']) : '',
            'title_vi' => isset($_POST['title_vi']) ? trim($_POST['title_vi']) : '',
            'content_ja' => isset($_POST['content_ja']) ? $_POST['content_ja'] : '',
            'content_vi' => isset($_POST['content_vi']) ? $_POST['content_vi'] : '',
            'apply_electrical' => $bool('apply_electrical'),
            'apply_sanitary' => $bool('apply_sanitary'),
            'apply_architectural' => $bool('apply_architectural'),
            'apply_wood' => $bool('apply_wood', 1),
            'apply_steel_rc' => $bool('apply_steel_rc', 1),
            'requires_manual_confirm' => $bool('requires_manual_confirm'),
            'source_sheet' => isset($_POST['source_sheet']) ? trim($_POST['source_sheet']) : '',
            'is_active' => isset($_POST['is_active']) ? (intval($_POST['is_active']) ? 1 : 0) : 1,
        );
    }

    function seedIfEmpty($params = null) {
        $row = $this->fetchOne("SELECT COUNT(*) AS cnt FROM {$this->table}");
        if ($row && intval($row['cnt']) > 0) {
            return array('status' => 'success', 'seeded' => 0, 'message' => 'already seeded');
        }
        return $this->importSeed(array('force' => 0));
    }

    function importSeed($params = null) {
        $force = false;
        if (is_array($params) && !empty($params['force'])) {
            $force = true;
        }
        if (isset($_POST['force']) && $_POST['force']) {
            $force = true;
        }
        $path = dirname(__DIR__, 2) . '/assets/json/branch_spec_seed.json';
        if (!is_file($path)) {
            return array('status' => 'error', 'error' => 'seed file not found');
        }
        $json = file_get_contents($path);
        $rules = json_decode($json, true);
        if (!is_array($rules)) {
            return array('status' => 'error', 'error' => 'invalid seed json');
        }
        if ($force) {
            $this->query("TRUNCATE TABLE {$this->table}");
        } else {
            $row = $this->fetchOne("SELECT COUNT(*) AS cnt FROM {$this->table}");
            if ($row && intval($row['cnt']) > 0) {
                return array('status' => 'success', 'seeded' => 0, 'message' => 'already has data');
            }
        }
        $n = 0;
        foreach ($rules as $r) {
            $data = array(
                'department_key' => isset($r['department_key']) ? $r['department_key'] : 'isho',
                'company_key' => isset($r['company_key']) ? $r['company_key'] : '*',
                'priority' => isset($r['priority']) ? intval($r['priority']) : 0,
                'match_branch' => isset($r['match_branch']) ? $r['match_branch'] : '',
                'match_prefecture' => isset($r['match_prefecture']) ? $r['match_prefecture'] : '',
                'match_city' => isset($r['match_city']) ? $r['match_city'] : '',
                'match_structure' => isset($r['match_structure']) ? $r['match_structure'] : 'any',
                'match_type1' => isset($r['match_type1']) ? $r['match_type1'] : '',
                'match_type2' => isset($r['match_type2']) ? $r['match_type2'] : '',
                'match_scale_pattern' => isset($r['match_scale_pattern']) ? $r['match_scale_pattern'] : '',
                'match_feature' => isset($r['match_feature']) ? $r['match_feature'] : self::resolveFeatureKey($r),
                'match_note' => isset($r['match_note']) ? $r['match_note'] : '',
                'title_ja' => isset($r['title_ja']) ? $r['title_ja'] : '',
                'title_vi' => isset($r['title_vi']) ? $r['title_vi'] : '',
                'content_ja' => isset($r['content_ja']) ? $r['content_ja'] : '',
                'content_vi' => isset($r['content_vi']) ? $r['content_vi'] : '',
                'apply_electrical' => !empty($r['apply_electrical']) ? 1 : 0,
                'apply_sanitary' => !empty($r['apply_sanitary']) ? 1 : 0,
                'apply_architectural' => !empty($r['apply_architectural']) ? 1 : 0,
                'apply_wood' => isset($r['apply_wood']) ? (!empty($r['apply_wood']) ? 1 : 0) : 1,
                'apply_steel_rc' => isset($r['apply_steel_rc']) ? (!empty($r['apply_steel_rc']) ? 1 : 0) : 1,
                'requires_manual_confirm' => !empty($r['requires_manual_confirm']) ? 1 : 0,
                'source_sheet' => isset($r['source_sheet']) ? $r['source_sheet'] : '',
                'is_active' => isset($r['is_active']) ? (!empty($r['is_active']) ? 1 : 0) : 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            );
            if ($this->query_insert($data)) {
                $n++;
            }
        }
        return array('status' => 'success', 'seeded' => $n);
    }

    /**
     * Match rules for a parent project. API:
     * GET model=branchspec&method=matchForParent&parent_project_id=N
     * Optional: department_key, scope=electrical|sanitary|architectural
     */
    function matchForParent($params = null) {
        $this->seedIfEmpty();
        $parentId = 0;
        if (is_array($params) && isset($params['parent_project_id'])) {
            $parentId = intval($params['parent_project_id']);
        }
        if (!$parentId && isset($_GET['parent_project_id'])) {
            $parentId = intval($_GET['parent_project_id']);
        }
        if (!$parentId && isset($_POST['parent_project_id'])) {
            $parentId = intval($_POST['parent_project_id']);
        }

        $parent = null;
        if ($parentId) {
            $parent = $this->fetchOne(sprintf(
                "SELECT * FROM %sparent_projects WHERE id = %d",
                DB_PREFIX,
                $parentId
            ));
        }
        if (!$parent) {
            $parent = array(
                'company_name' => '',
                'branch_name' => '',
                'construction_branch' => '',
                'construction_city' => '',
                'structure_type' => '',
                'spec_features' => '',
                'scale' => '',
                'type1' => '',
                'type2' => '',
            );
        }
        // Overlay request params for live preview while editing
        foreach (array('company_name', 'branch_name', 'construction_branch', 'construction_city', 'structure_type', 'spec_features', 'scale', 'type1', 'type2') as $field) {
            if (isset($_REQUEST[$field]) && (string)$_REQUEST[$field] !== '') {
                $parent[$field] = $_REQUEST[$field];
            }
        }
        // Allow clearing structure/features via explicit empty when preview=1
        if (isset($_REQUEST['structure_type'])) {
            $parent['structure_type'] = $_REQUEST['structure_type'];
        }
        if (isset($_REQUEST['spec_features'])) {
            $parent['spec_features'] = $_REQUEST['spec_features'];
        }

        $filterDept = isset($_REQUEST['department_key']) ? trim($_REQUEST['department_key']) : '';
        $filterScope = isset($_REQUEST['scope']) ? trim($_REQUEST['scope']) : '';

        $ctx = $this->buildContext($parent);
        $rows = $this->fetchAll(sprintf(
            "SELECT * FROM %s WHERE is_active = 1 ORDER BY department_key ASC, priority ASC, id ASC",
            $this->table
        ));
        if (!$rows) {
            $rows = array();
        }

        $matched = array();
        $manual = array();
        foreach ($rows as $rule) {
            if ($filterDept !== '' && $rule['department_key'] !== $filterDept) {
                continue;
            }
            if ($filterScope === 'electrical' && empty($rule['apply_electrical'])) {
                continue;
            }
            if ($filterScope === 'sanitary' && empty($rule['apply_sanitary'])) {
                continue;
            }
            if ($filterScope === 'architectural' && empty($rule['apply_architectural'])) {
                continue;
            }

            $result = $this->ruleMatches($rule, $ctx);
            if ($result['match']) {
                $rule['match_reasons'] = $result['reasons'];
                $rule['department_label'] = isset(self::DEPT_LABELS[$rule['department_key']])
                    ? self::DEPT_LABELS[$rule['department_key']]
                    : $rule['department_key'];
                $matched[] = $rule;
            } elseif (!empty($rule['requires_manual_confirm']) && $result['soft']) {
                $rule['match_reasons'] = $result['reasons'];
                $rule['department_label'] = isset(self::DEPT_LABELS[$rule['department_key']])
                    ? self::DEPT_LABELS[$rule['department_key']]
                    : $rule['department_key'];
                $manual[] = $rule;
            }
        }

        $byDept = array();
        foreach (array_merge($matched, array()) as $r) {
            $k = $r['department_key'];
            if (!isset($byDept[$k])) {
                $byDept[$k] = array(
                    'key' => $k,
                    'label' => isset(self::DEPT_LABELS[$k]) ? self::DEPT_LABELS[$k] : $k,
                    'rules' => array(),
                );
            }
            $byDept[$k]['rules'][] = $r;
        }

        $userDeptKeys = $this->currentUserDepartmentKeys();

        return array(
            'status' => 'success',
            'context' => $ctx,
            'data' => $matched,
            'manual' => $manual,
            'by_department' => array_values($byDept),
            'user_department_keys' => $userDeptKeys,
            'department_labels' => self::DEPT_LABELS,
            'feature_labels' => self::FEATURE_LABELS,
        );
    }

    private function buildContext($parent) {
        $branchName = isset($parent['branch_name']) ? $parent['branch_name'] : '';
        $constructionBranch = isset($parent['construction_branch']) ? $parent['construction_branch'] : '';
        $city = isset($parent['construction_city']) ? $parent['construction_city'] : '';
        $scale = isset($parent['scale']) ? $parent['scale'] : '';
        $company = isset($parent['company_name']) ? $parent['company_name'] : '';
        $type1 = isset($parent['type1']) ? $parent['type1'] : '';
        $type2 = isset($parent['type2']) ? $parent['type2'] : '';
        $structureType = isset($parent['structure_type']) ? $parent['structure_type'] : '';
        $features = self::csvToList(isset($parent['spec_features']) ? $parent['spec_features'] : '');

        $branchNorm = self::normalizeBranch($branchName);
        $prefList = self::csvToList($constructionBranch);
        $locationBlob = $constructionBranch . ' ' . $city . ' ' . $branchName;

        return array(
            'company_name' => $company,
            'branch_name' => $branchName,
            'branch_norm' => $branchNorm,
            'construction_branch' => $constructionBranch,
            'construction_city' => $city,
            'location_blob' => $locationBlob,
            'prefectures' => $prefList,
            'scale' => $scale,
            'structure_type' => $structureType,
            'structure' => self::structureFromParent($structureType, $scale),
            'spec_features' => $features,
            'type1' => $type1,
            'type2' => $type2,
        );
    }

    private function ruleMatches($rule, $ctx) {
        $reasons = array();
        $soft = false;

        // company
        $ck = isset($rule['company_key']) ? trim($rule['company_key']) : '*';
        if ($ck !== '' && $ck !== '*') {
            if ($ctx['company_name'] === '' || mb_strpos($ctx['company_name'], $ck) === false) {
                return array('match' => false, 'soft' => false, 'reasons' => array('company'));
            }
            $reasons[] = 'company:' . $ck;
        }

        // branch
        $branches = self::csvToList(isset($rule['match_branch']) ? $rule['match_branch'] : '');
        if (count($branches) > 0) {
            $ok = false;
            foreach ($branches as $b) {
                $bn = self::normalizeBranch($b);
                if ($bn === '') {
                    continue;
                }
                if ($ctx['branch_norm'] !== '' && (
                    mb_strpos($ctx['branch_norm'], $bn) !== false
                    || mb_strpos($bn, $ctx['branch_norm']) !== false
                    || mb_strpos($ctx['branch_name'], $b) !== false
                )) {
                    $ok = true;
                    $reasons[] = 'branch:' . $b;
                    break;
                }
            }
            if (!$ok) {
                return array('match' => false, 'soft' => false, 'reasons' => array('branch'));
            }
        }

        // prefecture
        $prefs = self::csvToList(isset($rule['match_prefecture']) ? $rule['match_prefecture'] : '');
        if (count($prefs) > 0) {
            $ok = false;
            foreach ($prefs as $p) {
                if ($p !== '' && mb_strpos($ctx['location_blob'], $p) !== false) {
                    $ok = true;
                    $reasons[] = 'prefecture:' . $p;
                    break;
                }
                // short form 愛知 in blob
                $short = str_replace(array('県', '府', '都'), '', $p);
                if ($short !== '' && mb_strpos($ctx['location_blob'], $short) !== false) {
                    $ok = true;
                    $reasons[] = 'prefecture:' . $p;
                    break;
                }
            }
            if (!$ok) {
                return array('match' => false, 'soft' => false, 'reasons' => array('prefecture'));
            }
        }

        // city
        $cities = self::csvToList(isset($rule['match_city']) ? $rule['match_city'] : '');
        if (count($cities) > 0) {
            $ok = false;
            foreach ($cities as $c) {
                if ($c !== '' && mb_strpos($ctx['location_blob'], $c) !== false) {
                    $ok = true;
                    $reasons[] = 'city:' . $c;
                    break;
                }
            }
            if (!$ok) {
                return array('match' => false, 'soft' => false, 'reasons' => array('city'));
            }
        }

        // structure
        $ms = isset($rule['match_structure']) ? $rule['match_structure'] : 'any';
        if ($ms && $ms !== 'any' && $ms !== 'both') {
            $cs = $ctx['structure'];
            if ($cs === 'any') {
                // unknown structure: soft fail for structure-specific rules
                if (!empty($rule['requires_manual_confirm'])) {
                    $soft = true;
                } else {
                    // still allow if apply flags allow both; else fail soft show in manual
                    $soft = true;
                    return array('match' => false, 'soft' => true, 'reasons' => array('structure_unknown'));
                }
            } elseif ($ms === 'wood' && $cs !== 'wood' && $cs !== 'both') {
                return array('match' => false, 'soft' => false, 'reasons' => array('structure'));
            } elseif ($ms === 'steel_rc' && $cs !== 'steel_rc' && $cs !== 'both') {
                return array('match' => false, 'soft' => false, 'reasons' => array('structure'));
            } else {
                $reasons[] = 'structure:' . $ms;
            }
        } elseif ($ms === 'both') {
            $reasons[] = 'structure:both';
        }

        // type1
        $t1 = self::csvToList(isset($rule['match_type1']) ? $rule['match_type1'] : '');
        if (count($t1) > 0) {
            $ok = false;
            foreach ($t1 as $t) {
                if ($t !== '' && $ctx['type1'] !== '' && mb_stripos($ctx['type1'], $t) !== false) {
                    $ok = true;
                    $reasons[] = 'type1:' . $t;
                    break;
                }
            }
            if (!$ok) {
                // TAC/特注 optional soft when empty type1
                if ($ctx['type1'] === '') {
                    return array('match' => false, 'soft' => true, 'reasons' => array('type1_unknown'));
                }
                return array('match' => false, 'soft' => false, 'reasons' => array('type1'));
            }
        }

        // type2
        $t2 = self::csvToList(isset($rule['match_type2']) ? $rule['match_type2'] : '');
        if (count($t2) > 0) {
            $ok = false;
            foreach ($t2 as $t) {
                if ($t !== '' && $ctx['type2'] !== '' && mb_stripos($ctx['type2'], $t) !== false) {
                    $ok = true;
                    $reasons[] = 'type2:' . $t;
                    break;
                }
            }
            if (!$ok) {
                if ($ctx['type2'] === '') {
                    return array('match' => false, 'soft' => true, 'reasons' => array('type2_unknown'));
                }
                return array('match' => false, 'soft' => false, 'reasons' => array('type2'));
            }
        }

        // scale pattern
        $pat = isset($rule['match_scale_pattern']) ? trim($rule['match_scale_pattern']) : '';
        if ($pat !== '') {
            $parts = explode('|', $pat);
            $ok = false;
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p === '') {
                    continue;
                }
                if ($ctx['scale'] !== '' && mb_stripos($ctx['scale'], str_replace('\\', '', $p)) !== false) {
                    $ok = true;
                    break;
                }
                // also try as regex
                $regex = '/' . str_replace('/', '\\/', $p) . '/iu';
                if ($ctx['scale'] !== '' && @preg_match($regex, $ctx['scale'])) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                if ($ctx['scale'] === '') {
                    return array('match' => false, 'soft' => true, 'reasons' => array('scale_unknown'));
                }
                return array('match' => false, 'soft' => false, 'reasons' => array('scale'));
            }
            $reasons[] = 'scale:' . $pat;
        }

        // Feature flags (MB給湯器 / 消火水槽 / 鉄骨階段 / GH・L型)
        $featureKey = self::resolveFeatureKey($rule);
        if ($featureKey !== '') {
            $parentFeatures = isset($ctx['spec_features']) && is_array($ctx['spec_features'])
                ? $ctx['spec_features']
                : array();
            if (!in_array($featureKey, $parentFeatures, true)) {
                return array('match' => false, 'soft' => false, 'reasons' => array('feature_missing:' . $featureKey));
            }
            $reasons[] = 'feature:' . $featureKey;
        }

        // Shared rules with no match dimensions: show only when we have some context,
        // or always for department shared baselines that have no filters.
        $hasAnyFilter = count($branches) || count($prefs) || count($cities) || count($t1) || count($t2) || $pat !== ''
            || ($ms && $ms !== 'any') || $featureKey !== '';
        if (!$hasAnyFilter) {
            $reasons[] = 'shared';
        }

        if (!empty($rule['requires_manual_confirm']) && !$hasAnyFilter && $featureKey === '') {
            // person/partner tips — soft manual when no building feature key
            return array('match' => false, 'soft' => true, 'reasons' => array('manual'));
        }

        return array('match' => true, 'soft' => $soft, 'reasons' => $reasons);
    }

    private function currentUserDepartmentKeys() {
        $keys = array();
        try {
            if (empty($_SESSION['userid'])) {
                return $keys;
            }
            $sql = sprintf(
                "SELECT d.name FROM %sdepartments d
                 INNER JOIN %suser_department ud ON ud.department_id = d.id
                 WHERE ud.userid = '%s'",
                DB_PREFIX,
                DB_PREFIX,
                $this->quote($_SESSION['userid'])
            );
            $rows = $this->fetchAll($sql);
            if ($rows) {
                foreach ($rows as $r) {
                    $k = $this->departmentKeyFromName(isset($r['name']) ? $r['name'] : '');
                    if ($k && !in_array($k, $keys, true)) {
                        $keys[] = $k;
                    }
                }
            }
        } catch (Exception $e) {
            // ignore
        }
        return $keys;
    }
}
