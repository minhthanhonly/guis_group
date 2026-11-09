<?php

/**
 * Team model. Table team_members: user_id = numeric (user.id), not userid string.
 * team_supervisors: team management supervisors (may also be members).
 */
class Team extends ApplicationModel {
	
	function __construct() {
		
		$this->table = DB_PREFIX.'team';
		$this->schema = array(
			'id'=>array('ID', 'numeric', 'length:10', 'except'=>array('search')),
			'name'=>array('チーム名'),
			'department_id'=>array('部門'),
			'description'=>array('説明'),
			'created_at'=>array('作成日', 'except'=>array('search')),
			'updated_at'=>array('更新日', 'except'=>array('search')),
			'is_active'=>array('ステータス'),
		);
		
		$this->connect();
		$this->ensureSupervisorsTable();
	}

	private function supervisorsTable() {
		return DB_PREFIX . 'team_supervisors';
	}

	/** Create team_supervisors if missing (managers who are not members). */
	private function ensureSupervisorsTable() {
		static $ensured = false;
		if ($ensured) {
			return;
		}
		$ensured = true;
		$table = $this->supervisorsTable();
		$row = $this->fetchOne(
			"SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.TABLES "
			. "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->quote($table) . "'"
		);
		if (!empty($row['cnt'])) {
			return;
		}
		$this->query(
			"CREATE TABLE `{$table}` ("
			. "`id` int(11) NOT NULL AUTO_INCREMENT,"
			. "`team_id` int(11) NOT NULL,"
			. "`user_id` int(11) NOT NULL COMMENT 'user.id (numeric)',"
			. "`created_at` datetime DEFAULT NULL,"
			. "PRIMARY KEY (`id`),"
			. "UNIQUE KEY `team_supervisor_user` (`team_id`,`user_id`),"
			. "KEY `idx_team_supervisors_team` (`team_id`),"
			. "KEY `idx_team_supervisors_user` (`user_id`)"
			. ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
		);
	}

	/** Normalize POST user-id list to unique positive ints. */
	private function normalizeUserIdList($raw) {
		if (!is_array($raw)) {
			return array();
		}
		$out = array();
		foreach ($raw as $id) {
			$uid = intval($id);
			if ($uid > 0) {
				$out[$uid] = $uid;
			}
		}
		return array_values($out);
	}

	/** Persist supervisors (may overlap with members). */
	private function replaceSupervisors($teamId, array $supervisorIds) {
		$teamId = intval($teamId);
		if ($teamId <= 0) {
			return;
		}
		$table = $this->supervisorsTable();
		$this->query(sprintf("DELETE FROM {$table} WHERE team_id = %d", $teamId));
		$now = date('Y-m-d H:i:s');
		foreach ($supervisorIds as $uid) {
			$uid = intval($uid);
			if ($uid <= 0) {
				continue;
			}
			$this->query_insert(array(
				'team_id' => $teamId,
				'user_id' => $uid,
				'created_at' => $now
			), $table);
		}
	}

	private function fetchSupervisors($teamId) {
		$query = sprintf(
			"SELECT ts.*, u.realname as user_name
			FROM %s ts
			LEFT JOIN %suser u ON ts.user_id = u.id
			WHERE ts.team_id = %d
			  AND (u.is_suspend = 0 OR u.is_suspend IS NULL OR u.is_suspend = '')
			ORDER BY u.realname ASC",
			$this->supervisorsTable(),
			DB_PREFIX,
			intval($teamId)
		);
		return $this->fetchAll($query) ?: array();
	}
	

	function list() {
		$query = sprintf(
			"SELECT t.*, 
			d.name as department_name,
			COUNT(DISTINCT tm.user_id) as member_count,
			GROUP_CONCAT(DISTINCT u.realname ORDER BY u.realname SEPARATOR ', ') as members,
			GROUP_CONCAT(DISTINCT su.realname ORDER BY su.realname SEPARATOR ', ') as supervisors
			FROM {$this->table} t
			LEFT JOIN " . DB_PREFIX . "departments d ON t.department_id = d.id
			LEFT JOIN " . DB_PREFIX . "team_members tm ON t.id = tm.team_id
			LEFT JOIN " . DB_PREFIX . "user u ON tm.user_id = u.id
				AND (u.is_suspend = 0 OR u.is_suspend IS NULL OR u.is_suspend = '')
			LEFT JOIN " . $this->supervisorsTable() . " ts ON t.id = ts.team_id
			LEFT JOIN " . DB_PREFIX . "user su ON ts.user_id = su.id
				AND (su.is_suspend = 0 OR su.is_suspend IS NULL OR su.is_suspend = '')
			WHERE t.is_active = 1
			GROUP BY t.id, d.name
			ORDER BY t.id ASC"
		);
		$result = $this->fetchAll($query);
		
		return $result;
	}

	function listbydepartment() {
		$department_id = $_GET['department_id'];
		$query = sprintf(
			"SELECT *
			FROM {$this->table} t
			WHERE t.is_active = 1 AND t.department_id = %d
			ORDER BY t.id ASC", 
			intval($department_id)
		);
		$result = $this->fetchAll($query);
		
		return $result;
	}

	function add() {
		$data = array(
			'name' => $_POST['name'],
			'department_id' => $_POST['department_id'],
			'description' => $_POST['description'],
			'created_at' => date('Y-m-d H:i:s')
		);
		$team_id = $this->query_insert($data);
		
		$memberIds = $this->normalizeUserIdList(isset($_POST['members']) ? $_POST['members'] : array());
		$supervisorIds = $this->normalizeUserIdList(isset($_POST['supervisors']) ? $_POST['supervisors'] : array());

		// Add team members. team_members.user_id = numeric (user.id), not userid string.
		foreach ($memberIds as $user_id) {
			$member_data = array(
				'team_id' => $team_id,
				'user_id' => $user_id,
				'leader' => isset($_POST['leader'][$user_id]) && ($_POST['leader'][$user_id] === 'true' || $_POST['leader'][$user_id] === true || $_POST['leader'][$user_id] === '1' || $_POST['leader'][$user_id] === 1) ? 1 : 0
			);
			$this->query_insert($member_data, DB_PREFIX . 'team_members');
		}

		$this->replaceSupervisors($team_id, $supervisorIds);

		$cacheIds = array_merge($memberIds, $supervisorIds);
		if (!empty($cacheIds)) {
			$this->invalidateApiCacheForNumericUserIds($cacheIds);
		}
		
		return $team_id;
	}

	function edit() {
		$id = $_GET['id'];
		$this->invalidateApiCacheForTeamId($id);
		$data = array(
			'name' => $_POST['name'],
			'department_id' => $_POST['department_id'],
			'description' => $_POST['description'],
			'updated_at' => date('Y-m-d H:i:s')
		);
		
		// Update team info
		$this->query_update($data, ['id' => $id]);

		$memberIds = $this->normalizeUserIdList(isset($_POST['members']) ? $_POST['members'] : array());
		$supervisorIds = $this->normalizeUserIdList(isset($_POST['supervisors']) ? $_POST['supervisors'] : array());
		
		// Update team members. team_members.user_id = numeric (user.id), not userid string.
		$this->query("DELETE FROM " . DB_PREFIX . "team_members WHERE team_id = " . intval($id));
		foreach ($memberIds as $user_id) {
			$member_data = array(
				'team_id' => $id,
				'user_id' => $user_id,
				'leader' => isset($_POST['leader'][$user_id]) && ($_POST['leader'][$user_id] === 'true' || $_POST['leader'][$user_id] === true || $_POST['leader'][$user_id] === '1' || $_POST['leader'][$user_id] === 1) ? 1 : 0
			);
			$this->query_insert($member_data, DB_PREFIX . 'team_members');
		}

		$this->replaceSupervisors($id, $supervisorIds);

		$cacheIds = array_merge($memberIds, $supervisorIds);
		if (!empty($cacheIds)) {
			$this->invalidateApiCacheForNumericUserIds($cacheIds);
		}
		
		return true;
	}

	function delete() {
		$id = $_GET['id'];
		$this->invalidateApiCacheForTeamId($id);
		$this->query(sprintf("DELETE FROM %s WHERE team_id = %d", $this->supervisorsTable(), intval($id)));
		return $this->query_update(['is_active' => 0], ['id' => $id]);
	}

	function get() {
		$id = $_GET['id'];
		$query = sprintf(
			"SELECT t.*, d.name as department_name
			FROM {$this->table} t
			LEFT JOIN " . DB_PREFIX . "departments d ON t.department_id = d.id
			WHERE t.id = %d",
			intval($id)
		);
		$team = $this->fetchOne($query);
		
		if ($team) {
			// Get team members with their permissions
			$query = sprintf(
				"SELECT tm.*, u.realname as user_name
				FROM " . DB_PREFIX . "team_members tm
				LEFT JOIN " . DB_PREFIX . "user u ON tm.user_id = u.id
				WHERE tm.team_id = %d AND (u.is_suspend = 0 OR u.is_suspend IS NULL OR u.is_suspend = '')",
				intval($id)
			);
			$team['members'] = $this->fetchAll($query);
			$team['supervisors'] = $this->fetchSupervisors($id);

			$query = sprintf(
				"SELECT u.id, u.realname as user_name
				FROM " . DB_PREFIX . "user u
				JOIN " . DB_PREFIX . "user_department ud ON u.userid = ud.userid
				WHERE ud.department_id = %d
				ORDER BY u.userid ASC",
				intval($team['department_id'])
			);
			$team['whitelist'] = $this->fetchAll($query);
		}
		
		return $team;
	}

	function getDepartmentUsers() {
		$department_id = $_GET['department_id'];
		$query = sprintf(
			"SELECT u.id, u.realname as name
			FROM " . DB_PREFIX . "user u
			JOIN " . DB_PREFIX . "user_department ud ON u.userid = ud.userid
			WHERE ud.department_id = %d
			  AND (u.is_suspend = 0 OR u.is_suspend IS NULL OR u.is_suspend = '')
			ORDER BY u.userid ASC",
			intval($department_id)
		);
		return $this->fetchAll($query);
	}

	function listbyids() {
		$ids = isset($_GET['ids']) ? $_GET['ids'] : '';
		if (!$ids) return [];
		$idArr = array_map('intval', explode(',', $ids));
		$idStr = implode(',', $idArr);
		$query = sprintf(
			"SELECT id, name FROM %s WHERE id IN (%s)",
			$this->table,
			$idStr
		);
		return $this->fetchAll($query);
	}
}

?>
