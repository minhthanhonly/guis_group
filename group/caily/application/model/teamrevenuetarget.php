<?php
class Teamrevenuetarget extends ApplicationModel {
    private $department_target_table;
    
    function __construct() {
        $this->table = DB_PREFIX . 'team_revenue_targets';
        $this->department_target_table = DB_PREFIX . 'department_revenue_targets';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'team_id' => array(),
            'year' => array(),
            'yearly_target' => array(),
            'monthly_target' => array(),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search')),
        );
        
        $this->connect();
    }

    private function ensureDepartmentTargetTable() {
        static $ensured = false;
        if ($ensured) return;

        $sql = "CREATE TABLE IF NOT EXISTS `{$this->department_target_table}` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `department_id` int(11) NOT NULL COMMENT '部署ID',
            `year` int(4) NOT NULL COMMENT '年度',
            `yearly_target` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT '部署年間目標売上高',
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_department_year` (`department_id`, `year`),
            KEY `idx_department_id` (`department_id`),
            KEY `idx_year` (`year`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='部署売上目標テーブル'";
        $this->query($sql);
        $ensured = true;
    }

    /**
     * Get all teams
     */
    function getTeams() {
        $department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
        $departmentWhere = $department_id > 0 ? " AND t.department_id = " . $department_id : "";

        $query = sprintf(
            "SELECT t.id, t.name, t.department_id, d.name as department_name
             FROM " . DB_PREFIX . "team t
             LEFT JOIN " . DB_PREFIX . "departments d ON t.department_id = d.id
             WHERE t.is_active = 1
             %s
             ORDER BY t.department_id ASC, t.name ASC"
            ,
            $departmentWhere
        );
        
        return $this->fetchAll($query);
    }

    /**
     * Get all revenue targets for a specific year
     */
    function list($params = null) {
        $year = isset($_GET['year']) ? intval($_GET['year']) : (isset($params['year']) ? intval($params['year']) : date('Y'));
        $department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
        $departmentWhere = $department_id > 0 ? " AND t.department_id = " . $department_id : "";
        
        $query = sprintf(
            "SELECT trt.*, t.name as team_name, t.department_id, d.name as department_name
             FROM {$this->table} trt
             LEFT JOIN " . DB_PREFIX . "team t ON trt.team_id = t.id
             LEFT JOIN " . DB_PREFIX . "departments d ON t.department_id = d.id
             WHERE trt.year = %d
             %s
             ORDER BY t.department_id ASC, t.name ASC",
            intval($year),
            $departmentWhere
        );
        
        return $this->fetchAll($query);
    }

    /**
     * Get revenue target for a specific team and year
     */
    function get($params = null) {
        $team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : (isset($params['team_id']) ? intval($params['team_id']) : 0);
        $year = isset($_GET['year']) ? intval($_GET['year']) : (isset($params['year']) ? intval($params['year']) : date('Y'));
        
        if ($team_id <= 0) {
            return null;
        }
        
        $query = sprintf(
            "SELECT trt.*, t.name as team_name
             FROM {$this->table} trt
             LEFT JOIN " . DB_PREFIX . "team t ON trt.team_id = t.id
             WHERE trt.team_id = %d AND trt.year = %d
             LIMIT 1",
            intval($team_id),
            intval($year)
        );
        
        return $this->fetchOne($query);
    }

    /**
     * Add or update revenue target
     */
    function save($params = null) {
        // Support both $_GET, $_POST and $params array
        if (is_array($params)) {
            $team_id = isset($params['team_id']) ? intval($params['team_id']) : 0;
            $year = isset($params['year']) ? intval($params['year']) : date('Y');
            $yearly_target = isset($params['yearly_target']) ? floatval($params['yearly_target']) : 0;
        } else {
            // Try GET first (for URL parameters), then POST
            $team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : (isset($_POST['team_id']) ? intval($_POST['team_id']) : 0);
            $year = isset($_GET['year']) ? intval($_GET['year']) : (isset($_POST['year']) ? intval($_POST['year']) : date('Y'));
            $yearly_target = isset($_GET['yearly_target']) ? floatval($_GET['yearly_target']) : (isset($_POST['yearly_target']) ? floatval($_POST['yearly_target']) : 0);
        }
        
        if ($team_id <= 0) {
            $this->error[] = 'チームIDが無効です';
            return null;
        }
        
        if ($year < 2000 || $year > 2100) {
            $this->error[] = '年度が無効です';
            return null;
        }
        
        // Calculate monthly target (yearly / 12)
        $monthly_target = $yearly_target / 12;
        
        // Check if target already exists
        $existing = $this->get(array('team_id' => $team_id, 'year' => $year));
        
        if ($existing) {
            // Update existing target
            $query = sprintf(
                "UPDATE {$this->table} 
                 SET yearly_target = %.2f, monthly_target = %.2f, updated_at = NOW()
                 WHERE id = %d",
                $yearly_target,
                $monthly_target,
                intval($existing['id'])
            );
            $this->query($query);
            return array('id' => $existing['id'], 'action' => 'updated');
        } else {
            // Insert new target
            $query = sprintf(
                "INSERT INTO {$this->table} (team_id, year, yearly_target, monthly_target, created_at, updated_at)
                 VALUES (%d, %d, %.2f, %.2f, NOW(), NOW())",
                intval($team_id),
                intval($year),
                $yearly_target,
                $monthly_target
            );
            $this->query($query);
            $id = $this->insertid();
            return array('id' => $id, 'action' => 'created');
        }
    }

    /**
     * Delete revenue target
     */
    function delete($params = null) {
        $id = isset($_GET['id']) ? intval($_GET['id']) : (isset($params['id']) ? intval($params['id']) : 0);
        
        if ($id <= 0) {
            $this->error[] = 'IDが無効です';
            return null;
        }
        
        $query = sprintf("DELETE FROM {$this->table} WHERE id = %d", intval($id));
        $this->query($query);
        
        return array('success' => true);
    }

    /**
     * Get all years that have targets
     */
    function getYears($params = null) {
        $query = sprintf(
            "SELECT DISTINCT year FROM {$this->table} ORDER BY year DESC"
        );
        
        $rows = $this->fetchAll($query);
        $years = array();
        foreach ($rows as $row) {
            $years[] = intval($row['year']);
        }
        
        return $years;
    }

    function getDepartmentTarget($params = null) {
        $this->ensureDepartmentTargetTable();
        $department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : (isset($params['department_id']) ? intval($params['department_id']) : 0);
        $year = isset($_GET['year']) ? intval($_GET['year']) : (isset($params['year']) ? intval($params['year']) : date('Y'));

        if ($department_id <= 0) {
            return null;
        }

        $query = sprintf(
            "SELECT * FROM {$this->department_target_table}
             WHERE department_id = %d AND year = %d
             LIMIT 1",
            intval($department_id),
            intval($year)
        );
        return $this->fetchOne($query);
    }

    function saveDepartmentTarget($params = null) {
        $this->ensureDepartmentTargetTable();
        if (is_array($params)) {
            $department_id = isset($params['department_id']) ? intval($params['department_id']) : 0;
            $year = isset($params['year']) ? intval($params['year']) : date('Y');
            $yearly_target = isset($params['yearly_target']) ? floatval($params['yearly_target']) : 0;
        } else {
            $department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : (isset($_POST['department_id']) ? intval($_POST['department_id']) : 0);
            $year = isset($_GET['year']) ? intval($_GET['year']) : (isset($_POST['year']) ? intval($_POST['year']) : date('Y'));
            $yearly_target = isset($_GET['yearly_target']) ? floatval($_GET['yearly_target']) : (isset($_POST['yearly_target']) ? floatval($_POST['yearly_target']) : 0);
        }

        if ($department_id <= 0) {
            $this->error[] = '部署IDが無効です';
            return null;
        }
        if ($year < 2000 || $year > 2100) {
            $this->error[] = '年度が無効です';
            return null;
        }
        if ($yearly_target < 0) {
            $yearly_target = 0;
        }

        $existing = $this->getDepartmentTarget(array('department_id' => $department_id, 'year' => $year));
        if ($existing) {
            $query = sprintf(
                "UPDATE {$this->department_target_table}
                 SET yearly_target = %.2f, updated_at = NOW()
                 WHERE id = %d",
                $yearly_target,
                intval($existing['id'])
            );
            $this->query($query);
            return array('id' => $existing['id'], 'action' => 'updated');
        }

        $query = sprintf(
            "INSERT INTO {$this->department_target_table} (department_id, year, yearly_target, created_at, updated_at)
             VALUES (%d, %d, %.2f, NOW(), NOW())",
            intval($department_id),
            intval($year),
            $yearly_target
        );
        $this->query($query);
        return array('id' => $this->insertid(), 'action' => 'created');
    }
}

