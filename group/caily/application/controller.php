<?php


class Controller {
	
	function dispatch() {
		$this->requiring();
		$authority = new Authority;
		if (!$authority->ensureAuthenticated()) {
			if (basename($_SERVER['SCRIPT_NAME']) != 'login.php') {
				$_SESSION['referer'] = $_SERVER['REQUEST_URI'];
				header('Location:' . ROOT . 'login.php');
				exit();
			}
		} elseif (basename($_SERVER['SCRIPT_NAME']) == 'login.php') {
			$redirect = isset($_SESSION['referer']) ? $_SESSION['referer'] : 'index.php';
			unset($_SESSION['referer']);
			header('Location: ' . $redirect);
			exit();
		}
		
		return $this->execute();
	}

	function json() {
		$this->requiring();
		$authority = new Authority;
		if (!$authority->ensureAuthenticated()) {
			die('認証に失敗しました。ログインし直してください。');
		}
		return $this->execute();
	}

	function initApi() {
		$this->requiring();
		$authority = new Authority;
		if (!$authority->ensureAuthenticated()) {
			die('認証に失敗しました。ログインし直してください。');
		}
	}

	function api($model, $method, $params) {
		return $this->executeApi($model, $method, $params);
	}
	
	function execute() {
		if (!file_exists('application')) {
			$directory = basename(dirname($_SERVER['SCRIPT_NAME']));
		} else {
			$directory = 'general';
		}
		$modelfile = DIR_MODEL.$directory.'.php';
		$class = ucfirst($directory);
		$method = str_replace('.php', '', basename($_SERVER['SCRIPT_NAME']));
		$hash = array();
		
		if (file_exists($modelfile)) {
			require_once($modelfile);
			if (class_exists($class)) {
				
				$model = new $class;
				$model->checkSuspend();
				if (method_exists($model, $method)) {
					$model->connect();
					$hash = $model->$method();
					$hash = $model->sanitize($hash);
					$model->close();
					if (isset($model->error) && count($model->error) > 0) {
						$hash['error'] = $model->error;
					}
				}
			}
		}
		return $hash;

	}

	function executeApi($model, $method, $params) {
		$modelfile = DIR_MODEL.$model.'.php';
		$class = ucfirst($model);
		$hash = array();
		
		if (file_exists($modelfile)) {
			try {
				require_once($modelfile);
				if (class_exists($class)) {
					$model = new $class;
					if (method_exists($model, $method)) {
						$model->connect();
						$hash = $model->$method($params);
						// Phase 4 – If model returns http_status (e.g. 403 Forbidden), set response code and remove from body
						if (isset($hash['http_status']) && is_numeric($hash['http_status'])) {
							http_response_code((int) $hash['http_status']);
							unset($hash['http_status']);
						}
						// Phase 5.2 – Audit log for AI-triggered writes (request has ai_action=1 and response is success)
						if (isset($_REQUEST['ai_action']) && $_REQUEST['ai_action'] == '1' && isset($hash['status']) && $hash['status'] === 'success') {
							$userId = isset($_SESSION['userid']) ? $_SESSION['userid'] : (isset($_SESSION['id']) ? (string) $_SESSION['id'] : '');
							$targetIds = [];
							if (isset($hash['id'])) $targetIds['id'] = $hash['id'];
							if (isset($hash['project_id'])) $targetIds['project_id'] = $hash['project_id'];
							Helper::logAiTriggeredAction($model, $method, $userId, $targetIds);
						}
						$hash = $model->sanitize($hash);
						$model->close();
						if (isset($model->error) && count($model->error) > 0) {
							$hash['error'] = $model->error;
						}
					} else {
						$hash['error'] = 'Method ' . $method . ' not found in class ' . $class;
					}
				} else {
					$hash['error'] = 'Class ' . $class . ' not found in file ' . $modelfile;
				}
			} catch (Exception $e) {
				$hash['error'] = 'Error loading model: ' . $e->getMessage();
			} catch (Error $e) {
				$hash['error'] = 'Fatal error: ' . $e->getMessage();
			}
		} else {
			$hash['error'] = 'Model file not found: ' . $modelfile;
		}
		return json_encode($hash);
	}

	function requiring() {
	
		mb_internal_encoding('UTF-8');
		require_once(dirname(__FILE__).'/config.php');
		if (DB_STORAGE == 'mysql') {
			require_once(DIR_LIBRARY.'connection'.DB_STORAGE.'.php');
		} else {
			require_once(DIR_LIBRARY.'connection.php');
		}
		require_once(DIR_LIBRARY.'validation.php');
		require_once(DIR_LIBRARY.'helper.php');
		require_once(DIR_LIBRARY.'pagination.php');
		require_once(DIR_LIBRARY.'authority.php');
		require_once(DIR_LIBRARY.'filing.php');
		require_once(DIR_LIBRARY.'postcode.php');
		require_once(DIR_MODEL.'model.php');
		require_once(DIR_MODEL.'applicationmodel.php');
		require_once(DIR_MODEL.'config.php');
		require_once(DIR_VIEW.'view.php');
		require_once(DIR_VIEW.'applicationview.php');
		require_once(DIR_VIEW.'calendar.php');
		require_once(DIR_VIEW.'explanation.php');
		$input = $_POST;
		$input = array_map(function($value) {
			if (is_array($value)) {
				return array_map(function($v) {
					return is_string($v) ? stripslashes($v) : $v;
				}, $value);
			}
			return is_string($value) ? stripslashes($value) : $value;
		}, $input);
		$_POST = $input;

		$input = $_GET;
		$input = array_map(function($value) {
			if (is_array($value)) {
				return array_map(function($v) {
					return is_string($v) ? stripslashes($v) : $v;
				}, $value);
			}
			return is_string($value) ? stripslashes($value) : $value;
		}, $input);
		$_GET = $input;
	}

	function strip($data) {
	
		if (is_array($data)) {
			return array_map(array($this, 'strip'), $data);
		} else {
			return stripslashes($data);
		}
		
	}

}

?>