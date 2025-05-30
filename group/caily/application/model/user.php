<?php


class User extends ApplicationModel {
	
	function __construct() {
		if (basename($_SERVER['SCRIPT_NAME']) != 'feedApi.php' && basename($_SERVER['SCRIPT_NAME']) != 'feed.php' && $_SERVER['SCRIPT_NAME'] != '/api/index.php') {
			$this->authorize('administrator', 'manager');
		}
		
		$this->table = DB_PREFIX.'user';
		$this->schema = array(
		'userid'=>array('ユーザーID', 'notnull', 'userid', 'length:100', 'distinct'),
		'password'=>array('パスワード', 'alphaNumeric', 'length:4:32', 'except'=>array('search', 'update')),
		'password_default'=>array('except'=>array('search', 'update')),
		'lastname'=>array('姓', 'length:100'),
		'firstname'=>array('名', 'length:100'),
		'user_group'=>array('グループ', 'numeric', 'length:100', 'except'=>array('search')),
		'user_groupname'=>array(),
		'authority'=>array('権限', 'notnull', 'alpha', 'length:100', 'except'=>array('search')),
		'user_order'=>array('順序', 'numeric', 'length:10', 'except'=>array('search')),
		'edit_level'=>array('except'=>array('search')),
		'edit_group'=>array('except'=>array('search')),
		'edit_user'=>array('except'=>array('search')),
		'member_type'=>array('従業員の種類', 'length:100'),
		'is_suspend'=>array('ステータス', 'numeric', 'length:1'),
		);
	}
	
	function validate() {
		
		if (isset($this->post['password'])) {
			$this->post['password'] = md5(trim($this->post['password']));
			$this->post['password_default'] = $this->post['password'];
		} elseif ($_POST['resetpassword'] == 1) {
			$data = $this->fetchOne("SELECT password_default FROM ".$this->table." WHERE id = ".intval($_POST['id']));
			if (isset($data['password_default']) && strlen($data['password_default']) > 0) {
				$this->schema['password']['except'] = array('search');
				$this->post['password'] = $data['password_default'];
				$this->post['resetpassword'] = 1;
			} else {
				$this->error[] = '初期登録時のパスワードを取得できませんでした。<br />初期登録時のパスワードが設定されていない場合はパスワードを戻すことはできません。';
			}
		} elseif (strlen($_POST['password']) > 0 || strlen($_POST['newpassword']) > 0 || strlen($_POST['confirmpassword']) > 0) {
			$this->validator('password', 'パスワード', array('alphaNumeric', 'length:4:32'));
			$this->validator('newpassword', '新しいパスワード', array('alphaNumeric', 'length:4:32'));
			$this->validator('confirmpassword', '新しいパスワード(確認)', array('alphaNumeric', 'length:4:32'));
			$_POST['password'] = trim($_POST['password']);
			$_POST['newpassword'] = trim($_POST['newpassword']);
			$_POST['confirmpassword'] = trim($_POST['confirmpassword']);
			if ($_POST['newpassword'] != $_POST['confirmpassword']) {
				$this->error[] = '新しいパスワードと確認用パスワードが違います。';
			} else {
				$data = $this->fetchOne("SELECT password FROM ".$this->table." WHERE id = ".intval($_POST['id']));
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
	
	}
	
	function index() {
		$config = new Config($this->handler);
		if ($_GET['group'] != 'all') {
			if ($_GET['group'] <= 0) {
				$_GET['group'] = $_SESSION['group'];
			}
			$this->where[] = "(user_group = '".intval($_GET['group'])."')";
		}
		if ($_GET['type'] == 'active') {
			$this->where[] = "(user_group <> '5')";
		}
		$hash = $this->findLimit('user_order', 0);

		$hash += $this->permitGroup($_GET['group'], 'public');
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
	
	function view() {
		$config = new Config($this->handler);
		$hash['data'] = $this->findView();
		$hash += $this->permitGroup($hash['data']['user_group'], 'public');
		$hash += $this->findUser($hash['data']);

		$hash['data']['list_config'] = $config->getListConfigTimecard();

		$arr_config = array();
		foreach ($hash['data']['list_config'] as $key => $value) {
			$arr_config[$value["config_type"]] = $value["config_name"];
		}
		if($hash['data']["member_type"] == null){
			$hash['data']["member_type_name"] = $arr_config['timecard'];
		} else{
			$hash['data']["member_type_name"] = $arr_config[$hash['data']["member_type"]];
		}
		return $hash;

	}
	
	function add() {
		$config = new Config($this->handler);
		$hash = $this->permitGroup($_POST['user_group'], 'add');
		if ($_POST['user_group'] > 0) {
			$this->post['user_groupname'] = $hash['parent']['group_name'];
		}
		$hash['data'] = $this->permitInsert();
		$hash['data']['list_config'] = $config->getListConfigTimecard();
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$hash += $this->findUser($hash['data']);
		}
	
		return $hash;
	
	}
	
	function edit() {
		$config = new Config($this->handler);
		$hash['data'] = $this->permitFind('edit');
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$hash += $this->permitGroup($_POST['user_group'], 'add');
			if ($_POST['user_group'] > 0) {
				$this->post['user_groupname'] = $hash['parent']['group_name'];
			}
			$hash['data'] = $this->permitUpdate();
		} else {
			$hash += $this->permitGroup($hash['data']['user_group'], 'add');
		}
	
		$hash += $this->findUser($hash['data']);
		$hash['data']['list_config'] = $config->getListConfigTimecard();
		return $hash;
	
	}
	
	function delete() {
	
		$this->checkUser();
		$hash['data'] = $this->permitFind('edit');
		$hash += $this->permitGroup($hash['data']['user_group'], 'add');
		$this->deletePost();
		$this->redirect();
		$hash += $this->findUser($hash['data']);
		return $hash;

	}
	
	function checkUser() {
	
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$data = $this->fetchOne("SELECT id FROM ".$this->table." WHERE userid = '".$this->quote($_SESSION['userid'])."'");
			if (is_array($data) && count($data) > 0) {
				if ((isset($_POST['id']) && $_POST['id'] == $data['id']) || (is_array($_POST['checkedid']) && in_array($data['id'], $_POST['checkedid']))) {
					$this->error[] = 'ログインしているユーザーは削除できません。';
				}
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
	
	function feed() {
		
		if ($_REQUEST['type'] == 1 && $_REQUEST['group'] <= 0) {
			$_REQUEST['group'] = $_SESSION['group'];
		}
		if ($_REQUEST['group'] > 0) {
			$query = "SELECT userid, realname FROM ".$this->table." WHERE (user_group = '".intval($_REQUEST['group'])."') ORDER BY user_order,id";
			$hash['list'] = $this->fetchAll($query);
		}
		$hash['group'] = $this->findGroup();
		return $hash;
		
	}

	function feedApi() {
		
		if ($_REQUEST['type'] == 1 && $_REQUEST['group'] <= 0) {
			$_REQUEST['group'] = $_SESSION['group'];
		}
		if ($_REQUEST['group'] > 0) {
			$query = "SELECT userid, realname FROM ".$this->table." WHERE (user_group = '".intval($_REQUEST['group'])."') ORDER BY user_order,id";
			$hash['list'] = $this->fetchAll($query);
		}
		$hash['group'] = $this->findGroup();
		return $hash;
		
	}

	function getList() {
		$retrict_group = array(RETIRE_GROUP);
		$query = "SELECT userid, realname, user_group FROM ".$this->table." WHERE (`is_suspend` = '' OR`is_suspend` IS NULL OR is_suspend = '0') and user_group NOT IN ('".implode("','", $retrict_group)."') ORDER BY user_order,id";
		$hash['list'] = $this->fetchAll($query);
		return $hash;
	}

}

?>