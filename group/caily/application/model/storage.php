<?php


class Storage extends ApplicationModel {
	
	function __construct() {
	
		$this->schema = array(
		'storage_folder'=>array('fix'=>intval($_GET['folder']), 'except'=>array('search', 'update')),
		'storage_type'=>array('fix'=>'file'),
		'storage_title'=>array('タイトル', 'notnull', 'length:1000'),
		'storage_name'=>array('fix'=>$_SESSION['realname']),
		'storage_comment'=>array('内容', 'length:10000', 'line:100'),
		'storage_date'=>array('fix'=>date('Y-m-d H:i:s'), 'except'=>array('search', 'update')),
		'storage_file'=>array('except'=>array('update')),
		'storage_size'=>array('except'=>array('search', 'update')),
		'is_protected'=>array('fix'=>'0', 'except'=>array('search')),
		'add_level'=>array('except'=>array('search')),
		'add_group'=>array('except'=>array('search')),
		'add_user'=>array('except'=>array('search')),
		'public_level'=>array('except'=>array('search')),
		'public_group'=>array('except'=>array('search')),
		'public_user'=>array('except'=>array('search')),
		'edit_level'=>array('except'=>array('search')),
		'edit_group'=>array('except'=>array('search')),
		'edit_user'=>array('except'=>array('search')));
		
	}
	
	function index() {
		
		$hash['parent'] = $this->permitFind('public', $_GET['folder']);
		$this->where[] = "(storage_folder = '".intval($_GET['folder'])."')";
		$hash += $this->permitList('storage_type DESC, storage_date', 1);
		if ($_GET['folder'] > 0 && is_array($hash['parent']) && isset($hash['parent']['storage_folder'])) {
			$query = sprintf("SELECT id, storage_title FROM %s WHERE (storage_folder = %d) AND (storage_type = 'folder') AND %s ORDER BY storage_title", $this->table, intval($hash['parent']['storage_folder']), $this->permitWhere());
			$data = $this->fetchAll($query);
			$hash['folder'] = array();
			if (is_array($data) && count($data) > 0) {
				foreach ($data as $row) {
					$hash['folder'][$row['id']] = $row['storage_title'];
				}
			}
		}
		return $hash;
	
	}

	function view() {
		
		$hash['data'] = $this->permitFind();
		if ($hash['data']['storage_folder'] > 0) {
			$hash['folder'] = $this->permitFind('public', $hash['data']['storage_folder']);
		}
		$hash += $this->findUser($hash['data']);
		return $hash;
	
	}
	
	function add() {
	
		$folderId = isset($_REQUEST['folder']) ? intval($_REQUEST['folder']) : intval($_GET['folder'] ?? 0);
		$hash['folder'] = $this->permitFolder($folderId);
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$hasUpload = (is_array($_FILES['uploadfile']['name'] ?? null) && strlen((string)($_FILES['uploadfile']['name'][0] ?? '')) > 0)
				|| (is_array($_POST['uploadedfile'] ?? null) && strlen((string)($_POST['uploadedfile'][0] ?? '')) > 0);
			if (!$hasUpload) {
				$this->error[] = 'アップロードするファイルを選択してください。';
			}
			$this->schema['storage_folder']['fix'] = $folderId;
			$this->schema['storage_type']['fix'] = 'file';
			$this->schema['storage_date']['fix'] = date('Y-m-d H:i:s');
			$this->validateSchema('insert');
			$this->permitValidate();
			$this->normalizeStorageInsertPost('file', $folderId);
			$ts = strtotime((string)($this->post['storage_date'] ?? ''));
			if ($ts === false) {
				$ts = time();
				$this->post['storage_date'] = date('Y-m-d H:i:s', $ts);
			}
			$prefix = $_SESSION['userid'].'_'.$ts;
			$this->post['storage_file'] = $this->uploadfile('storage', $prefix);
			if (strlen((string)$this->post['storage_file']) <= 0 && count($this->error) <= 0) {
				$this->error[] = 'アップロードするファイルを選択してください。';
			}
			$this->post['storage_size'] = $this->storageTotalSize($prefix, $this->post['storage_file']);
			$this->post['is_protected'] = !empty($_POST['is_protected']) ? 1 : 0;
			$this->insertPost();
			$this->redirect('index.php'.$this->parameter(array('folder'=>$folderId)));
			$hash['data'] = $this->post;
		}
		$hash += $this->findUser($hash['data']);
		return $hash;
	
	}

	function edit() {
		
		$hash['data'] = $this->permitFind('edit');
		$this->type($hash['data'], 'file');
		$hash['folder'] = $this->permitFolder($hash['data']['storage_folder']);
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->schema['storage_file']['except'] = array();
			$this->schema['storage_size']['except'] = array();
			$this->validateSchema('update');
			$this->permitValidate();
			$prefix = $hash['data']['owner'].'_'.strtotime($hash['data']['storage_date']);
			$this->post['storage_file'] = $this->uploadfile('storage', $prefix, $hash['data']['storage_file']);
			if (strlen((string)$this->post['storage_file']) <= 0) {
				$this->error[] = 'ファイルを選択してください。';
			} else {
				$this->post['storage_size'] = $this->storageTotalSize($prefix, $this->post['storage_file']);
			}
			$this->post['is_protected'] = !empty($_POST['is_protected']) ? 1 : 0;
			$this->updatePost();
			$this->redirect('index.php'.$this->parameter(array('folder'=>$hash['data']['storage_folder'])));
			$this->post['storage_date'] = $hash['data']['storage_date'];
			if (!isset($this->post['storage_file']) || strlen((string)$this->post['storage_file']) <= 0) {
				$this->post['storage_file'] = $hash['data']['storage_file'];
			}
			$hash['data'] = $this->post;
		}
		$hash += $this->findUser($hash['data']);
		return $hash;
	
	}
	
	function delete() {
		
		$hash['data'] = $this->permitFind('edit');
		$this->type($hash['data'], 'file');
		$hash['folder'] = $this->permitFolder($hash['data']['storage_folder']);
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->deletePost();
			if ($this->response && count($this->error) <= 0) {
				$this->uploadfile('storage', $hash['data']['owner'].'_'.strtotime($hash['data']['storage_date']), $hash['data']['storage_file']);
				$this->redirect('index.php'.$this->parameter(array('folder'=>$hash['data']['storage_folder'])));
			}
		}
		$hash += $this->findUser($hash['data']);
		return $hash;

	}

	function folderview() {
		
		return $this->view();
	
	}
	
	function folderadd() {
		if(!isset($_GET['folder'])){
			$_GET['folder'] = 0;
		}
		$folderId = isset($_REQUEST['folder']) ? intval($_REQUEST['folder']) : intval($_GET['folder']);
		$hash['folder'] = $this->permitFolder($folderId);
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->schema['storage_type']['fix'] = 'folder';
			$this->schema['storage_folder']['fix'] = $folderId;
			$this->schema['storage_title'][0] = 'フォルダ名';
			$this->schema['storage_date']['fix'] = date('Y-m-d H:i:s');
			$this->validateSchema('insert');
			$this->permitValidate();
			$this->normalizeStorageInsertPost('folder', $folderId);
			$this->insertPost();
			$this->redirect('index.php'.$this->parameter(array('folder'=>$folderId)));
			$hash['data'] = $this->post;
		}
		$hash += $this->findUser($hash['data']);
		return $hash;
	
	}

	function folderedit() {
		
		$hash['data'] = $this->permitFind('edit');
		$this->type($hash['data'], 'folder');
		$hash['folder'] = $this->permitFolder($hash['data']['storage_folder']);
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->schema['storage_type']['fix'] = 'folder';
			$this->schema['storage_title'][0] = 'フォルダ名';
			$this->schema['is_protected']['except'] = array('search', 'update');
			$hash['data'] = $this->permitUpdate('index.php'.$this->parameter(array('folder'=>$hash['data']['storage_folder'])));
		}
		$hash += $this->findUser($hash['data']);
		return $hash;
	
	}
	
	function folderdelete() {
		
		$hash['data'] = $this->permitFind('edit');
		$this->type($hash['data'], 'folder');
		$hash['folder'] = $this->permitFolder($hash['data']['storage_folder']);
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$query = "SELECT ".implode(',', $this->schematize())." FROM ".$this->table." WHERE storage_folder = ".intval($_POST['id']);
			$data = $this->fetchAll($query);
			if (is_array($data) && count($data) > 0) {
				foreach ($data as $row) {
					if ($row['storage_type'] == 'folder') {
						$this->error[] = 'サブフォルダが存在するフォルダは削除できません。<br />サブフォルダを削除してください。';
						break;
					} elseif (!$this->permitted($row, 'public') || !$this->permitted($row, 'edit')) {
						$this->error[] = '編集権限のないファイルが存在します。<br />フォルダを削除できませんでした。';
						break;
					}
				}
			}
			$this->deletePost();
			if ($this->response && count($this->error) <= 0) {
				$query = "DELETE FROM ".$this->table." WHERE storage_folder = ".intval($_POST['id']);
				$this->response = $this->query($query);
				if ($this->response && is_array($data) && count($data) > 0) {
					foreach ($data as $row) {
						$this->removefile('storage', $row['owner'].'_'.strtotime($row['storage_date']), $row['storage_file']);
					}
				}
				$this->redirect('index.php'.$this->parameter(array('folder'=>$hash['data']['storage_folder'])));
			}
		}
		$hash += $this->findUser($hash['data']);
		return $hash;

	}
	
	function permitFolder($id) {
		
		if ($id >= 0) {
			$data = $this->permitFind('public', $id);
			if ($this->permitted($data, 'add')) {
				return $data;
			} else {
				$this->died('このフォルダへの書き込み権限がありません。');
			}
		}
	
	}
	
	function type($data, $type) {
		
		if ($type == 'file' && $data['storage_type'] == 'folder') {
			header('Location:folder'.basename($_SERVER['SCRIPT_NAME']).'?id='.$data['id']);
			exit();
		} elseif ($type == 'folder' && $data['storage_type'] == 'file') {
			header('Location:'.str_replace('folder', '', basename($_SERVER['SCRIPT_NAME'])).'?id='.$data['id']);
			exit();
		}
	
	}

	/**
	 * Ensure insert payload has valid type/folder/int fields (avoid '' into TINYINT / lost folder).
	 */
	private function normalizeStorageInsertPost($type, $folderId) {
		$this->post['storage_type'] = ($type === 'folder') ? 'folder' : 'file';
		$this->post['storage_folder'] = intval($folderId);
		$this->post['is_protected'] = !empty($this->post['is_protected']) ? 1 : 0;
		foreach (array('add_level', 'public_level', 'edit_level') as $levelKey) {
			if (!isset($this->post[$levelKey]) || $this->post[$levelKey] === '' || $this->post[$levelKey] === null) {
				$this->post[$levelKey] = 0;
			} else {
				$this->post[$levelKey] = intval($this->post[$levelKey]);
			}
		}
		if (!isset($this->post['storage_date']) || !strtotime((string)$this->post['storage_date'])) {
			$this->post['storage_date'] = date('Y-m-d H:i:s');
		}
		if ($type === 'folder') {
			$this->post['storage_file'] = '';
			$this->post['storage_size'] = '';
			$this->post['is_protected'] = 0;
		}
	}
	
	function download() {
		
		$data = $this->permitFind();
		if ($data['storage_folder'] > 0) {
			$hash['folder'] = $this->permitFind('public', $data['storage_folder']);
		}
		$requestFile = isset($_REQUEST['file']) ? (string)$_REQUEST['file'] : '';
		$files = $this->storageFileList($data['storage_file'] ?? '');
		if ($requestFile === '' || !in_array($requestFile, $files, true)) {
			$this->died('ファイルが見つかりません。');
		}

		$isProtected = isset($data['is_protected']) && (intval($data['is_protected']) === 1);
		$wantInline = isset($_REQUEST['inline']) && $_REQUEST['inline'] == '1';

		$prefix = $data['owner'].'_'.strtotime($data['storage_date']);
		$path = $this->resolveUploadFilePath('storage', $prefix, $requestFile);
		if (!file_exists($path)) {
			$this->died('ファイルが見つかりません。');
		}

		// Inline preview (PDF/image) — allowed for permitted viewers (viewer page only)
		if ($wantInline) {
			$this->logStorageAccess($data['id'], $requestFile, 'preview');
			$this->streamInlineFile($path, $requestFile);
			return;
		}

		// Protected: no download at all (view-only via title → preview)
		if ($isProtected) {
			$this->died('保護ファイルのためダウンロードできません。一覧のタイトルから閲覧してください。');
		}

		$this->logStorageAccess($data['id'], $requestFile, 'download');
		$this->attachment('storage', $prefix, $requestFile, 'attachment');
	
	}

	private function streamInlineFile($path, $filename) {
		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		$mimeMap = array(
			'pdf' => 'application/pdf',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'gif' => 'image/gif',
			'webp' => 'image/webp',
			'bmp' => 'image/bmp',
			'svg' => 'image/svg+xml',
		);
		$mime = isset($mimeMap[$ext]) ? $mimeMap[$ext] : 'application/octet-stream';
		$size = filesize($path);
		$encoded = rawurlencode($filename);
		header('Content-Type: ' . $mime);
		header('Content-Length: ' . $size);
		header(sprintf(
			'Content-Disposition: inline; filename="%s"; filename*=UTF-8\'\'%s',
			$encoded,
			$encoded
		));
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('X-Content-Type-Options: nosniff');
		if (ob_get_level()) {
			ob_end_clean();
		}
		readfile($path);
		exit;
	}

	private function logStorageAccess($storageId, $filename, $action = 'view') {
		$storageId = intval($storageId);
		if ($storageId <= 0) {
			return;
		}
		try {
			$this->query(sprintf(
				"INSERT INTO " . DB_PREFIX . "storage_view_logs
				 (storage_id, filename, userid, realname, action, ip, user_agent, created_at)
				 VALUES (%d, '%s', '%s', '%s', '%s', '%s', '%s', NOW())",
				$storageId,
				$this->quote((string)$filename),
				$this->quote((string)($_SESSION['userid'] ?? '')),
				$this->quote((string)($_SESSION['realname'] ?? '')),
				$this->quote((string)$action),
				$this->quote((string)($_SERVER['REMOTE_ADDR'] ?? '')),
				$this->quote(substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500))
			));
		} catch (Exception $e) {
			// migration may be pending
		}
	}

	/** @return string[] */
	function storageFileList($filelist) {
		if ($filelist === null || $filelist === '') {
			return array();
		}
		$list = array();
		foreach (explode(',', (string)$filelist) as $name) {
			$name = trim($name);
			if ($name !== '') {
				$list[] = $name;
			}
		}
		return $list;
	}

	function storageTotalSize($prefix, $filelist) {
		$total = 0;
		foreach ($this->storageFileList($filelist) as $name) {
			$file = $this->resolveUploadFilePath('storage', $prefix, $name);
			if (file_exists($file)) {
				$size = @filesize($file);
				if ($size > 0) {
					$total += $size;
				}
			}
		}
		if ($total > 1024) {
			return number_format(floor($total / 1024)).'K';
		}
		if ($total <= 0) {
			return '0K';
		}
		return '1K';
	}

}

?>
