<?php


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
	}
	

	function list() {
		$query = sprintf(
			"SELECT t.*, 
			d.name as department_name,
			(SELECT COUNT(*) FROM " . DB_PREFIX . "team_members WHERE team_id = t.id) as member_count,
			GROUP_CONCAT(u.realname SEPARATOR ', ') as members
			FROM {$this->table} t
			LEFT JOIN " . DB_PREFIX . "departments d ON t.department_id = d.id
			LEFT JOIN " . DB_PREFIX . "team_members tm ON t.id = tm.team_id
			LEFT JOIN " . DB_PREFIX . "user u ON tm.user_id = u.id
			WHERE t.is_active = 1
			GROUP BY t.id
			ORDER BY t.id ASC"
		);
		$result = $this->fetchAll($query);

		
		// Process the members_json field
		// foreach ($result as &$team) {
		// 	if ($team['members_json'] && $team['members_json'] != 'null') {
		// 		$team['members'] = json_decode($team['members_json'], true);
		// 	} else {
		// 		$team['members'] = [];
		// 	}
		// 	unset($team['members_json']);
		// }
		
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
		
		// Add team members
		if (isset($_POST['members']) && is_array($_POST['members'])) {
			foreach ($_POST['members'] as $user_id) {
				$member_data = array(
					'team_id' => $team_id,
					'user_id' => $user_id,
					'leader' => isset($_POST['leader'][$user_id]) && $_POST['leader'][$user_id] == 'true' ? 1 : 0
				);
				$this->query_insert($member_data, DB_PREFIX . 'team_members');
			}
		}
		
		return $team_id;
	}

	function edit() {
		$id = $_GET['id'];
		$data = array(
			'name' => $_POST['name'],
			'department_id' => $_POST['department_id'],
			'description' => $_POST['description'],
			'updated_at' => date('Y-m-d H:i:s')
		);
		
		// Update team info
		$this->query_update($data, ['id' => $id]);
		
		// Update team members
		if (isset($_POST['members']) && is_array($_POST['members'])) {
			// First delete existing members
			$this->query("DELETE FROM " . DB_PREFIX . "team_members WHERE team_id = " . intval($id));
			
			// Then add new members
			foreach ($_POST['members'] as $user_id) {
				$member_data = array(
					'team_id' => $id,
					'user_id' => $user_id,
					'leader' => isset($_POST['leader'][$user_id]) && $_POST['leader'][$user_id] == 'true' ? 1 : 0
				);
				$this->query_insert($member_data, DB_PREFIX . 'team_members');
			}
		}
		
		return true;
	}

	function delete() {
		$id = $_GET['id'];
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
				WHERE tm.team_id = %d",
				intval($id)
			);
			$team['members'] = $this->fetchAll($query);

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