<?php


class General extends ApplicationModel {
	
	function index() {
		
		// if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		// 	if (isset($_POST['timecard_open']) || isset($_POST['timecard_close']) || isset($_POST['timecard_interval'])) {
		// 		require_once(DIR_MODEL.'timecard.php');
		// 		$timecard = new Timecard;
		// 		$timecard->handler = $this->handler;
		// 		$timecard->add();
		// 	} elseif (isset($_POST['folder']) && $_POST['folder'] == 'complete') {
		// 		require_once(DIR_MODEL.'todo.php');
		// 		$todo = new Todo;
		// 		$todo->handler = $this->handler;
		// 		$todo->move();
		// 	}
		// }
		$sessionuserid = $this->quote($_SESSION['userid']);
		$hash['year'] = date('Y');
		$hash['month'] = date('n');
		$hash['day'] = date('j');
		$hash['weekday'] = date('w');
		$monthly = mktime(0, 0, 0, $hash['month'] - 1, $hash['day'], $hash['year']);
	
		$field = "*";
		$query = sprintf("SELECT %s FROM %stimecard WHERE (timecard_date = '%s') AND (owner = '%s')", $field, DB_PREFIX, date('Y-m-d'), $sessionuserid);
		$hash['timecard'] = $this->fetchOne($query);
		// $field = "*";
		// $query = sprintf("SELECT %s FROM %stodo WHERE (owner = '%s') AND (todo_complete = 0) ORDER BY todo_noterm, todo_term", $field, DB_PREFIX, $sessionuserid);
		// $hash['todo'] = $this->fetchLimit($query, 0, 5);
		// $field = "*";
		// $query = sprintf("SELECT %s FROM %smessage WHERE (owner = '%s') AND (folder_id = 0) AND (message_type = 'received') ORDER BY message_date DESC", $field, DB_PREFIX, $sessionuserid);
		// $hash['message'] = $this->fetchLimit($query, 0, 5);
		$where = array();
		$category = $this->permitCategory('forum');
		$where[] = $this->folderWhere($category['folder'], 'all');
		$where[] = "(forum_parent = 0)";
		$where[] = $this->permitWhere();
		$field = "*";
		$query = sprintf("SELECT %s FROM %sforum WHERE %s ORDER BY forum_lastupdate DESC", $field, DB_PREFIX, implode(" AND ", $where));
		$hash['forum'] = $this->fetchLimit($query, 0, 8);
		// $where = array();
		// $category = $this->permitCategory('bookmark');
		// $where[] = $this->folderWhere($category['folder']);
		// $where[] = $this->permitWhere();
		// $field = "*";
		// $query = sprintf("SELECT %s FROM %sbookmark WHERE %s ORDER BY bookmark_order, bookmark_date DESC", $field, DB_PREFIX, implode(" AND ", $where));
		// $hash['bookmark'] = $this->fetchLimit($query, 0, 5);
		// $where = array();
		// $category = $this->permitCategory('project');
		// $where[] = "(project_end >= '".date('Y-m-d')."')";
		// $where[] = $this->folderWhere($category['folder'], 'all');
		// $where[] = "(project_parent = '0')";
		// $where[] = $this->permitWhere();
		// $field = "*";
		// $query = sprintf("SELECT %s FROM %sproject WHERE %s ORDER BY project_begin", $field, DB_PREFIX, implode(" AND ", $where));
		// $hash['project'] = $this->fetchLimit($query, 0, 5);
		// $hash['group'] = $this->findGroup();
		return $hash;
	
	}
	
	function administration() {
	
		$this->authorize('administrator', 'manager');
		if (file_exists('setup.php')) {
			if ($_SERVER['REQUEST_METHOD'] == 'POST') {
				if (is_writable('setup.php')) {
					if (@unlink('setup.php') == false) {
						$this->error[] = 'セットアップファイルの削除に失敗しました。';
					}
				} else {
					$this->error[] = 'セットアップファイルに書き込み権限がありません。<br />削除に失敗しました。';
				}
			} else {
				$this->error[] = 'セットアップファイル(setup.php)が存在します。<br />削除してください。';
			}
		}
	
	}

}

?>