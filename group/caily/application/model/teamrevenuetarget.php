<?php
class Teamrevenuetarget extends ApplicationModel {
    
    function __construct() {
        $this->table = DB_PREFIX . 'team_revenue_targets';
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

    /**
     * Get all teams
     */
    function getTeams() {
        $query = sprintf(
            "SELECT t.id, t.name, t.department_id, d.name as department_name
             FROM " . DB_PREFIX . "team t
             LEFT JOIN " . DB_PREFIX . "departments d ON t.department_id = d.id
             WHERE t.is_active = 1
             ORDER BY t.department_id ASC, t.name ASC"
        );
        
        return $this->fetchAll($query);
    }

    /**
     * Get all revenue targets for a specific year
     */
    function list($params = null) {
        $year = isset($_GET['year']) ? intval($_GET['year']) : (isset($params['year']) ? intval($params['year']) : date('Y'));
        
        $query = sprintf(
            "SELECT trt.*, t.name as team_name, t.department_id, d.name as department_name
             FROM {$this->table} trt
             LEFT JOIN " . DB_PREFIX . "team t ON trt.team_id = t.id
             LEFT JOIN " . DB_PREFIX . "departments d ON t.department_id = d.id
             WHERE trt.year = %d
             ORDER BY t.department_id ASC, t.name ASC",
            intval($year)
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
}

