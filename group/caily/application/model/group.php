<?php


class Group extends ApplicationModel {
	
	function __construct() {
		if (basename($_SERVER['SCRIPT_NAME']) != 'feedApi.php' && basename($_SERVER['SCRIPT_NAME']) != 'feed.php' && $_SERVER['SCRIPT_NAME'] != '/api/index.php') {
			$this->authorize('administrator');
		}
		$this->schema = array(
		'group_name'=>array('グループ名', 'notnull', 'length:100'),
		'group_order'=>array('順序', 'numeric', 'length:10', 'except'=>array('search')),
		'add_level'=>array('except'=>array('search')),
		'add_group'=>array('except'=>array('search')),
		'add_user'=>array('except'=>array('search')),
		'edit_level'=>array('except'=>array('search')),
		'edit_group'=>array('except'=>array('search')),
		'edit_user'=>array('except'=>array('search')));
	
	}
	
	function index() {
		
		$hash = $this->findLimit('group_order', 0);
		return $hash;
	
	}

	function view() {
	
		$hash['data'] = $this->findView();
		$hash += $this->findUser($hash['data']);
		return $hash;

	}
	
	function add() {
		
		$hash['data'] = $this->permitInsert();
		$hash += $this->findUser($hash['data']);
		return $hash;

	}
	
	function edit() {
	
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->validateSchema('update');
			$this->permitValidate();
			$this->updatePost();
			if ($this->response) {
				$query = sprintf("UPDATE %s SET user_groupname = '%s' WHERE user_group = %s", DB_PREFIX.'user', $this->quote($this->post['group_name']), intval($_POST['id']));
				$this->response = $this->query($query);
			}
			$this->redirect();
			$hash['data'] = $this->post;
		} else {
			$hash['data'] = $this->findView();
		}
		$hash += $this->findUser($hash['data']);
		return $hash;
	
	}
	
	function delete() {
	
		$this->checkGroupuser($_REQUEST['id']);
		$hash['data'] = $this->permitFind('edit');
		$this->deletePost();
		$this->redirect('index.php');
		$hash += $this->findUser($hash['data']);
		return $hash;

	}
	
	function checkGroupuser($id) {
	
		$count = $this->fetchCount(DB_PREFIX.'user', "WHERE user_group = '".intval($id)."'", 'id');
		if ($count > 0) {
			$this->error[] = 'このグループに所属しているユーザーが存在します。<br />ユーザーのグループを変更してください。';
		}
		
	}

	function getList() {
		$query = "SELECT id, group_name FROM ".DB_PREFIX."group WHERE id <> ".RETIRE_GROUP." ORDER BY group_order,id";
		$hash['list'] = $this->fetchAll($query);
		return $hash;
	}

}

?>