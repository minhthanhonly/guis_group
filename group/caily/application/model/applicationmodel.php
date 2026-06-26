<?php

class ApplicationModel extends Model {
	const USER_SUSPEND_CHECK_TTL = 60;
	const PROJECT_MANAGER_CHECK_TTL = 300;

	private static $cachedUserList = null;

	function __construct() {
		parent::__construct();
	}

	/**
	 * Lazy-load active users once per request (chat dropdown, etc.).
	 */
	function getUserList() {
		if (self::$cachedUserList !== null) {
			return self::$cachedUserList;
		}
		self::$cachedUserList = $this->findAllActiveUser();
		return self::$cachedUserList;
	}

	function __get($name) {
		if ($name === 'user_list') {
			return $this->getUserList();
		}
		return null;
	}

	function findProjectManager(){
		$now = time();
		if (isset($_SESSION['isProjectManager'], $_SESSION['_pm_checked_at'])
			&& ($now - (int)$_SESSION['_pm_checked_at']) < self::PROJECT_MANAGER_CHECK_TTL) {
			return (bool)$_SESSION['isProjectManager'];
		}
		$this->connect();
		$query = "SELECT count(id) as count FROM ".DB_PREFIX."user_department WHERE userid = '".$_SESSION['userid']."' AND project_manager = 1";
		$data = $this->fetchOne($query);
		$result = $_SESSION['group'] == ADMIN_GROUP || $data['count'] > 0;
		$_SESSION['isProjectManager'] = $result;
		$_SESSION['_pm_checked_at'] = $now;
		return $result;
	}
	function authorize() {
		$this->connect();
		$authorized = false;
		$argument = func_get_args();
		if (is_array($argument) && count($argument) > 0) {
			$data = $this->fetchOne("SELECT authority FROM ".DB_PREFIX."user WHERE userid = '".$this->quote($_SESSION['userid'])."'");
			foreach ($argument as $value) {
				if (strlen($value) > 0 && $value === $data['authority']) {
					$authorized = true;
				}
			}
		}
		if ($authorized !== true) {
			$this->died('権限がありません。');
		}
		return $authorized;
	
	}

	function checkSuspend() {
		if (empty($_SESSION['id']) || empty($_SESSION['userid'])) {
			return;
		}
		$this->connect();
		$userId = intval($_SESSION['id']);
		$query = sprintf(
			"SELECT is_suspend, show_project, updated, authority, user_group, is_soumu FROM %suser WHERE id = %d",
			DB_PREFIX,
			$userId
		);
		$data = $this->fetchOne($query);
		if (empty($data)) {
			$this->forceSessionLogout('ユーザーの設定が変更されたため、ログインし直してください。');
			return;
		}
		if ($data['is_suspend'] == 1) {
			$this->forceSessionLogout('アカウントが無効化されています。');
			return;
		}

		$sessionUpdated = isset($_SESSION['user_updated']) ? (string) $_SESSION['user_updated'] : '';
		$dbUpdated = isset($data['updated']) ? (string) $data['updated'] : '';
		if ($sessionUpdated !== '' && $dbUpdated !== '' && $sessionUpdated !== $dbUpdated) {
			$this->forceSessionLogout('ユーザーの設定が変更されたため、ログインし直してください。');
			return;
		}

		if ($sessionUpdated === '') {
			if ((string) $_SESSION['show_project'] !== (string) $data['show_project']
				|| (string) $_SESSION['authority'] !== (string) $data['authority']
				|| (string) $_SESSION['group'] !== (string) $data['user_group']
				|| (int) ($_SESSION['is_soumu'] ?? 0) !== (int) ($data['is_soumu'] ?? 0)) {
				$this->forceSessionLogout('ユーザーの設定が変更されたため、ログインし直してください。');
			}
		}
	}

	protected function forceSessionLogout($message) {
		$authority = new Authority;
		$authority->sessionDestroy();
		$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
		setcookie('remember_me', '', time() - 3600, '/', '', $secure, true);
		$isApi = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false;
		if ($isApi) {
			header('Content-Type: application/json');
			http_response_code(401);
			echo json_encode(['status' => 'error', 'error' => $message], JSON_UNESCAPED_UNICODE);
			exit();
		}
		$this->died($message);
	}

	protected function invalidateUserLoginByUserid($userid) {
		$userid = trim((string) $userid);
		if ($userid === '') {
			return;
		}
		$now = date('Y-m-d H:i:s');
		$this->query(sprintf(
			"UPDATE %suser SET remember_token = NULL, updated = '%s' WHERE userid = '%s'",
			DB_PREFIX,
			$this->quote($now),
			$this->quote($userid)
		));
		$this->invalidateApiCacheForUserids([$userid]);
	}

	protected function invalidateUserLoginByUserids(array $userids) {
		foreach (array_unique(array_filter(array_map('strval', $userids), function ($uid) {
			return $uid !== '';
		})) as $userid) {
			$this->invalidateUserLoginByUserid($userid);
		}
	}

	protected function getDepartmentMemberUserids($departmentId) {
		$departmentId = intval($departmentId);
		if ($departmentId <= 0) {
			return [];
		}
		$rows = $this->fetchAll(sprintf(
			"SELECT userid FROM %suser_department WHERE department_id = %d",
			DB_PREFIX,
			$departmentId
		));
		return array_values(array_filter(array_column($rows, 'userid')));
	}

	protected function invalidateUserLoginForDepartmentId($departmentId) {
		$this->invalidateUserLoginByUserids($this->getDepartmentMemberUserids($departmentId));
	}

	protected function invalidateUserLoginById($id) {
		$id = intval($id);
		if ($id <= 0) {
			return;
		}
		$row = $this->fetchOne(sprintf(
			"SELECT userid FROM %suser WHERE id = %d",
			DB_PREFIX,
			$id
		));
		if (!empty($row['userid'])) {
			$this->invalidateUserLoginByUserid($row['userid']);
		}
	}

	protected function refreshSessionFromUserRow(array $data) {
		if (empty($data['userid']) || $data['userid'] !== ($_SESSION['userid'] ?? '')) {
			return;
		}
		unset($_SESSION['_suspend_checked_at'], $_SESSION['_pm_checked_at']);
		$_SESSION['lastname'] = $data['lastname'] ?? $_SESSION['lastname'];
		$_SESSION['firstname'] = $data['firstname'] ?? $_SESSION['firstname'];
		$_SESSION['realname'] = $data['realname'] ?? $_SESSION['realname'];
		if (array_key_exists('user_image', $data)) {
			$_SESSION['user_image'] = $data['user_image'];
		}
		if (isset($data['updated'])) {
			$_SESSION['user_updated'] = (string) $data['updated'];
		}
	}

	function authorizeApi() {
		
		$this->connect();
		$authorized = false;
		$argument = func_get_args();
		if (is_array($argument) && count($argument) > 0) {
			$data = $this->fetchOne("SELECT authority FROM ".DB_PREFIX."user WHERE userid = '".$this->quote($_SESSION['userid'])."'");
			foreach ($argument as $value) {
				if (strlen($value) > 0 && $value === $data['authority']) {
					$authorized = true;
				}
			}
		}
		if ($authorized !== true) {
			$this->diedApi('権限がありません。');
		}
		return $authorized;
	
	}

	/**
	 * Check department-level permission for current user (any department).
	 */
	function hasDepartmentPermission($field) {
		if (($_SESSION['authority'] ?? '') === 'administrator') {
			return true;
		}
		$allowedFields = [
			'project_manager',
			'project_director_stat',
			'project_director_view',
			'project_director_edit',
			'project_add',
			'project_edit',
			'project_delete',
			'project_comment',
			'task_view',
			'task_add',
			'task_edit',
			'task_delete',
		];
		if (!in_array($field, $allowedFields, true)) {
			return false;
		}
		$this->connect();
		$row = $this->fetchOne(sprintf(
			"SELECT COUNT(*) AS c FROM %suser_department WHERE userid = '%s' AND %s = 1",
			DB_PREFIX,
			$this->quote($_SESSION['userid']),
			$field
		));
		return intval($row['c'] ?? 0) > 0;
	}
	
	function permitList($sort = 'id', $desc = 1) {
		
		$this->where[] = $this->permitWhere();
		return $this->findLimit($sort, $desc);
	
	}
	
	function permitWhere($level = '') {
		
		$query = "(public_level = 0 OR owner = '%s' OR ";
		$query .= "(public_level = 2 AND (public_group LIKE '%%[%s]%%' OR public_user LIKE '%%[%s]%%')))";
		$where = sprintf($query, $this->quote($_SESSION['userid']), $this->quote($_SESSION['group']), $this->quote($_SESSION['userid']));
		if ($level == 'add') {
			$query = "AND (add_level = 0 OR owner = '%s' OR ";
			$query .= "(add_level = 2 AND (add_group LIKE '%%[%s]%%' OR add_user LIKE '%%[%s]%%')))";
			$where .= sprintf($query, $this->quote($_SESSION['userid']), $this->quote($_SESSION['group']), $this->quote($_SESSION['userid']));
		}
		return $where;
		
	}

	/**
	 * Batch-load comment like counts and liker lists (avoids per-comment subqueries).
	 */
	protected function attachCommentLikeAggregates(array &$comments) {
		if (empty($comments)) {
			return;
		}
		$commentIds = array_values(array_filter(array_map('intval', array_column($comments, 'id')), function ($id) {
			return $id > 0;
		}));
		if (empty($commentIds)) {
			return;
		}
		$idsList = implode(',', $commentIds);
		$likeRows = $this->fetchAll(sprintf(
			"SELECT comment_id, user_id, name FROM %scomment_likes WHERE comment_id IN (%s) ORDER BY comment_id, id",
			DB_PREFIX,
			$idsList
		));
		$stats = [];
		foreach ($likeRows as $row) {
			$cid = (int)$row['comment_id'];
			if (!isset($stats[$cid])) {
				$stats[$cid] = ['liked_by' => [], 'liked_by_names' => []];
			}
			if (!empty($row['user_id'])) {
				$stats[$cid]['liked_by'][] = $row['user_id'];
			}
			if (isset($row['name']) && $row['name'] !== '') {
				$stats[$cid]['liked_by_names'][] = $row['name'];
			}
		}
		foreach ($comments as &$comment) {
			$cid = (int)$comment['id'];
			if (isset($stats[$cid])) {
				$comment['liked_by'] = $stats[$cid]['liked_by'];
				$comment['liked_by_names'] = $stats[$cid]['liked_by_names'];
				$comment['like_count'] = count($stats[$cid]['liked_by']);
			} else {
				$comment['liked_by'] = [];
				$comment['liked_by_names'] = [];
				$comment['like_count'] = 0;
			}
		}
		unset($comment);
	}

	/**
	 * Batch file/subfolder counts for folder list rows.
	 * Options: parent_project_id — limit attachment counts to a parent project scope.
	 */
	protected function attachFolderListAggregates(array &$folders, array $options = []) {
		if (empty($folders)) {
			return;
		}
		$folderIds = array_values(array_filter(array_map('intval', array_column($folders, 'id')), function ($id) {
			return $id > 0;
		}));
		if (empty($folderIds)) {
			return;
		}
		$idsList = implode(',', $folderIds);
		$fileWhere = "folder_id IN ({$idsList})";
		if (!empty($options['parent_project_id'])) {
			$fileWhere .= ' AND parent_project_id = ' . intval($options['parent_project_id']);
		}

		$fileMap = [];
		$fileRows = $this->fetchAll(sprintf(
			"SELECT folder_id, COUNT(*) as file_count FROM %sproject_attachments WHERE %s GROUP BY folder_id",
			DB_PREFIX,
			$fileWhere
		));
		foreach ($fileRows as $row) {
			$fileMap[(int)$row['folder_id']] = (int)$row['file_count'];
		}

		$subfolderMap = [];
		$subfolderRows = $this->fetchAll(sprintf(
			"SELECT parent_folder_id, COUNT(*) as subfolder_count FROM %sproject_folders WHERE parent_folder_id IN (%s) GROUP BY parent_folder_id",
			DB_PREFIX,
			$idsList
		));
		foreach ($subfolderRows as $row) {
			$subfolderMap[(int)$row['parent_folder_id']] = (int)$row['subfolder_count'];
		}

		$includeSubfolders = !empty($options['include_subfolder_count']);
		foreach ($folders as &$folder) {
			$fid = (int)$folder['id'];
			$folder['file_count'] = $fileMap[$fid] ?? 0;
			if ($includeSubfolders) {
				$folder['subfolder_count'] = $subfolderMap[$fid] ?? 0;
			}
		}
		unset($folder);
	}

	/**
	 * Build breadcrumb trail from a preloaded folder map (avoids N queries walking parents).
	 */
	protected function buildFolderBreadcrumbsFromMap($folderId, array $folderById) {
		$folderId = (int)$folderId;
		if ($folderId <= 0 || empty($folderById)) {
			return [];
		}
		$breadcrumbs = [];
		$currentId = $folderId;
		$guard = 0;
		while ($currentId > 0 && isset($folderById[$currentId]) && $guard < 100) {
			$guard++;
			$folder = $folderById[$currentId];
			array_unshift($breadcrumbs, [
				'id' => (int)$folder['id'],
				'name' => $folder['name'],
			]);
			$parentId = isset($folder['parent_folder_id']) ? (int)$folder['parent_folder_id'] : 0;
			$currentId = $parentId > 0 ? $parentId : 0;
		}
		return $breadcrumbs;
	}

	/**
	 * Batch-load project members/managers for a set of project IDs.
	 */
	protected function fetchProjectMembersByProjectIds(array $projectIds) {
		$projectIds = array_values(array_filter(array_map('intval', $projectIds), function ($id) {
			return $id > 0;
		}));
		if (empty($projectIds)) {
			return [];
		}
		$idsList = implode(',', $projectIds);
		$membersByProject = [];
		$memberRows = $this->fetchAll(sprintf(
			"SELECT pm.project_id, pm.role, pm.user_id, u.realname, u.user_image
			 FROM %sproject_members pm
			 LEFT JOIN %suser u ON pm.user_id = u.id
			 WHERE pm.project_id IN (%s) AND pm.role IN ('member', 'manager')
			 ORDER BY pm.project_id, pm.role, pm.user_id",
			DB_PREFIX,
			DB_PREFIX,
			$idsList
		));
		foreach ($memberRows as $row) {
			$pid = (int)$row['project_id'];
			$role = $row['role'] === 'manager' ? 'manager' : 'member';
			if (!isset($membersByProject[$pid])) {
				$membersByProject[$pid] = ['member' => [], 'manager' => []];
			}
			$membersByProject[$pid][$role][] = $row['user_id'] . ':' . ($row['realname'] ?? '') . ':' . ($row['user_image'] ?? '');
		}
		return $membersByProject;
	}

	protected function attachProjectFavoriteAggregates(array &$projects, $userId) {
		if (empty($projects)) {
			return;
		}
		$projectIds = array_values(array_filter(array_map('intval', array_column($projects, 'id')), function ($id) {
			return $id > 0;
		}));
		if (empty($projectIds)) {
			return;
		}
		$userId = (int)$userId;
		$idsList = implode(',', $projectIds);
		$favoriteSet = [];
		$favRows = $this->fetchAll(sprintf(
			"SELECT project_id FROM %sproject_favorites WHERE user_id = %d AND project_id IN (%s)",
			DB_PREFIX,
			$userId,
			$idsList
		));
		foreach ($favRows as $row) {
			$favoriteSet[(int)$row['project_id']] = true;
		}
		foreach ($projects as &$project) {
			$project['is_favorite'] = isset($favoriteSet[(int)$project['id']]) ? 1 : 0;
		}
		unset($project);
	}
	
	function permitFind($level = 'public', $id = 0) {
		
		if ($id <= 0) {
			$id = $_REQUEST['id'];
		}
		if ($id > 0) {
			$field = implode(',', $this->schematize());
			$data = $this->fetchOne("SELECT ".$field." FROM ".$this->table." WHERE id = ".intval($id));
			if ($this->permitted($data, 'public')) {
				if ($level == 'edit' && !$this->permitted($data, 'edit')) {
					$this->died('編集する権限がありません。');
				} else {
					return $data;
				}
			} else {
				$this->died('閲覧する権限がありません。');
			}
		}
		
	}

	function permitFindApi($level = 'public', $id = 0) {
		
		if ($id <= 0) {
			$id = $_REQUEST['id'];
		}
		if ($id > 0) {
			$field = implode(',', $this->schematize());
			$data = $this->fetchOne("SELECT ".$field." FROM ".$this->table." WHERE id = ".intval($id));
			if ($this->permitted($data, 'public')) {
				if ($level == 'edit' && !$this->permitted($data, 'edit')) {
					$this->error[] = '編集する権限がありません。';
				} else {
					return $data;
				}
			} else {
				$this->error[] = '閲覧する権限がありません。';
			}
		}
		
	}
	
	function permitted($data, $level = 'public') {
		if($_SESSION['userid'] == 'admin'){
			return true;
		}
		$permission = false;
		if ($data[$level.'_level'] == 0) {
			$permission = true;
		} elseif (strlen($data['owner']) > 0 && $data['owner'] == $_SESSION['userid']) {
			$permission = true;
		} elseif ($data[$level.'_level'] == 2 && (stristr($data[$level.'_group'], '['.$_SESSION['group'].']') || stristr($data[$level.'_user'], '['.$_SESSION['userid'].']'))) {
			$permission = true;
		}
		return $permission;
	
	}
	
	function permitInsert($redirect = 'index.php') {
		
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->validateSchema('insert');
			$this->permitValidate();
			if (method_exists($this, 'validate')) {
				$this->validate();
			}
			$this->insertPost();
			$this->redirect($redirect);
		}
		return $this->post;
	
	}
	
	function permitUpdate($redirect = 'index.php') {
	
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->validateSchema('update');
			$this->permitValidate();
			if (method_exists($this, 'validate')) {
				$this->validate();
			}
			$this->updatePost();
			$this->redirect($redirect);
			return $this->post;
		}
		
	}
	
	function permitValidate() {
	
		$array = array('public'=>'公開', 'edit'=>'編集を許可', 'add'=>'書き込みを許可');
		foreach ($array as $key => $value) {
			if (isset($_POST[$key.'_level'])) {
				$this->post[$key.'_level'] = intval($_POST[$key.'_level']);
				if ($_POST[$key.'_level'] == 2) {
					if (!isset($_POST[$key]['group']) && !isset($_POST[$key]['user']) ) {
						$this->error[] = $value.'するグループ・ユーザーを選択してください。';
					} else {
						$this->post[$key.'_group'] = $this->permitParse($_POST[$key]['group']);
						$this->post[$key.'_user'] = $this->permitParse($_POST[$key]['user']);
					}
				} else {
					$this->post[$key.'_group'] = '';
					$this->post[$key.'_user'] = '';
				}
			}
		}
	
	}
	
	function permitParse($array) {
	
		if (is_array($array) && count($array) > 0) {
			$array = array_unique(array_keys($array));
			$string = '['.implode('][', $array).']';
			if (!preg_match('/^[-_a-zA-Z0-9\.\[\]]*$/', $string)) {
				$this->error[] = '権限の設定が無効です。';
			}
		}
		return $string;
	
	}
	
	function permitOwner($id = 0) {
		if ($id <= 0) {
			$id = $_REQUEST['id'];
		}
		if ($id > 0) {
			$field = implode(',', $this->schematize());
			// $query = "SELECT ".$field." FROM ".$this->table." WHERE (id = ".intval($id).") AND (owner = '".$this->quote($_SESSION['userid'])."')";
			$query = "SELECT ".$field." FROM ".$this->table." WHERE (id = ".intval($id).")";
			$data = $this->fetchOne($query);
			return $data;
		}

	}
	
	function findGroup() {
		
		$data = $this->fetchAll("SELECT id,group_name FROM ".DB_PREFIX."group ORDER BY group_order,id");
		$array = array();
		if (is_array($data) && count($data) > 0) {
			foreach ($data as $row) {
				$array[$row['id']] = $row['group_name'];
			}
		}
		return $array;
	
	}
	
	function findUser() {
		
		$group = $this->findGroup();
		$argument = func_get_args();
		$array = array();
		foreach ($argument as $row) {
			if (isset($row['owner']) && strlen($row['owner']) > 0) {
				$array[] = $this->quote($row['owner']);
			}
			if (isset($row['editor']) && strlen($row['editor']) > 0) {
				$array[] = $this->quote($row['editor']);
			}
			if (is_array($row) && count($row) > 0) {
				foreach ($row as $key => $value) {
					if (strlen($value) > 0 && stristr($key, '_user') && stristr($value, '[')) {
						$string .= $value;
					}
				}
			}
		}
		if (strlen($string) > 0) {
			$data = explode(',', str_replace(array('][', '[', ']'), array(',', '', ''), $string));
			$data = array_unique($data);
			if (is_array($data) && count($data) > 0) {
				foreach ($data as $value) {
					if (strlen($value) > 0) {
						$array[] = $this->quote($value);
					}
				}
			}
		}
		$array = array_unique($array);
		$user = array();
		$user_image = array();
		if (is_array($array) && count($array) > 0) {
			$query = "SELECT userid, realname, user_image FROM ".DB_PREFIX."user WHERE userid IN ('".implode("','", $array)."') ORDER BY user_order,id";
			$data = $this->fetchAll($query);
			if (is_array($data) && count($data) > 0) {
				foreach ($data as $row) {
					$user[$row['userid']] = $row['realname'];
					$user_image[$row['userid']] = $row['user_image'];
				}
			}
		}
		return array('group'=>$group, 'user'=>$user, 'user_image'=>$user_image);
	
	}

	function findUserImage($array) {
		$query = "SELECT userid, user_image FROM ".DB_PREFIX."user WHERE userid IN ('".implode("','", $array)."') ORDER BY user_order,id";
		$data = $this->fetchAll($query);
		return $data;
	}
	
	function permitCategory($type, $id = 0, $level = 'public') {
		
		$query = sprintf("SELECT folder_id,folder_caption FROM %sfolder WHERE (folder_type = '%s') AND %s ORDER BY folder_order,folder_name", DB_PREFIX, $type, $this->permitWhere($level));
		$data = $this->fetchAll($query);
		$result['folder'] = array();
		if (is_array($data) && count($data) > 0) {
			foreach ($data as $row) {
				$result['folder'][$row['folder_id']] = $row['folder_caption'];
			}
		}
		if ($id > 0) {
			$query = sprintf("SELECT * FROM %sfolder WHERE (folder_type = '%s') AND (folder_id = '%s')", DB_PREFIX, $type, intval($id));
			$data = $this->fetchOne($query);
			if ($this->permitted($data, 'public')) {
				if ($level == 'add' && !$this->permitted($data, 'add')) {
					$this->died('このカテゴリへの書き込み権限がありません。');
				} else {
					$result['category'] = $data;
				}
			} else {
				$this->died('閲覧する権限がありません。');
			}
		}
		return $result;
		
	}
	
	function folderWhere($folder, $default = '0') {
		
		if (strlen($_GET['folder']) > 0) {
			$id = $_GET['folder'];
		} else {
			$id = $default;
		}
		if ($id === 'all') {
			if (is_array($folder) && count($folder) > 0) {
				$array = array_keys($folder);
			}
			$array[] = '0';
			return "(folder_id IN (".implode(",", $array)."))";
		} else {
			return "(folder_id = ".intval($id).")";
		}
	
	}
	
	function findFolder($type) {
		
		$query = sprintf("SELECT folder_id,folder_caption FROM %sfolder WHERE (folder_type = '%s') AND (owner = '%s') ORDER BY folder_order,folder_name", DB_PREFIX, $type, $_SESSION['userid']);
		$data = $this->fetchAll($query);
		$result = array();
		if (is_array($data) && count($data) > 0) {
			foreach ($data as $row) {
				$result[$row['folder_id']] = $row['folder_caption'];
			}
		}
		return $result;
		
	}
	
	function parameter($array) {
		
		if (is_array($array) && count($array) > 0) {
			foreach ($array as $key => $value) {
				if ($value > 0) {
					$result[] = $key.'='.intval($value);
				}
			}
		}
		if (is_array($result) && count($result) > 0) {
			return '?'.implode('&', $result);
		}
		
	}

	function findAllActiveUser() {
		$this->connect();
		$retrict_group = array(RETIRE_GROUP);
		$query = sprintf(
			"SELECT userid, realname, user_groupname, user_image, authority FROM %suser WHERE (`is_suspend` = '' OR `is_suspend` IS NULL OR is_suspend = '0') AND user_group NOT IN ('%s') ORDER BY id",
			DB_PREFIX,
			implode("','", $retrict_group)
		);
		$data = $this->fetchAll($query);
		return $data;
	}

	function Log($data, $type = 'data') {
		$logDir = dirname(__FILE__) . '/../logs';
		if (!file_exists($logDir)) {
			mkdir($logDir, 0777, true);
		}
		
		$filename = $logDir . '/application_' . date('Y-m-d') . '.log';
		$timestamp = date('Y-m-d H:i:s');
		
		if (is_array($data) || is_object($data)) {
			$logData = print_r($data, true);
		} else {
			$logData = $data;
		}
		
		$logEntry = "[{$timestamp}] [{$type}] {$logData}\n";
		file_put_contents($filename, $logEntry, FILE_APPEND | LOCK_EX);
	}

	
    // Hàm gọi API create_notification
    public function callNotificationAPI($payload) {
		try {
			
			$this->Log('pay' . $payload);
			$api = new NotificationAPI();
			// Chuyển đổi payload sang dạng phù hợp nếu cần
			if (is_string($payload)) {
				$payload = json_decode($payload, true);
			}
		
			// Gọi trực tiếp method createNotification
			$result = $api->createNotification($payload);
			$this->Log(json_encode($result));
		} catch (Exception $e) {
			$this->Log($e->getMessage());
		}
		
        return $result;
    }

    /**
     * Shared flexible keyword search helpers (project list, command palette, etc.)
     */
    protected function normalizeSearchKeyword($str) {
        $str = trim((string)$str);
        if ($str === '') {
            return '';
        }
        if (function_exists('mb_convert_kana')) {
            $str = mb_convert_kana($str, 'as', 'UTF-8');
        }
        $str = str_replace(['－', '﹣', '―', '‐', '‑', '−', '–'], '-', $str);
        return trim($str);
    }

    protected function normalizeSearchKeywordAlnumOnly($str) {
        $str = trim((string)$str);
        if ($str === '') {
            return '';
        }
        if (function_exists('mb_convert_kana')) {
            $str = mb_convert_kana($str, 'a', 'UTF-8');
        }
        return str_replace(['－', '﹣', '―', '‐', '‑', '−', '–'], '-', $str);
    }

    protected function normalizeKeywordSpaces($str) {
        $str = trim((string)$str);
        if ($str === '') {
            return '';
        }
        return trim(preg_replace('/[\s　\x{00A0}\x{3000}]+/u', ' ', $str));
    }

    protected function sqlNormalizeSpacesExpr($field) {
        return "REPLACE(REPLACE(REPLACE($field, '　', ' '), CHAR(9), ' '), CHAR(10), ' ')";
    }

    protected function toFullWidthSearchKeyword($str) {
        $str = $this->normalizeSearchKeywordAlnumOnly($str);
        if ($str === '') {
            return '';
        }
        if (function_exists('mb_convert_kana')) {
            $str = mb_convert_kana($str, 'AS', 'UTF-8');
        }
        return str_replace('-', '－', $str);
    }

    protected function getSearchKeywordVariants($raw) {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return [];
        }
        $half = $this->normalizeSearchKeyword($raw);
        $alnumOnly = $this->normalizeSearchKeywordAlnumOnly($raw);
        $full = $this->toFullWidthSearchKeyword($raw);
        $spaces = $this->normalizeKeywordSpaces($half);
        return array_values(array_unique(array_filter([
            $raw,
            $half,
            $alnumOnly,
            $full,
            $spaces,
        ], function ($v) {
            return $v !== '';
        })));
    }

    protected function buildFlexibleLikeWhere($rawKeyword, array $fields) {
        $variants = $this->getSearchKeywordVariants($rawKeyword);
        if (empty($variants)) {
            return '';
        }
        $orParts = [];
        foreach ($variants as $variant) {
            $kw = $this->quote($variant);
            foreach ($fields as $field) {
                $orParts[] = "$field LIKE '%$kw%'";
                $normField = $this->sqlNormalizeSpacesExpr($field);
                $orParts[] = "$normField LIKE '%$kw%'";
            }
        }
        return '(' . implode(' OR ', array_unique($orParts)) . ')';
    }

    protected function getPaletteSearchQuery() {
        return isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    }

    protected function isNumericPaletteQuery($q) {
        $normalized = $this->normalizeSearchKeyword($q);
        return $normalized !== '' && ctype_digit($normalized);
    }

    protected function paletteNumericId($q) {
        return intval($this->normalizeSearchKeyword($q));
    }

    protected function buildPaletteSearchWhere($rawKeyword, array $likeFields, $idField = null) {
        $parts = [];
        if ($idField !== null && $this->isNumericPaletteQuery($rawKeyword)) {
            $parts[] = sprintf('%s = %d', $idField, $this->paletteNumericId($rawKeyword));
        }
        $likeWhere = $this->buildFlexibleLikeWhere($rawKeyword, $likeFields);
        if ($likeWhere !== '') {
            $parts[] = $likeWhere;
        }
        if (empty($parts)) {
            return '';
        }
        return '(' . implode(' OR ', $parts) . ')';
    }

    protected function invalidateApiCacheForUserids(array $userids) {
        $userids = array_values(array_unique(array_filter(array_map('strval', $userids), function ($uid) {
            return $uid !== '';
        })));
        if (empty($userids)) {
            return;
        }
        if (!defined('DIR_LIBRARY') || !file_exists(DIR_LIBRARY . 'ApiCache.php')) {
            return;
        }
        require_once DIR_LIBRARY . 'ApiCache.php';
        ApiCache::invalidateUsers($userids);
    }

    protected function invalidateApiCacheForNumericUserIds(array $numericIds) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $numericIds), function ($id) {
            return $id > 0;
        })));
        if (empty($ids)) {
            return;
        }
        $rows = $this->fetchAll(sprintf(
            "SELECT userid FROM %suser WHERE id IN (%s)",
            DB_PREFIX,
            implode(',', $ids)
        ));
        $this->invalidateApiCacheForUserids(array_column($rows, 'userid'));
    }

    protected function invalidateApiCacheForDepartmentId($departmentId) {
        $departmentId = intval($departmentId);
        if ($departmentId <= 0) {
            return;
        }
        $rows = $this->fetchAll(sprintf(
            "SELECT userid FROM %suser_department WHERE department_id = %d",
            DB_PREFIX,
            $departmentId
        ));
        $this->invalidateApiCacheForUserids(array_column($rows, 'userid'));
    }

    protected function invalidateApiCacheForTeamId($teamId) {
        $teamId = intval($teamId);
        if ($teamId <= 0) {
            return;
        }
        $rows = $this->fetchAll(sprintf(
            "SELECT user_id FROM %steam_members WHERE team_id = %d",
            DB_PREFIX,
            $teamId
        ));
        $this->invalidateApiCacheForNumericUserIds(array_column($rows, 'user_id'));
    }
}

?>