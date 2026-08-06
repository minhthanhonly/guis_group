<?php


class Config extends ApplicationModel {
	
	function __construct($handler = null) {
		
		$this->table = DB_PREFIX.'config';
		$this->schema = array(
		'config_type'=>array(),
		'config_key'=>array(),
		'config_name'=>array(),
		'config_value'=>array());
		if ($handler && !$this->handler) {
			$this->handler = $handler;
		}
		
	}

	function get($type) {
		$data = $this->configure($type);
		$data['list_config'] = $this->selectAllConfigTimecard();
		return $data;
	}

	function getListConfigTimecard() {
		$data = $this->selectAllConfigTimecard();
		return $data;
	}

	function getConfigTimeCardByUser($userid) {
		$query = sprintf("SELECT member_type FROM %suser WHERE (userid = '%s')", DB_PREFIX, $this->quote($userid));
		$data = $this->fetchOne($query);
		$type = 'timecard';
		$result = array();
		if($data['member_type'] != ""){
			$type = $data['member_type'];
		}
		$result = $this->configure($type);
		return $result;
	}

	/**
	 * Public API: working hours for userid via member_type → config_type.
	 */
	function get_work_hours_public($params) {
		$hash = array(
			'status' => 'error',
			'message_code' => '',
			'data' => null,
		);
		$userid = isset($params['userid']) ? trim((string)$params['userid']) : '';
		if ($userid === '') {
			$hash['message_code'] = 'userid is required';
			return $hash;
		}

		$user = $this->fetchOne(sprintf(
			"SELECT userid, realname, member_type, work_hours_warning FROM %suser WHERE userid = '%s' LIMIT 1",
			DB_PREFIX,
			$this->quote($userid)
		));
		if (!$user || empty($user['userid'])) {
			$hash['message_code'] = 'user not found';
			return $hash;
		}

		$member_type = isset($user['member_type']) ? trim((string)$user['member_type']) : '';
		$config_type = ($member_type !== '') ? $member_type : 'timecard';

		$query = sprintf(
			"SELECT config_key, config_value, config_name, config_type FROM %sconfig WHERE config_type = '%s'",
			DB_PREFIX,
			$this->quote($config_type)
		);
		$rows = $this->fetchAll($query);
		$config = array();
		$config_name = '';
		if (is_array($rows) && count($rows) > 0) {
			foreach ($rows as $row) {
				$config[$row['config_key']] = $row['config_value'];
				if ($config_name === '' && isset($row['config_name'])) {
					$config_name = $row['config_name'];
				}
			}
		}

		$openhour = isset($config['openhour']) ? intval($config['openhour']) : null;
		$openminute = isset($config['openminute']) ? intval($config['openminute']) : null;
		$closehour = isset($config['closehour']) ? intval($config['closehour']) : null;
		$closeminute = isset($config['closeminute']) ? intval($config['closeminute']) : null;
		$lunchopenhour = isset($config['lunchopenhour']) ? intval($config['lunchopenhour']) : null;
		$lunchopenminute = isset($config['lunchopenminute']) ? intval($config['lunchopenminute']) : null;
		$lunchclosehour = isset($config['lunchclosehour']) ? intval($config['lunchclosehour']) : null;
		$lunchcloseminute = isset($config['lunchcloseminute']) ? intval($config['lunchcloseminute']) : null;

		$hash['status'] = 'success';
		$hash['message_code'] = '';
		$hash['data'] = array(
			'userid' => $user['userid'],
			'realname' => isset($user['realname']) ? $user['realname'] : '',
			'member_type' => $member_type,
			'config_type' => $config_type,
			'config_name' => $config_name,
			'config' => $config,
			'work_start' => ($openhour !== null && $openminute !== null)
				? sprintf('%02d:%02d', $openhour, $openminute) : null,
			'work_end' => ($closehour !== null && $closeminute !== null)
				? sprintf('%02d:%02d', $closehour, $closeminute) : null,
			'lunch_start' => ($lunchopenhour !== null && $lunchopenminute !== null)
				? sprintf('%02d:%02d', $lunchopenhour, $lunchopenminute) : null,
			'lunch_end' => ($lunchclosehour !== null && $lunchcloseminute !== null)
				? sprintf('%02d:%02d', $lunchclosehour, $lunchcloseminute) : null,
			// Asia/Tokyo (set in api/loader.php) — used by GUIS Plus lock / overtime checks
			'timezone' => date_default_timezone_get(),
			'server_time' => date('H:i:s'),
			'server_now' => date('Y-m-d H:i:s'),
			// Administrator toggles this on member/online.php (default off)
			'work_hours_warning' => !empty($user['work_hours_warning']) ? 1 : 0,
		);
		return $hash;
	}
	
	function edit($type) {
		$data = $this->configure($type);
		if ($_SERVER['REQUEST_METHOD'] == 'POST' && is_array($_POST[$type]) && count($_POST[$type]) > 0) {
			foreach ($_POST[$type] as $key => $value) {
				if (preg_match('/^[a-zA-Z0-9]+$/', $key) && preg_match('/^[a-zA-Z0-9]*$/', $value)) {
					if (is_array($data) && array_key_exists($key, $data)) {
						$query = sprintf("UPDATE %s SET config_value='%s', editor='%s', updated='%s' WHERE (config_key = '%s') AND (config_type = '%s')", $this->table, $this->quote($value), $this->quote($_SESSION['userid']), date('Y-m-d H:i:s'), $this->quote($key), $this->quote($type));
						$this->response = $this->query($query);
					} else {
						$this->post['config_type'] = $type;
						$this->post['config_key'] = $key;
						$this->post['config_value'] = $value;
						$this->insertPost();
					}
					$data[$key] = $value;
				}
			}
			if ($this->response) {
				$this->error[] = '設定を保存しました。';
			}
		}
		return $data;
	}

	function editConfigTimecard($type) {
		if ($_SERVER['REQUEST_METHOD'] == 'POST' && is_array($_POST[$type]) && count($_POST[$type]) > 0) {
			foreach ($_POST[$type] as $key => $value) {
				if (preg_match('/^[a-zA-Z0-9]+$/', $key) && preg_match('/^[a-zA-Z0-9]*$/', $value)) {
					$query = sprintf("UPDATE %s SET config_value='%s', editor='%s', updated='%s', config_name='%s' WHERE (config_key = '%s') AND (config_type = '%s')", $this->table, $this->quote($value), $this->quote($_SESSION['userid']), date('Y-m-d H:i:s'),$this->quote($_POST["config_name"]) , $this->quote($key), $this->quote($type));
					$this->response = $this->query($query);
					$data["config_name"] = $_POST["config_name"];
					$data[$key] = $value;
				}
			}
			if ($this->response) {
				$this->error[] = '設定を保存しました。';
			}
		}
		$data['list_config'] = $this->selectAllConfigTimecard();
		return $data;
	
	}

	function add($type) {
		if($_SERVER['REQUEST_METHOD'] == 'POST'){
			$name = $_POST["config_name"];
			if(empty($name)){
				$this->error[] = '名前を入力してください。';
			}
			if($_POST["config_name"] && $this->check_config_name($name)){
				$this->error[] = '同じ名前の設定が既に存在します。';
			}
			$data['config_name'] = $name;
			if (is_array($_POST[$type]) && count($_POST[$type]) > 0) {
				
				foreach ($_POST[$type] as $key => $value) {
					if (preg_match('/^[a-zA-Z0-9]+$/', $key) && preg_match('/^[a-zA-Z0-9]*$/', $value)) {
						$this->post['config_type'] = $type;
						$this->post['config_key'] = $key;
						$this->post['config_value'] = $value;
						$this->post['config_name'] = $name;
						if(count($this->error) == 0){
							$this->insertPost();
						}
						$data[$key] = $value;
					}
				}
			}
		}
		
		return $data;
	}


	function configure($type) {
		$query = sprintf("SELECT %s FROM %sconfig WHERE (config_type = '%s')", implode(',', $this->schematize()), DB_PREFIX, $this->quote($type));
		$data = $this->fetchAll($query);
		$result = array();
		if (is_array($data) && count($data) > 0) {
			foreach ($data as $row) {
				$result[$row['config_key']] = $row['config_value'];
			}
		}
		$result['config_name'] = $data[0]['config_name'];
		return $result;
	}

	function selectAllConfigTimecard() {
		$query = sprintf("SELECT DISTINCT %s FROM %sconfig  ORDER BY config_type", "config_type,config_name", DB_PREFIX);
		$data = $this->fetchAll($query);
		return $data;
	}

	function check_config_name($name) {
		if (empty($name)) {
			return false;
		}
		$where = sprintf("WHERE %s = '%s'", "config_name", $name);
		$count = $count = $this->fetchCount($this->table, $where, 'config_name');
		if ($count > 0) {
			return true;
		}
		return false;
	}
}

?>