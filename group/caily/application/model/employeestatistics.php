<?php
class Employeestatistics extends ApplicationModel {
    
    function __construct() {
        $this->table = DB_PREFIX . 'employee_statistics';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'user_id' => array(),
            'team_id' => array(),
            'period_type' => array(),
            'period_start' => array(),
            'period_end' => array(),
            'revenue' => array(),
            'task_likes' => array(),
            'task_dislikes' => array(),
            'total_drawings_revenue' => array(),
            'drawing_count' => array(),
            'task_count' => array(),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search')),
        );
        
        $this->connect();
    }

    /**
     * Calculate and save statistics for a period
     */
    function calculateStatistics() {
        $period_type = isset($_GET['period_type']) ? $_GET['period_type'] : 'month';
        $period_start = isset($_GET['period_start']) ? $_GET['period_start'] : date('Y-m-01');
        $period_end = isset($_GET['period_end']) ? $_GET['period_end'] : date('Y-m-t');

        // Lấy tất cả (user, team) mà user đang thuộc về
        $userTeams = $this->getUsersByTeam(null);
        
        $results = [];
        
        foreach ($userTeams as $row) {
            $user_internal_id = $row['id'];
            $user_id         = $row['userid'];
            $team_id         = isset($row['team_id']) ? intval($row['team_id']) : null;
            
            // Tính thống kê cho từng cặp (user, team)
            $stats = $this->calculateUserStatistics(
                $user_internal_id,
                $user_id,
                $period_type,
                $period_start,
                $period_end,
                $team_id
            );
            
            // Lưu hoặc update thống kê theo (user, team, period)
            $existing = $this->getExistingStatistics(
                $user_id,
                $team_id,
                $period_type,
                $period_start,
                $period_end
            );
            
            if ($existing) {
                // Update existing - handle NULL for team_id
                $updateData = $stats;
                $updateData['team_id'] = ($stats['team_id'] === null || $stats['team_id'] === '') ? null : intval($stats['team_id']);
                $this->updateStatistics($existing['id'], $updateData);
                $stats['id'] = $existing['id'];
            } else {
                // Insert new - handle NULL for team_id
                $insertData = $stats;
                $insertData['team_id'] = ($stats['team_id'] === null || $stats['team_id'] === '') ? null : intval($stats['team_id']);
                $stats['id'] = $this->insertStatistics($insertData);
            }
            
            $stats['user_name'] = $row['realname'];
            $stats['team_name'] = $row['team_name'] ?? null;
            $results[] = $stats;
        }
        
        return [
            'status' => 'success',
            'message' => count($results) . '件の統計を計算しました',
            'data' => $results
        ];
    }

    /**
     * Calculate statistics for a single user
     */
    private function calculateUserStatistics($id, $user_id, $period_type, $period_start, $period_end , $team_id) {
        // Get task likes/dislikes
        $reactions = $this->getTaskReactions($id, $period_start, $period_end);
        
        // Get drawings revenue
        $drawings = $this->getDrawingsRevenue($user_id, $period_start, $period_end);
        
        // Get task count
        $task_count = $this->getTaskCount($id, $period_start, $period_end);
        
        
        return [
            'user_id' => $user_id,
            'team_id' => ($team_id && $team_id > 0) ? intval($team_id) : null,
            'period_type' => $period_type,
            'period_start' => $period_start,
            'period_end' => $period_end,
            'revenue' => $drawings['total_revenue'] ?? 0,
            'task_likes' => $reactions['likes'] ?? 0,
            'task_dislikes' => $reactions['dislikes'] ?? 0,
            'total_drawings_revenue' => $drawings['total_revenue'] ?? 0,
            'drawing_count' => $drawings['count'] ?? 0,
            'task_count' => $task_count,
        ];
    }

    /**
     * Get task reactions (likes/dislikes) for user's tasks
     */
    private function getTaskReactions($id, $period_start, $period_end) {
        $query = sprintf(
            "SELECT 
                SUM(CASE WHEN tr.type = 'like' THEN 1 ELSE 0 END) as likes,
                SUM(CASE WHEN tr.type = 'dislike' THEN 1 ELSE 0 END) as dislikes
            FROM " . DB_PREFIX . "task_reactions tr
            INNER JOIN " . DB_PREFIX . "tasks t ON tr.task_id = t.id
            WHERE (FIND_IN_SET('%s', t.assigned_to) > 0 OR t.assigned_to = '%s')
            AND DATE(tr.created_at) BETWEEN '%s' AND '%s'",
            $this->quote($id),
            $this->quote($id),
            $this->quote($period_start),
            $this->quote($period_end)
        );
        
        $result = $this->fetchOne($query);
        return [
            'likes' => intval($result['likes'] ?? 0),
            'dislikes' => intval($result['dislikes'] ?? 0)
        ];
    }

    /**
     * Get drawings revenue for user
     */
    private function getDrawingsRevenue($user_id, $period_start, $period_end) {
        $query = sprintf(
            "SELECT 
                COUNT(*) as count,
                COALESCE(SUM(price), 0) as total_revenue
            FROM " . DB_PREFIX . "project_drawings
            WHERE created_by LIKE '%%%s%%'
            AND DATE(created_at) BETWEEN '%s' AND '%s'
            AND price IS NOT NULL",
            $this->quote($user_id),
            $this->quote($period_start),
            $this->quote($period_end)
        );
        
        $result = $this->fetchOne($query);
        return [
            'count' => intval($result['count'] ?? 0),
            'total_revenue' => floatval($result['total_revenue'] ?? 0)
        ];
    }

    /**
     * Get task count for user
     */
    private function getTaskCount($user_id, $period_start, $period_end) {
        $query = sprintf(
            "SELECT COUNT(*) as count
            FROM " . DB_PREFIX . "tasks
            WHERE (FIND_IN_SET('%s', assigned_to) > 0 OR assigned_to = '%s')
            AND DATE(created_at) BETWEEN '%s' AND '%s'",
            $this->quote($user_id),
            $this->quote($user_id),
            $this->quote($period_start),
            $this->quote($period_end)
        );
        
        $result = $this->fetchOne($query);
        return intval($result['count'] ?? 0);
    }

    /**
     * Get users by team (or all (user, team) pairs if team_id is null)
     */
    private function getUsersByTeam($team_id = null) {
        if ($team_id) {
            $query = sprintf(
                "SELECT DISTINCT 
                    u.id as id,
                    u.userid as userid,
                    u.realname,
                    tm.team_id as team_id,
                    t.name as team_name
                FROM " . DB_PREFIX . "user u
                INNER JOIN " . DB_PREFIX . "team_members tm ON u.id = tm.user_id
                INNER JOIN " . DB_PREFIX . "team t ON tm.team_id = t.id
                WHERE tm.team_id = %d AND t.is_active = 1
                ORDER BY u.id ASC",
                intval($team_id)
            );
        } else {
            // Tất cả cặp (user, team) đang active
            $query = sprintf(
                "SELECT DISTINCT 
                    u.id as id,
                    u.userid as userid,
                    u.realname,
                    tm.team_id as team_id,
                    t.name as team_name
                FROM " . DB_PREFIX . "user u
                INNER JOIN " . DB_PREFIX . "team_members tm ON u.id = tm.user_id
                INNER JOIN " . DB_PREFIX . "team t ON tm.team_id = t.id AND t.is_active = 1
                ORDER BY u.id ASC, tm.team_id ASC"
            );
        }
        
        return $this->fetchAll($query);
    }

    /**
     * Get user's team ID
     */
    private function getUserTeamId($user_id) {
        $query = sprintf(
            "SELECT team_id
            FROM " . DB_PREFIX . "team_members
            WHERE user_id = '%s'
            LIMIT 1",
            $this->quote($user_id)
        );
        
        $result = $this->fetchOne($query);
        return $result ? intval($result['team_id']) : null;
    }
    
    /**
     * Get statistics list with user and team names
     */
    function list() {
        $period_type = isset($_GET['period_type']) ? $_GET['period_type'] : null;
        $period_start = isset($_GET['period_start']) ? $_GET['period_start'] : null;
        $period_end = isset($_GET['period_end']) ? $_GET['period_end'] : null;
        $team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;
        
        $whereArr = [];
        
        if ($period_type) {
            $whereArr[] = sprintf("es.period_type = '%s'", $this->quote($period_type));
        }
        if ($period_start) {
            $whereArr[] = sprintf("es.period_start >= '%s'", $this->quote($period_start));
        }
        if ($period_end) {
            $whereArr[] = sprintf("es.period_end <= '%s'", $this->quote($period_end));
        }
        if ($team_id) {
            $whereArr[] = sprintf("es.team_id = %d", intval($team_id));
        }
        
        $where = !empty($whereArr) ? "WHERE " . implode(" AND ", $whereArr) : "";
        
        $query = sprintf(
            "SELECT es.*, u.realname as user_name, t.name as team_name
            FROM {$this->table} es
            LEFT JOIN " . DB_PREFIX . "user u ON es.user_id = u.userid
            LEFT JOIN " . DB_PREFIX . "team t ON es.team_id = t.id
            %s
            ORDER BY es.period_start DESC, t.name ASC, u.realname ASC",
            $where
        );
        
        return $this->fetchAll($query);
    }

    /**
     * Get existing statistics (per user, team and period)
     */
    private function getExistingStatistics($user_id, $team_id, $period_type, $period_start, $period_end) {
        $whereTeam = $team_id ? sprintf("AND team_id = %d", intval($team_id)) : "AND team_id IS NULL";

        $query = sprintf(
            "SELECT * FROM {$this->table}
            WHERE user_id = '%s'
            AND period_type = '%s'
            AND period_start = '%s'
            AND period_end = '%s'
            %s
            LIMIT 1",
            $this->quote($user_id),
            $this->quote($period_type),
            $this->quote($period_start),
            $this->quote($period_end),
            $whereTeam
        );
        
        return $this->fetchOne($query);
    }
    
    /**
     * Insert statistics with proper NULL handling
     */
    private function insertStatistics($data) {
        $keys = array();
        $values = array();
        
        foreach ($data as $key => $value) {
            $keys[] = $key;
            if ($value === null) {
                $values[] = 'NULL';
            } elseif (is_numeric($value)) {
                $values[] = $value;
            } else {
                $values[] = "'" . $this->quote($value) . "'";
            }
        }
        
        $query = "INSERT INTO {$this->table} (" . implode(", ", $keys) . ") VALUES (" . implode(", ", $values) . ")";
        $result = $this->query($query);
        return $result ? $this->insertid() : false;
    }
    
    /**
     * Update statistics with proper NULL handling
     */
    private function updateStatistics($id, $data) {
        $set = array();
        
        foreach ($data as $key => $value) {
            if ($value === null) {
                $set[] = $key . " = NULL";
            } elseif (is_numeric($value)) {
                $set[] = $key . " = " . $value;
            } else {
                $set[] = $key . " = '" . $this->quote($value) . "'";
            }
        }
        
        $query = "UPDATE {$this->table} SET " . implode(", ", $set) . " WHERE id = " . intval($id);
        return $this->query($query);
    }

    /**
     * Get statistics summary by team
     */
    function getSummaryByTeam() {
        $period_type = isset($_GET['period_type']) ? $_GET['period_type'] : null;
        $period_start = isset($_GET['period_start']) ? $_GET['period_start'] : null;
        $period_end = isset($_GET['period_end']) ? $_GET['period_end'] : null;
        $team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;
        
        $whereArr = [];
        
        if ($period_type) {
            $whereArr[] = sprintf("es.period_type = '%s'", $this->quote($period_type));
        }
        if ($period_start) {
            $whereArr[] = sprintf("es.period_start >= '%s'", $this->quote($period_start));
        }
        if ($period_end) {
            $whereArr[] = sprintf("es.period_end <= '%s'", $this->quote($period_end));
        }
        if ($team_id) {
            $whereArr[] = sprintf("es.team_id = %d", intval($team_id));
        }
        
        $where = !empty($whereArr) ? "WHERE " . implode(" AND ", $whereArr) : "";
        
        $query = sprintf(
            "SELECT 
                t.id as team_id,
                t.name as team_name,
                COUNT(DISTINCT es.user_id) as member_count,
                COALESCE(SUM(es.revenue), 0) as total_revenue,
                COALESCE(SUM(es.task_likes), 0) as total_likes,
                COALESCE(SUM(es.task_dislikes), 0) as total_dislikes,
                COALESCE(SUM(es.total_drawings_revenue), 0) as total_drawings_revenue,
                COALESCE(SUM(es.drawing_count), 0) as total_drawing_count,
                COALESCE(SUM(es.task_count), 0) as total_task_count
            FROM {$this->table} es
            LEFT JOIN " . DB_PREFIX . "team t ON es.team_id = t.id
            %s
            GROUP BY t.id, t.name
            ORDER BY t.name ASC",
            $where
        );
        
        return $this->fetchAll($query);
    }
}

?>

