<?php


class Member extends ApplicationModel {
	
	function __construct() {
		
		$this->table = DB_PREFIX.'user';
		$this->schema = array(
		'userid'=>array('except'=>array('search', 'update')),
		'user_group'=>array('except'=>array('search', 'update')),
		'user_groupname'=>array('except'=>array('update')),
		'realname'=>array('名前', 'notnull', 'length:100'),
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
		'member_type'=>array('従業員の種類', 'length:100'),
	);
		
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

	function get_member() {
		$config = new Config($this->handler);
		$query = "SELECT groupware_user.id as `id`, `userid`, `password`, `password_default`, `realname`, `authority`, `user_group`, `gender`, `user_email`, `user_skype`, `user_ruby`, `user_postcode`, `user_address`, `user_addressruby`, `user_phone`, `user_mobile`, `user_order`, `status`, `idle_time`, `pc_hashs`, `member_type`, `user_image`, `is_suspend` , groupware_group.group_name as group_name FROM groupware_user, groupware_group WHERE groupware_user.user_group = groupware_group.id order by is_suspend asc, groupware_user.id asc";
		$hash['list'] = $this->fetchAll($query);
		$hash['group'] = $this->findGroup();

		$hash['list_config'] = $config->getListConfigTimecard();

		$arr_config = array();
		foreach ($hash['list_config'] as $key => $value) {
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
				echo $this->response. "<br>".'aaa';
				if ($this->response == '1') {
					
					$check = TRUE;
				}
			}
			$hash['data'] = $this->post;
			if($check){
				$hash['data']['message'] = 'パスワードを変更しました。';
			}
		}
		
		return $hash;
	}

	function edit() {
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->validateSchema('update');
			//$this->validate();
			$this->post['editor'] = $_SESSION['userid'];
			$this->post['updated'] = date('Y-m-d H:i:s');
			if(isset($_FILES['user_image']) && $_FILES['user_image']['name'] != '') {
				$this->post['user_image'] = $_SESSION['userid'].'_'.$_FILES['user_image']['name'];
				$this->uploadAvatar($_SESSION['userid'].'_'.$_FILES['user_image']['name']);
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
			} else {
				$this->error[] = '画像ファイルを選択してください。';
			}
		}
	}

}

?>