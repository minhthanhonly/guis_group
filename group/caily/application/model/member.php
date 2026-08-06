<?php


class Member extends ApplicationModel {
	
	function __construct() {
		
		$this->table = DB_PREFIX.'user';
		$this->schema = array(
		'userid'=>array('ユーザーID', 'notnull', 'userid', 'length:100', 'distinct', 'except'=>array('update')),
		'user_group'=>array('except'=>array('search')),
		'user_groupname'=>array('except'=>array('search')),
		'lastname'=>array('姓', 'notnull', 'length:100'),
		'lastname_after_married'=>array('結婚後の姓', 'length:100'),
		'firstname'=>array('名', 'length:100'),
		'realname'=>array('名前', 'length:100'),
		'user_ruby'=>array('かな', 'length:100'),
		'authority'=>array('権限', 'length:20'),
		'user_postcode'=>array('郵便番号', 'postcode', 'length:8'),
		'user_address'=>array('住所', 'length:1000'),
		'user_addressruby'=>array('住所(かな)', 'length:1000'),
		'user_phone'=>array('電話番号', 'phone', 'length:20'),
		'user_mobile'=>array('携帯電話', 'phone', 'length:20'),
		'user_email'=>array('メールアドレス', 'email', 'length:1000'),
		'user_skype'=>array('スカイプID', 'userid', 'length:1000'),
		'user_image'=>array('写真', 'length:100'),
		'user_order'=>array('順序', 'numeric', 'length:10', 'except'=>array('search')),
		'edit_level'=>array('except'=>array('search')),
		'edit_group'=>array('except'=>array('search')),
		'edit_user'=>array('except'=>array('search')),
		'member_type'=>array('従業員の種類', 'length:100'),
		'is_suspend'=>array('ステータス', 'numeric', 'length:1','except'=>array('search', 'update')),
		'position'=>array('役職', 'length:100'),
		'branch_id'=>array('支店', 'numeric', 'length:10'),
		'show_project'=>array('案件関連を表示', 'numeric', 'length:1'),
		'can_approve_request'=>array('申請関係の承認を許可します', 'numeric', 'length:1'),
		'is_soumu'=>array('総務管理を許可します', 'numeric', 'length:1'),
		'quite_date'=>array('退職日', 'except'=>array('search', 'insert')),
		);
		
	}

	function connect() {
		parent::connect();
		$this->ensureQuiteDateColumn();
	}

	/**
	 * Add quite_date (退職日) if missing — column may not exist until migration runs.
	 */
	/**
	 * quite_date: 空文字は INSERT/UPDATE しない（MySQL DATETIME エラー回避）
	 * @param bool $isUpdate 編集時は退職グループで空の場合に現在日時を設定
	 * @return bool 編集後に quite_date を NULL にする必要があるか
	 */
	private function normalizeQuiteDatePost($isUpdate) {
		$clearQuiteDate = false;
		$isRetireGroup = isset($this->post['user_group'])
			&& (string) $this->post['user_group'] === (string) RETIRE_GROUP;
		if (!array_key_exists('quite_date', $this->post)) {
			if ($isUpdate && $isRetireGroup) {
				$this->post['quite_date'] = date('Y-m-d H:i:s');
			}
			return false;
		}
		$qd = trim((string) $this->post['quite_date']);
		if ($qd === '') {
			if ($isUpdate && $isRetireGroup) {
				$this->post['quite_date'] = date('Y-m-d H:i:s');
			} else {
				unset($this->post['quite_date']);
				if ($isUpdate) {
					$clearQuiteDate = true;
				}
			}
		} else {
			$this->post['quite_date'] = $qd;
		}
		return $clearQuiteDate;
	}

	private function ensureQuiteDateColumn() {
		static $ensured = false;
		if ($ensured) {
			return;
		}
		$ensured = true;
		$table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $this->table);
		if ($table === '') {
			return;
		}
		$row = $this->fetchOne(
			"SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS "
			. "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->quote($table) . "' AND COLUMN_NAME = 'quite_date'"
		);
		if (!empty($row['cnt'])) {
			return;
		}
		$this->query(
			"ALTER TABLE `{$table}` ADD COLUMN `quite_date` DATETIME NULL DEFAULT NULL COMMENT '退職日'"
		);
	}

	/**
	 * GUIS Plus overtime toast preference (administrator toggles on member/online.php).
	 * Default 0 = off.
	 */
	function ensureWorkHoursWarningColumn() {
		static $ensured = false;
		if ($ensured) {
			return;
		}
		$ensured = true;
		$table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $this->table);
		if ($table === '') {
			$table = 'groupware_user';
		}
		$row = $this->fetchOne(
			"SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS "
			. "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->quote($table) . "' AND COLUMN_NAME = 'work_hours_warning'"
		);
		if (!empty($row['cnt'])) {
			return;
		}
		$this->query(
			"ALTER TABLE `{$table}` ADD COLUMN `work_hours_warning` TINYINT(1) NOT NULL DEFAULT 0 "
			. "COMMENT 'GUIS Plus overtime toast: 1=enabled 0=disabled'"
		);
	}

	function get_member() {
		$this->ensureWorkHoursWarningColumn();
		$config = new Config($this->handler);
		$query = "SELECT groupware_user.id as `id`, `userid`, `realname`, `lastname`, `firstname`, `lastname_after_married`, `authority`, `user_group`, `gender`, `user_email`, `user_skype`, `user_ruby`, `user_postcode`, `user_address`, `user_addressruby`, `user_phone`, `user_mobile`, `user_order`, `status`, `idle_time`, `pc_hashs`, `member_type`, `user_image`, `is_suspend`, `quite_date`, branch_id, `show_project`, `can_approve_request`, `work_hours_warning`, `is_soumu`, groupware_group.group_name as group_name FROM groupware_user, groupware_group WHERE groupware_user.user_group = groupware_group.id order by is_suspend asc, groupware_user.id asc";
		$hash['list'] = $this->fetchAll($query);
		$hash['group'] = $this->findGroup();

		$hash['list_config'] = $config->getListConfigTimecard();

		$arr_config = array();
		foreach ($hash['list_config'] as $key => $value) {
			$arr_config[$value["config_type"]] = $value["config_name"];
		}

		// Load shift hours once per config_type (member_type → open/close)
		$hoursRows = $this->fetchAll(
			"SELECT config_type, config_key, config_value FROM " . DB_PREFIX . "config "
			. "WHERE config_key IN ("
			. "'openhour','openminute','closehour','closeminute',"
			. "'lunchopenhour','lunchopenminute','lunchclosehour','lunchcloseminute'"
			. ")"
		);
		$hoursByType = array();
		if (is_array($hoursRows)) {
			foreach ($hoursRows as $row) {
				$type = isset($row['config_type']) ? (string)$row['config_type'] : '';
				$key = isset($row['config_key']) ? (string)$row['config_key'] : '';
				if ($type === '' || $key === '') {
					continue;
				}
				if (!isset($hoursByType[$type])) {
					$hoursByType[$type] = array();
				}
				$hoursByType[$type][$key] = $row['config_value'];
			}
		}

		// 支店 (branch) → company: CAILY if branch name is CAILY, else GUIS
		$branchNameById = array();
		$branchRows = $this->fetchAll("SELECT id, name FROM " . DB_PREFIX . "branches");
		if (is_array($branchRows)) {
			foreach ($branchRows as $branchRow) {
				$bid = isset($branchRow['id']) ? intval($branchRow['id']) : 0;
				if ($bid > 0) {
					$branchNameById[$bid] = isset($branchRow['name']) ? trim((string)$branchRow['name']) : '';
				}
			}
		}

		foreach ($hash['list'] as $key => $value) {
			$memberType = isset($value['member_type']) ? trim((string)$value['member_type']) : '';
			$configType = ($memberType !== '') ? $memberType : 'timecard';
			if ($memberType === '' || $value['member_type'] == null) {
				$hash['list'][$key]['member_type_name'] = isset($arr_config['timecard']) ? $arr_config['timecard'] : 'timecard';
			} else {
				$hash['list'][$key]['member_type_name'] = isset($arr_config[$memberType])
					? $arr_config[$memberType]
					: $memberType;
			}
			$hash['list'][$key]['member_type'] = $memberType;
			$hash['list'][$key]['department_id'] = $this->getDepartment($value['userid']);
			$hash['list'][$key]['work_hours_warning'] = !empty($value['work_hours_warning']) ? 1 : 0;

			$branchId = isset($value['branch_id']) ? intval($value['branch_id']) : 0;
			$branchName = ($branchId > 0 && isset($branchNameById[$branchId]))
				? $branchNameById[$branchId]
				: '';
			$hash['list'][$key]['branch_id'] = $branchId > 0 ? $branchId : null;
			$hash['list'][$key]['branch_name'] = $branchName;
			$hash['list'][$key]['company'] = (strcasecmp($branchName, 'CAILY') === 0) ? 'CAILY' : 'GUIS';

			$cfg = isset($hoursByType[$configType]) ? $hoursByType[$configType] : array();
			if (empty($cfg) && $configType !== 'timecard' && isset($hoursByType['timecard'])) {
				$cfg = $hoursByType['timecard'];
			}
			$openHour = isset($cfg['openhour']) && $cfg['openhour'] !== '' ? intval($cfg['openhour']) : null;
			$openMinute = isset($cfg['openminute']) && $cfg['openminute'] !== '' ? intval($cfg['openminute']) : null;
			$closeHour = isset($cfg['closehour']) && $cfg['closehour'] !== '' ? intval($cfg['closehour']) : null;
			$closeMinute = isset($cfg['closeminute']) && $cfg['closeminute'] !== '' ? intval($cfg['closeminute']) : null;
			$lunchOpenHour = isset($cfg['lunchopenhour']) && $cfg['lunchopenhour'] !== '' ? intval($cfg['lunchopenhour']) : null;
			$lunchOpenMinute = isset($cfg['lunchopenminute']) && $cfg['lunchopenminute'] !== '' ? intval($cfg['lunchopenminute']) : null;
			$lunchCloseHour = isset($cfg['lunchclosehour']) && $cfg['lunchclosehour'] !== '' ? intval($cfg['lunchclosehour']) : null;
			$lunchCloseMinute = isset($cfg['lunchcloseminute']) && $cfg['lunchcloseminute'] !== '' ? intval($cfg['lunchcloseminute']) : null;

			$workStart = ($openHour !== null && $openMinute !== null)
				? sprintf('%02d:%02d', $openHour, $openMinute)
				: null;
			$workEnd = ($closeHour !== null && $closeMinute !== null)
				? sprintf('%02d:%02d', $closeHour, $closeMinute)
				: null;
			$lunchStart = ($lunchOpenHour !== null && $lunchOpenMinute !== null)
				? sprintf('%02d:%02d', $lunchOpenHour, $lunchOpenMinute)
				: null;
			$lunchEnd = ($lunchCloseHour !== null && $lunchCloseMinute !== null)
				? sprintf('%02d:%02d', $lunchCloseHour, $lunchCloseMinute)
				: null;

			// 00:00〜00:00 means no lunch break
			$lunchNone = ($lunchStart === '00:00' && $lunchEnd === '00:00');
			if ($lunchNone) {
				$lunchStart = null;
				$lunchEnd = null;
			}

			$hash['list'][$key]['work_start'] = $workStart;
			$hash['list'][$key]['work_end'] = $workEnd;
			$hash['list'][$key]['lunch_start'] = $lunchStart;
			$hash['list'][$key]['lunch_end'] = $lunchEnd;
			$hash['list'][$key]['lunch_none'] = $lunchNone ? 1 : 0;
			$hash['list'][$key]['lunch_label'] = $lunchNone
				? '休憩無し'
				: (($lunchStart && $lunchEnd) ? ('昼 ' . $lunchStart . '〜' . $lunchEnd) : '');

			$label = '';
			if ($workStart && $workEnd) {
				$label = $workStart . '〜' . $workEnd;
			}
			if (!empty($hash['list'][$key]['lunch_label'])) {
				$label .= ($label !== '' ? ' / ' : '') . $hash['list'][$key]['lunch_label'];
			}
			$hash['list'][$key]['work_hours_label'] = $label;
		}
		return $hash;
	
	}

	/**
	 * Administrator: enable/disable GUIS Plus overtime toast for a user.
	 * POST/GET: userid, enabled (0|1|true|false)
	 */
	function set_work_hours_warning($params = array()) {
		$this->authorizeApi('administrator');
		$this->ensureWorkHoursWarningColumn();

		$userid = '';
		$enabledRaw = null;
		if (is_array($params)) {
			$userid = isset($params['userid']) ? trim((string)$params['userid']) : '';
			$enabledRaw = array_key_exists('enabled', $params) ? $params['enabled'] : null;
		}
		if ($userid === '' && isset($_REQUEST['userid'])) {
			$userid = trim((string)$_REQUEST['userid']);
		}
		if ($enabledRaw === null && array_key_exists('enabled', $_REQUEST)) {
			$enabledRaw = $_REQUEST['enabled'];
		}

		$hash = array(
			'status' => 'error',
			'message' => '',
			'userid' => $userid,
			'work_hours_warning' => 0,
		);

		if ($userid === '') {
			$hash['message'] = 'userid is required';
			return $hash;
		}

		$enabled = 0;
		if ($enabledRaw === true || $enabledRaw === 1 || $enabledRaw === '1' || $enabledRaw === 'true' || $enabledRaw === 'on') {
			$enabled = 1;
		}

		$user = $this->fetchOne(sprintf(
			"SELECT userid FROM %s WHERE userid = '%s' LIMIT 1",
			$this->table,
			$this->quote($userid)
		));
		if (!$user || empty($user['userid'])) {
			$hash['message'] = 'user not found';
			return $hash;
		}

		// Do NOT bump `updated` / `editor` — ApplicationModel::checkSuspend()
		// logs the user out when session user_updated !== DB updated.
		$query = sprintf(
			"UPDATE %s SET work_hours_warning = %d WHERE userid = '%s'",
			$this->table,
			$enabled,
			$this->quote($userid)
		);
		$this->query($query);

		$hash['status'] = 'success';
		$hash['work_hours_warning'] = $enabled;
		return $hash;
	}

	function validate() {
		$this->validator('password', 'パスワード', array('alphaNumeric', 'length:4:32'));
		$this->validator('newpassword', '新しいパスワード', array('alphaNumeric', 'length:4:32'));
		$this->validator('confirmpassword', '新しいパスワード(確認)', array('alphaNumeric', 'length:4:32'));
		$_POST['password'] = trim($_POST['password']);
		$_POST['newpassword'] = trim($_POST['newpassword']);
		$_POST['confirmpassword'] = trim($_POST['confirmpassword']);
		if ($_POST['newpassword'] != $_POST['confirmpassword']) {
			$this->error[] = '新しいパスワードと確認用パスワードが違います。';
		} else {
			$data = $this->fetchOne("SELECT password FROM ".$this->table." WHERE userid = '".$this->quote($_SESSION['userid'])."'");
			if (is_array($data) && count($data) > 0) {
				if ($data['password'] === md5($_POST['password'])) {
					$this->schema['password']['except'] = array('search');
					$this->post['password'] = md5($_POST['newpassword']);
				} else {
					$this->error[] = '現在のパスワードが違います。';
				}
			} else {
				$this->error[] = 'パスワード確認時にエラーが発生しました。';
			}
		}
	}

	function permitGroup($id, $level = 'public') {
		
		if ($level == 'add') {
			$where = "WHERE (add_level = 0 OR owner = '%s' OR ";
			$where .= "(add_level = 2 AND (add_group LIKE '%%[%s]%%' OR add_user LIKE '%%[%s]%%')))";
			$where = sprintf($where, $this->quote($_SESSION['userid']), $this->quote($_SESSION['group']), $this->quote($_SESSION['userid']));
		}
		$query = "SELECT id,group_name FROM ".DB_PREFIX."group ".$where." ORDER BY group_order,id";
		$data = $this->fetchAll($query);
		$result['folder'] = array();
		if (is_array($data) && count($data) > 0) {
			foreach ($data as $row) {
				$result['folder'][$row['id']] = $row['group_name'];
			}
		}
		if ($id > 0) {
			$data = $this->fetchOne("SELECT * FROM ".DB_PREFIX."group WHERE id = ".intval($id));
			if ($level == 'add' && !$this->permitted($data, 'add')) {
				$this->died('このグループへの書き込み権限がありません。');
			} else {
				$result['parent'] = $data;
			}
		}
		return $result;
		
	}

	function validateAdd() {
		$hash = $this->permitGroup($_POST['user_group'], 'add');
		$this->post['password'] = md5(trim($_POST['password']));
		$this->post['password_default'] = $this->post['password'];
		$this->schema['password']['except'] = array('search');
		$this->schema['password_default']['except'] = array('search');
		$this->validator('password', 'パスワード', array('alphaNumeric', 'length:4:32'));

		$this->post['user_order'] = 0;
		if ($_POST['user_group'] > 0) {
			$this->post['user_groupname'] = $hash['parent']['group_name'];
			
		}
	}

	function validateEdit() {
		$hash = $this->permitGroup($_POST['user_group'], 'add');
		
		if ($_POST['user_group'] > 0) {
			$this->post['user_groupname'] = $hash['parent']['group_name'];
		}
	}
	
	function index() {
		$config = new Config($this->handler);
		if ($_GET['group'] != 'all') {
			if ($_GET['group'] <= 0) {
				$_GET['group'] = $_SESSION['group'];
			}
			$this->where[] = "(user_group = '".intval($_GET['group'])."')";
		}
		$hash = $this->findLimit('user_order', 0);
		$hash['group'] = $this->findGroup();

		$hash['data']['list_config'] = $config->getListConfigTimecard();

		$arr_config = array();
		foreach ($hash['data']['list_config'] as $key => $value) {
			$arr_config[$value["config_type"]] = $value["config_name"];
		}
		foreach ($hash['list'] as $key => $value) {
			if($value["member_type"] == null){
				$hash['list'][$key]["member_type_name"] = $arr_config['timecard'];
			} else{
				$hash['list'][$key]["member_type_name"] = $arr_config[$value["member_type"]];
			}
			
		}
		return $hash;
	
	}

	/**
	 * Online/offline presence from Firebase connected_users (web / app).
	 */
	function get_presence($params = array()) {
		$hash = array(
			'status' => 'success',
			'presence' => array(),
			'updated_at' => date('Y-m-d H:i:s'),
		);
		try {
			require_once dirname(__DIR__) . '/library/firebase_helper.php';
			$firebase = new FirebaseHelper();
			$hash['presence'] = $firebase->getConnectedUsersPresence();
		} catch (Exception $e) {
			$hash['status'] = 'error';
			$hash['message'] = $e->getMessage();
			$hash['presence'] = array();
		}
		return $hash;
	}

	/**
	 * Today's leave / outing / trip / holiday_work / overtime forms (all users).
	 */
	function get_today_forms($params = array()) {
		$hash = array(
			'status' => 'success',
			'date' => date('Y-m-d'),
			'by_user' => array(),
		);
		try {
			require_once dirname(__FILE__) . '/request.php';
			$request = new Request();
			$date = '';
			if (is_array($params) && isset($params['date'])) {
				$date = trim((string)$params['date']);
			} elseif (isset($_GET['date'])) {
				$date = trim((string)$_GET['date']);
			}
			$bundle = $request->get_forms_for_date_bulk($date);
			$hash['date'] = !empty($bundle['date']) ? $bundle['date'] : date('Y-m-d');
			$hash['by_user'] = isset($bundle['by_user']) && is_array($bundle['by_user']) ? $bundle['by_user'] : array();
			if (method_exists($request, 'close')) {
				$request->close();
			}
		} catch (Exception $e) {
			$hash['status'] = 'error';
			$hash['message'] = $e->getMessage();
		} catch (Error $e) {
			$hash['status'] = 'error';
			$hash['message'] = $e->getMessage();
		}
		return $hash;
	}

	function list_request_approvers() {
		// Danh sách user có quyền duyệt đơn (can_approve_request = 1, không bị suspend)
		$query = "SELECT userid, realname, lastname, firstname, lastname_after_married FROM ".DB_PREFIX."user WHERE (is_suspend IS NULL OR is_suspend = 0) AND can_approve_request = 1 ORDER BY id ASC";
		return $this->fetchAll($query);
	}

	/*API*/
	function add_member() {
		$this->authorizeApi('administrator', 'manager');
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->validateSchema('insert');
			$this->permitValidate();
			if (method_exists($this, 'validateAdd')) {
				$this->validateAdd();
			}
			$this->post['realname'] = $this->post['lastname'];
			if($this->post['firstname'] != '') {
				$this->post['realname'] .= ' '.$this->post['firstname'];
			}
			// Handle branch_id: convert empty string to NULL
			if (isset($this->post['branch_id']) && $this->post['branch_id'] === '') {
				$this->post['branch_id'] = NULL;
			}
			// Handle show_project checkbox: if not set in POST, set to 0
			if (!isset($_POST['show_project']) || $_POST['show_project'] != '1') {
				$this->post['show_project'] = 0;
			} else {
				$this->post['show_project'] = 1;
			}
			// Handle can_approve_request checkbox: if not set in POST, set to 0
			if (!isset($_POST['can_approve_request']) || $_POST['can_approve_request'] != '1') {
				$this->post['can_approve_request'] = 0;
			} else {
				$this->post['can_approve_request'] = 1;
			}
			// Handle is_soumu checkbox: if not set in POST, set to 0
			if (!isset($_POST['is_soumu']) || $_POST['is_soumu'] != '1') {
				$this->post['is_soumu'] = 0;
			} else {
				$this->post['is_soumu'] = 1;
			}
			$this->normalizeQuiteDatePost(false);
			$this->insertPost();
			if($_POST['department_id']){
				$this->updateDepartment($_POST['userid'], $_POST['department_id']);
			}
		}
		if(count($this->error) > 0){
			$hash['status'] = 'error';
			$hash['error'] = $this->error;
			return $hash;
		}

		if (!empty($_POST['userid'])) {
			$this->invalidateApiCacheForUserids([$_POST['userid']]);
		}
		
		$hash['status'] = 'success';
		$hash['message_code'] = 11;
		return $hash;
	}


	function edit_member() {
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->validateSchema('update');
			$this->permitValidate();
			if (method_exists($this, 'validateEdit')) {
				$this->validateEdit();
			}
			$this->post['realname'] = $this->post['lastname'];
            if($this->post['firstname'] != '') {
                $this->post['realname'] .= ' '.$this->post['firstname'];
            }
			//remve user_groupname from post
			unset($this->post['user_ruby']);
			unset($this->post['id']);
			unset($this->post['user_postcode']);
			unset($this->post['user_address']);
			unset($this->post['user_addressruby']);
			unset($this->post['user_mobile']);
			unset($this->post['user_image']);
			unset($this->post['user_skype']);
			
			// Handle branch_id: convert empty string to NULL
			if (isset($this->post['branch_id']) && $this->post['branch_id'] === '') {
				$this->post['branch_id'] = NULL;
			}
			
			// Handle show_project checkbox: if not set in POST, set to 0
			if (!isset($_POST['show_project']) || $_POST['show_project'] != '1') {
				$this->post['show_project'] = 0;
			} else {
				$this->post['show_project'] = 1;
			}

			// Handle can_approve_request checkbox: if not set in POST, set to 0
			if (!isset($_POST['can_approve_request']) || $_POST['can_approve_request'] != '1') {
				$this->post['can_approve_request'] = 0;
			} else {
				$this->post['can_approve_request'] = 1;
			}
			// Handle is_soumu checkbox: if not set in POST, set to 0
			if (!isset($_POST['is_soumu']) || $_POST['is_soumu'] != '1') {
				$this->post['is_soumu'] = 0;
			} else {
				$this->post['is_soumu'] = 1;
			}

			$clearQuiteDate = $this->normalizeQuiteDatePost(true);

			$this->updatePost();

			if ($clearQuiteDate && !empty($_POST['id'])) {
				$table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $this->table);
				$this->query(sprintf(
					"UPDATE `%s` SET quite_date = NULL WHERE id = %d",
					$table,
					intval($_POST['id'])
				));
			}

			if($_POST['department_id']){
				$this->updateDepartment($_POST['userid'], $_POST['department_id']);
			}
		}
		if(count($this->error) > 0){
			$hash['status'] = 'error';
			$hash['error'] = $this->error;
			return $hash;
		}

		if (!empty($_POST['userid'])) {
			$this->invalidateUserLoginByUserid($_POST['userid']);
		}
		
		$hash['status'] = 'success';
		$hash['message_code'] = 11;
		return $hash;
	}

	function updateDepartment($user_id, $department_ids){
		$query = sprintf("DELETE FROM groupware_user_department WHERE userid = '%s'", $user_id);
		$this->query($query);
		foreach($department_ids as $department_id){
			$query = sprintf("INSERT INTO groupware_user_department (userid, department_id) VALUES ('%s', '%s')", $user_id, $department_id);
			$this->query($query);
		}
	}


	/*API*/
	function suspend_member() {
		$id = $_POST['id'];
		if(!$id){
			$hash['status'] = 'error';
			$hash['message_code'] = 15;
			return $hash;
		}
		$hash['data'] = $this->permitFindApi('edit');
		if(count($this->error) > 0){
			$hash['status'] = 'error';
			$hash['message_code'] = $this->error;
			return $hash;
		}

		$date = date('Y-m-d H:i:s');
		$editor = $_SESSION['userid'];

		$query = sprintf(
			"UPDATE groupware_user SET remember_token = NULL, is_suspend = '1', editor = '%s', updated = '%s' WHERE id = '%s'",
			$editor,
			$date,
			$id,
		);
		$response = $this->update_query($query);
		if($response > 0){
			$this->invalidateUserLoginById($id);
			$hash['status'] = 'success';
			$hash['message_code'] = $response;
		} else{
			$hash['status'] = 'error';
			$hash['message_code'] = $response;
		}
		return $hash;
	}
	
	/*API*/
	function active_member() {
		$id = $_POST['id'];
		if(!$id){
			$hash['status'] = 'error';
			$hash['message_code'] = 15;
			return $hash;
		}
		$hash['data'] = $this->permitFindApi('edit');
		if(count($this->error) > 0){
			$hash['status'] = 'error';
			$hash['message_code'] = $this->error;
			return $hash;
		}
		
		$date = date('Y-m-d H:i:s');
		$editor = $_SESSION['userid'];

		$query = sprintf(
			"UPDATE groupware_user SET is_suspend = NULL, quite_date = NULL, editor = '%s', updated = '%s' WHERE id = '%s'",
			$editor,
			$date,
			$id,
		);
		$response = $this->update_query($query);
		if($response > 0){
			$hash['status'] = 'success';
			$hash['message_code'] = $response;
		} else{
			$hash['status'] = 'error';
			$hash['message_code'] = $response;
		}
		return $hash;
	}

	/*API*/
	function change_password_api() {
		$id = $_POST['id'];
		$password = md5(trim($_POST['password']));
		if(!$id){
			$hash['status'] = 'error';
			$hash['message_code'] = 'idが指定されていません';
			return $hash;
		}
		if(!$password){
			$hash['status'] = 'error';
			$hash['message_code'] = 'パスワードが指定されていません';
			return $hash;
		}
		$hash['data'] = $this->permitFindApi('edit');
		if(count($this->error) > 0){
			$hash['status'] = 'error';
			$hash['message_code'] = $this->error;
			return $hash;
		}
		
		$date = date('Y-m-d H:i:s');
		$editor = $_SESSION['userid'];

		$query = sprintf(
			"UPDATE groupware_user SET remember_token = NULL, password = '%s', editor = '%s', updated = '%s' WHERE id = '%s'",
			$password,
			$editor,
			$date,
			$id,
		);
		$response = $this->update_query($query);
		if($response > 0){
			$this->invalidateUserLoginById($id);
			$hash['status'] = 'success';
			$hash['message_code'] = $response;
		} else{
			$hash['status'] = 'error';
			$hash['message_code'] = $response;
		}
		return $hash;
	}

	function delete_member() {
		$id = $_POST['id'];
		$hash['data'] = $this->permitFindApi('edit');
		if(count($this->error) > 0){
			$hash['status'] = 'error';
			$hash['message_code'] = $this->error;
			return $hash;
		}
		if(!$id){
			$hash['status'] = 'error';
			$hash['message_code'] = 15;
			return $hash;
		}

		$query = sprintf(
			"DELETE FROM groupware_user WHERE id = '%s'",
			$id,
		);
		$response = $this->update_query($query);
		if($response > 0){
			$hash['status'] = 'success';
			$hash['message_code'] = $response;
		} else{
			$hash['status'] = 'error';
			$hash['message_code'] = $response;
		}
		return $hash;
	}


	function get_user_by_id() {
		$id = $_POST['id'];
		if(!$id){
			$hash['status'] = 'error';
			$hash['message_code'] = 15;
			return $hash;
		}
		$hash['data'] = $this->permitFindApi('edit');
		$hash += $this->findUser($hash['data']);

		$hash['data']['department_id'] = $this->getDepartment($hash['data']['userid']);
		if(count($this->error) > 0){
			$hash['status'] = 'error';
			$hash['message_code'] = '12';
			return $hash;
		}
		
		$hash['error'] = $this->error;
		$hash['status'] = 'success';
		return $hash;
	}

	function getDepartment($userid){
		$query = sprintf("SELECT department_id FROM groupware_user_department WHERE userid = '%s'", $userid);
		$result = $this->fetchAll($query);
		$department_ids = array();
		foreach($result as $row){
			$department_ids[] = $row['department_id'];
		}
		return $department_ids;
	}

	/*API*/
	function resign_member() {
		$id = $_POST['id'];
		$hash['data'] = $this->permitFindApi('edit');
		if(count($this->error) > 0){
			$hash['status'] = 'error';
			$hash['message_code'] = $this->error;
			return $hash;
		}
		if(!$id){
			$hash['status'] = 'error';
			$hash['message_code'] = 15;
			return $hash;
		}
		$date = date('Y-m-d H:i:s');
		$editor = $_SESSION['userid'];

		$query = sprintf(
			"UPDATE groupware_user SET remember_token = NULL, user_group = '". RETIRE_GROUP ."', user_groupname = '". RETIRE_GROUP_NAME ."', is_suspend = '1', quite_date = '%s', editor = '%s', updated = '%s' WHERE id = '%s'",
			$this->quote($date),
			$editor,
			$date,
			$id
		);
		$response = $this->update_query($query);
		if($response > 0){
			$this->invalidateUserLoginById($id);
			$hash['status'] = 'success';
			$hash['message_code'] = $response;
		} else{
			$hash['status'] = 'error';
			$hash['message_code'] = $response;
		}
		return $hash;
	}
	
	
	function view() {
		$hash['data'] = $this->findView();
		return $hash;
	}

	function change_password(){
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->validate();
			$check = FALSE;
			if (count($this->error) <= 0) {
				$this->post['editor'] = $_SESSION['userid'];
				$this->post['updated'] = date('Y-m-d H:i:s');
				$field = $this->schematize('update');
				foreach ($field as $key) {
					if (isset($this->post[$key])) {
						$array[] = $key." = '".$this->quote($this->post[$key])."'";
					}
				}
				$query = sprintf("UPDATE %s SET %s WHERE userid = '%s'", $this->table, implode(",", $array), $this->quote($_SESSION['userid']));
				$this->response = $this->update_query($query);
				if ($this->response == '1') {
					$check = TRUE;
				}
			}
			$hash['data'] = $this->post;
			if($check){
				$hash['data']['message'] = 'パスワードを変更しました。';
				$_SESSION['user_updated'] = $this->post['updated'];
			}
		}
		
		return $hash;
	}

	function edit() {
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->post['userid'] = $_SESSION['userid'];
			$this->validateSchema('update');
			$this->post['editor'] = $_SESSION['userid'];
			$this->post['updated'] = date('Y-m-d H:i:s');
			$reset_image = $_POST['reset_image'];
			if(isset($_FILES['user_image']) && $_FILES['user_image']['name'] != '') {
				$this->post['user_image'] = $_SESSION['userid'].'_'.$_FILES['user_image']['name'];
				$this->uploadAvatar($_SESSION['userid'].'_'.$_FILES['user_image']['name']);
				if($_SESSION['user_image'] != '' && count($this->error) <= 0){
					try{
						$old_image = '../assets/upload/avatar/'.$_SESSION['user_image'];
						if(file_exists($old_image)){
							unlink($old_image);
						}
					} catch (Exception $e) {
						
					}
				}
			}
			if($reset_image == 1) {
				$this->post['user_image'] = '';
				$_SESSION['user_image'] = '';
			}
			$this->post['realname'] = $this->post['lastname'];
			if($this->post['firstname'] != '') {
				$this->post['realname'] .= ' '.$this->post['firstname'];
			}
			if (count($this->error) <= 0) {
				$field = $this->schematize('update');
				foreach ($field as $key) {
					if (isset($this->post[$key])) {
						$array[] = $key." = '".$this->quote($this->post[$key])."'";
					}
				}
				$query = sprintf("UPDATE %s SET %s WHERE userid = '%s'", $this->table, implode(",", $array), $this->quote($_SESSION['userid']));
				$this->response = $this->query($query);
			}
			if(isset($_FILES['user_image']) && $_FILES['user_image']['name'] != '' && $this->response) {
				$_SESSION['user_image'] = $_SESSION['userid'].'_'.$_FILES['user_image']['name'];
				$hash['data']['user_image'] = $_SESSION['user_image'];
			}
			if($this->response) {
				$_SESSION['firstname'] = $this->post['firstname'];
				$_SESSION['lastname'] = $this->post['lastname'];
				$_SESSION['realname'] = $this->post['lastname'];
				if($this->post['firstname'] != '') {
					$_SESSION['realname'] .= ' '.$this->post['firstname'];
				}
				$_SESSION['user_updated'] = $this->post['updated'];
			}

			$this->redirect();
			$hash['data'] = $this->post;
			
		} else {
			$field = implode(',', $this->schematize());
			$hash['data'] = $this->fetchOne("SELECT ".$field." FROM ".$this->table." WHERE userid = '".$this->quote($_SESSION['userid'])."'");
		}
		return $hash;
	
	}

	

	function uploadAvatar($filename){
		//help me upload file to /assets/upload/avatar/
		if (isset($_FILES['user_image']) && $_FILES['user_image']['error'] == UPLOAD_ERR_OK) {
			//check if file is image
			$allowed = array('jpg', 'jpeg', 'png', 'gif');
			$ext = pathinfo($filename, PATHINFO_EXTENSION);
			if (in_array($ext, $allowed)) {
				//move file to /assets/upload/avatar/
				move_uploaded_file($_FILES['user_image']['tmp_name'], '../assets/upload/avatar/'.$filename);
				
				// Resize image to 100px width
				$source_path = '../assets/upload/avatar/'.$filename;
				list($width, $height) = getimagesize($source_path);
				$new_width = 100;
				$new_height = ($height/$width) * $new_width;
				
				$new_image = imagecreatetruecolor($new_width, $new_height);
				
				switch($ext) {
					case 'jpg':
					case 'jpeg':
						$source = imagecreatefromjpeg($source_path);
						break;
					case 'png':
						$source = imagecreatefrompng($source_path);
						break;
					case 'gif':
						$source = imagecreatefromgif($source_path);
						break;
				}
				
				imagecopyresampled($new_image, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
				
				switch($ext) {
					case 'jpg':
					case 'jpeg':
						imagejpeg($new_image, $source_path);
						break;
					case 'png':
						imagepng($new_image, $source_path);
						break;
					case 'gif':
						imagegif($new_image, $source_path);
						break;
				}
				
				imagedestroy($new_image);
				imagedestroy($source);
				
			} else {
				$this->error[] = '画像ファイルを選択してください。';
			}
		}
	}

}

?>