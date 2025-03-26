<?php
/*
 * Copyright(c) 2009 limitlink,Inc. All Rights Reserved.
 * http://limitlink.jp/
 * 文字コード UTF-8
 */

class Timecard extends ApplicationModel {
	var $holidays = array();
	function Timecard() {
		$this->holidays = $this->getHolidays();
		$this->table = DB_PREFIX.'timecard';
		$this->schema = array(
			'timecard_year'=>array(),				/*年*/
			'timecard_month'=>array(),				/*月*/
			'timecard_day'=>array(),				/*日*/
			'timecard_date'=>array(),				/*入力日(Y-M-D)*/
			'timecard_open'=>array(),				/*出社時間*/
			'timecard_close'=>array(),				/*退社時間*/
			'timecard_interval'=>array(),			/*休憩時間*/
			'timecard_originalopen'=>array(),		/*出社時間(元)*/
			'timecard_originalclose'=>array(),		/*退社時間(元)*/
			'timecard_originalinterval'=>array(),	/*休憩時間(元)*/
			'timecard_time'=>array(),				/*勤務時間*/
			'timecard_timeover'=>array(),			/*残業時間*/
			'timecard_timeinterval'=>array(),		/*休憩時間*/
			'timecard_comment'=>array());			/*コメント*/
		if ($_GET['year'] > 1900 && $_GET['year'] <= 3000) {
			$_GET['year'] = intval($_GET['year']);
		} else {
			$_GET['year'] = date('Y');
		}

		if ($_GET['day'] > 0 && $_GET['day'] <= 31) {
			$_GET['day'] = intval($_GET['day']);
		} else {
			$_GET['day'] = date('j');
		}

		if ($_GET['month'] > 0 && $_GET['month'] <= 12) {
			$_GET['month'] = intval($_GET['month']);
		} else {
			if ($_GET['day'] > 20){
				$_GET['month'] = date('n')+1;
			}else{
				$_GET['month'] = date('n');
			}
		}
	}

	function getHolidays(){
		$filename = DIR_MODEL . "holidays.txt";
		$handle = fopen($filename, "r") or die("Unable to open file!");
		$contents = fread($handle, filesize($filename));
		fclose($handle);
		$holidays = explode(',', $contents);
		for ($i = 0; $i < count($holidays); $i++) {
			$holidays[$i] = trim($holidays[$i]);
		}
		return $holidays;
	}

	function saveHolidays($holidays){
		$filename = DIR_MODEL . "holidays.txt";
		$handle = fopen($filename, "w") or die("Unable to open file!");
		fwrite($handle, $holidays);
		fclose($handle);
		$this->redirect();
	}

	function holiday() {
		$this->authorize('administrator', 'manager');
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			if (isset($_POST['holidays'])) {
				$this->saveHolidays($_POST['holidays']);
			}
		}
		$this->holidays = $this->getHolidays();
		$hash['data']['holidays'] = implode(', ', $this->holidays);
		return $hash;
	}

	function index() {

		/*オーナー特定*/
		$hash = $this->findOwner($_GET['member']);
		/*データ取得*/
		$this->add();

		/*リスト取得*/
		$field = implode(',', $this->schematize());
		

		/*1月対応*/
		$mt = $_GET['month'];
		$yr = $_GET['year'];
		if(strcmp($mt,'1') == 0){
			$mt = $mt + 11;
			$yr = $yr-1;
		}else{
			$mt = $mt - 1;
		}

		/*20日締め対応*/
		$query = sprintf("SELECT %s FROM %s WHERE (((timecard_year = %d) AND (timecard_month = %d) AND (timecard_day BETWEEN '21' AND '31')) OR ((timecard_year = %d) AND (timecard_month = %d) AND (timecard_day BETWEEN '01' AND '20')))  AND (owner = '%s') ORDER BY timecard_date", $field, $this->table, $yr, $mt, $_GET['year'], $_GET['month'], $this->quote($hash['owner']['userid']));

		/*$query = sprintf("SELECT %s FROM %s WHERE (timecard_year = %d) AND (timecard_month = %d) AND (owner = '%s') ORDER BY timecard_date", $field, $this->table, $_GET['year'], $_GET['month'], $this->quote($hash['owner']['userid']));*/

		$hash['list'] = $this->fetchAll($query);

		/*再計算時*/
		if ($_GET['recalculate'] == 1 && $_SERVER['REQUEST_METHOD'] != 'POST') {
			$hash['list'] = $this->recalculate($hash['list']);
		}
		
		$hash['config'] = $this->getConfigStatus();
		return $hash;
	}

	function getConfigStatus(){
		$config = new Config($this->handler);
		if($_GET["member"]){
			return $config->getConfigTimeCardByUser($_GET["member"]);
		}
		return $config->getConfigTimeCardByUser($_SESSION['userid']);
	}

	function checkHoliday($year, $month, $day, $weekday, $lastday = 31) {
		$date = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
		if ($weekday == 0 || ($day > 0 && $day <= $lastday && in_array($date, $this->holidays))) {
			return true;
		} elseif ($weekday == 6) {
			return true;
		} else {
			return false;
		}
		return false;
	}


	function add() {
		$hash = $this->findOwner($_GET['member']);
		/*時間取得*/
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {

			/*データ取得*/
			$data = $this->findRecord($hash);
			$time = date('G:i');
			if (isset($_POST['timecard_open']) && !$data && !$data['timecard_open'] && !$data['timecard_comment']) {
				/*出社時間取得*/
				$this->post['timecard_year'] = date('Y');
				$this->post['timecard_month'] = date('n');
				$this->post['timecard_day'] = date('j');
				$this->post['timecard_date'] = date('Y-m-d');
				$this->post['timecard_originalopen'] = $time;
				$this->post['timecard_open'] = $time;
				$this->insertPost();

			} else {

				/*退出時間取得*/
				if (isset($_POST['timecard_interval'])) {

					if ($data['timecard_interval']) {
						/*外出時間取得*/
						if (preg_match('/.*-[0-9]+:[0-9]+$/', $data['timecard_interval'])) {
							/*外出時間*/
							$time = ' '.$time;
						} elseif (preg_match('/.*[0-9]+:[0-9]+$/', $data['timecard_interval'])) {
							/*復帰時間*/
							$time = '-'.$time;
						}
					}
					/*外出時間セット*/
					$this->post['timecard_originalinterval'] = trim($data['timecard_originalinterval'].$time);
					$this->post['timecard_interval'] = $data['timecard_interval'].$time;

				} elseif (isset($_POST['timecard_close'])){

					/*退社時間セット*/
					$this->post['timecard_year'] = date('Y');
					$this->post['timecard_day'] = date('j');
					$this->post['timecard_month'] = date('n');
					$this->post['timecard_originalclose'] = $time;
					$this->post['timecard_close'] = $time;

					/*勤務時間取得*/
					$this->post += $this->sumAdd($data['timecard_open'], $this->post['timecard_close'], $data['timecard_interval']='', $this->post['timecard_day']);
				}
				$this->record($this->post , $hash, $this->post['timecard_year'], $this->post['timecard_month'], $this->post['timecard_day']);
			}
		}

	}

	function edit() {
		$hash = $this->findOwner($_GET['member']);
		$syr = $_GET['year'];
		$smt = $_GET['month'];
		$sdt = $_GET['day'];

		$hash['data'] = $this->findRecord($hash, $syr, $smt, $sdt);
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->validator('timecard_comment', '内容', array('length:10000', 'line:100'));
			$this->post['timecard_open'] = $this->validatetime($_POST['openhour'], $_POST['openminute']);
			$this->post['timecard_close'] = $this->validatetime($_POST['closehour'], $_POST['closeminute']);

			$array = array();
			if (is_array($_POST['intervalopenhour']) && count($_POST['intervalopenhour']) > 0) {
				for ($i = 0; $i < count($_POST['intervalopenhour']); $i++) {
					$open = $this->validatetime($_POST['intervalopenhour'][$i], $_POST['intervalopenminute'][$i]);
					$close = $this->validatetime($_POST['intervalclosehour'][$i], $_POST['intervalcloseminute'][$i]);
					if (strlen($open) > 0 && strlen($close) > 0) {
						$array[] = $open.'-'.$close;
					}
				}
			}
			$this->post['timecard_interval'] = implode(' ', $array);
			if (strlen($this->post['timecard_close']) > 0) {
				$array = $this->sumEdit($this->post['timecard_open'], $this->post['timecard_close'], $this->post['timecard_interval'], $sdt);
				$this->post['timecard_time'] = $array['timecard_time'];
				$this->post['timecard_timeover'] = $array['timecard_timeover'];
				$this->post['timecard_timeinterval'] = $array['timecard_timeinterval'];
			} else {
				$this->post['timecard_time'] = '';
				$this->post['timecard_timeover'] = '';
				$this->post['timecard_timeinterval'] = '';
			}
			$this->post['editor'] = $_SESSION['userid'];
			$this->post['updated'] = date('Y-m-d H:i:s');
			if (is_array($hash['data']) && $hash['data']['id'] > 0 && count($this->error) <= 0) {
				$this->record($this->post, $hash, $syr, $smt, $sdt);
			} else {
				if (!$this->post['timecard_open']) {
					/*$this->error[] = '出社時間を入力してください。';*/
				}
				$this->schema += array('editor'=>'', 'updated'=>'');
				$this->post['timecard_year'] = $syr;
				$this->post['timecard_month'] = $smt;
				$this->post['timecard_day'] = $sdt;
				$this->post['timecard_date'] = date('Y-m-d', mktime(0, 0, 0, $smt,$sdt,$syr ));
				$this->post['owner'] = $hash['owner']['userid'];
				$this->insertPost();
			}

			/*1月/20日締め対応*/
			if($sdt > 20){
				if(strcmp($smt,'12')==0){
					$syr = $syr + 1;
					$smt = $smt-11;
				}else{
					$smt = $smt+1;
				}
			}

			$this->redirect('index.php?year='.$syr.'&month='.$smt.'&member='.$hash['owner']['userid']);
			$hash['data'] = $this->post;
		}

		return $hash;

	}

	function record($data, $hash, $year = null, $month = null, $day = null) {

		if (is_array($data) && count($data) > 0) {
			if (isset($year) && isset($month) && isset($day)) {
				$date = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
			} else {
				$date = date('Y-m-d');
			}
			foreach ($data as $key => $value) {
				$array[] = $key." = '".$this->quote($value)."'";
			}
			$query = sprintf("UPDATE %s SET %s WHERE (timecard_date = '%s') AND (owner = '%s')", $this->table, implode(",", $array), $date, $this->quote($hash['owner']['userid']));
			$this->response = $this->query($query);
			return $this->response;
		}
	}

	function sumAdd($open, $close, $interval = '', $sdt) {
		$syr = $_GET['year'];
		if ($sdt < 21) {
			$smt = $_GET['month'];
		} else $smt = $_GET['month'] - 1;
		$timestamp01 = mktime(0, 0, 0, $smt, $sdt, $syr);
		$lastday = date('t', $timestamp01);
		$weekday = date('w', $timestamp01);
		$open = $this->minute($open);
		$close = $this->minute($close);
		$close2 = $close;

		$over = ''; // ngoài giờ

		/*コンフィグ情報取得*/
		$config = new Config($this->handler);
		//$status = $config->configure('timecard');
		$status = $this->getConfigStatus();

		/*定時：開始時間取得*/

		$status['open'] = intval($status['openhour']) * 60 + intval($status['openminute']); // giờ bắt đầu qui định
		if ($open < 300) {
			$open = 300;
		}

		/*定時：終了時間取得*/
		$status['close'] = intval($status['closehour']) * 60 + intval($status['closeminute']); // giờ kết thúc qui định
		if ($status['close'] > 0 && $close > $status['close']) {

			/*定時間外計算*/
			if($status['close'] < $open){
				$over = $close - $open;
			}else{
				$over = $close - $status['close'];
			}
			/*定時：終了時間設定*/
			$close = $status['close'];
		}

		if ($status['timeround'] == 1) {
			$open = ceil($open / 10) * 10;
			$close = floor($close / 10) * 10;
		}

		/*ランチタイム計算*/
		$status['lunchopen'] = intval($status['lunchopenhour']) * 60 + intval($status['lunchopenminute']);
		$status['lunchclose'] = intval($status['lunchclosehour']) * 60 + intval($status['lunchcloseminute']);
		if ($status['intervalround'] == 1) {
			$status['lunchopen'] = floor($status['lunchopen'] / 10) * 10;
			$status['lunchclose'] = ceil($status['lunchclose'] / 10) * 10;
		}

		/*休憩時間計算*/
		$intervalsum = 0;
		if ( $open < $status['lunchclose'] && $open > $status['lunchopen'] ){
			if ($status['lunchopen'] < $status['lunchclose']) {
				$intervalsum += $status['lunchclose'] - $open;
			}
		}else if(($open < $status['lunchclose'] && $close > $status['lunchclose']) || ($close2 >= 0 && $close2 <  $status['open'])){
			$intervalsum += $status['lunchclose'] - $status['lunchopen'];
		}
		if($open >= $status['lunchclose']) $intervalsum = 0;
		$checkHoliday = $this->checkHoliday($syr, $smt, $sdt, $weekday, $lastday);
		/*勤務時間計算*/
		if ($checkHoliday) {
			// if($sum >= 360){
			// 	$intervalsum = 60;
			// 	$sum -= $intervalsum;
			// } else $intervalsum = 0;
			$intervalsum = 0;
			if ($close2 >= 0 && $close2 < $open) {
				$sum = (24 * 60) - $open + $close2;
				$over = 0;
			} else{
				$sum = $close2 - $open;
				$over = 0;
			}
		} else {
			if($close2 >= 0 && $close2 <  $status['open']){
				$total = (24 * 60) - $open + $close2 ;
				if($open>$status['open']){
					$sum = ($status['close'] - $open) - $intervalsum;

				}else{
					$sum = ($status['close'] - $status['open']) - $intervalsum;
				}
				$over  = $total - $sum - $intervalsum;
			}
			else{
				if ($open >= $status['open'] - 30  && $open <= $status['open']) {
					$open = $status['open'];
				}
				if ($open < $status['open'] - 30) {
					$temp1 = $status['open'] - 30 - $open;
					$open = $status['open'];
				}
				$sum = $close - $open - $intervalsum;
				$over = $temp1 + $over ;
			}
		}

		if ($sum < 0) {
			$sum = 0;
		}

		$result['timecard_time'] = sprintf('%d:%02d', (($sum - ($sum % 60)) / 60), ($sum % 60));
		$result['timecard_timeover'] = sprintf('%d:%02d', (($over - ($over % 60)) / 60), ($over % 60));
		$result['timecard_timeinterval'] = sprintf('%d:%02d', (($intervalsum - ($intervalsum % 60)) / 60), ($intervalsum % 60));
		return $result;

	}

	function sumEdit($open, $close, $interval = '', $sdt) {
		$syr = $_GET['year'];
		$smt = $_GET['month'];
		$timestamp01 = mktime(0, 0, 0, $smt, $sdt, $syr);
		$lastday = date('t', $timestamp01);
		$weekday = date('w', $timestamp01);
		$open = $this->minute($open);
		$close = $this->minute($close);
		$close2 = $close;

		$over = ''; // ngoài giờ

		/*コンフィグ情報取得*/
		$config = new Config($this->handler);
		$status = $this->getConfigStatus();

		/*定時：開始時間取得*/

		$status['open'] = intval($status['openhour']) * 60 + intval($status['openminute']); // giờ bắt đầu qui định
		if ($open < 300) {
			$open = 300;
		}

		/*定時：終了時間取得*/
		$status['close'] = intval($status['closehour']) * 60 + intval($status['closeminute']); // giờ kết thúc qui định
		if ($status['close'] > 0 && $close > $status['close']) {

			/*定時間外計算*/
			if($status['close'] < $open){
				$over = $close - $open;
			}else{
				$over = $close - $status['close'];
			}
			/*定時：終了時間設定*/
			$close = $status['close'];
		}

		if ($status['timeround'] == 1) {
			$open = ceil($open / 10) * 10;
			$close = floor($close / 10) * 10;
		}

		/*ランチタイム計算*/
		$status['lunchopen'] = intval($status['lunchopenhour']) * 60 + intval($status['lunchopenminute']);
		$status['lunchclose'] = intval($status['lunchclosehour']) * 60 + intval($status['lunchcloseminute']);
		if ($status['intervalround'] == 1) {
			$status['lunchopen'] = floor($status['lunchopen'] / 10) * 10;
			$status['lunchclose'] = ceil($status['lunchclose'] / 10) * 10;
		}

		/*休憩時間計算*/
		$intervalsum = 0;
		if ( $open < $status['lunchclose'] && $open > $status['lunchopen'] ){
			if ($status['lunchopen'] < $status['lunchclose']) {
				$intervalsum += $status['lunchclose'] - $open;
			}
		}else if(($open < $status['lunchclose'] && $close > $status['lunchclose']) || ($close2 >= 0 && $close2 <  $status['open'])){
			$intervalsum += $status['lunchclose'] - $status['lunchopen'];
		}
		if($open >= $status['lunchclose']) $intervalsum = 0;

		$checkHoliday = $this->checkHoliday($syr, $smt, $sdt, $weekday, $lastday);
		/*勤務時間計算*/
		if ($checkHoliday) {
			// if($sum >= 360){
			// 	$intervalsum = 60;
			// 	$sum -= $intervalsum;
			// } else $intervalsum = 0;
			$intervalsum = 0;
			if ($close2 >= 0 && $close2 < $open) {
				$sum = (24 * 60) - $open + $close2;
				$over = 0;
			} else{
				$sum = $close2 - $open;
				$over = 0;
			}
		} else {
			if($close2 >= 0 && $close2 <  $status['open']){
				/*if($open>$status['open']){
					$sum = ($status['close'] - $open) - $intervalsum;
					$total = (24 * 60) - $open + $close2 ;
				}else{
					$sum = ($status['close'] - $status['open']) - $intervalsum;
					$total = (24 * 60) - $status['open'] + $close2 ;
				}
				$over  = $total - $sum - $intervalsum;*/

				if ($close2 > $open) {
					$over = $close2 - $open;
					$intervalsum = 0;
					$sum = 0;
				} else{
					if ($open >= $status['open'] - 30  && $open <= $status['open']) {
						$open = $status['open'];
					}
					if ($open < $status['open'] - 30) {
						$temp1 = $status['open'] - 30 - $open;
						$open = $status['open'];
					}
					$total = (24 * 60) - $open + $close2;
					$sum = ($status['close'] - $open) - $intervalsum;
					$over = $temp1 + $total - $sum - $intervalsum;
				}
			}
			else{
				if ($open >= $status['open'] - 30  && $open <= $status['open']) {
					$open = $status['open'];
				}
				if ($open < $status['open'] - 30) {
					$temp1 = $status['open'] - 30 - $open;
					$open = $status['open'];
				}
				$sum = $close - $open - $intervalsum;
				$over = $temp1 + $over ;
			}
		}

		if ($sum < 0) {
			$sum = 0;
		}

		$result['timecard_time'] = sprintf('%d:%02d', (($sum - ($sum % 60)) / 60), ($sum % 60));
		$result['timecard_timeover'] = sprintf('%d:%02d', (($over - ($over % 60)) / 60), ($over % 60));
		$result['timecard_timeinterval'] = sprintf('%d:%02d', (($intervalsum - ($intervalsum % 60)) / 60), ($intervalsum % 60));
		return $result;

	}

	function minute($time) {

		$array = explode(':', $time);
		return intval($array[0]) * 60 + intval($array[1]);

	}

	function findRecord($hash, $year = null, $month = null, $day = null) {

		/* DB情報取得 */

		/*更新日付取得*/
		if (isset($year) && isset($month) && isset($day)) {
			$date = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
		} else {
			$date = date('Y-m-d');
		}
		/*データ取得*/
		$field = implode(',', $this->schematize());
		$query = sprintf("SELECT %s FROM %s WHERE (timecard_date = '%s') AND (owner = '%s')", $field, $this->table, $date, $this->quote($hash['owner']['userid']));
		return $this->fetchOne($query);

	}

	function validatetime($hour, $minute) {

		if (strlen($hour) > 0 && strlen($minute) > 0 && $hour >= 0 && $hour < 24 && $minute >= 0 && $minute < 60) {
			return sprintf('%d:%02d', intval($hour), intval($minute));
		}

	}

	function recalculate($data) {
		$hash = $this->findOwner($_GET['member']);
		if (is_array($data) && count($data) > 0) {
			$this->response = true;
			foreach ($data as $key => $row) {
				if (strlen($row['timecard_close']) > 0) {
					$array = $this->sumAdd($row['timecard_open'], $row['timecard_close'], $row['timecard_interval'], $row['timecard_day']);
					$this->record($array, $hash, $row['timecard_year'], $row['timecard_month'], $row['timecard_day']);
					$data[$key]['timecard_time'] = $array['timecard_time'];
					$data[$key]['timecard_timeover'] = $array['timecard_timeover'];
					$data[$key]['timecard_timeinterval'] = $array['timecard_timeinterval'];
				}
			}
			if ($this->response) {
				$this->error[] = '勤務時間と外出時間の再計算結果を保存しました。';
			} else {
				$this->died('勤務時間と外出時間の再計算に失敗しました。');
			}
		}
		return $data;

	}

	function config() {
		$this->authorize('administrator');
		$config = new Config($this->handler);
		$type = 'timecard';
		if($_GET['type']){
			$type = $_GET['type'];
		}
		$hash['type_id'] = $type;
		if($_SERVER['REQUEST_METHOD'] == 'POST'){
			$hash['data'] = $config->editConfigTimecard($_POST['type_id']);
			$this->error = $config->error;
			foreach ($hash['data']['list_config'] as $key => $value) {
				if($value['config_type'] == $_POST['type_id']){
					$hash['data']["type_id"] = $value["config_type"];
					break;
				}
			}
		} else{
			$hash['data'] = $config->get($type);
			$hash['data']["config_name"] = $hash['data']['list_config'][0]["config_name"];
			$hash['data']["type_id"] = $hash['data']['list_config'][0]["config_type"];
			if($_SERVER['REQUEST_METHOD'] == 'GET' && $_GET['type']){
				foreach ($hash['data']['list_config'] as $key => $value) {
					if($value['config_type'] == $_GET['type']){
						$hash['data']["config_name"] = $value["config_name"];
						$hash['data']["type_id"] = $value["config_type"];
						break;
					}
				}
			}
		}
		return $hash;
	}

	function add_config() {
		$this->authorize('administrator');
		$config = new Config($this->handler);
		$hash['type_id'] = "timecard" . date('YmdHis');
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$hash['type_id'] = $_POST['type_id'];
			$hash['data'] = $config->add($_POST['type_id']);
			$this->error = $config->error;
			if(count($this->error) == 0){
				echo "<script>window.location.href='config.php'</script>";
			}
		}
		return $hash;
	}

	function csv() {
		$field = implode(',', $this->schematize());

		/*1月対応*/
		$syr = $yr = $_GET['year'];
		$mt = $_GET['month'];
		$smt = $mt - 1;

		/*1月(年越し)対応*/
		if(strcmp($mt,'1') == 0){
			$smt = $mt + 11;
			$syr = $yr - 1;
		}

		/*20日締め対応*/
		$query = sprintf("SELECT %s FROM %s WHERE (((timecard_year = %d) AND (timecard_month = %d) AND (timecard_day BETWEEN '21' AND '31')) OR ((timecard_year = %d) AND (timecard_month = %d) AND (timecard_day BETWEEN '01' AND '20')))  AND (owner = '%s') ORDER BY timecard_date", $field, $this->table, $syr, $smt, $yr, $mt, $this->quote($_SESSION['userid']));

		/*$query = sprintf("SELECT %s FROM %s WHERE (timecard_year = %d) AND (timecard_month = %d) AND (owner = '%s') ORDER BY timecard_date", $field, $this->table, $_GET['year'], $_GET['month'], $this->quote($_SESSION['userid']));*/

		$list = $this->fetchAll($query);
		if (is_array($list) && count($list) > 0) {
			$csv = $yr.'年'.$mt.'月'."\n";
			$csv .= '"日付","出社","退社","勤務時間","時間外","休日出勤","備考"'."\n";

			$timestamp = mktime(0, 0, 0, $_GET['month']-1, 21, $_GET['year']);
			/*$timestamp = mktime(0, 0, 0, $smt, 1, $syr);*/
			$lastday = date('t', $timestamp);
			$weekday = date('w', $timestamp);
			$week = array('日', '月', '火', '水', '木', '金', '土');
			foreach ($list as $row) {
				$data[$row['timecard_day']] = $row;
			}
			$sum = 0;
			$sum_over = 0;
			$sum_holiday = 0;
			$dt =0;

			for ($i = 1; $i <= $lastday; $i++) {

				/*21日始まり対応*/
				if($i <= ($lastday - 20)){
					$dt = $i+20;
				}else{
					$dt = $i - ($lastday - 20);
					if($dt == 1){
						$syr = $yr;
						$smt = $mt;
					}
				}
				$checkholiday = $this->checkHoliday($syr, $smt, $dt, $weekday, $lastday);
				if (strlen($data[$dt]['timecard_time']) > 0 && $checkholiday) {
					$array = explode(':', $data[$dt]['timecard_time']);
					$sum_holiday += intval($array[0]) * 60 + intval($array[1]);
				} else{
					$array = explode(':', $data[$dt]['timecard_time']);
					$sum += intval($array[0]) * 60 + intval($array[1]);
				}
				if (strlen($data[$dt]['timecard_timeover']) > 0) {
					$array = explode(':', $data[$dt]['timecard_timeover']);
					$sum_over += intval($array[0]) * 60 + intval($array[1]);
				}
				$csv .= '"'.$smt.'/'.$dt.' ('.$week[$weekday].')","';
				$csv .= $data[$dt]['timecard_open'].'","';
				$csv .= $data[$dt]['timecard_close'].'","';
				if (!$checkholiday) {
					$csv .= $data[$dt]['timecard_time'].'","';
				} else{
					$csv .= '","';
				}
				$csv .= $data[$dt]['timecard_timeover'].'","';
				if ($checkholiday) {
					$csv .= $data[$dt]['timecard_time'].'","';
				} else{
					$csv .= '","';
				}
				$csv .= $data[$dt]['timecard_comment'].'"'."\n";
				$weekday = ($weekday + 1) % 7;
			}
			$csv .= '"勤務時間合計","'.sprintf('%d:%02d', (($sum - ($sum % 60)) / 60), ($sum % 60)).'"'."\n";
			$csv .= '"時間外合計","'.sprintf('%d:%02d', (($sum_over - ($sum_over % 60)) / 60), ($sum_over % 60)).'"'."\n";
			$csv .= '"休日出勤合計","'.sprintf('%d:%02d', (($sum_holiday - ($sum_holiday % 60)) / 60), ($sum_holiday % 60)).'"'."\n";

			header('Content-Disposition: attachment; filename=timecard'.date('Ymd').'.csv');
			header('Content-Type: application/octet-stream; name=timecard'.date('Ymd').'.csv');
			echo mb_convert_encoding($csv, 'SJIS', 'UTF-8');
			exit();
		} else {
			$this->died('データが見つかりません。');
		}

	}

	function group() {
		$this->authorize('administrator', 'manager');
		if ($_GET['group'] <= 0) {
			$_GET['group'] = $_SESSION['group'];
		}
		$data = $this->fetchAll("SELECT userid, realname FROM ".DB_PREFIX."user WHERE user_group = ".intval($_GET['group'])." ORDER BY user_order,id");
		$hash['user'] = array();
		if (is_array($data) && count($data) > 0) {
			foreach ($data as $row) {
				$hash['user'][$row['userid']] = $row['realname'];
			}
			$user = implode("','", array_keys($hash['user']));
			$field = implode(',', $this->schematize());
			if ($_GET['month'] > 1) {
				$query = sprintf("SELECT %s FROM %s WHERE (timecard_year = %d) AND (((timecard_month = %d) AND (timecard_day > 20)) or ((timecard_month = %d) AND (timecard_day < 21))) AND (owner IN ('%s')) ORDER BY timecard_date", $field, $this->table, $_GET['year'], $_GET['month'] - 1, $_GET['month'], $user);
			} else{
				$query = sprintf("SELECT %s FROM %s WHERE (((timecard_year = %d) AND (timecard_month = %d) AND (timecard_day > 20)) or ((timecard_year = %d) AND (timecard_month = %d) AND (timecard_day < 21))) AND (owner IN ('%s')) ORDER BY timecard_date", $field, $this->table, $_GET['year'] - 1, $_GET['month'] + 11,$_GET['year'], $_GET['month'], $user);
			}
			$hash['list'] = $this->fetchAll($query);
		}
		$hash['group'] = $this->findGroup();
		$config = new Config($this->handler);
		$hash['config'] = $config->configure('timecard');
		return $hash;

	}

	function findOwner($owner) {

		if (strlen($owner) > 0) {
			$this->authorize('administrator', 'manager');
			$result = $this->fetchOne("SELECT userid, realname, user_group FROM ".DB_PREFIX."user WHERE userid = '".$this->quote($owner)."'");
			if (count($result) <= 0) {
				$this->died('選択されたユーザーは存在しません。');
			}
		} else {
			$result['userid'] = $_SESSION['userid'];
			$result['realname'] = $_SESSION['realname'];
			$result['user_group'] = $_SESSION['group'];
		}
		$data = $this->fetchAll("SELECT userid, realname FROM ".DB_PREFIX."user WHERE user_group = ".intval($result['user_group'])." ORDER BY user_order,id");
		$user = array();
		if (is_array($data) && count($data) > 0) {
			foreach ($data as $row) {
				$user[$row['userid']] = $row['realname'];
			}
		}
		return array('owner'=>$result, 'user'=>$user);

	}

}

?>
