<?php


class Member extends ApplicationModel {
	
	function __construct() {
		
		$this->table = DB_PREFIX.'user';
		$this->schema = array(
		'userid'=>array('ユーザーID', 'notnull', 'userid', 'length:100', 'distinct'),
		'user_group'=>array('except'=>array('search')),
		'user_groupname'=>array('except'=>array('search')),
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
		'user_order'=>array('順序', 'numeric', 'length:10', 'except'=>array('search')),
		'edit_level'=>array('except'=>array('search')),
		'edit_group'=>array('except'=>array('search')),
		'edit_user'=>array('except'=>array('search')),
		'member_type'=>array('従業員の種類', 'length:100'),
		'is_suspend'=>array('ステータス', 'numeric', 'length:1','except'=>array('search', 'update')),
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

	/*API*/
	function add_member() {
		$this->authorizeApi('administrator', 'manager');
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->validateSchema('insert');
			$this->permitValidate();
			if (method_exists($this, 'validateAdd')) {
				$this->validateAdd();
			}
			$this->insertPost();
		}
		if(count($this->error) > 0){
			$hash['status'] = 'error';
			$hash['error'] = $this->error;
			return $hash;
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
			$this->updatePost();
		}
		if(count($this->error) > 0){
			$hash['status'] = 'error';
			$hash['error'] = $this->error;
			return $hash;
		}
		
		$hash['status'] = 'success';
		$hash['message_code'] = 11;
		return $hash;
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
			"UPDATE groupware_user SET is_suspend = NULL, editor = '%s', updated = '%s' WHERE id = '%s'",
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
			"UPDATE groupware_user SET password = '%s', editor = '%s', updated = '%s' WHERE id = '%s'",
			$password,
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
		if(count($this->error) > 0){
			$hash['status'] = 'error';
			$hash['message_code'] = '12';
			return $hash;
		}
		
		$hash['error'] = $this->error;
		$hash['status'] = 'success';
		return $hash;
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
			"UPDATE groupware_user SET remember_token = NULL, user_group = '". RETIRE_GROUP ."', user_groupname = '". RETIRE_GROUP_NAME ."', is_suspend = '1', editor = '%s', updated = '%s' WHERE id = '%s'",
			$editor,
			$date,
			$id
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