<?php

class Timecard extends ApplicationModel {
	var $holidays = array();
	function __construct() {
		$this->table = DB_PREFIX.'timecard';
		$this->schema = array(
			'id'=> array('except'=>array('search')),
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
		$this->connect();
		$this->holidays = $this->getHolidays();
	}

	function getHolidays(){
		$query = "SELECT * FROM groupware_holiday ORDER BY date DESC";
		$hash['list'] = $this->fetchAll($query);
		$holidays = [];
		foreach ($hash['list'] as $key => $value) {
			$holidays[] = $value['date'];
		}
		return $holidays;
	}

	
	function saveHolidays($holidays){
		// $filename = DIR_MODEL . "holidays.txt";
		// $handle = fopen($filename, "w") or die("Unable to open file!");
		// fwrite($handle, $holidays);
		// fclose($handle);
		// $this->redirect();
	}

	function holiday() {
		$this->authorize('administrator', 'manager');
		$hash['empty'] = '';
	}

	function index() {
	}

	/*API*/
	function timecardlist() {
		/*オーナー特定*/
		$hash = $this->findOwnerApi($_GET['member']);
		/*リスト取得*/
		$field = implode(',', $this->schematize());
		
		/*1月対応*/
		$mt = $_GET['month'];
		$yr = $_GET['year'];
	
		$start = DateTime::createFromFormat('Y-m-d', "$yr-$mt-" . TIMECARD_START_DATE);
		if(TIMECARD_START_DATE != 1){
			$start->modify('-1 month');
		}
		$end = clone $start; // Clone $start to create a new DateTime object
		$end->modify('+1 month'); // Set the end date to one month after the start date
		$end->modify('-1 day'); // Subtract one day to get the correct end date

		/*20日締め対応*/
		$query = sprintf(
			"SELECT %s FROM %s WHERE STR_TO_DATE(timecard_date, '%%Y-%%m-%%d') BETWEEN '%s' AND '%s' AND owner = '%s' ORDER BY timecard_date",
			$field,
			$this->table,
			$start->format('Y-m-d'), // Định dạng ngày bắt đầu
			$end->format('Y-m-d'),
			$hash['owner']['userid']
		);
	
		$hash['list'] = $this->fetchAll($query);

		for($i = 0; $i < count($hash['list']); $i++){
			//check holiday
			if($this->checkWeekendAndHoliday($hash['list'][$i]['timecard_date'])){
				$hash['list'][$i]['holiday'] = 1;
			}else{
				$hash['list'][$i]['holiday'] = 0;
			}
		}

		$hash['isSameUser'] = $hash['owner']['userid'] == $_SESSION['userid'];
		$hash['holidays'] = [];

		
		foreach($this->holidays as $key => $value){
			$dayPart = explode('-', $value);
			if(($dayPart[0] ==  $start->format('Y') || $dayPart[0] == $end->format('Y')) 
			&& ($dayPart[1] == $start->format('m') || $dayPart[1] == $end->format('m'))){
				$hash['holidays'][] = $value;
			}
		}


		$hash['config'] = $this->getConfigStatusByUser($hash['owner']['userid']);
		return $hash;
	}

	function getConfigStatusByUser($userid){
		$config = new Config($this->handler);
		return $config->getConfigTimeCardByUser($userid);
	}

	/*API*/
	function get_holiday(){
		$this->authorizeApi('administrator', 'manager');
		$startDate = $_GET['start_date'];
		$endDate = $_GET['end_date'];
		if(!$startDate && !$endDate){
			$query = "SELECT * FROM groupware_holiday ORDER BY date DESC";
		} else{
			$query = "SELECT * FROM groupware_holiday WHERE date BETWEEN '$startDate' AND '$endDate' ORDER BY date DESC";
		}
		$hash['list'] = $this->fetchAll($query);
		return $hash;
	}

	/*API*/
	function get_lastest_holiday(){
		$this->authorizeApi('administrator', 'manager');
		$query = "SELECT * FROM groupware_holiday ORDER BY date DESC LIMIT 1";
		$hash['list'] = $this->fetchAll($query);
		return $hash;
	}

	/*API*/
	function add_holiday_list(){
		$this->authorizeApi('administrator', 'manager');
		$json = file_get_contents('php://input');
		$holidayList = json_decode($json, true);
		$holidayList = $holidayList['holidayList'];
		$query = "INSERT INTO groupware_holiday (date, name, is_api) VALUES ";
		foreach($holidayList as $holiday){
			if(isset($holiday['name']) && $holiday['name'] == ''){
				continue;
			}
			$query .= "('".$holiday['date']."', '".$holiday['name']."', 1),";
		}
		$query = rtrim($query, ',');
		$response = $this->update_query($query);
		if($response > 0){
			$hash['status'] = 'success';
			$hash['message_code'] = 11;
		} else{
			$hash['status'] = 'error';
			$hash['message_code'] = 12;
		}
		return $hash;
	}

	/*API*/
	function add_holiday() {
		$this->authorizeApi('administrator', 'manager');
		$date = $_POST['date'];
		$holiday = $_POST['name'];
		$date = date('Y-m-d', strtotime($date));
		// check if date already exists
		$result = $this->fetchCount("groupware_holiday", "WHERE date = '$date'", 'id');
		if($result){
			$hash['status'] = 'error';
			$hash['message_code'] = 10;
			return $hash;
		}

		$query = sprintf("INSERT INTO groupware_holiday (date, name) VALUES ('%s', '%s')", $date, $holiday);
		$response = $this->query($query);
		if($response){
			$hash['status'] = 'success';
			$hash['message_code'] = 11;
		}
		return $hash;
	}

	/*API*/
	function delete_holiday() {
		$this->authorizeApi('administrator', 'manager');
		$id = $_POST['id'];
		if(!$id){
			$hash['status'] = 'error';
			$hash['message_code'] = 15;
			return $hash;
		}

		$query = sprintf("DELETE FROM groupware_holiday WHERE id = '%s'", $id);
		$response = $this->update_query($query);
		if($response){
			$hash['status'] = 'success';
			$hash['message_code'] = 13;
			return $hash;
		}
		$hash['status'] = 'error';
		$hash['message_code'] = 14;
		return $hash;
	}

	/*API*/
	function edit_holiday() {
		$this->authorizeApi('administrator', 'manager');
		$date_old = $_POST['date_old'];
		$id = $_POST['id'];
		if(!$id){
			$hash['status'] = 'error';
			$hash['message_code'] = 15;
			return $hash;
		}
		$date = $_POST['date'];
		$holiday = $_POST['name'];
		$date = date('Y-m-d', strtotime($date));
		$date_old = date('Y-m-d', strtotime($date_old));
		
		// check if date already exists
		if($date != $date_old){
			$result = $this->fetchCount("groupware_holiday", "WHERE date = '$date'", 'id');
			if($result){
				$hash['status'] = 'error';
				$hash['message_code'] = 10;
				return $hash;
			}
		}

		$query = sprintf(
			"UPDATE groupware_holiday SET name = '%s', date = '%s', is_api = '' WHERE id = '%s'",
			$holiday,
			$date,
			$id
		);
		$response = $this->query($query);
		if($response){
			$hash['status'] = 'success';
			$hash['message_code'] = 12;
		}
		return $hash;
	}

	/*API*/
	function checkin() {
		/*時間取得*/
		$hash = array();
		$userName = $_POST['owner'];
		
		if($userName == ''){
			$userName = $_SESSION['userid'];
		}
		if($userName == ''){
			$hash['status'] = 'error';
			$hash['message_code'] = 'ユーザーが見つかりません。';
			return $hash;
		}
		$thisDay = date('j');
		$thisMonth = date('n');
		$thisYear = date('Y');
		$date = date("Y-m-d H:i:s");
		$date02 = date("Y-m-d");
		$hour = date("H:i");

		//check if user already checkin
		$query = sprintf("SELECT id FROM %stimecard WHERE timecard_year='%s' and timecard_day='%s' and timecard_month='%s' and owner= '%s'", DB_PREFIX, $thisYear, $thisDay, $thisMonth, $userName);
		$data = $this->fetchOne($query);
		$timecard_id = 0;
		if(count($data) > 0){
			$timecard_id = $data['id'];
			if ($timecard_id != 0) {
				$sql02 = "UPDATE `groupware_timecard` SET timecard_open = '$hour', timecard_originalopen = '$hour' WHERE id = $timecard_id";
				$this->query($sql02);
				$hash['status'] = 'success';
				$hash['message_code'] = '完了しました。';
			} else{
				$hash['status'] = 'error';
				$hash['message_code'] = 'エラーが発生しました。';
			}
		} else{
			$sql = "INSERT INTO `groupware_timecard` (`timecard_year`, `timecard_month`, `timecard_day`, `timecard_date`, `owner`, `created`, `timecard_open` , `timecard_originalopen`) 
			VALUES ('$thisYear', '$thisMonth', '$thisDay', '$date02' , '$userName', '$date', '$hour', '$hour');";
			$this->query($sql);
			$timecard_id = $this->insertid();
			if ($timecard_id != 0) {
				$hash['status'] = 'success';
				$hash['timecard_id'] = $timecard_id;
				$hash['timecard_open'] = $hour;
				$hash['message_code'] = '完了しました。';
			}
			else{
				$hash['status'] = 'error';
				$hash['message_code'] = 'エラーが発生しました。';
			}
		}

		return $hash;
	}

	/*API*/
	function checkout() {
        $hash = array();
		$userName = $_POST['owner'];
		
		if($userName == ''){
			$userName = $_SESSION['userid'];
		}
		if($userName == ''){
			$hash['status'] = 'error';
			$hash['message_code'] = 'ユーザーが見つかりません。';
			return $hash;
		}
		$id = $_POST['id'];
		$open = $_POST['open'];

		if($userName == '' || $id == '' || $open == ''){
			$hash['status'] = 'error';
			$hash['message_code'] = 'ユーザーが見つかりません。';
			return $hash;
		}
		$hour = date("H:i");
		$result = $this->sumAdd($open, $hour, date("Y-m-d"), $userName);
		$query = sprintf("UPDATE %stimecard SET timecard_close = '%s', timecard_originalclose = '%s', timecard_time = '%s', timecard_timeover = '%s', timecard_timeinterval = '%s', update_time='%s' WHERE id = %s", DB_PREFIX, $hour, $hour, $result["timecard_time"], $result["timecard_timeover"], $result["timecard_timeinterval"], date('Y-m-d H:i:s'), $id);
		$data = $this->update_query($query);
		if($data > 0){
			$hash['status'] = 'success';
			$hash['message_code'] = '完了しました。';
			$hash['timecard_id'] = $id;
			$hash['timecard_close'] = $hour;
			$hash['timecard_open'] = $open;
			$hash['timecard_time'] = $result["timecard_time"];
			$hash['timecard_timeover'] = $result["timecard_timeover"];
			$hash['timecard_timeinterval'] = $result["timecard_timeinterval"];
		} else{
			$hash['status'] = 'error';
			$hash['message_code'] = 'エラーが発生しました。';
		}
		return $hash;
	}

	function recalculateApi(){
		/*オーナー特定*/
		$hash = $this->findOwnerApi($_GET['member']);
		/*リスト取得*/
		$field = implode(',', $this->schematize());
		
		/*1月対応*/
		$mt = $_GET['month'];
		$yr = $_GET['year'];
	
		$start = DateTime::createFromFormat('Y-m-d', "$yr-$mt-" . TIMECARD_START_DATE);
		if(TIMECARD_START_DATE != 1){
			$start->modify('-1 month');
		}
		$end = clone $start; // Clone $start to create a new DateTime object
		$end->modify('+1 month'); // Set the end date to one month after the start date
		$end->modify('-1 day'); // Subtract one day to get the correct end date

		/*20日締め対応*/
		$query = sprintf(
			"SELECT %s FROM %s WHERE STR_TO_DATE(timecard_date, '%%Y-%%m-%%d') BETWEEN '%s' AND '%s' AND owner = '%s' ORDER BY timecard_date",
			$field,
			$this->table,
			$start->format('Y-m-d'), // Định dạng ngày bắt đầu
			$end->format('Y-m-d'),
			$hash['owner']['userid']
		);
	
		$hash['list'] = $this->fetchAll($query);
		$hash['list'] = $this->recalculate($hash['list']);
		$hash['status'] = 'success';
		$hash['message_code'] = '再計算しました';
		return $hash;
	}

	function edit_timecard(){
		$date = $_POST['date'];
		$userid = $_POST['userid'];
		$open = $_POST['timecard_open'];
		$close = $_POST['timecard_close'];
		$comment = $_POST['timecard_comment'];

		$column = ['timecard_open', 'timecard_close', 'timecard_comment'];
		$array = [];
		foreach($_POST as $key => $value){
			if(in_array($key, $column)){
				$array[$key] = $value;
			}
		}
		//check if the timecard is already exists
		$query = sprintf("SELECT id FROM %stimecard WHERE timecard_date = '%s' AND owner = '%s'", DB_PREFIX, $date, $userid);
		$data = $this->fetchAll($query);
		$owner = $userid;
		$array['owner'] = $owner;

		if($array['timecard_close'] != ''){
			$result = $this->sumAdd($array['timecard_open'], $array['timecard_close'], $date, $userid);
			$array['timecard_time'] = $result['timecard_time'];
			$array['timecard_timeover'] = $result['timecard_timeover'];
			$array['timecard_timeinterval'] = $result['timecard_timeinterval'];
		} else{
			$array['timecard_time'] = '';
			$array['timecard_timeover'] = '';
			$array['timecard_timeinterval'] = '';
		}
		
		if(count($data) > 0){
			$array['updated'] = date('Y-m-d H:i:s');
			$array['editor'] = $_SESSION['userid'];
			$id = $data[0]['id'];
			$term = '';
			foreach($array as $key => $value){
				$term .= $key . " = '" . $value . "', ";
			}
			$term = rtrim($term, ', ');
			$query = sprintf("UPDATE %stimecard SET %s WHERE id = %s", DB_PREFIX, $term , $id);
			$response = $this->query($query);
		} else{
			$dateStr = date('Y-m-d', strtotime($date));
			$array['timecard_year'] = date('Y', strtotime($dateStr));
			$array['timecard_month'] = date('n', strtotime($dateStr));
			$array['timecard_day'] = date('j', strtotime($dateStr));
			$array['timecard_date'] = $date;
			$array['created'] = date('Y-m-d H:i:s');
			$array['timecard_originalopen'] = $open;
			$array['timecard_originalclose'] = $close;
			$keys = implode(',', array_keys($array));
			$values = implode(',', array_values($array));
			$query = sprintf("INSERT INTO %stimecard (%s) VALUES (%s)", DB_PREFIX, $keys, $values);
			$response = $this->query($query);
		}	
		if($response){
			$hash['status'] = 'success';
			$hash['message_code'] = '更新しました';
		}else{
			$hash['status'] = 'error';
			$hash['message_code'] = '更新に失敗しました';
		}
		return $hash;
	}

	function get_timecard_by_id() {
		$date = $_POST['date'];
		$userid = $_POST['userid'];
		if(!$date || !$userid){
			$hash['status'] = 'error';
			$hash['message_code'] = 'データが見つかりません。';
			return $hash;
		}
		$query = sprintf("SELECT * FROM %stimecard WHERE timecard_date = '%s' AND owner = '%s'", DB_PREFIX, $date, $userid);
		$data = $this->fetchOne($query);
		if(count($this->error) > 0){
			$hash['status'] = 'error';
			$hash['message_code'] = $this->error;
			return $hash;
		}
		$hash['data'] = $data;
		$hash['status'] = 'success';
		return $hash;
	}


	function getConfigStatus($member = ''){
		$config = new Config($this->handler);
		if($member){
			return $config->getConfigTimeCardByUser($member);
		}
		return $config->getConfigTimeCardByUser($_SESSION['userid']);
	}

	function checkWeekendAndHoliday($date) {
		$date = date($date);
		$timestamp = strtotime($date);
		$weekday = date('w', $timestamp);

		if ($weekday == 0 || $weekday == 6 || in_array($date, $this->holidays)) {
			return true;
		} 
		return false;
	}

	function checkHoliday($year, $month, $day) {
		$date = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
		if (in_array($date, $this->holidays)) {
			return true;
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

	function sumAdd($open, $close, $date = '', $owner = '') {
		$open = $this->minute($open);
		$close = $this->minute($close);
		$close2 = $close;
		$sum = 0;
		$intervalsum = 0;
		$over = 0;
		$temp1 = 0;

		/*コンフィグ情報取得*/
		$status = $this->getConfigStatus($owner);

		/*定時：開始時間取得*/

		$status['open'] = intval($status['openhour']) * 60 + intval($status['openminute']); // giờ bắt đầu qui định
		if ($open < $status['open']) {
			$open = $status['open'];
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
		$checkHoliday = $this->checkWeekendAndHoliday($date);
		/*勤務時間計算*/
		if ($checkHoliday) {
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

		$checkHoliday = $this->checkWeekendAndHoliday($syr, $smt, $sdt, $weekday, $lastday);
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
			foreach ($data as $key => $row) {
				if (strlen($row['timecard_close']) > 0) {
					$array = $this->sumAdd($row['timecard_open'], $row['timecard_close'], $row['timecard_date'], $row['owner']);
					$this->record($array, $hash, $row['timecard_year'], $row['timecard_month'], $row['timecard_day']);
					$data[$key]['timecard_time'] = $array['timecard_time'];
					$data[$key]['timecard_timeover'] = $array['timecard_timeover'];
					$data[$key]['timecard_timeinterval'] = $array['timecard_timeinterval'];
				}
			}
		}
		return $data;

	}

	function config() {
		$this->authorize('administrator', 'manager');
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
		$this->authorize('administrator', 'manager');
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
				$checkholiday = $this->checkWeekendAndHoliday($syr, $smt, $dt, $weekday, $lastday);
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

		$mt = $_GET['month'];
		$yr = $_GET['year'];
	
		$start = DateTime::createFromFormat('Y-m-d', "$yr-$mt-" . TIMECARD_START_DATE);
		if(TIMECARD_START_DATE != 1){
			$start->modify('-1 month');
		}
		$end = clone $start; // Clone $start to create a new DateTime object
		$end->modify('+1 month'); // Set the end date to one month after the start date
		$end->modify('-1 day'); // Subtract one day to get the correct end date


		$data = $this->fetchAll("SELECT userid, realname FROM ".DB_PREFIX."user WHERE user_group = ".intval($_GET['group'])." ORDER BY user_order,id");
		$hash['user'] = array();
		if (is_array($data) && count($data) > 0) {
			foreach ($data as $row) {
				$hash['user'][$row['userid']] = $row['realname'];
			}
			$user = implode("','", array_keys($hash['user']));
			$field = implode(',', $this->schematize());
			$query = sprintf("SELECT %s FROM %s WHERE STR_TO_DATE(timecard_date, '%%Y-%%m-%%d') BETWEEN '%s' AND '%s' AND (owner IN ('%s')) ORDER BY timecard_date", 
				$field, 
				$this->table, 
				$start->format('Y-m-d'),
				$end->format('Y-m-d'),
				$user
			);
			
			$hash['list'] = $this->fetchAll($query);
		}
		$hash['group'] = $this->findGroup();
		$config = new Config($this->handler);
		$hash['config'] = $config->configure('timecard');
		return $hash;

	}

	function findOwner($owner) {
		if (strlen($owner) > 0) {
			if($owner != $_SESSION['userid']){
				$this->authorize('administrator', 'manager');
			}
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

	function findOwnerApi($owner) {
		if (strlen($owner) > 0) {
			if($owner != $_SESSION['userid']){
				$this->authorizeApi('administrator', 'manager');
			}
			$result = $this->fetchOne("SELECT userid, realname, user_group FROM ".DB_PREFIX."user WHERE userid = '".$this->quote($owner)."'");
			if (count($result) <= 0) {
				$this->diedApi('選択されたユーザーは存在しません。');
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


	function excel() {
		if($_SESSION['authority'] == 'member'){
			if($_GET['userid'] != $_SESSION['userid']){
				$this->died('権限がありません。');
			}
		} else {
			$this->authorize('editor','manager','administrator');
		}
		require_once "class.writeexcel_biffwriter.inc.php";
		require_once "class.writeexcel_formula.inc.php";
		require_once "class.writeexcel_format.inc.php";
		require_once "class.writeexcel_olewriter.inc.php";
		require_once "class.writeexcel_worksheet.inc.php";
		require_once "class.writeexcel_workbook.inc.php";
		$fname = tempnam("/tmp", "timecard.xlsx");
		$options = array('temp_dir' => $fname);
		$workbook = new writeexcel_workbook($options);
		# Some common formats
		# Create a border format
		$border1 =& $workbook->addformat();
		$border1->set_color('black');
		$border1->set_bold();
		$border1->set_size(15);
		$border1->set_pattern(0x1);
		$border1->set_fg_color('white');
		$border1->set_border_color('black');

		$border1->set_align('center');
		$border1->set_align('vcenter');
		$border1->set_merge(); # This is the key feature

	
		$border2 =& $workbook->addformat();
		$border2->set_color('black');
		$border2->set_bold();
		$border2->set_size(15);
		$border2->set_pattern(0x1);
		$border2->set_fg_color('white');
		$border2->set_border_color('black');

		$border2->set_align('center');
		$border2->set_align('vcenter');
		$border2->set_merge(); # This is the key feature
		
		$border3 =& $workbook->addformat();
		$border3->set_color('black');
		$border3->set_bold();

		$border3->set_pattern(0x1);
		$border3->set_fg_color('white');
		$border3->set_border_color('black');
		
		$border3->set_align('center');
		$border3->set_align('vcenter');
		$border3->set_merge(); # This is the key feature 

	
		$border4 =& $workbook->addformat();
		$border4->set_color('black');
		$border4->set_bold();
	
		$border4->set_pattern(0x1);
		$border4->set_fg_color('white');
		$border4->set_border_color('black');
		
		$border4->set_align('center');
		$border4->set_align('vcenter');
		$border4->set_merge(); # This is the key feature
		
		$border5 =& $workbook->addformat();
		$border5->set_fg_color('white');
		$border5->set_border_color('black');
		$border5->set_align('center');
		$border5->set_align('vcenter');
		$border5->set_merge();
		
		/*$border6 =& $workbook->addformat();
		$border6->set_color('red');
		$border6->set_align('center');
		$border6->set_border_color('black');*/
		
		$center  =& $workbook->addformat(array('align' => 'center','font'=>"Arial"));
		$heading =& $workbook->addformat(array('align' => 'center', 'bold' => 1,'font'=>"Arial"));
		$border =& $workbook->addformat(array('align' => 'center', 'border'   => 1));
		$border_over =& $workbook->addformat(array('align' => 'center', 'border'   => 1, 'color' => 'red'));
		$border_sat =& $workbook->addformat(array('align' => 'center', 'border'   => 1, 'fg_color'   => 41));
		$border_sun =& $workbook->addformat(array('align' => 'center', 'border'   => 1,'fg_color'   => 45));
		$heading_border =& $workbook->addformat(array('align' => 'center', 'border'   => 1,'fg_color'   => 'black' , 'color'=>'white'));

		$query = "SELECT realname FROM groupware_user WHERE userid='".$_GET['userid']."'";
		$real_name = $this->fetchAll($query);
		$real_name = $real_name[0]['realname'];



		$worksheet1 = $workbook->addworksheet($_GET['userid']);

		$worksheet1->set_column(0, 5, 12);

		$worksheet1->write(0, 0, mb_convert_encoding($real_name, 'SJIS', 'auto'),$border1);
		$worksheet1->write(0, 1,'',$border2);
		$worksheet1->write(0, 2,'',$border2);
		$worksheet1->write(0, 3,'',$border2);
		$worksheet1->write(0, 4,'',$border2);
		$worksheet1->write(0, 5,'',$border2);
		
		$worksheet1->write(1, 0, mb_convert_encoding($_GET['month'].'/'.$_GET['year'], 'SJIS', 'auto'), $border3);
		$worksheet1->write(1, 1,'',$border4);
		$worksheet1->write(1, 2,'',$border4);
		$worksheet1->write(1, 3,'',$border4);
		$worksheet1->write(1, 4,'',$border4);
		$worksheet1->write(1, 5,'',$border4);
		$worksheet1->write(2, 0,'',$border4);
		$worksheet1->write(2, 1,'',$border4);
		$worksheet1->write(2, 2,'',$border4);
		$worksheet1->write(2, 3,'',$border4);
		$worksheet1->write(2, 4,'',$border4);
		$worksheet1->write(2, 5,'',$border4);
		
		$worksheet1->write(3, 0, mb_convert_encoding('Date', 'SJIS', 'auto'), $heading_border);
		$worksheet1->write(3, 1, mb_convert_encoding('In', 'SJIS', 'auto'), $heading_border);
		$worksheet1->write(3, 2, mb_convert_encoding('Out', 'SJIS', 'auto'), $heading_border);
		$worksheet1->write(3, 3, mb_convert_encoding('Worktime', 'SJIS', 'auto'), $heading_border);
		$worksheet1->write(3, 4, mb_convert_encoding('Overtime', 'SJIS', 'auto'), $heading_border);
		$worksheet1->write(3, 5, mb_convert_encoding('Note', 'SJIS', 'auto'), $heading_border);
		
		
		$query = sprintf("SELECT * FROM groupware_timecard WHERE (timecard_year = %d) AND (((timecard_month = %d) AND (timecard_day BETWEEN '0' AND '31')))  AND (owner = '%s') ORDER BY timecard_date", $_GET['year'],$_GET['month'],  $_GET['userid']);
		$list = $this->fetchAll($query);
		$field = $this->schematize();
		
		if (is_array($list) && count($list) > 0) {
		
			$timestamp = mktime(0, 0, 0, $_GET['month'], 1, $_GET['year']);
			$lastday = date('t', $timestamp);
			$weekday = date('w', $timestamp);
			$week = array('日', '月', '火', '水', '木', '金', '土');
			$j=1;
			foreach ($list as $row) {
				if ($temp == $row['timecard_day'])
				{
					$data[$row['timecard_day']][$j] = $row;
					$j++;
				} else {
					$data[$row['timecard_day']][0] = $row;
					$j=1;
				}
				$temp = $row['timecard_day'];
			}

			$sum = 0;
			$sum_over = 0;
			$mt =$_GET['month'];
			$dt = 1;
			
			for ($i = 1; $i <= $lastday; $i++) {
				if(!isset($data[$i])){
					continue;
				}
				$timeaday = count($data[$i]);
				if ($timeaday == 0) {
					if (strlen($data[$dt]['timecard_time']) > 0) {
						$array = explode(':', $data[$dt]['timecard_time']);
						$sum += intval($array[0]) * 60 + intval($array[1]);
					}
					if (strlen($data[$dt]['timecard_timeover']) > 0) {
						$array = explode(':', $data[$dt]['timecard_timeover']);
						$sum_over += intval($array[0]) * 60 + intval($array[1]);
					}
					$border_style = $border;
					if($weekday == 0){
						$border_style = $border_sun;
					}elseif($weekday == 6){
						$border_style = $border_sat;
					}
					
				
					$worksheet1->write($dt + 3, 0, $i.'/'.$mt.' ('.$week[$weekday].')', $border_style);
					$worksheet1->write($dt + 3, 1, mb_convert_encoding($data[$i][$j]['timecard_open'], 'SJIS', 'auto'), $border_style);
					$worksheet1->write($dt + 3, 2, mb_convert_encoding($data[$i][$j]['timecard_close'], 'SJIS', 'auto'), $border_style);
					$worksheet1->write($dt + 3, 3, mb_convert_encoding($data[$i][$j]['timecard_time'], 'SJIS', 'auto'), $border_style);
					$worksheet1->write($dt + 3, 4, mb_convert_encoding($data[$i][$j]['timecard_timeover'], 'SJIS', 'auto'),$border_style);
					$worksheet1->write($dt + 3, 5, mb_convert_encoding($data[$i][$j]['timecard_comment'], 'SJIS', 'auto'), $border_style);
					$dt++;
				}
				$temp_sum_over = 0;
				/* calculate timeover first */
				for($j= 0; $j < $timeaday; $j++) {
					$array = explode(':', $data[$i][$j]['timecard_timeover']);
					$temp_sum_over += intval($array[0]) * 60 + intval($array[1]);
				}
				
				for ($j=0; $j < $timeaday; $j++ ) {

					if (strlen($data[$i][$j]['timecard_time']) > 0) {
						$array = explode(':', $data[$i][$j]['timecard_time']);
						$sum += intval($array[0]) * 60 + intval($array[1]);
					}
					if ($j == ($timeaday-1)){
						if ($temp_sum_over > 29){
							$temp_sum_over = ($temp_sum_over - ($temp_sum_over%30));
							$sum_over += $temp_sum_over ;
						}	
					}	
					$border_style = $border;
					if($weekday == 0){
						$border_style = $border_sun;
					}elseif($weekday == 6){
						$border_style = $border_sat;
					}
					if($timeaday == 1) 
						$worksheet1->write($dt + 3, 0, $i.'/'.$mt.' ('.$week[$weekday].')', $border_style);
					elseif($j == 0 && $timeaday > 1) $worksheet1->write($dt + 3, 0, $i.'/'.$mt.' ('.$week[$weekday].')', $border5);
					elseif($j >= 1 && $timeaday > 1) $worksheet1->write($dt + 3, 0,'', $border5);
					
					$worksheet1->write($dt + 3, 1, mb_convert_encoding($data[$i][$j]['timecard_open'], 'SJIS', 'auto'), $border_style);
					$worksheet1->write($dt + 3, 2, mb_convert_encoding($data[$i][$j]['timecard_close'], 'SJIS', 'auto'), $border_style);
					$worksheet1->write($dt + 3, 3, mb_convert_encoding($data[$i][$j]['timecard_time'], 'SJIS', 'auto'), $border_style);
					if ($temp_sum_over > 29)
					    $worksheet1->write($dt + 3, 4, mb_convert_encoding($data[$i][$j]['timecard_timeover'], 'SJIS', 'auto'),$border_over);
					else
						$worksheet1->write($dt + 3, 4, mb_convert_encoding($data[$i][$j]['timecard_timeover'], 'SJIS', 'auto'),$border_style);
					$worksheet1->write($dt + 3, 5, mb_convert_encoding($data[$i][$j]['timecard_comment'], 'SJIS', 'auto'), $border_style);
					$dt++;
					
				}
				$weekday = ($weekday + 1) % 7;
			}
			
			$worksheet1->write($lastday + 4 , 0, mb_convert_encoding('Total worktime', 'SJIS', 'auto'), $heading_border);
			$worksheet1->write($lastday + 4 , 1, mb_convert_encoding(sprintf('%d:%02d', (($sum - ($sum % 60)) / 60), ($sum % 60)), 'SJIS', 'auto'), $border);
			$worksheet1->write($lastday + 5 , 0, mb_convert_encoding('Total overtime', 'SJIS', 'auto'),$heading_border);
			$worksheet1->write($lastday + 5 , 1, mb_convert_encoding(sprintf('%d:%02d', (($sum_over - ($sum_over % 60)) / 60), ($sum_over % 60)), 'SJIS', 'auto'), $border);
		
			$workbook->close();
			header('Content-Type: application/x-msexcel; name=timecard_'.$_GET['userid'].'_'.$_GET['year'].'_'.$_GET['month'].'.xlsx');
			header('Content-Disposition: inline; filename=timecard_'.$_GET['userid'].'_'.$_GET['year'].'_'.$_GET['month'].'.xlsx');
			$fh=fopen($fname, "rb");
			fpassthru($fh);
			unlink($fname);
			exit();
			
		} else {
			$this->died('No data.');
		}

	}

}

?>
