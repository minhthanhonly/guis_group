<?php
class Employeestatistics extends ApplicationModel {
    
    // Configuration: Enable/disable automatic statistics calculation on page load
    // Set to true to enable auto-calculation, false to disable
    const AUTO_CALCULATE_STATISTICS = false;
    
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
     * Get auto-calculate statistics configuration
     * Returns whether automatic statistics calculation is enabled
     */
    function getAutoCalculateConfig() {
        return [
            'enabled' => self::AUTO_CALCULATE_STATISTICS
        ];
    }

    /**
     * Fiscal year end Y = Jul (Y-1) .. Jun (Y). Resolve from fiscal_year, selected_month (YYYY-MM), or today.
     */
    private function resolveFiscalEndYear() {
        if (isset($_GET['fiscal_year'])) {
            $y = intval($_GET['fiscal_year']);
            if ($y >= 2000 && $y <= 2100) {
                return $y;
            }
        }
        if (isset($_GET['year'])) {
            $y = intval($_GET['year']);
            if ($y >= 2000 && $y <= 2100) {
                return $y;
            }
        }
        if (!empty($_GET['selected_month']) && preg_match('/^(\d{4})-(\d{2})$/', $_GET['selected_month'], $m)) {
            $cal = intval($m[1]);
            $mo = intval($m[2]);
            return ($mo >= 7) ? ($cal + 1) : $cal;
        }
        $mo = intval(date('n'));
        $y = intval(date('Y'));
        return ($mo >= 7) ? ($y + 1) : $y;
    }

    private function getFiscalYearStartDate($fiscalEndYear = null) {
        if ($fiscalEndYear === null) {
            $fiscalEndYear = $this->resolveFiscalEndYear();
        }
        return sprintf('%d-07-01', intval($fiscalEndYear) - 1);
    }

    /**
     * Active employees for stats: exclude 退職者 with no quite_date, or quite_date before fiscal year start.
     */
    private function getActiveEmployeeStatsSql($userAlias = 'u') {
        $fiscalStart = $this->getFiscalYearStartDate();
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $userAlias) ?: 'u';
        $retire = RETIRE_GROUP;
        return "(
            (({$a}.quite_date IS NULL) AND {$a}.user_group <> '" . $this->quote($retire) . "')
            OR ({$a}.quite_date >= '" . $this->quote($fiscalStart) . "')
        )";
    }

    /**
     * Calculate and save statistics for a period
     */
    function calculateStatistics() {
        $period_type = isset($_GET['period_type']) ? $_GET['period_type'] : 'month';
        $months = isset($_GET['months']) ? intval($_GET['months']) : 12;

        // Lấy tất cả (user, team) mà user đang thuộc về
        $userTeams = $this->getUsersByTeam(null);
        
        $results = [];
        
        if ($period_type === 'year') {
            // Calculate statistics for each year in the last N months (convert to years)
            $years = ceil($months / 12);
            $current_date = new DateTime();
            for ($i = 0; $i < $years; $i++) {
                $date = clone $current_date;
                $date->modify("-$i years");
                
                $period_start = $date->format('Y-01-01');
                $period_end = $date->format('Y-12-31');
                
                foreach ($userTeams as $row) {
                    $user_internal_id = $row['id'];
                    $user_id         = $row['userid'];
                    $team_id         = isset($row['team_id']) ? intval($row['team_id']) : null;
                    
                    // Tính thống kê cho từng cặp (user, team) trong năm này
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
            }
        } else {
            // Calculate statistics for each month in the last N months
            $current_date = new DateTime();
            // Set to first day of current month to avoid issues with modify()
            $current_date->modify('first day of this month');
            $current_date->setTime(0, 0, 0);
            
            for ($i = 0; $i < $months; $i++) {
                $date = clone $current_date;
                if ($i > 0) {
                    $date->modify("-$i months");
                }
                
                $period_start = $date->format('Y-m-01');
                $period_end = $date->format('Y-m-t');
                
                foreach ($userTeams as $row) {
                    $user_internal_id = $row['id'];
                    $user_id         = $row['userid'];
                    $team_id         = isset($row['team_id']) ? intval($row['team_id']) : null;
                    
                    // Tính thống kê cho từng cặp (user, team) trong tháng này
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
            }
        }
        
        return [
            'status' => 'success',
            'message' => count($results) . '件の統計を計算しました',
            'data' => $results
        ];
    }

    /**
     * Generate sample statistics data for simulation (dummy data)
     * This does NOT use real task/drawing data, but random values instead.
     */
    function generateSampleData() {
        $period_type = isset($_GET['period_type']) ? $_GET['period_type'] : 'month';
        $months = isset($_GET['months']) ? intval($_GET['months']) : 12;

        // Get all (user, team) pairs
        $userTeams = $this->getUsersByTeam(null);
        
        if (empty($userTeams)) {
            return [
                'status' => 'error',
                'message' => '従業員が見つかりません。'
            ];
        }

        $results = [];

        // For simplicity, generate sample data per month for the last N months
        $current_date = new DateTime();
        $current_date->modify('first day of this month');
        $current_date->setTime(0, 0, 0);

        for ($i = 0; $i < $months; $i++) {
            $date = clone $current_date;
            if ($i > 0) {
                $date->modify("-$i months");
            }

            $period_start = $date->format('Y-m-01');
            $period_end   = $date->format('Y-m-t');

            foreach ($userTeams as $row) {
                $user_internal_id = $row['id'];
                $user_id         = $row['userid'];
                $team_id         = isset($row['team_id']) ? intval($row['team_id']) : null;

                // Generate random sample stats
                $likes      = rand(0, 50);
                $dislikes   = rand(0, 10);
                $drawCount  = rand(0, 30);
                $taskCount  = rand(0, 100);
                $revenue    = $drawCount > 0 ? rand(50000, 200000) : 0;

                $stats = [
                    'user_id'                => $user_id,
                    'team_id'                => ($team_id && $team_id > 0) ? intval($team_id) : null,
                    'period_type'            => 'month',
                    'period_start'           => $period_start,
                    'period_end'             => $period_end,
                    'revenue'                => $revenue,
                    'task_likes'             => $likes,
                    'task_dislikes'          => $dislikes,
                    'total_drawings_revenue' => $revenue,
                    'drawing_count'          => $drawCount,
                    'task_count'             => $taskCount,
                ];

                // Upsert based on (user_id, team_id, period)
                $existing = $this->getExistingStatistics(
                    $user_id,
                    $team_id,
                    'month',
                    $period_start,
                    $period_end
                );

                if ($existing) {
                    $updateData = $stats;
                    $updateData['team_id'] = ($stats['team_id'] === null || $stats['team_id'] === '') ? null : intval($stats['team_id']);
                    $this->updateStatistics($existing['id'], $updateData);
                    $stats['id'] = $existing['id'];
                } else {
                    $insertData = $stats;
                    $insertData['team_id'] = ($stats['team_id'] === null || $stats['team_id'] === '') ? null : intval($stats['team_id']);
                    $stats['id'] = $this->insertStatistics($insertData);
                }

                $stats['user_name'] = $row['realname'];
                $stats['team_name'] = $row['team_name'] ?? null;
                $results[] = $stats;
            }
        }

        return [
            'status'  => 'success',
            'message' => count($results) . '件のサンプル統計データを追加・更新しました',
            'data'    => $results,
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
     * Based on task actual_end_date
     */
    private function getTaskReactions($id, $period_start, $period_end) {
        $query = sprintf(
            "SELECT 
                SUM(CASE WHEN tr.type = 'like' THEN 1 ELSE 0 END) as likes,
                SUM(CASE WHEN tr.type = 'dislike' THEN 1 ELSE 0 END) as dislikes
            FROM " . DB_PREFIX . "task_reactions tr
            INNER JOIN " . DB_PREFIX . "tasks t ON tr.task_id = t.id
            WHERE (FIND_IN_SET('%s', t.assigned_to) > 0 OR t.assigned_to = '%s')
            AND (
                (t.actual_end_date IS NOT NULL AND DATE(t.actual_end_date) BETWEEN '%s' AND '%s')
                OR (t.actual_end_date IS NULL AND t.due_date IS NOT NULL AND DATE(t.due_date) BETWEEN '%s' AND '%s')
            )",
            $this->quote($id),
            $this->quote($id),
            $this->quote($period_start),
            $this->quote($period_end),
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
     * Get drawings revenue for user (only approved drawings)
     */
    private function getDrawingsRevenue($user_id, $period_start, $period_end) {
        $query = sprintf(
            "SELECT 
                COUNT(*) as count,
                COALESCE(SUM(price), 0) as total_revenue
            FROM " . DB_PREFIX . "project_drawings
            WHERE (FIND_IN_SET('%s', created_by) > 0 OR created_by = '%s')
            AND DATE(created_at) BETWEEN '%s' AND '%s'
            AND price IS NOT NULL
            AND status = 'approved'",
            $this->quote($user_id),
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
     * Based on task actual_end_date
     */
    private function getTaskCount($user_id, $period_start, $period_end) {
        $query = sprintf(
            "SELECT COUNT(*) as count
            FROM " . DB_PREFIX . "tasks
            WHERE (FIND_IN_SET('%s', assigned_to) > 0 OR assigned_to = '%s')
            AND (
                (actual_end_date IS NOT NULL AND DATE(actual_end_date) BETWEEN '%s' AND '%s')
                OR (actual_end_date IS NULL AND due_date IS NOT NULL AND DATE(due_date) BETWEEN '%s' AND '%s')
            )",
            $this->quote($user_id),
            $this->quote($user_id),
            $this->quote($period_start),
            $this->quote($period_end),
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
                AND " . $this->getActiveEmployeeStatsSql('u') . "
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
                WHERE " . $this->getActiveEmployeeStatsSql('u') . "
                ORDER BY u.id ASC, tm.team_id ASC"
            );
        }
        
        return $this->fetchAll($query);
    }

    /**
     * Get user's team ID.
     * team_members.user_id is numeric (user.id), not userid string.
     */
    private function getUserTeamId($user_id) {
        $uid = intval($user_id);
        if ($uid <= 0) return null;
        $query = sprintf(
            "SELECT team_id FROM " . DB_PREFIX . "team_members WHERE user_id = %d LIMIT 1",
            $uid
        );
        $result = $this->fetchOne($query);
        return $result ? intval($result['team_id']) : null;
    }
    
    /**
     * Get statistics list with user and team names
     */
    function list() {
        $period_type = isset($_GET['period_type']) ? $_GET['period_type'] : 'month';
        $months = isset($_GET['months']) ? intval($_GET['months']) : 12;
        $team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;
        $user_id = isset($_GET['user_id']) ? $this->quote($_GET['user_id']) : null;
        
        // Calculate date range for last N months
        $end_date = date('Y-m-t'); // Last day of current month
        $start_date = date('Y-m-01', strtotime("-$months months")); // First day of N months ago
        
        $whereArr = [];
        
        if ($period_type) {
            $whereArr[] = sprintf("es.period_type = '%s'", $this->quote($period_type));
        }
        
        // Filter by date range (last N months)
        $whereArr[] = sprintf("es.period_start >= '%s'", $this->quote($start_date));
        $whereArr[] = sprintf("es.period_end <= '%s'", $this->quote($end_date));
        
        if ($team_id) {
            $whereArr[] = sprintf("es.team_id = %d", intval($team_id));
        }
        
        if ($user_id) {
            $whereArr[] = sprintf("es.user_id = '%s'", $user_id);
        }

        $whereArr[] = $this->getActiveEmployeeStatsSql('u');
        
        $where = !empty($whereArr) ? "WHERE " . implode(" AND ", $whereArr) : "";
        
        $query = sprintf(
            "SELECT es.*, u.realname as user_name, t.name as team_name
            FROM {$this->table} es
            INNER JOIN " . DB_PREFIX . "user u ON es.user_id = u.userid
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
     * Insert statistics with proper NULL handling and duplicate key handling
     */
    private function insertStatistics($data) {
        $keys = array();
        $values = array();
        $updates = array();
        
        foreach ($data as $key => $value) {
            $keys[] = $key;
            if ($value === null) {
                $values[] = 'NULL';
            } elseif (is_numeric($value)) {
                $values[] = $value;
            } else {
                $values[] = "'" . $this->quote($value) . "'";
            }
            
            // Prepare update clause for ON DUPLICATE KEY UPDATE
            if ($key !== 'user_id' && $key !== 'period_type' && $key !== 'period_start' && $key !== 'period_end') {
                if ($value === null) {
                    $updates[] = $key . " = NULL";
                } elseif (is_numeric($value)) {
                    $updates[] = $key . " = " . $value;
                } else {
                    $updates[] = $key . " = '" . $this->quote($value) . "'";
                }
            }
        }
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE to handle race conditions
        $query = "INSERT INTO {$this->table} (" . implode(", ", $keys) . ") VALUES (" . implode(", ", $values) . ")";
        if (!empty($updates)) {
            $query .= " ON DUPLICATE KEY UPDATE " . implode(", ", $updates) . ", updated_at = CURRENT_TIMESTAMP";
        }
        
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
        $period_type = isset($_GET['period_type']) ? $_GET['period_type'] : 'month';
        $months = isset($_GET['months']) ? intval($_GET['months']) : 12;
        $team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;
        
        // Calculate date range for last N months
        $end_date = date('Y-m-t'); // Last day of current month
        $start_date = date('Y-m-01', strtotime("-$months months")); // First day of N months ago
        
        $whereArr = [];
        
        if ($period_type) {
            $whereArr[] = sprintf("es.period_type = '%s'", $this->quote($period_type));
        }
        
        // Filter by date range (last N months)
        $whereArr[] = sprintf("es.period_start >= '%s'", $this->quote($start_date));
        $whereArr[] = sprintf("es.period_end <= '%s'", $this->quote($end_date));
        
        if ($team_id) {
            $whereArr[] = sprintf("es.team_id = %d", intval($team_id));
        }

        $whereArr[] = $this->getActiveEmployeeStatsSql('u');
        
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
            INNER JOIN " . DB_PREFIX . "user u ON es.user_id = u.userid
            LEFT JOIN " . DB_PREFIX . "team t ON es.team_id = t.id
            %s
            GROUP BY t.id, t.name
            ORDER BY t.name ASC",
            $where
        );
        
        return $this->fetchAll($query);
    }

    /**
     * Annual summary per team with scoring and ranking
     */
    function getAnnualSummary($params = null) {
        $year = isset($_GET['year']) ? intval($_GET['year']) : (isset($params['year']) ? intval($params['year']) : intval(date('Y')));
        if ($year < 2000 || $year > 2100) {
            $year = intval(date('Y'));
        }
        // Fiscal year: Jul (previous year) -> Jun (selected year)
        $startFiscalYear = $year - 1;
        $startDate = sprintf("%d-07-01", $startFiscalYear);
        $endDate   = sprintf("%d-06-30", $year);

        // 1) Load active teams
        $teams = $this->fetchAll(
            "SELECT t.id, t.name, t.department_id
             FROM " . DB_PREFIX . "team t
             WHERE t.is_active = 1
             ORDER BY t.department_id ASC, t.name ASC"
        );

        // 2) Load revenue targets for both fiscal parts: previous year (Jul-Dec) and current year (Jan-Jun)
        $targetRows = $this->fetchAll(
            "SELECT team_id, year, yearly_target, monthly_target
             FROM " . DB_PREFIX . "team_revenue_targets
             WHERE year IN (" . intval($startFiscalYear) . ", " . intval($year) . ")"
        );
        $targetsByYear = [];
        foreach ($targetRows as $row) {
            $monthly = isset($row['monthly_target']) ? floatval($row['monthly_target']) : 0;
            $yearly  = isset($row['yearly_target']) ? floatval($row['yearly_target']) : 0;
            if ($monthly <= 0 && $yearly > 0) {
                $monthly = $yearly / 12.0;
            }
            if ($yearly <= 0 && $monthly > 0) {
                $yearly = $monthly * 12.0;
            }
            $targetsByYear[intval($row['year'])][intval($row['team_id'])] = [
                'monthly' => $monthly,
                'yearly'  => $yearly
            ];
        }

        // Helper to get monthly target for a team in a specific calendar year
        $getMonthlyTarget = function($teamId, $yearKey) use ($targetsByYear) {
            if (isset($targetsByYear[$yearKey][$teamId])) {
                $t = $targetsByYear[$yearKey][$teamId];
                if ($t['monthly'] > 0) return $t['monthly'];
                if ($t['yearly'] > 0) return $t['yearly'] / 12.0;
            }
            return 0;
        };

        $activeUserSql = $this->getActiveEmployeeStatsSql('u');

        // 3) Load monthly statistics within the fiscal window (period_type = month)
        $monthlyRows = $this->fetchAll(
            "SELECT 
                es.team_id,
                DATE_FORMAT(es.period_start, '%Y-%m') AS ym,
                SUM(es.revenue) AS revenue,
                SUM(es.task_likes) AS likes,
                SUM(es.task_dislikes) AS dislikes,
                SUM(es.total_drawings_revenue) AS drawings_revenue,
                SUM(es.drawing_count) AS drawing_count,
                SUM(es.task_count) AS task_count
             FROM {$this->table} es
             INNER JOIN " . DB_PREFIX . "user u ON es.user_id = u.userid
             WHERE es.period_type = 'month'
               AND es.period_start >= '" . $this->quote($startDate) . "'
               AND es.period_start <= '" . $this->quote($endDate) . "'
               AND " . $activeUserSql . "
             GROUP BY es.team_id, ym"
        );

        // 4) Aggregate yearly totals
        $yearRows = $this->fetchAll(
            "SELECT 
                es.team_id,
                SUM(es.revenue) AS revenue_year,
                SUM(es.task_likes) AS total_likes,
                SUM(es.task_dislikes) AS total_dislikes,
                SUM(es.drawing_count) AS total_drawing_count,
                SUM(es.task_count) AS total_task_count
             FROM {$this->table} es
             INNER JOIN " . DB_PREFIX . "user u ON es.user_id = u.userid
             WHERE es.period_type = 'month'
               AND es.period_start >= '" . $this->quote($startDate) . "'
               AND es.period_start <= '" . $this->quote($endDate) . "'
               AND " . $activeUserSql . "
             GROUP BY es.team_id"
        );
        $yearTotals = [];
        foreach ($yearRows as $row) {
            $tid = $row['team_id'] ? intval($row['team_id']) : 0;
            $yearTotals[$tid] = $row;
        }

        // 5) Prepare monthly breakdown map per team
        $monthlyMap = [];
        foreach ($monthlyRows as $row) {
            $tid = $row['team_id'] ? intval($row['team_id']) : 0;
            if (!isset($monthlyMap[$tid])) {
                $monthlyMap[$tid] = [];
            }
            $monthlyMap[$tid][$row['ym']] = $row;
        }

        $results = [];
        foreach ($teams as $team) {
            $tid = intval($team['id']);
            // Compute target yearly across fiscal window (Jul-Dec prev year, Jan-Jun current year)
            $targetMonthlyPrev = $getMonthlyTarget($tid, $startFiscalYear);
            $targetMonthlyCurr = $getMonthlyTarget($tid, $year);

            // If only one side has target, apply it for all 12 months (spread evenly)
            if ($targetMonthlyPrev <= 0 && $targetMonthlyCurr > 0) {
                $targetMonthlyPrev = $targetMonthlyCurr;
            } elseif ($targetMonthlyCurr <= 0 && $targetMonthlyPrev > 0) {
                $targetMonthlyCurr = $targetMonthlyPrev;
            }

            $targetYearly = $targetMonthlyPrev * 6 + $targetMonthlyCurr * 6;

            // Build 12 months data
            $months = [];
            $monthsHit = 0;
            $monthsMiss = 0;
            $monthsWithTarget = 0;
            $bestMonth = null;
            $worstMonth = null;
            // Iterate months from Jul (prev fiscal) to Jun (current)
            for ($i = 0; $i < 12; $i++) {
                $monthTs = strtotime($startDate . " +" . $i . " months");
                $ym = date('Y-m', $monthTs);
                $label = date('Y年n月', $monthTs);

                $row = isset($monthlyMap[$tid][$ym]) ? $monthlyMap[$tid][$ym] : null;
                $revenue = $row ? floatval($row['revenue']) : 0;

                $monthNum = intval(date('n', $monthTs));
                $yearOfMonth = intval(date('Y', $monthTs));
                // Target: use previous fiscal year targets for Jul-Dec, current year targets for Jan-Jun
                $target = ($monthNum >= 7)
                    ? $getMonthlyTarget($tid, $yearOfMonth) // Jul-Dec uses that calendar year (startFiscalYear)
                    : $getMonthlyTarget($tid, $year);       // Jan-Jun uses selected fiscal end year
                // Fallback: if missing, try opposite year target; if still missing, keep 0
                if ($target <= 0) {
                    $target = ($monthNum >= 7)
                        ? $getMonthlyTarget($tid, $year)    // fallback to current year
                        : $getMonthlyTarget($tid, $startFiscalYear); // fallback to prev year
                }

                $pct = ($target > 0) ? ($revenue / $target * 100) : null;

                // Count all 12 months; if no target, treat as miss to reflect absence
                $monthsWithTarget++;
                if ($target > 0 && $revenue >= $target) {
                    $monthsHit++;
                } else {
                    $monthsMiss++;
                }

                if ($pct !== null) {
                    // Best month: allow 0 as usual (for completeness)
                    if ($bestMonth === null || $pct > $bestMonth['pct']) {
                        $bestMonth = ['label' => $label, 'revenue' => $revenue, 'target' => $target, 'pct' => $pct];
                    }
                    // Worst month: ignore months with 0 revenue
                    if ($revenue > 0 && ($worstMonth === null || $pct < $worstMonth['pct'])) {
                        $worstMonth = ['label' => $label, 'revenue' => $revenue, 'target' => $target, 'pct' => $pct];
                    }
                } else {
                    // If no target, track by revenue for worst/best fallback
                    if ($bestMonth === null || $revenue > $bestMonth['revenue']) {
                        $bestMonth = ['label' => $label, 'revenue' => $revenue, 'target' => $target, 'pct' => null];
                    }
                    // Worst month: ignore months with 0 revenue
                    if ($revenue > 0 && ($worstMonth === null || $revenue < $worstMonth['revenue'])) {
                        $worstMonth = ['label' => $label, 'revenue' => $revenue, 'target' => $target, 'pct' => null];
                    }
                }

                $months[] = [
                    'label' => $label,
                    'revenue' => $revenue,
                    'target' => $target,
                    'pct' => $pct
                ];
            }

            $yearData = isset($yearTotals[$tid]) ? $yearTotals[$tid] : [
                'revenue_year' => 0,
                'total_likes' => 0,
                'total_dislikes' => 0,
                'total_drawing_count' => 0,
                'total_task_count' => 0
            ];
            $revenueYear = floatval($yearData['revenue_year']);
            $pctYear = ($targetYearly > 0) ? ($revenueYear / $targetYearly * 100) : 0;

            // Scores
            $revenueScore = ($targetYearly > 0) ? min(100, ($revenueYear / $targetYearly * 100)) : 0;
            $stabilityScore = ($monthsWithTarget > 0) ? min(100, ($monthsHit / $monthsWithTarget) * 100) : 0;
            $likes = intval($yearData['total_likes']);
            $dislikes = intval($yearData['total_dislikes']);
            $reactions = $likes + $dislikes;
            $qualityScore = ($reactions > 0) ? ($likes / $reactions * 100) : 50;

            // Scoring weights (without Productivity):
            // Revenue 60%, Stability 20%, Quality 20%
            $score = round(
                $revenueScore * 0.6 +
                $stabilityScore * 0.2 +
                $qualityScore * 0.2,
                1
            );
            $rank = '-';
            if ($score >= 90) $rank = 'A';
            else if ($score >= 75) $rank = 'B';
            else if ($score >= 50) $rank = 'C';
            else $rank = 'D';

            $results[] = [
                'team_id' => $tid,
                'team_name' => $team['name'],
                'revenue_year' => $revenueYear,
                'target_year' => $targetYearly,
                'pct_year' => $pctYear,
                'months_hit' => $monthsHit,
                'months_miss' => $monthsMiss,
                'months' => $months,
                'best_month' => $bestMonth,
                'worst_month' => $worstMonth,
                'total_likes' => $likes,
                'total_dislikes' => $dislikes,
                'total_drawing_count' => intval($yearData['total_drawing_count']),
                'total_task_count' => intval($yearData['total_task_count']),
                'score' => $score,
                'rank' => $rank
            ];
        }

        return $results;
    }
    
    /**
     * Delete statistics for last N months
     */
    function deleteStatistics() {
        $months = isset($_GET['months']) ? intval($_GET['months']) : 12;
        
        // Calculate date range for last N months
        $end_date = date('Y-m-t'); // Last day of current month
        $start_date = date('Y-m-01', strtotime("-$months months")); // First day of N months ago
        
        // Count records before deletion
        $count_query = sprintf(
            "SELECT COUNT(*) as count FROM {$this->table}
            WHERE period_start >= '%s'
            AND period_end <= '%s'",
            $this->quote($start_date),
            $this->quote($end_date)
        );
        $count_result = $this->fetchOne($count_query);
        $deleted_count = intval($count_result['count'] ?? 0);
        
        // Delete statistics within the date range
        $query = sprintf(
            "DELETE FROM {$this->table}
            WHERE period_start >= '%s'
            AND period_end <= '%s'",
            $this->quote($start_date),
            $this->quote($end_date)
        );
        
        $this->query($query);
        
        return [
            'status' => 'success',
            'message' => $deleted_count . '件の統計データを削除しました',
            'deleted_count' => $deleted_count
        ];
    }
}

?>

