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
        $isCli = (php_sapi_name() === 'cli');
        @set_time_limit($isCli ? 0 : 300);
        @ini_set('memory_limit', $isCli ? '512M' : '256M');

        $period_type = isset($_GET['period_type']) ? $_GET['period_type'] : 'month';
        $months = isset($_GET['months']) ? intval($_GET['months']) : 12;
        $months = max(1, $months);
        if ($isCli) {
            $months = min($months, 120);
        } else {
            $months = min($months, 12);
        }

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
                if (strcmp($period_end, $this->getStatisticsMinDate()) < 0) {
                    continue;
                }
                $period_start = $this->clampStatisticsStartDate($period_start);
                
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
                if (!$this->isStatisticsMonthAllowed(substr($period_start, 0, 7))) {
                    continue;
                }
                
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
            if (!$this->isStatisticsMonthAllowed(substr($period_start, 0, 7))) {
                continue;
            }

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
     * Get drawings revenue for user (completed drawings on delivered projects only)
     */
    private function getDrawingsRevenue($user_id, $period_start, $period_end) {
        $query = sprintf(
            "SELECT 
                COUNT(*) as count,
                COALESCE(SUM(pd.price), 0) as total_revenue
            FROM " . DB_PREFIX . "project_drawings pd
            INNER JOIN " . DB_PREFIX . "projects p ON pd.project_id = p.id
            WHERE (FIND_IN_SET('%s', pd.created_by) > 0 OR pd.created_by = '%s')
            AND pd.completed_at IS NOT NULL
            AND DATE(pd.completed_at) BETWEEN '%s' AND '%s'
            AND pd.price IS NOT NULL
            AND %s",
            $this->quote($user_id),
            $this->quote($user_id),
            $this->quote($period_start),
            $this->quote($period_end),
            $this->getDrawingRevenueEligibilitySql('pd', 'p')
        );
        
        $result = $this->fetchOne($query);
        return [
            'count' => intval($result['count'] ?? 0),
            'total_revenue' => floatval($result['total_revenue'] ?? 0)
        ];
    }

    /**
     * Get task count for user
     * Based on task actual_end_date; excludes todo and cancelled
     */
    private function getTaskCount($user_id, $period_start, $period_end) {
        $query = sprintf(
            "SELECT COUNT(*) as count
            FROM " . DB_PREFIX . "tasks t
            WHERE (FIND_IN_SET('%s', t.assigned_to) > 0 OR t.assigned_to = '%s')
            AND %s
            AND (
                (t.actual_end_date IS NOT NULL AND DATE(t.actual_end_date) BETWEEN '%s' AND '%s')
                OR (t.actual_end_date IS NULL AND t.due_date IS NOT NULL AND DATE(t.due_date) BETWEEN '%s' AND '%s')
            )",
            $this->quote($user_id),
            $this->quote($user_id),
            $this->getTaskCountStatusSql('t'),
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
     * Monthly periods for employee list (single selected month or each month in range).
     */
    private function getListPeriods($period_type, $months, $start_date, $end_date, $ignoreSelectedMonth = false) {
        if ($period_type !== 'month') {
            return [[
                'start' => $start_date,
                'end' => $end_date
            ]];
        }

        if (
            !$ignoreSelectedMonth
            && !empty($_GET['selected_month'])
            && preg_match('/^\d{4}-\d{2}$/', $_GET['selected_month'])
        ) {
            return [[
                'start' => $start_date,
                'end' => $end_date
            ]];
        }

        $periods = [];
        $cursor = new DateTime(date('Y-m-01', strtotime($start_date)));
        $endMonth = new DateTime(date('Y-m-01', strtotime($end_date)));

        while ($cursor <= $endMonth) {
            $ym = $cursor->format('Y-m');
            if ($this->isStatisticsMonthAllowed($ym)) {
                $periods[] = [
                    'start' => $cursor->format('Y-m-01'),
                    'end' => $cursor->format('Y-m-t')
                ];
            }
            $cursor->modify('+1 month');
        }

        if (empty($periods)) {
            $periods[] = [
                'start' => $start_date,
                'end' => $end_date
            ];
        }

        return array_reverse($periods);
    }

    /**
     * Active team members for employee statistics list.
     */
    private function getListMembers($team_id = null, $department_id = null, $user_id = null) {
        $whereArr = [
            $this->getActiveEmployeeStatsSql('u'),
            't.is_active = 1'
        ];

        if ($team_id) {
            $whereArr[] = sprintf('tm.team_id = %d', intval($team_id));
        }
        if ($department_id) {
            $whereArr[] = sprintf('t.department_id = %d', intval($department_id));
        }
        if ($user_id) {
            $whereArr[] = sprintf("u.userid = '%s'", $this->quote($user_id));
        }

        $query = sprintf(
            "SELECT DISTINCT
                u.userid,
                u.realname,
                u.user_group,
                tm.team_id,
                t.name AS team_name,
                t.department_id,
                d.name AS department_name
            FROM " . DB_PREFIX . "user u
            INNER JOIN " . DB_PREFIX . "team_members tm ON u.id = tm.user_id
            INNER JOIN " . DB_PREFIX . "team t ON tm.team_id = t.id
            LEFT JOIN " . DB_PREFIX . "departments d ON t.department_id = d.id
            WHERE %s
            ORDER BY t.name ASC, u.realname ASC",
            implode(' AND ', $whereArr)
        );

        return $this->fetchAll($query);
    }

    /**
     * Get statistics list with user and team names (includes members with zero activity).
     */
    function list() {
        $period_type = isset($_GET['period_type']) ? $_GET['period_type'] : 'month';
        $months = isset($_GET['months']) ? intval($_GET['months']) : 12;
        $team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;
        $department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : null;
        $user_id = isset($_GET['user_id']) ? $this->quote($_GET['user_id']) : null;
        
        $range = $this->getStatisticsDateRange($months);
        $start_date = $range['start_date'];
        $end_date = $range['end_date'];

        $members = $this->getListMembers($team_id, $department_id, $user_id);
        if (empty($members)) {
            return [];
        }

        // Employee monthly chart needs full range; list view uses selected month when set.
        $useFullRange = !empty($user_id);
        if ($useFullRange) {
            $metricStart = $this->clampStatisticsStartDate(date('Y-m-01', strtotime("-$months months")));
            $metricEnd = date('Y-m-t');
            $periods = $this->getListPeriods($period_type, $months, $metricStart, $metricEnd, true);
        } else {
            $metricStart = $start_date;
            $metricEnd = $end_date;
            $periods = $this->getListPeriods($period_type, $months, $start_date, $end_date);
        }

        $workloadMap = $this->getWorkloadByUserPeriod($metricStart, $metricEnd, $team_id, $department_id);
        $metricsMap = $this->getProjectMetricsByUserPeriod($metricStart, $metricEnd, $team_id, $department_id);
        $guiseTimecardMap = $this->getGuiseTimecardMinutesByUserMonthMap($metricStart, $metricEnd);
        $cailyTimecardMap = $this->getCailyTimecardMinutesByUserMonthMap($metricStart, $metricEnd, $members);

        if ($department_id) {
            $deptNameRow = $this->fetchOne(sprintf(
                "SELECT name FROM " . DB_PREFIX . "departments WHERE id = %d LIMIT 1",
                intval($department_id)
            ));
            $filteredDeptName = $deptNameRow['name'] ?? null;
        } else {
            $filteredDeptName = null;
        }

        $rows = [];
        $syntheticId = 1;
        foreach ($periods as $period) {
            $ym = substr($period['start'], 0, 7);
            foreach ($members as $member) {
                $teamId = isset($member['team_id']) ? intval($member['team_id']) : 0;
                $key = $this->getUserTeamPeriodKey($member['userid'], $teamId, $ym);
                $wl = isset($workloadMap[$key]) ? $workloadMap[$key] : $this->emptyWorkloadBreakdown();
                $metrics = isset($metricsMap[$key]) ? $metricsMap[$key] : $this->emptyProjectMetrics();
                $isCailyEmployee = $this->isCailyEmployeeUserGroup($member['user_group'] ?? null);
                $isGuiseEmployee = $this->isGuiseEmployeeUserGroup($member['user_group'] ?? null);
                $timecardKey = $member['userid'] . '|' . $ym;
                if ($isCailyEmployee) {
                    $timecardTotalMinutes = intval($cailyTimecardMap[$timecardKey] ?? 0);
                } elseif ($isGuiseEmployee) {
                    $timecardTotalMinutes = intval($guiseTimecardMap[$timecardKey] ?? 0);
                } else {
                    $timecardTotalMinutes = null;
                }

                $rows[] = [
                    'id' => $syntheticId++,
                    'user_id' => $member['userid'],
                    'team_id' => $teamId ?: null,
                    'period_type' => $period_type,
                    'period_start' => $period['start'],
                    'period_end' => $period['end'],
                    'user_name' => $member['realname'],
                    'team_name' => $member['team_name'] ?? null,
                    'department_name' => $filteredDeptName ?: ($member['department_name'] ?? null),
                    'user_group' => $member['user_group'] ?? null,
                    'revenue' => $metrics['revenue'],
                    'task_likes' => $metrics['task_likes'],
                    'task_dislikes' => $metrics['task_dislikes'],
                    'total_drawings_revenue' => $metrics['total_drawings_revenue'],
                    'drawing_count' => $metrics['drawing_count'],
                    'task_count' => $metrics['task_count'],
                    'timecard_total_minutes' => $timecardTotalMinutes,
                    'total_workload' => $wl['total_workload'],
                    'workload_new' => $wl['workload_new'],
                    'workload_error_fix' => $wl['workload_error_fix'],
                    'workload_change_fix' => $wl['workload_change_fix'],
                    'workload_other' => $wl['workload_other'],
                    'updated_at' => null
                ];
            }
        }

        usort($rows, function ($a, $b) {
            $periodCmp = strcmp($b['period_start'], $a['period_start']);
            if ($periodCmp !== 0) {
                return $periodCmp;
            }
            $teamCmp = strcmp($a['team_name'] ?? '', $b['team_name'] ?? '');
            if ($teamCmp !== 0) {
                return $teamCmp;
            }
            return strcmp($a['user_name'] ?? '', $b['user_name'] ?? '');
        });

        return $rows;
    }

    /**
     * Debug: full CAILY timecard API URL (with userids) for current list filters.
     */
    function getCailyTimecardApiUrl() {
        $months = isset($_GET['months']) ? intval($_GET['months']) : 12;
        $team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;
        $department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : null;
        $user_id = isset($_GET['user_id']) ? $this->quote($_GET['user_id']) : null;

        $range = $this->getStatisticsDateRange($months);
        $start_date = $range['start_date'];
        $end_date = $range['end_date'];

        $members = $this->getListMembers($team_id, $department_id, $user_id);
        if (empty($members)) {
            return ['url' => null];
        }

        $useFullRange = !empty($user_id);
        if ($useFullRange) {
            $metricStart = $this->clampStatisticsStartDate(date('Y-m-01', strtotime("-$months months")));
            $metricEnd = date('Y-m-t');
        } else {
            $metricStart = $start_date;
            $metricEnd = $end_date;
        }

        return [
            'url' => $this->buildCailyTimecardApiRequestUrl($metricStart, $metricEnd, $members)
        ];
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
        $months = isset($_GET['months']) ? intval($_GET['months']) : 12;
        $team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;
        $department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : null;

        $range = $this->getStatisticsDateRange($months);
        $start_date = $range['start_date'];
        $end_date = $range['end_date'];
        
        $whereArr = ["t.is_active = 1"];
        
        if ($team_id) {
            $whereArr[] = sprintf("t.id = %d", intval($team_id));
        }

        if ($department_id) {
            $whereArr[] = sprintf("t.department_id = %d", intval($department_id));
        }

        $where = "WHERE " . implode(" AND ", $whereArr);
        $activeUserSql = $this->getActiveEmployeeStatsSql('u');
        
        $query = sprintf(
            "SELECT 
                t.id as team_id,
                t.name as team_name,
                t.department_id,
                (
                    SELECT COUNT(DISTINCT tm.user_id)
                    FROM " . DB_PREFIX . "team_members tm
                    INNER JOIN " . DB_PREFIX . "user u ON tm.user_id = u.id
                    WHERE tm.team_id = t.id AND %s
                ) as member_count
            FROM " . DB_PREFIX . "team t
            %s
            ORDER BY t.name ASC",
            $activeUserSql,
            $where
        );
        
        $rows = $this->fetchAll($query);
        $workloadMap = $this->getWorkloadByTeam($start_date, $end_date, null, $department_id);
        $metricsMap = $this->getProjectMetricsByTeamMap($start_date, $end_date, $team_id, $department_id);

        foreach ($rows as &$row) {
            $tid = $row['team_id'] ? intval($row['team_id']) : 0;
            $wl = isset($workloadMap[$tid]) ? $workloadMap[$tid] : $this->emptyWorkloadBreakdown();
            $row['total_workload'] = $wl['total_workload'];
            $row['workload_task_count'] = $wl['workload_task_count'];
            $row['workload_new'] = $wl['workload_new'];
            $row['workload_error_fix'] = $wl['workload_error_fix'];
            $row['workload_change_fix'] = $wl['workload_change_fix'];
            $row['workload_other'] = $wl['workload_other'];

            $metrics = isset($metricsMap[$tid]) ? $metricsMap[$tid] : $this->emptyProjectMetrics();
            $row['total_revenue'] = $metrics['total_revenue'];
            $row['total_likes'] = $metrics['total_likes'];
            $row['total_dislikes'] = $metrics['total_dislikes'];
            $row['total_drawings_revenue'] = $metrics['total_drawings_revenue'];
            $row['total_drawing_count'] = $metrics['total_drawing_count'];
            $row['total_task_count'] = $metrics['total_task_count'];
        }
        unset($row);

        return $rows;
    }

    /**
     * Earliest month included in employee statistics (YYYY-MM).
     */
    private function getStatisticsMinMonth() {
        return '2026-06';
    }

    private function getStatisticsMinDate() {
        return $this->getStatisticsMinMonth() . '-01';
    }

    private function clampStatisticsStartDate($start_date) {
        $start = substr((string) $start_date, 0, 10);
        $min = $this->getStatisticsMinDate();
        return strcmp($start, $min) < 0 ? $min : $start;
    }

    private function isStatisticsMonthAllowed($ym) {
        return preg_match('/^\d{4}-\d{2}$/', (string) $ym)
            && strcmp($ym, $this->getStatisticsMinMonth()) >= 0;
    }

    /**
     * Date range for statistics queries (last N months or single selected month).
     */
    private function getStatisticsDateRange($months = 12) {
        if (!empty($_GET['selected_month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['selected_month'])) {
            $ym = $_GET['selected_month'];
            if (!$this->isStatisticsMonthAllowed($ym)) {
                $ym = $this->getStatisticsMinMonth();
            }
            return [
                'start_date' => $ym . '-01',
                'end_date' => date('Y-m-t', strtotime($ym . '-01'))
            ];
        }
        return [
            'start_date' => $this->clampStatisticsStartDate(date('Y-m-01', strtotime("-$months months"))),
            'end_date' => date('Y-m-t')
        ];
    }

    /**
     * Time entry period filter (completed entries by end_time).
     */
    private function getTimeEntryPeriodSql($alias = 'en', $start_date, $end_date) {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 'en';
        return sprintf(
            "%s.end_time IS NOT NULL AND DATE(%s.end_time) BETWEEN '%s' AND '%s'",
            $a,
            $a,
            $this->quote($start_date),
            $this->quote($end_date)
        );
    }

    /**
     * Task period filter (actual_end_date or due_date).
     */
    private function getTaskPeriodSql($alias = 't', $start_date, $end_date) {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 't';
        return sprintf(
            "(
                ({$a}.actual_end_date IS NOT NULL AND DATE({$a}.actual_end_date) BETWEEN '%s' AND '%s')
                OR ({$a}.actual_end_date IS NULL AND {$a}.due_date IS NOT NULL AND DATE({$a}.due_date) BETWEEN '%s' AND '%s')
            )",
            $this->quote($start_date),
            $this->quote($end_date),
            $this->quote($start_date),
            $this->quote($end_date)
        );
    }

    /**
     * Task statuses included in タスク数 (exclude todo and cancelled).
     */
    private function getTaskCountStatusSql($alias = 't') {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 't';
        return sprintf("%s.status NOT IN ('todo', 'cancelled')", $a);
    }

    /**
     * SQL expression for task kind category (new / error fix / change fix / other).
     */
    private function getTaskKindCategorySql($alias = 't') {
        $k = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 't';
        return sprintf(
            "CASE
                WHEN TRIM(COALESCE(%s.task_kind, '')) IN ('新規', '新規作成') THEN 'new'
                WHEN TRIM(COALESCE(%s.task_kind, '')) = '修正(エラー)' THEN 'error_fix'
                WHEN TRIM(COALESCE(%s.task_kind, '')) = '修正(変更)' THEN 'change_fix'
                ELSE 'other'
            END",
            $k,
            $k,
            $k
        );
    }

    /**
     * Filter projects by department (explicit id or any assigned department).
     */
    private function getProjectDepartmentWhereSql($projectAlias = 'p', $department_id = null) {
        $p = preg_replace('/[^a-zA-Z0-9_]/', '', $projectAlias) ?: 'p';
        if ($department_id) {
            return sprintf("%s.department_id = %d", $p, intval($department_id));
        }
        return sprintf("%s.department_id IS NOT NULL", $p);
    }

    /**
     * Scope tasks/drawings to projects in team's department, or an explicit department filter.
     */
    private function getTeamProjectScopeSql($teamAlias = 'te', $projectAlias = 'p', $department_id = null) {
        $te = preg_replace('/[^a-zA-Z0-9_]/', '', $teamAlias) ?: 'te';
        $p = preg_replace('/[^a-zA-Z0-9_]/', '', $projectAlias) ?: 'p';
        if ($department_id) {
            return sprintf("%s.department_id = %d", $p, intval($department_id));
        }
        return sprintf("%s.department_id = %s.department_id", $p, $te);
    }

    /**
     * Scope tasks/drawings to a team's department via subquery (when team id is known).
     */
    private function getTeamIdProjectScopeSql($team_id, $projectAlias = 'p') {
        $p = preg_replace('/[^a-zA-Z0-9_]/', '', $projectAlias) ?: 'p';
        return sprintf(
            "%s.department_id = (SELECT te.department_id FROM " . DB_PREFIX . "team te WHERE te.id = %d)",
            $p,
            intval($team_id)
        );
    }

    /**
     * Drawing statuses that count toward revenue (completed; legacy approved).
     */
    private function getCompletedDrawingStatusSql($alias = 'pd') {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $alias);
        $col = $a !== '' ? $a . '.status' : 'status';
        return sprintf("%s IN ('completed', 'approved')", $col);
    }

    /**
     * Projects that count toward drawing revenue: CAILY or GUIS 納品済み.
     */
    private function getCompletedProjectStatusSql($alias = 'p') {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $alias);
        $prefix = $a !== '' ? $a . '.' : '';
        return sprintf(
            "(%s LIKE '%%納品済み%%' OR %s LIKE '%%納品済み%%')",
            $prefix . 'caily_nouki_status',
            $prefix . 'guis_nouki_status'
        );
    }

    /**
     * Drawing revenue eligibility: completed drawing on a delivered project.
     */
    private function getDrawingRevenueEligibilitySql($drawingAlias = 'pd', $projectAlias = 'p') {
        return $this->getCompletedDrawingStatusSql($drawingAlias)
            . ' AND ' . $this->getCompletedProjectStatusSql($projectAlias);
    }

    /**
     * Drawing period filter (completed_at).
     */
    private function getDrawingPeriodSql($alias = 'pd', $start_date, $end_date) {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 'pd';
        return sprintf(
            "%s.completed_at IS NOT NULL AND DATE(%s.completed_at) BETWEEN '%s' AND '%s'",
            $a,
            $a,
            $this->quote($start_date),
            $this->quote($end_date)
        );
    }

    /**
     * SQL match for drawing creator (created_by stores user.userid string).
     */
    private function getDrawingCreatorJoinSql($drawingAlias = 'pd', $userAlias = 'u') {
        $d = preg_replace('/[^a-zA-Z0-9_]/', '', $drawingAlias) ?: 'pd';
        $u = preg_replace('/[^a-zA-Z0-9_]/', '', $userAlias) ?: 'u';
        return sprintf(
            "(FIND_IN_SET(%s.userid, %s.created_by) > 0 OR %s.created_by = %s.userid)",
            $u,
            $d,
            $d,
            $u
        );
    }

    /**
     * Task count grouped by project department.
     */
    private function getTaskCountByDepartmentMap($start_date, $end_date, $department_id = null) {
        $query = sprintf(
            "SELECT p.department_id, COUNT(*) AS task_count
            FROM " . DB_PREFIX . "tasks t
            INNER JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id
            WHERE %s AND %s AND %s
            GROUP BY p.department_id",
            $this->getProjectDepartmentWhereSql('p', $department_id),
            $this->getTaskPeriodSql('t', $start_date, $end_date),
            $this->getTaskCountStatusSql('t')
        );
        $map = [];
        foreach ($this->fetchAll($query) as $row) {
            $map[intval($row['department_id'])] = intval($row['task_count'] ?? 0);
        }
        return $map;
    }

    /**
     * Task reactions grouped by project department.
     */
    private function getReactionMetricsByDepartmentMap($start_date, $end_date, $department_id = null) {
        $query = sprintf(
            "SELECT p.department_id,
                SUM(CASE WHEN tr.type = 'like' THEN 1 ELSE 0 END) AS likes,
                SUM(CASE WHEN tr.type = 'dislike' THEN 1 ELSE 0 END) AS dislikes
            FROM " . DB_PREFIX . "task_reactions tr
            INNER JOIN " . DB_PREFIX . "tasks t ON tr.task_id = t.id
            INNER JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id
            WHERE %s AND %s
            GROUP BY p.department_id",
            $this->getProjectDepartmentWhereSql('p', $department_id),
            $this->getTaskPeriodSql('t', $start_date, $end_date)
        );
        $map = [];
        foreach ($this->fetchAll($query) as $row) {
            $map[intval($row['department_id'])] = [
                'likes' => intval($row['likes'] ?? 0),
                'dislikes' => intval($row['dislikes'] ?? 0)
            ];
        }
        return $map;
    }

    /**
     * Drawing revenue grouped by project department.
     */
    private function getDrawingMetricsByDepartmentMap($start_date, $end_date, $department_id = null) {
        $query = sprintf(
            "SELECT p.department_id,
                COUNT(*) AS drawing_count,
                COALESCE(SUM(pd.price), 0) AS total_revenue
            FROM " . DB_PREFIX . "project_drawings pd
            INNER JOIN " . DB_PREFIX . "projects p ON pd.project_id = p.id
            WHERE %s
              AND pd.price IS NOT NULL
              AND %s
              AND %s
            GROUP BY p.department_id",
            $this->getDrawingRevenueEligibilitySql('pd', 'p'),
            $this->getProjectDepartmentWhereSql('p', $department_id),
            $this->getDrawingPeriodSql('pd', $start_date, $end_date)
        );
        $map = [];
        foreach ($this->fetchAll($query) as $row) {
            $map[intval($row['department_id'])] = [
                'drawing_count' => intval($row['drawing_count'] ?? 0),
                'total_revenue' => floatval($row['total_revenue'] ?? 0)
            ];
        }
        return $map;
    }

    /**
     * Distinct assignees grouped by project department.
     */
    private function getMemberCountByDepartmentMap($start_date, $end_date, $department_id = null) {
        $assigneeJoin = $this->getTaskAssigneeJoinSql('t', 'u');
        $query = sprintf(
            "SELECT p.department_id, COUNT(DISTINCT u.userid) AS member_count
            FROM " . DB_PREFIX . "tasks t
            INNER JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id
            INNER JOIN " . DB_PREFIX . "user u ON %s
            WHERE %s AND %s AND %s
            GROUP BY p.department_id",
            $assigneeJoin,
            $this->getProjectDepartmentWhereSql('p', $department_id),
            $this->getTaskPeriodSql('t', $start_date, $end_date),
            $this->getActiveEmployeeStatsSql('u')
        );
        $map = [];
        foreach ($this->fetchAll($query) as $row) {
            $map[intval($row['department_id'])] = intval($row['member_count'] ?? 0);
        }
        return $map;
    }

    /**
     * Project-based metrics by team (tasks/reactions/drawings on projects in team department).
     */
    private function getProjectMetricsByTeamMap($start_date, $end_date, $team_id = null, $department_id = null) {
        $teamWhere = ["te.is_active = 1"];
        if ($team_id) {
            $teamWhere[] = sprintf("te.id = %d", intval($team_id));
        }
        if ($department_id) {
            $teamWhere[] = sprintf("te.department_id = %d", intval($department_id));
        }
        $teamWhereSql = implode(' AND ', $teamWhere);
        $assigneeJoin = $this->getTaskAssigneeJoinSql('t', 'u');
        $creatorJoin = $this->getDrawingCreatorJoinSql('pd', 'u');
        $projectScope = $this->getTeamProjectScopeSql('te', 'p', $department_id);

        $taskRows = $this->fetchAll(sprintf(
            "SELECT tm.team_id, COUNT(*) AS task_count
            FROM " . DB_PREFIX . "tasks t
            INNER JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id
            INNER JOIN " . DB_PREFIX . "user u ON %s
            INNER JOIN " . DB_PREFIX . "team_members tm ON u.id = tm.user_id
            INNER JOIN " . DB_PREFIX . "team te ON tm.team_id = te.id
            WHERE %s AND %s AND %s AND %s AND %s
            GROUP BY tm.team_id",
            $assigneeJoin,
            $projectScope,
            $this->getTaskPeriodSql('t', $start_date, $end_date),
            $this->getTaskCountStatusSql('t'),
            $teamWhereSql,
            $this->getActiveEmployeeStatsSql('u')
        ));

        $reactionRows = $this->fetchAll(sprintf(
            "SELECT tm.team_id,
                SUM(CASE WHEN tr.type = 'like' THEN 1 ELSE 0 END) AS likes,
                SUM(CASE WHEN tr.type = 'dislike' THEN 1 ELSE 0 END) AS dislikes
            FROM " . DB_PREFIX . "task_reactions tr
            INNER JOIN " . DB_PREFIX . "tasks t ON tr.task_id = t.id
            INNER JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id
            INNER JOIN " . DB_PREFIX . "user u ON %s
            INNER JOIN " . DB_PREFIX . "team_members tm ON u.id = tm.user_id
            INNER JOIN " . DB_PREFIX . "team te ON tm.team_id = te.id
            WHERE %s AND %s AND %s AND %s
            GROUP BY tm.team_id",
            $assigneeJoin,
            $projectScope,
            $this->getTaskPeriodSql('t', $start_date, $end_date),
            $teamWhereSql,
            $this->getActiveEmployeeStatsSql('u')
        ));

        $drawingRows = $this->fetchAll(sprintf(
            "SELECT tm.team_id,
                COUNT(*) AS drawing_count,
                COALESCE(SUM(pd.price), 0) AS total_revenue
            FROM " . DB_PREFIX . "project_drawings pd
            INNER JOIN " . DB_PREFIX . "projects p ON pd.project_id = p.id
            INNER JOIN " . DB_PREFIX . "user u ON %s
            INNER JOIN " . DB_PREFIX . "team_members tm ON u.id = tm.user_id
            INNER JOIN " . DB_PREFIX . "team te ON tm.team_id = te.id
            WHERE %s
              AND pd.price IS NOT NULL
              AND %s
              AND %s
              AND %s
              AND %s
            GROUP BY tm.team_id",
            $creatorJoin,
            $this->getDrawingRevenueEligibilitySql('pd', 'p'),
            $projectScope,
            $this->getDrawingPeriodSql('pd', $start_date, $end_date),
            $teamWhereSql,
            $this->getActiveEmployeeStatsSql('u')
        ));

        $map = [];
        foreach ($taskRows as $row) {
            $tid = intval($row['team_id']);
            if (!isset($map[$tid])) {
                $map[$tid] = $this->emptyProjectMetrics();
            }
            $map[$tid]['total_task_count'] = intval($row['task_count'] ?? 0);
        }
        foreach ($reactionRows as $row) {
            $tid = intval($row['team_id']);
            if (!isset($map[$tid])) {
                $map[$tid] = $this->emptyProjectMetrics();
            }
            $map[$tid]['total_likes'] = intval($row['likes'] ?? 0);
            $map[$tid]['total_dislikes'] = intval($row['dislikes'] ?? 0);
        }
        foreach ($drawingRows as $row) {
            $tid = intval($row['team_id']);
            if (!isset($map[$tid])) {
                $map[$tid] = $this->emptyProjectMetrics();
            }
            $map[$tid]['total_drawings_revenue'] = floatval($row['total_revenue'] ?? 0);
            $map[$tid]['total_drawing_count'] = intval($row['drawing_count'] ?? 0);
            $map[$tid]['total_revenue'] = floatval($row['total_revenue'] ?? 0);
        }
        return $map;
    }

    /**
     * Project-based metrics by user and month.
     */
    private function getUserTeamPeriodKey($userid, $teamId, $ym) {
        return $userid . '|' . intval($teamId) . '|' . $ym;
    }

    /**
     * Project-based metrics by user, team and month.
     */
    private function getProjectMetricsByUserPeriod($start_date, $end_date, $team_id = null, $department_id = null) {
        $whereArr = [
            $this->getTeamProjectScopeSql('te', 'p', $department_id),
            $this->getTaskPeriodSql('t', $start_date, $end_date),
            "te.is_active = 1",
            $this->getActiveEmployeeStatsSql('u')
        ];
        if ($team_id) {
            $whereArr[] = sprintf("tm.team_id = %d", intval($team_id));
        }
        if ($department_id) {
            $whereArr[] = sprintf("te.department_id = %d", intval($department_id));
        }
        $where = "WHERE " . implode(' AND ', $whereArr);
        $assigneeJoin = $this->getTaskAssigneeJoinSql('t', 'u');
        $taskWhere = "WHERE " . implode(' AND ', array_merge($whereArr, [$this->getTaskCountStatusSql('t')]));

        $taskRows = $this->fetchAll(sprintf(
            "SELECT u.userid, tm.team_id,
                DATE_FORMAT(COALESCE(t.actual_end_date, t.due_date), '%%Y-%%m') AS ym,
                COUNT(*) AS task_count
            FROM " . DB_PREFIX . "tasks t
            INNER JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id
            INNER JOIN " . DB_PREFIX . "user u ON %s
            INNER JOIN " . DB_PREFIX . "team_members tm ON u.id = tm.user_id
            INNER JOIN " . DB_PREFIX . "team te ON tm.team_id = te.id
            %s
            GROUP BY u.userid, tm.team_id, ym",
            $assigneeJoin,
            $taskWhere
        ));

        $reactionRows = $this->fetchAll(sprintf(
            "SELECT u.userid, tm.team_id,
                DATE_FORMAT(COALESCE(t.actual_end_date, t.due_date), '%%Y-%%m') AS ym,
                SUM(CASE WHEN tr.type = 'like' THEN 1 ELSE 0 END) AS likes,
                SUM(CASE WHEN tr.type = 'dislike' THEN 1 ELSE 0 END) AS dislikes
            FROM " . DB_PREFIX . "task_reactions tr
            INNER JOIN " . DB_PREFIX . "tasks t ON tr.task_id = t.id
            INNER JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id
            INNER JOIN " . DB_PREFIX . "user u ON %s
            INNER JOIN " . DB_PREFIX . "team_members tm ON u.id = tm.user_id
            INNER JOIN " . DB_PREFIX . "team te ON tm.team_id = te.id
            %s
            GROUP BY u.userid, tm.team_id, ym",
            $assigneeJoin,
            $where
        ));

        $drawingWhereArr = [
            $this->getTeamProjectScopeSql('te', 'p', $department_id),
            $this->getDrawingPeriodSql('pd', $start_date, $end_date),
            $this->getDrawingRevenueEligibilitySql('pd', 'p'),
            "pd.price IS NOT NULL",
            "te.is_active = 1",
            $this->getActiveEmployeeStatsSql('u')
        ];
        if ($team_id) {
            $drawingWhereArr[] = sprintf("tm.team_id = %d", intval($team_id));
        }
        if ($department_id) {
            $drawingWhereArr[] = sprintf("te.department_id = %d", intval($department_id));
        }
        $drawingWhere = "WHERE " . implode(' AND ', $drawingWhereArr);
        $creatorJoin = $this->getDrawingCreatorJoinSql('pd', 'u');

        $drawingRows = $this->fetchAll(sprintf(
            "SELECT u.userid, tm.team_id,
                DATE_FORMAT(pd.completed_at, '%%Y-%%m') AS ym,
                COUNT(*) AS drawing_count,
                COALESCE(SUM(pd.price), 0) AS total_revenue
            FROM " . DB_PREFIX . "project_drawings pd
            INNER JOIN " . DB_PREFIX . "projects p ON pd.project_id = p.id
            INNER JOIN " . DB_PREFIX . "user u ON %s
            INNER JOIN " . DB_PREFIX . "team_members tm ON u.id = tm.user_id
            INNER JOIN " . DB_PREFIX . "team te ON tm.team_id = te.id
            %s
            GROUP BY u.userid, tm.team_id, ym",
            $creatorJoin,
            $drawingWhere
        ));

        $map = [];
        foreach ($taskRows as $row) {
            if (empty($row['userid']) || empty($row['ym'])) {
                continue;
            }
            $key = $this->getUserTeamPeriodKey($row['userid'], $row['team_id'] ?? 0, $row['ym']);
            if (!isset($map[$key])) {
                $map[$key] = $this->emptyProjectMetrics();
            }
            $map[$key]['task_count'] = intval($row['task_count'] ?? 0);
        }
        foreach ($reactionRows as $row) {
            if (empty($row['userid']) || empty($row['ym'])) {
                continue;
            }
            $key = $this->getUserTeamPeriodKey($row['userid'], $row['team_id'] ?? 0, $row['ym']);
            if (!isset($map[$key])) {
                $map[$key] = $this->emptyProjectMetrics();
            }
            $map[$key]['task_likes'] = intval($row['likes'] ?? 0);
            $map[$key]['task_dislikes'] = intval($row['dislikes'] ?? 0);
        }
        foreach ($drawingRows as $row) {
            if (empty($row['userid']) || empty($row['ym'])) {
                continue;
            }
            $key = $this->getUserTeamPeriodKey($row['userid'], $row['team_id'] ?? 0, $row['ym']);
            if (!isset($map[$key])) {
                $map[$key] = $this->emptyProjectMetrics();
            }
            $map[$key]['drawing_count'] = intval($row['drawing_count'] ?? 0);
            $map[$key]['total_drawings_revenue'] = floatval($row['total_revenue'] ?? 0);
            $map[$key]['revenue'] = floatval($row['total_revenue'] ?? 0);
        }
        return $map;
    }

    /**
     * Default project-based metrics structure.
     */
    private function emptyProjectMetrics() {
        return [
            'revenue' => 0,
            'task_likes' => 0,
            'task_dislikes' => 0,
            'total_drawings_revenue' => 0,
            'drawing_count' => 0,
            'task_count' => 0,
            'total_revenue' => 0,
            'total_likes' => 0,
            'total_dislikes' => 0,
            'total_drawing_count' => 0,
            'total_task_count' => 0
        ];
    }

    /**
     * GUIS employee: user_group is not CAILY (6, 7).
     */
    private function isGuiseEmployeeUserGroup($userGroup) {
        $groupId = intval($userGroup);
        return $groupId !== 6 && $groupId !== 7;
    }

    private function isCailyEmployeeUserGroup($userGroup) {
        $groupId = intval($userGroup);
        return $groupId === 6 || $groupId === 7;
    }

    /**
     * CAILY API userid => GUIS userid (same mapping as dayoff-events.js).
     */
    private function getCailyTimecardUseridGuiAliases() {
        return [
            'nguyen' => 'duynguyen',
        ];
    }

    private function resolveGuisUseridFromCailyApi($cailyUserid) {
        $aliases = $this->getCailyTimecardUseridGuiAliases();
        $key = trim((string) $cailyUserid);
        return isset($aliases[$key]) ? $aliases[$key] : $key;
    }

    private function resolveCailyApiUseridFromGuis($guisUserid) {
        $aliases = $this->getCailyTimecardUseridGuiAliases();
        $key = trim((string) $guisUserid);
        foreach ($aliases as $apiUserid => $mappedGuisUserid) {
            if ($mappedGuisUserid === $key) {
                return $apiUserid;
            }
        }
        return $key;
    }

    /**
     * Parse timecard HH:MM field to minutes.
     */
    private function parseTimecardFieldToMinutes($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }
        $parts = explode(':', $value);
        if (count($parts) < 2) {
            return 0;
        }
        $hours = intval($parts[0]);
        $minutes = intval($parts[1]);
        if ($hours < 0 || $minutes < 0) {
            return 0;
        }
        return ($hours * 60) + $minutes;
    }

    /**
     * Total attendance minutes (timecard_time + timecard_timeover) for GUIS users by month.
     */
    private function getGuiseTimecardMinutesByUserMonthMap($start_date, $end_date) {
        $query = sprintf(
            "SELECT tc.owner AS userid,
                DATE_FORMAT(tc.timecard_date, '%%Y-%%m') AS ym,
                tc.timecard_time,
                tc.timecard_timeover,
                tc.timecard_close
            FROM %stimecard tc
            INNER JOIN %suser u ON u.userid = tc.owner
            WHERE tc.timecard_date BETWEEN '%s' AND '%s'
              AND u.user_group NOT IN (6, 7)
            ORDER BY tc.owner, tc.timecard_date",
            DB_PREFIX,
            DB_PREFIX,
            $this->quote($start_date),
            $this->quote($end_date)
        );

        $map = [];
        foreach ($this->fetchAll($query) as $row) {
            if (empty($row['userid']) || empty($row['ym'])) {
                continue;
            }
            if (strlen(trim((string) ($row['timecard_close'] ?? ''))) === 0) {
                continue;
            }
            $key = $row['userid'] . '|' . $row['ym'];
            if (!isset($map[$key])) {
                $map[$key] = 0;
            }
            $map[$key] += $this->parseTimecardFieldToMinutes($row['timecard_time'] ?? '');
            $map[$key] += $this->parseTimecardFieldToMinutes($row['timecard_timeover'] ?? '');
        }
        return $map;
    }

    /**
     * Total attendance minutes for CAILY users (user_group 6/7) via CAILY API.
     */
    private function getCailyTimecardMinutesByUserMonthMap($start_date, $end_date, $members) {
        $userids = $this->getCailyTimecardApiUserids($members);
        if (empty($userids)) {
            return [];
        }

        $apiUrl = getenv('CAILY_API_URL') ?: 'https://group.caily.com.vn/api/index.php';
        $postFields = http_build_query([
            'type' => 'get_employee_timecard_stats',
            'token' => md5('caily2222'),
            'start_date' => $start_date,
            'end_date' => $end_date,
            'userids' => implode(',', $userids),
        ]);

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false || $response === '') {
            return [];
        }

        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['success']) || empty($data['list']) || !is_array($data['list'])) {
            return [];
        }

        $map = [];
        foreach ($data['list'] as $row) {
            if (empty($row['userid']) || empty($row['ym'])) {
                continue;
            }
            $guisUserid = $this->resolveGuisUseridFromCailyApi($row['userid']);
            $key = $guisUserid . '|' . $row['ym'];
            $map[$key] = intval($row['total_minutes'] ?? 0);
        }
        return $map;
    }

    private function getCailyTimecardApiUserids($members) {
        $userids = [];
        foreach ($members as $member) {
            if ($this->isCailyEmployeeUserGroup($member['user_group'] ?? null) && !empty($member['userid'])) {
                $userids[] = $this->resolveCailyApiUseridFromGuis($member['userid']);
            }
        }
        return array_values(array_unique($userids));
    }

    private function buildCailyTimecardApiRequestUrl($start_date, $end_date, $members) {
        $userids = $this->getCailyTimecardApiUserids($members);
        if (empty($userids)) {
            return null;
        }

        $apiUrl = rtrim(getenv('CAILY_API_URL') ?: 'https://group.caily.com.vn/api/index.php', '?&');
        $params = [
            'type' => 'get_employee_timecard_stats',
            'token' => md5('caily2222'),
            'start_date' => $start_date,
            'end_date' => $end_date,
            'userids' => implode(',', $userids),
            'debug' => '1',
        ];
        return $apiUrl . '?' . http_build_query($params);
    }

    /**
     * Default workload breakdown structure.
     */
    private function emptyWorkloadBreakdown() {
        return [
            'total_workload' => 0,
            'workload_task_count' => 0,
            'workload_new' => 0,
            'workload_error_fix' => 0,
            'workload_change_fix' => 0,
            'workload_other' => 0
        ];
    }

    /**
     * SQL join condition: task assigned to user (assigned_to stores user.id).
     */
    private function getTaskAssigneeJoinSql($taskAlias = 't', $userAlias = 'u') {
        $t = preg_replace('/[^a-zA-Z0-9_]/', '', $taskAlias) ?: 't';
        $u = preg_replace('/[^a-zA-Z0-9_]/', '', $userAlias) ?: 'u';
        return sprintf(
            "(FIND_IN_SET(%s.id, %s.assigned_to) > 0 OR %s.assigned_to = %s.id)",
            $u,
            $t,
            $t,
            $u
        );
    }

    /**
     * Match time_entries.user_id to groupware_user.userid.
     */
    private function getTimeEntryUserJoinSql($entryAlias = 'en', $userAlias = 'u') {
        $e = preg_replace('/[^a-zA-Z0-9_]/', '', $entryAlias) ?: 'en';
        $u = preg_replace('/[^a-zA-Z0-9_]/', '', $userAlias) ?: 'u';
        return sprintf(
            "%s.userid COLLATE utf8mb4_general_ci = %s.user_id COLLATE utf8mb4_general_ci",
            $u,
            $e
        );
    }

    /**
     * Workload (hours) by user and month from time_entries.end_time.
     */
    private function getWorkloadByUserPeriod($start_date, $end_date, $team_id = null, $department_id = null) {
        $whereArr = [
            $this->getTimeEntryPeriodSql('en', $start_date, $end_date),
            "te.is_active = 1"
        ];
        if ($team_id) {
            $whereArr[] = sprintf("tm.team_id = %d", intval($team_id));
        }
        if ($department_id) {
            $whereArr[] = sprintf("te.department_id = %d", intval($department_id));
        }
        $where = "WHERE " . implode(" AND ", $whereArr);
        $kindCategorySql = $this->getTaskKindCategorySql('t');
        $userJoin = $this->getTimeEntryUserJoinSql('en', 'u');

        $query = sprintf(
            "SELECT 
                u.userid,
                tm.team_id,
                DATE_FORMAT(en.end_time, '%%Y-%%m') AS ym,
                COALESCE(SUM(en.`hours`), 0) AS total_workload,
                COALESCE(SUM(CASE WHEN (%s) = 'new' THEN en.`hours` ELSE 0 END), 0) AS workload_new,
                COALESCE(SUM(CASE WHEN (%s) = 'error_fix' THEN en.`hours` ELSE 0 END), 0) AS workload_error_fix,
                COALESCE(SUM(CASE WHEN (%s) = 'change_fix' THEN en.`hours` ELSE 0 END), 0) AS workload_change_fix,
                COALESCE(SUM(CASE WHEN (%s) = 'other' THEN en.`hours` ELSE 0 END), 0) AS workload_other
            FROM " . DB_PREFIX . "time_entries en
            INNER JOIN " . DB_PREFIX . "user u ON %s
            INNER JOIN " . DB_PREFIX . "team_members tm ON u.id = tm.user_id
            INNER JOIN " . DB_PREFIX . "team te ON tm.team_id = te.id
            LEFT JOIN " . DB_PREFIX . "tasks t ON en.task_id = t.id
            %s
            GROUP BY u.userid, tm.team_id, DATE_FORMAT(en.end_time, '%%Y-%%m')",
            $kindCategorySql,
            $kindCategorySql,
            $kindCategorySql,
            $kindCategorySql,
            $userJoin,
            $where
        );

        try {
            $rows = $this->fetchAll($query);
        } catch (Exception $e) {
            error_log('getWorkloadByUserPeriod: ' . $e->getMessage());
            return [];
        }
        $map = [];
        foreach ($rows as $row) {
            if (empty($row['userid']) || empty($row['ym'])) {
                continue;
            }
            $map[$this->getUserTeamPeriodKey($row['userid'], $row['team_id'] ?? 0, $row['ym'])] = [
                'total_workload' => floatval($row['total_workload'] ?? 0),
                'workload_new' => floatval($row['workload_new'] ?? 0),
                'workload_error_fix' => floatval($row['workload_error_fix'] ?? 0),
                'workload_change_fix' => floatval($row['workload_change_fix'] ?? 0),
                'workload_other' => floatval($row['workload_other'] ?? 0)
            ];
        }
        return $map;
    }

    /**
     * Workload (hours) by team from members' time_entries.end_time.
     */
    private function getWorkloadByTeam($start_date, $end_date, $team_id = null, $department_id = null) {
        $whereArr = [
            $this->getTimeEntryPeriodSql('en', $start_date, $end_date),
            "tm.team_id IS NOT NULL",
            "te.is_active = 1"
        ];
        if ($team_id) {
            $whereArr[] = sprintf("tm.team_id = %d", intval($team_id));
        }
        if ($department_id) {
            $whereArr[] = sprintf("te.department_id = %d", intval($department_id));
        }
        $where = "WHERE " . implode(" AND ", $whereArr);
        $kindCategorySql = $this->getTaskKindCategorySql('t');
        $userJoin = $this->getTimeEntryUserJoinSql('en', 'u');

        $query = sprintf(
            "SELECT 
                tm.team_id,
                COALESCE(SUM(en.`hours`), 0) AS total_workload,
                COUNT(*) AS workload_task_count,
                COALESCE(SUM(CASE WHEN (%s) = 'new' THEN en.`hours` ELSE 0 END), 0) AS workload_new,
                COALESCE(SUM(CASE WHEN (%s) = 'error_fix' THEN en.`hours` ELSE 0 END), 0) AS workload_error_fix,
                COALESCE(SUM(CASE WHEN (%s) = 'change_fix' THEN en.`hours` ELSE 0 END), 0) AS workload_change_fix,
                COALESCE(SUM(CASE WHEN (%s) = 'other' THEN en.`hours` ELSE 0 END), 0) AS workload_other
            FROM " . DB_PREFIX . "time_entries en
            INNER JOIN " . DB_PREFIX . "user u ON %s
            INNER JOIN " . DB_PREFIX . "team_members tm ON u.id = tm.user_id
            INNER JOIN " . DB_PREFIX . "team te ON tm.team_id = te.id
            LEFT JOIN " . DB_PREFIX . "tasks t ON en.task_id = t.id
            %s
            GROUP BY tm.team_id",
            $kindCategorySql,
            $kindCategorySql,
            $kindCategorySql,
            $kindCategorySql,
            $userJoin,
            $where
        );

        try {
            $rows = $this->fetchAll($query);
        } catch (Exception $e) {
            error_log('getWorkloadByTeam: ' . $e->getMessage());
            return [];
        }
        $map = [];
        foreach ($rows as $row) {
            $map[intval($row['team_id'])] = [
                'total_workload' => floatval($row['total_workload'] ?? 0),
                'workload_task_count' => intval($row['workload_task_count'] ?? 0),
                'workload_new' => floatval($row['workload_new'] ?? 0),
                'workload_error_fix' => floatval($row['workload_error_fix'] ?? 0),
                'workload_change_fix' => floatval($row['workload_change_fix'] ?? 0),
                'workload_other' => floatval($row['workload_other'] ?? 0)
            ];
        }
        return $map;
    }

    /**
     * Workload (hours) by department from members' time_entries.end_time.
     */
    private function getWorkloadByDepartment($start_date, $end_date, $department_id = null) {
        $whereArr = [
            "te.department_id IS NOT NULL",
            "te.is_active = 1",
            $this->getTimeEntryPeriodSql('en', $start_date, $end_date)
        ];
        if ($department_id) {
            $whereArr[] = sprintf("te.department_id = %d", intval($department_id));
        }
        $where = "WHERE " . implode(" AND ", $whereArr);
        $kindCategorySql = $this->getTaskKindCategorySql('t');
        $userJoin = $this->getTimeEntryUserJoinSql('en', 'u');

        $query = sprintf(
            "SELECT 
                te.department_id,
                COALESCE(SUM(en.`hours`), 0) AS total_workload,
                COUNT(*) AS workload_task_count,
                COALESCE(SUM(CASE WHEN (%s) = 'new' THEN en.`hours` ELSE 0 END), 0) AS workload_new,
                COALESCE(SUM(CASE WHEN (%s) = 'error_fix' THEN en.`hours` ELSE 0 END), 0) AS workload_error_fix,
                COALESCE(SUM(CASE WHEN (%s) = 'change_fix' THEN en.`hours` ELSE 0 END), 0) AS workload_change_fix,
                COALESCE(SUM(CASE WHEN (%s) = 'other' THEN en.`hours` ELSE 0 END), 0) AS workload_other
            FROM " . DB_PREFIX . "time_entries en
            INNER JOIN " . DB_PREFIX . "user u ON %s
            INNER JOIN " . DB_PREFIX . "team_members tm ON u.id = tm.user_id
            INNER JOIN " . DB_PREFIX . "team te ON tm.team_id = te.id
            LEFT JOIN " . DB_PREFIX . "tasks t ON en.task_id = t.id
            %s
            GROUP BY te.department_id",
            $kindCategorySql,
            $kindCategorySql,
            $kindCategorySql,
            $kindCategorySql,
            $userJoin,
            $where
        );

        try {
            $rows = $this->fetchAll($query);
        } catch (Exception $e) {
            error_log('getWorkloadByDepartment: ' . $e->getMessage());
            return [];
        }
        $map = [];
        foreach ($rows as $row) {
            $map[intval($row['department_id'])] = [
                'total_workload' => floatval($row['total_workload'] ?? 0),
                'workload_task_count' => intval($row['workload_task_count'] ?? 0),
                'workload_new' => floatval($row['workload_new'] ?? 0),
                'workload_error_fix' => floatval($row['workload_error_fix'] ?? 0),
                'workload_change_fix' => floatval($row['workload_change_fix'] ?? 0),
                'workload_other' => floatval($row['workload_other'] ?? 0)
            ];
        }
        return $map;
    }

    /**
     * Get statistics summary by department (revenue, ratings, workload).
     */
    function getSummaryByDepartment() {
        $months = isset($_GET['months']) ? intval($_GET['months']) : 12;
        $range = $this->getStatisticsDateRange($months);
        $start_date = $range['start_date'];
        $end_date = $range['end_date'];

        $deptRows = $this->fetchAll(
            "SELECT d.id AS department_id, d.name AS department_name,
                (SELECT COUNT(*) FROM " . DB_PREFIX . "team t
                 WHERE t.department_id = d.id AND t.is_active = 1) AS team_count
             FROM " . DB_PREFIX . "departments d
             ORDER BY d.name ASC"
        );

        $taskCountMap = $this->getTaskCountByDepartmentMap($start_date, $end_date);
        $reactionMap = $this->getReactionMetricsByDepartmentMap($start_date, $end_date);
        $drawingMap = $this->getDrawingMetricsByDepartmentMap($start_date, $end_date);
        $memberMap = $this->getMemberCountByDepartmentMap($start_date, $end_date);
        $workloadMap = $this->getWorkloadByDepartment($start_date, $end_date);

        $rows = [];
        foreach ($deptRows as $dept) {
            $deptId = intval($dept['department_id']);
            $reactions = isset($reactionMap[$deptId]) ? $reactionMap[$deptId] : ['likes' => 0, 'dislikes' => 0];
            $drawings = isset($drawingMap[$deptId]) ? $drawingMap[$deptId] : ['drawing_count' => 0, 'total_revenue' => 0];
            $wl = isset($workloadMap[$deptId]) ? $workloadMap[$deptId] : $this->emptyWorkloadBreakdown();

            $hasActivity = intval($dept['team_count']) > 0
                || intval($memberMap[$deptId] ?? 0) > 0
                || intval($taskCountMap[$deptId] ?? 0) > 0
                || floatval($drawings['total_revenue']) > 0
                || floatval($wl['total_workload']) > 0;
            if (!$hasActivity) {
                continue;
            }

            $rows[] = [
                'department_id' => $deptId,
                'department_name' => $dept['department_name'],
                'team_count' => intval($dept['team_count']),
                'member_count' => intval($memberMap[$deptId] ?? 0),
                'total_revenue' => floatval($drawings['total_revenue']),
                'total_likes' => intval($reactions['likes']),
                'total_dislikes' => intval($reactions['dislikes']),
                'total_drawings_revenue' => floatval($drawings['total_revenue']),
                'total_drawing_count' => intval($drawings['drawing_count']),
                'total_task_count' => intval($taskCountMap[$deptId] ?? 0),
                'total_workload' => floatval($wl['total_workload']),
                'workload_task_count' => intval($wl['workload_task_count']),
                'workload_new' => floatval($wl['workload_new']),
                'workload_error_fix' => floatval($wl['workload_error_fix']),
                'workload_change_fix' => floatval($wl['workload_change_fix']),
                'workload_other' => floatval($wl['workload_other'])
            ];
        }

        return $rows;
    }

    /**
     * Monthly statistics for a department (chart data).
     */
    function getMonthlyByDepartment() {
        $department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
        if ($department_id <= 0) {
            return [];
        }

        $months = isset($_GET['months']) ? intval($_GET['months']) : 12;
        $end_date = date('Y-m-t');
        $start_date = $this->clampStatisticsStartDate(date('Y-m-01', strtotime("-$months months")));

        $taskRows = $this->fetchAll(sprintf(
            "SELECT DATE_FORMAT(COALESCE(t.actual_end_date, t.due_date), '%%Y-%%m') AS ym,
                COUNT(*) AS task_count
            FROM " . DB_PREFIX . "tasks t
            INNER JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id
            WHERE p.department_id = %d AND %s AND %s
            GROUP BY ym
            ORDER BY ym ASC",
            $department_id,
            $this->getTaskPeriodSql('t', $start_date, $end_date),
            $this->getTaskCountStatusSql('t')
        ));

        $reactionRows = $this->fetchAll(sprintf(
            "SELECT DATE_FORMAT(COALESCE(t.actual_end_date, t.due_date), '%%Y-%%m') AS ym,
                SUM(CASE WHEN tr.type = 'like' THEN 1 ELSE 0 END) AS likes,
                SUM(CASE WHEN tr.type = 'dislike' THEN 1 ELSE 0 END) AS dislikes
            FROM " . DB_PREFIX . "task_reactions tr
            INNER JOIN " . DB_PREFIX . "tasks t ON tr.task_id = t.id
            INNER JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id
            WHERE p.department_id = %d AND %s
            GROUP BY ym
            ORDER BY ym ASC",
            $department_id,
            $this->getTaskPeriodSql('t', $start_date, $end_date)
        ));

        $drawingRows = $this->fetchAll(sprintf(
            "SELECT DATE_FORMAT(pd.completed_at, '%%Y-%%m') AS ym,
                COALESCE(SUM(pd.price), 0) AS revenue
            FROM " . DB_PREFIX . "project_drawings pd
            INNER JOIN " . DB_PREFIX . "projects p ON pd.project_id = p.id
            WHERE p.department_id = %d
              AND %s
              AND pd.price IS NOT NULL
              AND %s
            GROUP BY ym
            ORDER BY ym ASC",
            $department_id,
            $this->getDrawingRevenueEligibilitySql('pd', 'p'),
            $this->getDrawingPeriodSql('pd', $start_date, $end_date)
        ));

        $statsMap = [];
        foreach ($taskRows as $row) {
            if (empty($row['ym'])) {
                continue;
            }
            if (!isset($statsMap[$row['ym']])) {
                $statsMap[$row['ym']] = ['task_count' => 0, 'likes' => 0, 'dislikes' => 0, 'revenue' => 0];
            }
            $statsMap[$row['ym']]['task_count'] = intval($row['task_count'] ?? 0);
        }
        foreach ($reactionRows as $row) {
            if (empty($row['ym'])) {
                continue;
            }
            if (!isset($statsMap[$row['ym']])) {
                $statsMap[$row['ym']] = ['task_count' => 0, 'likes' => 0, 'dislikes' => 0, 'revenue' => 0];
            }
            $statsMap[$row['ym']]['likes'] = intval($row['likes'] ?? 0);
            $statsMap[$row['ym']]['dislikes'] = intval($row['dislikes'] ?? 0);
        }
        foreach ($drawingRows as $row) {
            if (empty($row['ym'])) {
                continue;
            }
            if (!isset($statsMap[$row['ym']])) {
                $statsMap[$row['ym']] = ['task_count' => 0, 'likes' => 0, 'dislikes' => 0, 'revenue' => 0];
            }
            $statsMap[$row['ym']]['revenue'] = floatval($row['revenue'] ?? 0);
        }

        $kindCategorySql = $this->getTaskKindCategorySql('t');
        $userJoin = $this->getTimeEntryUserJoinSql('en', 'u');
        $workloadRows = $this->fetchAll(sprintf(
            "SELECT 
                DATE_FORMAT(en.end_time, '%%Y-%%m') AS ym,
                COALESCE(SUM(en.`hours`), 0) AS workload,
                COALESCE(SUM(CASE WHEN (%s) = 'new' THEN en.`hours` ELSE 0 END), 0) AS workload_new,
                COALESCE(SUM(CASE WHEN (%s) = 'error_fix' THEN en.`hours` ELSE 0 END), 0) AS workload_error_fix,
                COALESCE(SUM(CASE WHEN (%s) = 'change_fix' THEN en.`hours` ELSE 0 END), 0) AS workload_change_fix,
                COALESCE(SUM(CASE WHEN (%s) = 'other' THEN en.`hours` ELSE 0 END), 0) AS workload_other
            FROM " . DB_PREFIX . "time_entries en
            INNER JOIN " . DB_PREFIX . "user u ON %s
            INNER JOIN " . DB_PREFIX . "team_members tm ON u.id = tm.user_id
            INNER JOIN " . DB_PREFIX . "team te ON tm.team_id = te.id
            LEFT JOIN " . DB_PREFIX . "tasks t ON en.task_id = t.id
            WHERE te.department_id = %d
              AND te.is_active = 1
              AND %s
            GROUP BY DATE_FORMAT(en.end_time, '%%Y-%%m')
            ORDER BY ym ASC",
            $kindCategorySql,
            $kindCategorySql,
            $kindCategorySql,
            $kindCategorySql,
            $userJoin,
            $department_id,
            $this->getTimeEntryPeriodSql('en', $start_date, $end_date)
        ));

        $workloadMap = [];
        foreach ($workloadRows as $row) {
            if (!empty($row['ym'])) {
                $workloadMap[$row['ym']] = [
                    'workload' => floatval($row['workload'] ?? 0),
                    'workload_new' => floatval($row['workload_new'] ?? 0),
                    'workload_error_fix' => floatval($row['workload_error_fix'] ?? 0),
                    'workload_change_fix' => floatval($row['workload_change_fix'] ?? 0),
                    'workload_other' => floatval($row['workload_other'] ?? 0)
                ];
            }
        }

        $allMonths = array_unique(array_merge(array_keys($statsMap), array_keys($workloadMap)));
        sort($allMonths);

        $results = [];
        foreach ($allMonths as $ym) {
            if (!$this->isStatisticsMonthAllowed($ym)) {
                continue;
            }
            $stat = isset($statsMap[$ym]) ? $statsMap[$ym] : null;
            $wl = isset($workloadMap[$ym]) ? $workloadMap[$ym] : $this->emptyWorkloadBreakdown();
            $results[] = [
                'ym' => $ym,
                'revenue' => $stat ? floatval($stat['revenue']) : 0,
                'likes' => $stat ? intval($stat['likes']) : 0,
                'dislikes' => $stat ? intval($stat['dislikes']) : 0,
                'task_count' => $stat ? intval($stat['task_count']) : 0,
                'workload' => $wl['workload'] ?? $wl['total_workload'] ?? 0,
                'workload_new' => $wl['workload_new'] ?? 0,
                'workload_error_fix' => $wl['workload_error_fix'] ?? 0,
                'workload_change_fix' => $wl['workload_change_fix'] ?? 0,
                'workload_other' => $wl['workload_other'] ?? 0
            ];
        }

        return $results;
    }

    /**
     * Monthly statistics for a team (chart data).
     */
    function getMonthlyByTeam() {
        $team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;
        if ($team_id <= 0) {
            return [];
        }

        $months = isset($_GET['months']) ? intval($_GET['months']) : 12;
        $end_date = date('Y-m-t');
        $start_date = $this->clampStatisticsStartDate(date('Y-m-01', strtotime("-$months months")));

        $assigneeJoin = $this->getTaskAssigneeJoinSql('t', 'u');
        $creatorJoin = $this->getDrawingCreatorJoinSql('pd', 'u');
        $projectScope = $this->getTeamIdProjectScopeSql($team_id, 'p');
        $activeUserSql = $this->getActiveEmployeeStatsSql('u');
        $taskPeriodSql = $this->getTaskPeriodSql('t', $start_date, $end_date);
        $drawingPeriodSql = $this->getDrawingPeriodSql('pd', $start_date, $end_date);

        $taskRows = $this->fetchAll(sprintf(
            "SELECT DATE_FORMAT(COALESCE(t.actual_end_date, t.due_date), '%%Y-%%m') AS ym,
                COUNT(*) AS task_count
            FROM " . DB_PREFIX . "tasks t
            INNER JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id
            INNER JOIN " . DB_PREFIX . "user u ON %s
            INNER JOIN " . DB_PREFIX . "team_members tm ON u.id = tm.user_id
            WHERE tm.team_id = %d AND %s AND %s AND %s AND %s
            GROUP BY ym
            ORDER BY ym ASC",
            $assigneeJoin,
            $team_id,
            $projectScope,
            $taskPeriodSql,
            $this->getTaskCountStatusSql('t'),
            $activeUserSql
        ));

        $reactionRows = $this->fetchAll(sprintf(
            "SELECT DATE_FORMAT(COALESCE(t.actual_end_date, t.due_date), '%%Y-%%m') AS ym,
                SUM(CASE WHEN tr.type = 'like' THEN 1 ELSE 0 END) AS likes,
                SUM(CASE WHEN tr.type = 'dislike' THEN 1 ELSE 0 END) AS dislikes
            FROM " . DB_PREFIX . "task_reactions tr
            INNER JOIN " . DB_PREFIX . "tasks t ON tr.task_id = t.id
            INNER JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id
            INNER JOIN " . DB_PREFIX . "user u ON %s
            INNER JOIN " . DB_PREFIX . "team_members tm ON u.id = tm.user_id
            WHERE tm.team_id = %d AND %s AND %s AND %s
            GROUP BY ym
            ORDER BY ym ASC",
            $assigneeJoin,
            $team_id,
            $projectScope,
            $taskPeriodSql,
            $activeUserSql
        ));

        $drawingRows = $this->fetchAll(sprintf(
            "SELECT DATE_FORMAT(pd.completed_at, '%%Y-%%m') AS ym,
                COALESCE(SUM(pd.price), 0) AS drawing_revenue
            FROM " . DB_PREFIX . "project_drawings pd
            INNER JOIN " . DB_PREFIX . "projects p ON pd.project_id = p.id
            INNER JOIN " . DB_PREFIX . "user u ON %s
            INNER JOIN " . DB_PREFIX . "team_members tm ON u.id = tm.user_id
            WHERE tm.team_id = %d
              AND %s
              AND pd.price IS NOT NULL
              AND %s
              AND %s
              AND %s
            GROUP BY ym
            ORDER BY ym ASC",
            $creatorJoin,
            $team_id,
            $this->getDrawingRevenueEligibilitySql('pd', 'p'),
            $projectScope,
            $drawingPeriodSql,
            $activeUserSql
        ));

        $statsMap = [];
        foreach ($taskRows as $row) {
            if (empty($row['ym'])) {
                continue;
            }
            if (!isset($statsMap[$row['ym']])) {
                $statsMap[$row['ym']] = ['task_count' => 0, 'likes' => 0, 'dislikes' => 0, 'drawing_revenue' => 0, 'revenue' => 0];
            }
            $statsMap[$row['ym']]['task_count'] = intval($row['task_count'] ?? 0);
        }
        foreach ($reactionRows as $row) {
            if (empty($row['ym'])) {
                continue;
            }
            if (!isset($statsMap[$row['ym']])) {
                $statsMap[$row['ym']] = ['task_count' => 0, 'likes' => 0, 'dislikes' => 0, 'drawing_revenue' => 0, 'revenue' => 0];
            }
            $statsMap[$row['ym']]['likes'] = intval($row['likes'] ?? 0);
            $statsMap[$row['ym']]['dislikes'] = intval($row['dislikes'] ?? 0);
        }
        foreach ($drawingRows as $row) {
            if (empty($row['ym'])) {
                continue;
            }
            if (!isset($statsMap[$row['ym']])) {
                $statsMap[$row['ym']] = ['task_count' => 0, 'likes' => 0, 'dislikes' => 0, 'drawing_revenue' => 0, 'revenue' => 0];
            }
            $statsMap[$row['ym']]['drawing_revenue'] = floatval($row['drawing_revenue'] ?? 0);
            $statsMap[$row['ym']]['revenue'] = floatval($row['drawing_revenue'] ?? 0);
        }

        $kindCategorySql = $this->getTaskKindCategorySql('t');
        $userJoin = $this->getTimeEntryUserJoinSql('en', 'u');
        $workloadRows = $this->fetchAll(sprintf(
            "SELECT 
                DATE_FORMAT(en.end_time, '%%Y-%%m') AS ym,
                COALESCE(SUM(en.`hours`), 0) AS workload,
                COALESCE(SUM(CASE WHEN (%s) = 'new' THEN en.`hours` ELSE 0 END), 0) AS workload_new,
                COALESCE(SUM(CASE WHEN (%s) = 'error_fix' THEN en.`hours` ELSE 0 END), 0) AS workload_error_fix,
                COALESCE(SUM(CASE WHEN (%s) = 'change_fix' THEN en.`hours` ELSE 0 END), 0) AS workload_change_fix,
                COALESCE(SUM(CASE WHEN (%s) = 'other' THEN en.`hours` ELSE 0 END), 0) AS workload_other
            FROM " . DB_PREFIX . "time_entries en
            INNER JOIN " . DB_PREFIX . "user u ON %s
            INNER JOIN " . DB_PREFIX . "team_members tm ON u.id = tm.user_id
            LEFT JOIN " . DB_PREFIX . "tasks t ON en.task_id = t.id
            WHERE tm.team_id = %d
              AND %s
              AND %s
            GROUP BY DATE_FORMAT(en.end_time, '%%Y-%%m')
            ORDER BY ym ASC",
            $kindCategorySql,
            $kindCategorySql,
            $kindCategorySql,
            $kindCategorySql,
            $userJoin,
            $team_id,
            $this->getTimeEntryPeriodSql('en', $start_date, $end_date),
            $activeUserSql
        ));

        $workloadMap = [];
        foreach ($workloadRows as $row) {
            if (!empty($row['ym'])) {
                $workloadMap[$row['ym']] = [
                    'workload' => floatval($row['workload'] ?? 0),
                    'workload_new' => floatval($row['workload_new'] ?? 0),
                    'workload_error_fix' => floatval($row['workload_error_fix'] ?? 0),
                    'workload_change_fix' => floatval($row['workload_change_fix'] ?? 0),
                    'workload_other' => floatval($row['workload_other'] ?? 0)
                ];
            }
        }

        $allMonths = array_unique(array_merge(array_keys($statsMap), array_keys($workloadMap)));
        sort($allMonths);

        $results = [];
        foreach ($allMonths as $ym) {
            if (!$this->isStatisticsMonthAllowed($ym)) {
                continue;
            }
            $stat = isset($statsMap[$ym]) ? $statsMap[$ym] : null;
            $wl = isset($workloadMap[$ym]) ? $workloadMap[$ym] : $this->emptyWorkloadBreakdown();
            $results[] = [
                'ym' => $ym,
                'revenue' => $stat ? floatval($stat['revenue']) : 0,
                'drawing_revenue' => $stat ? floatval($stat['drawing_revenue']) : 0,
                'likes' => $stat ? intval($stat['likes']) : 0,
                'dislikes' => $stat ? intval($stat['dislikes']) : 0,
                'task_count' => $stat ? intval($stat['task_count']) : 0,
                'workload' => $wl['workload'] ?? $wl['total_workload'] ?? 0,
                'workload_new' => $wl['workload_new'] ?? 0,
                'workload_error_fix' => $wl['workload_error_fix'] ?? 0,
                'workload_change_fix' => $wl['workload_change_fix'] ?? 0,
                'workload_other' => $wl['workload_other'] ?? 0
            ];
        }

        return $results;
    }

    /**
     * Annual summary per team with scoring and ranking
     */
    function getAnnualSummary($params = null) {
        $year = isset($_GET['year']) ? intval($_GET['year']) : (isset($params['year']) ? intval($params['year']) : intval(date('Y')));
        if ($year < 2000 || $year > 2100) {
            $year = intval(date('Y'));
        }
        $department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : null;
        $team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;
        if ($department_id <= 0) {
            $department_id = null;
        }
        if ($team_id <= 0) {
            $team_id = null;
        }

        // Fiscal year: Jul (previous year) -> Jun (selected year)
        $startFiscalYear = $year - 1;
        $startDate = sprintf("%d-07-01", $startFiscalYear);
        $endDate   = sprintf("%d-06-30", $year);
        $startDate = $this->clampStatisticsStartDate($startDate);
        if (strcmp($startDate, $endDate) > 0) {
            return [];
        }

        // 1) Load active teams (optionally filtered by department / team)
        $teamWhere = ['t.is_active = 1'];
        if ($department_id) {
            $teamWhere[] = sprintf('t.department_id = %d', $department_id);
        }
        if ($team_id) {
            $teamWhere[] = sprintf('t.id = %d', $team_id);
        }
        $teams = $this->fetchAll(sprintf(
            "SELECT t.id, t.name, t.department_id, d.name AS department_name
             FROM " . DB_PREFIX . "team t
             LEFT JOIN " . DB_PREFIX . "departments d ON t.department_id = d.id
             WHERE %s
             ORDER BY t.department_id ASC, t.name ASC",
            implode(' AND ', $teamWhere)
        ));

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
        $assigneeJoin = $this->getTaskAssigneeJoinSql('t', 'u');
        $creatorJoin = $this->getDrawingCreatorJoinSql('pd', 'u');
        $projectScope = $this->getTeamProjectScopeSql('te', 'p', $department_id);
        $taskPeriodSql = $this->getTaskPeriodSql('t', $startDate, $endDate);
        $drawingPeriodSql = $this->getDrawingPeriodSql('pd', $startDate, $endDate);

        $teamMemberScope = '';
        if ($team_id) {
            $teamMemberScope = sprintf(' AND tm.team_id = %d', $team_id);
        } elseif ($department_id) {
            $teamMemberScope = sprintf(' AND te.department_id = %d', $department_id);
        }

        // 3) Load monthly statistics within the fiscal window (project department scope)
        $monthlyRows = $this->fetchAll(sprintf(
            "SELECT team_id, ym,
                SUM(revenue) AS revenue,
                SUM(likes) AS likes,
                SUM(dislikes) AS dislikes,
                SUM(drawings_revenue) AS drawings_revenue,
                SUM(drawing_count) AS drawing_count,
                SUM(task_count) AS task_count
             FROM (
                SELECT tm.team_id,
                    DATE_FORMAT(pd.completed_at, '%%Y-%%m') AS ym,
                    COALESCE(SUM(pd.price), 0) AS revenue,
                    0 AS likes,
                    0 AS dislikes,
                    COALESCE(SUM(pd.price), 0) AS drawings_revenue,
                    COUNT(*) AS drawing_count,
                    0 AS task_count
                FROM " . DB_PREFIX . "project_drawings pd
                INNER JOIN " . DB_PREFIX . "projects p ON pd.project_id = p.id
                INNER JOIN " . DB_PREFIX . "user u ON %s
                INNER JOIN " . DB_PREFIX . "team_members tm ON u.id = tm.user_id
                INNER JOIN " . DB_PREFIX . "team te ON tm.team_id = te.id AND te.is_active = 1
                WHERE %s
                  AND pd.price IS NOT NULL
                  AND %s
                  AND %s
                  AND %s%s
                GROUP BY tm.team_id, ym
                UNION ALL
                SELECT tm.team_id,
                    DATE_FORMAT(COALESCE(t.actual_end_date, t.due_date), '%%Y-%%m') AS ym,
                    0 AS revenue,
                    SUM(CASE WHEN tr.type = 'like' THEN 1 ELSE 0 END) AS likes,
                    SUM(CASE WHEN tr.type = 'dislike' THEN 1 ELSE 0 END) AS dislikes,
                    0 AS drawings_revenue,
                    0 AS drawing_count,
                    0 AS task_count
                FROM " . DB_PREFIX . "task_reactions tr
                INNER JOIN " . DB_PREFIX . "tasks t ON tr.task_id = t.id
                INNER JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id
                INNER JOIN " . DB_PREFIX . "user u ON %s
                INNER JOIN " . DB_PREFIX . "team_members tm ON u.id = tm.user_id
                INNER JOIN " . DB_PREFIX . "team te ON tm.team_id = te.id AND te.is_active = 1
                WHERE %s AND %s AND %s%s
                GROUP BY tm.team_id, ym
                UNION ALL
                SELECT tm.team_id,
                    DATE_FORMAT(COALESCE(t.actual_end_date, t.due_date), '%%Y-%%m') AS ym,
                    0 AS revenue,
                    0 AS likes,
                    0 AS dislikes,
                    0 AS drawings_revenue,
                    0 AS drawing_count,
                    COUNT(*) AS task_count
                FROM " . DB_PREFIX . "tasks t
                INNER JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id
                INNER JOIN " . DB_PREFIX . "user u ON %s
                INNER JOIN " . DB_PREFIX . "team_members tm ON u.id = tm.user_id
                INNER JOIN " . DB_PREFIX . "team te ON tm.team_id = te.id AND te.is_active = 1
                WHERE %s AND %s AND %s AND %s%s
                GROUP BY tm.team_id, ym
             ) monthly_stats
             GROUP BY team_id, ym",
            $creatorJoin,
            $this->getDrawingRevenueEligibilitySql('pd', 'p'),
            $projectScope,
            $drawingPeriodSql,
            $activeUserSql,
            $teamMemberScope,
            $assigneeJoin,
            $projectScope,
            $taskPeriodSql,
            $activeUserSql,
            $teamMemberScope,
            $assigneeJoin,
            $projectScope,
            $taskPeriodSql,
            $this->getTaskCountStatusSql('t'),
            $activeUserSql,
            $teamMemberScope
        ));

        // 4) Aggregate yearly totals
        $yearRows = $this->fetchAll(sprintf(
            "SELECT team_id,
                SUM(revenue) AS revenue_year,
                SUM(likes) AS total_likes,
                SUM(dislikes) AS total_dislikes,
                SUM(drawing_count) AS total_drawing_count,
                SUM(task_count) AS total_task_count
             FROM (
                SELECT tm.team_id,
                    COALESCE(SUM(pd.price), 0) AS revenue,
                    0 AS likes,
                    0 AS dislikes,
                    COUNT(*) AS drawing_count,
                    0 AS task_count
                FROM " . DB_PREFIX . "project_drawings pd
                INNER JOIN " . DB_PREFIX . "projects p ON pd.project_id = p.id
                INNER JOIN " . DB_PREFIX . "user u ON %s
                INNER JOIN " . DB_PREFIX . "team_members tm ON u.id = tm.user_id
                INNER JOIN " . DB_PREFIX . "team te ON tm.team_id = te.id AND te.is_active = 1
                WHERE %s
                  AND pd.price IS NOT NULL
                  AND %s
                  AND %s
                  AND %s%s
                GROUP BY tm.team_id
                UNION ALL
                SELECT tm.team_id,
                    0 AS revenue,
                    SUM(CASE WHEN tr.type = 'like' THEN 1 ELSE 0 END) AS likes,
                    SUM(CASE WHEN tr.type = 'dislike' THEN 1 ELSE 0 END) AS dislikes,
                    0 AS drawing_count,
                    0 AS task_count
                FROM " . DB_PREFIX . "task_reactions tr
                INNER JOIN " . DB_PREFIX . "tasks t ON tr.task_id = t.id
                INNER JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id
                INNER JOIN " . DB_PREFIX . "user u ON %s
                INNER JOIN " . DB_PREFIX . "team_members tm ON u.id = tm.user_id
                INNER JOIN " . DB_PREFIX . "team te ON tm.team_id = te.id AND te.is_active = 1
                WHERE %s AND %s AND %s%s
                GROUP BY tm.team_id
                UNION ALL
                SELECT tm.team_id,
                    0 AS revenue,
                    0 AS likes,
                    0 AS dislikes,
                    0 AS drawing_count,
                    COUNT(*) AS task_count
                FROM " . DB_PREFIX . "tasks t
                INNER JOIN " . DB_PREFIX . "projects p ON t.project_id = p.id
                INNER JOIN " . DB_PREFIX . "user u ON %s
                INNER JOIN " . DB_PREFIX . "team_members tm ON u.id = tm.user_id
                INNER JOIN " . DB_PREFIX . "team te ON tm.team_id = te.id AND te.is_active = 1
                WHERE %s AND %s AND %s AND %s%s
                GROUP BY tm.team_id
             ) yearly_stats
             GROUP BY team_id",
            $creatorJoin,
            $this->getDrawingRevenueEligibilitySql('pd', 'p'),
            $projectScope,
            $drawingPeriodSql,
            $activeUserSql,
            $teamMemberScope,
            $assigneeJoin,
            $projectScope,
            $taskPeriodSql,
            $activeUserSql,
            $teamMemberScope,
            $assigneeJoin,
            $projectScope,
            $taskPeriodSql,
            $this->getTaskCountStatusSql('t'),
            $activeUserSql,
            $teamMemberScope
        ));
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
                if (!$this->isStatisticsMonthAllowed($ym)) {
                    continue;
                }
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
                'department_id' => isset($team['department_id']) ? intval($team['department_id']) : null,
                'department_name' => $team['department_name'] ?? null,
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
        
        // Calculate date range for last N months (not before statistics min month)
        $end_date = date('Y-m-t');
        $start_date = $this->clampStatisticsStartDate(date('Y-m-01', strtotime("-$months months")));
        
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

