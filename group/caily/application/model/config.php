<?php


class Config extends ApplicationModel {
	
	function Config($handler = null) {
		
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