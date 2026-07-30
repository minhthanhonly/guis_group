<?php

class Timecard extends ApplicationModel {
	var $holidays = array();
	private function isSoumuUser() {
		if (!empty($_SESSION['is_soumu']) && (string)$_SESSION['is_soumu'] === '1') {
			return true;
		}
		if (empty($_SESSION['userid'])) {
			return false;
		}
		$row = $this->fetchOne("SELECT is_soumu FROM " . DB_PREFIX . "user WHERE userid = '" . $this->quote($_SESSION['userid']) . "'");
		return !empty($row['is_soumu']) && (string)$row['is_soumu'] === '1';
	}

	private function authorizeTimecardManage() {
		if ($this->isSoumuUser()) {
			return true;
		}
		return $this->authorize('administrator', 'manager');
	}

	private function authorizeTimecardManageApi() {
		if ($this->isSoumuUser()) {
			return true;
		}
		return $this->authorizeApi('administrator', 'manager');
	}

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
			$holidays[] = date('Y-m-d', strtotime($value['date']));
		}
		return $holidays;
	}

	/**
	 * タイムカード用表示名（共通ロジックは Helper::userDisplayName）。
	 */
	function timecardUserDisplayName($row) {
		return Helper::userDisplayName($row);
	}

	function timecardCsvEscapeCell($value) {
		$s = (string) $value;
		// セル内改行は CSV の行区切りと誤認されるため除去
		$s = str_replace(array("\r\n", "\r", "\n"), ' ', $s);
		return str_replace('"', '""', $s);
	}

	/** CSV 1行（各セルをクォート） */
	private function timecardCsvFormatRow(array $cells) {
		$parts = array();
		foreach ($cells as $cell) {
			$parts[] = '"' . $this->timecardCsvEscapeCell($cell) . '"';
		}
		return implode(',', $parts) . "\n";
	}

	/** 複数申請の区切り（CSV・画面共通） */
	private function timecardRequestFormsSeparator() {
		return '■';
	}

	/** CSV「申請」列: 各申請の先頭に「■」、申請間は半角スペース */
	private function timecardCsvRequestsCell($requestsByDate, $date) {
		if (empty($requestsByDate[$date]) || !is_array($requestsByDate[$date])) {
			return '';
		}
		$sep = $this->timecardRequestFormsSeparator();
		$parts = array();
		foreach ($requestsByDate[$date] as $item) {
			$text = isset($item['display_text']) ? trim((string) $item['display_text']) : '';
			if ($text !== '') {
				$parts[] = $sep . $text;
			}
		}
		return implode(' ', $parts);
	}

	
	function saveHolidays($holidays){
		// $filename = DIR_MODEL . "holidays.txt";
		// $handle = fopen($filename, "w") or die("Unable to open file!");
		// fwrite($handle, $holidays);
		// fclose($handle);
		// $this->redirect();
	}

	function holiday() {
		$this->authorizeTimecardManage();
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
		$hash['requests_by_date'] = $this->buildRequestsByDateForTimecard(
			$hash['owner']['userid'],
			$start->format('Y-m-d'),
			$end->format('Y-m-d')
		);
		return $hash;
	}

	/** タイムカード表示用: 勤怠関連申請を日付ごとにまとめる */
	private function buildRequestsByDateForTimecard($userId, $periodFrom, $periodTo) {
		$types = array('leave', 'outing', 'trip', 'holiday_work', 'overtime', 'attendance_correction');
		$typeIn = "'" . implode("','", array_map(array($this, 'quote'), $types)) . "'";
		// 承認済・処理完了のみ表示（下書き・承認待ち・却下は除外）
		$statuses = array('approved', 'completed');
		$statusIn = "'" . implode("','", array_map(array($this, 'quote'), $statuses)) . "'";
		$uid = $this->quote($userId);
		$table = DB_PREFIX . 'requests';
		// DATE 列に 0000-00-00 があると strict mode で SQL 比較が失敗するため、期間判定は PHP で行う
		$query = "SELECT id, type, status, start_date, end_date, data, created_at,"
			. " approver_id, approved_at, completed_userid, completed_at FROM {$table}"
			. " WHERE user_id = '{$uid}' AND type IN ({$typeIn}) AND status IN ({$statusIn})"
			. " ORDER BY id ASC";
		$rows = $this->fetchAll($query);
		$rows = $this->enrichTimecardRequestRowsForTooltip($rows);
		$byDate = array();
		if (!is_array($rows)) {
			return $byDate;
		}
		foreach ($rows as $row) {
			$status = isset($row['status']) ? (string)$row['status'] : '';
			if ($status !== 'approved' && $status !== 'completed') {
				continue;
			}
			list($start, $end) = $this->resolveRequestPeriodDatesForTimecard($row);
			if ($start === null || !$this->requestPeriodOverlapsTimecardRange($start, $end, $periodFrom, $periodTo)) {
				continue;
			}
			$cur = new DateTime($start);
			$endDt = new DateTime($end ?: $start);
			$item = array(
				'id' => intval($row['id']),
				'type' => $row['type'],
				'label' => $this->requestTypeLabelForTimecard($row['type']),
				'status' => $row['status'],
				'status_label' => $this->requestStatusLabelForTimecard($row['status']),
				'display_text' => $this->requestDisplayTextForTimecard($row),
				'tooltip_lines' => $this->requestTooltipLinesForTimecard($row),
			);
			while ($cur <= $endDt) {
				$d = $cur->format('Y-m-d');
				if ($d >= $periodFrom && $d <= $periodTo) {
					if (!isset($byDate[$d])) {
						$byDate[$d] = array();
					}
					$byDate[$d][] = $item;
				}
				$cur->modify('+1 day');
			}
		}
		return $byDate;
	}

	private function isValidTimecardRequestDate($value) {
		if ($value === null || $value === '') {
			return false;
		}
		$str = substr((string)$value, 0, 10);
		return preg_match('/^\d{4}-\d{2}-\d{2}$/', $str) === 1 && $str !== '0000-00-00';
	}

	private function requestPeriodOverlapsTimecardRange($start, $end, $periodFrom, $periodTo) {
		if (!$this->isValidTimecardRequestDate($start)) {
			return false;
		}
		if (!$this->isValidTimecardRequestDate($end)) {
			$end = $start;
		}
		return $start <= $periodTo && $end >= $periodFrom;
	}

	private function resolveRequestPeriodDatesForTimecard($row) {
		$start = $this->isValidTimecardRequestDate($row['start_date'] ?? null)
			? substr($row['start_date'], 0, 10) : null;
		$end = $this->isValidTimecardRequestDate($row['end_date'] ?? null)
			? substr($row['end_date'], 0, 10) : null;
		if ($start === null && !empty($row['data'])) {
			$data = is_string($row['data']) ? json_decode($row['data'], true) : $row['data'];
			if (is_array($data)) {
				$type = isset($row['type']) ? $row['type'] : '';
				if ($type === 'leave') {
					if (!empty($data['start_datetime'])) {
						$start = substr($data['start_datetime'], 0, 10);
					}
					if (!empty($data['end_datetime'])) {
						$end = substr($data['end_datetime'], 0, 10);
					}
				} elseif (in_array($type, array('outing', 'holiday_work', 'overtime', 'attendance_correction'), true) && !empty($data['date'])) {
					$start = preg_match('/^\d{4}-\d{2}-\d{2}/', $data['date']) ? substr($data['date'], 0, 10) : $data['date'];
					$end = $start;
				} elseif ($type === 'trip') {
					if (!empty($data['start_datetime'])) {
						$start = substr($data['start_datetime'], 0, 10);
					}
					if (!empty($data['end_datetime'])) {
						$end = substr($data['end_datetime'], 0, 10);
					}
				}
			}
		}
		if ($start === null && !empty($row['created_at'])) {
			$start = substr($row['created_at'], 0, 10);
		}
		if ($end === null) {
			$end = $start;
		}
		return array($start, $end);
	}

	private function requestTypeLabelForTimecard($type) {
		switch ($type) {
			case 'leave': return '休暇届';
			case 'outing': return '外出申請書';
			case 'trip': return '出張申請書';
			case 'holiday_work': return '休日勤務申請書';
			case 'overtime': return '遅刻・早退・時間外勤務';
			case 'attendance_correction': return '勤怠打刻修正';
			default: return '申請';
		}
	}

	private function requestStatusLabelForTimecard($status) {
		$map = array(
			'draft' => '下書き',
			'pending' => '承認待ち',
			'approved' => '承認済',
			'rejected' => '却下',
			'completed' => '完了',
		);
		return isset($map[$status]) ? $map[$status] : (string)$status;
	}

	private function decodeRequestDataForTimecard($row) {
		if (empty($row['data'])) {
			return array();
		}
		$decoded = is_string($row['data']) ? json_decode($row['data'], true) : $row['data'];
		return is_array($decoded) ? $decoded : array();
	}

	private function formatTimecardRequestTimeRange($start, $end) {
		$s = trim((string)$start);
		$e = trim((string)$end);
		if ($s !== '' && $e !== '') {
			return $s . '~' . $e;
		}
		if ($s !== '') {
			return $s;
		}
		return $e;
	}

	private function timecardRequestTimeFromData($data, $startKey, $endKey) {
		$start = isset($data[$startKey]) ? trim((string)$data[$startKey]) : '';
		$end = isset($data[$endKey]) ? trim((string)$data[$endKey]) : '';
		if ($start === '' && !empty($data['start_datetime'])) {
			$start = substr((string)$data['start_datetime'], 11, 5);
		}
		if ($end === '' && !empty($data['end_datetime'])) {
			$end = substr((string)$data['end_datetime'], 11, 5);
		}
		return array($start, $end);
	}

	/** タイムカード「申請」列の1行表示テキスト */
	private function requestDisplayTextForTimecard($row) {
		$data = $this->decodeRequestDataForTimecard($row);
		$type = isset($row['type']) ? $row['type'] : '';
		switch ($type) {
			case 'leave':
				$leaveType = isset($data['leave_type']) ? trim((string)$data['leave_type']) : '';
				$subType = '';
				if ($leaveType === '有給休暇' && !empty($data['paid_type'])) {
					$subType = trim((string)$data['paid_type']);
				} elseif ($leaveType === '無給休暇' && !empty($data['unpaid_type'])) {
					$subType = trim((string)$data['unpaid_type']);
				} elseif (!empty($data['paid_type'])) {
					$subType = trim((string)$data['paid_type']);
				} elseif (!empty($data['unpaid_type'])) {
					$subType = trim((string)$data['unpaid_type']);
				}
				if ($subType !== '' && $leaveType !== '') {
					return $subType . ' (' . $leaveType . ')';
				}
				return $subType !== '' ? $subType : $leaveType;
			case 'outing':
				list($st, $et) = $this->timecardRequestTimeFromData($data, 'start_time', 'end_time');
				return '外出: ' . $this->formatTimecardRequestTimeRange($st, $et);
			case 'trip':
				return '出張';
			case 'holiday_work':
				list($st, $et) = $this->timecardRequestTimeFromData($data, 'start_time', 'end_time');
				$text = '休日勤務: ' . $this->formatTimecardRequestTimeRange($st, $et);
				if (!empty($data['break_time'])) {
					$bt = trim((string)$data['break_time']);
					$text .= ', 休憩: ' . (preg_match('/h$/i', $bt) ? $bt : $bt . 'h');
				}
				return $text;
			case 'overtime':
				$purpose = isset($data['purpose']) ? trim((string)$data['purpose']) : '';
				list($st, $et) = $this->timecardRequestTimeFromData($data, 'start_time', 'end_time');
				return $purpose . ': ' . $this->formatTimecardRequestTimeRange($st, $et);
			case 'attendance_correction':
				$ct = isset($data['correction_type']) ? trim((string)$data['correction_type']) : '';
				$time = isset($data['time']) ? trim((string)$data['time']) : '';
				return '打刻修正: ' . $ct . ' : ' . $time;
			default:
				return $this->requestTypeLabelForTimecard($type);
		}
	}

	private function enrichTimecardRequestRowsForTooltip($rows) {
		if (!is_array($rows) || !count($rows)) {
			return $rows;
		}
		$user_ids = array();
		foreach ($rows as $row) {
			if (!empty($row['approver_id'])) {
				$user_ids[$row['approver_id']] = true;
			}
			if (!empty($row['completed_userid'])) {
				$user_ids[$row['completed_userid']] = true;
			}
		}
		$user_map = array();
		if (count($user_ids)) {
			$in = "'" . implode("','", array_map(array($this, 'quote'), array_keys($user_ids))) . "'";
			$users = $this->fetchAll(
				"SELECT userid, realname, lastname, firstname, lastname_after_married FROM " . DB_PREFIX . "user WHERE userid IN ($in)"
			);
			if (is_array($users)) {
				foreach ($users as $u) {
					if (!empty($u['userid'])) {
						$user_map[$u['userid']] = Helper::userDisplayName($u);
					}
				}
			}
		}
		foreach ($rows as &$row) {
			$row['approver_realname'] = !empty($row['approver_id']) && isset($user_map[$row['approver_id']])
				? $user_map[$row['approver_id']] : '';
			$row['completed_realname'] = !empty($row['completed_userid']) && isset($user_map[$row['completed_userid']])
				? $user_map[$row['completed_userid']] : '';
		}
		unset($row);
		return $rows;
	}

	private function formatTimecardRequestDatetime($value) {
		if ($value === null || $value === '') {
			return '';
		}
		$ts = strtotime((string)$value);
		if ($ts === false) {
			return trim((string)$value);
		}
		return date('Y/m/d H:i', $ts);
	}

	private function timecardTooltipLine($label, $value) {
		if ($value === null || $value === '') {
			return null;
		}
		return $label . ': ' . trim((string)$value);
	}

	private function appendTimecardRequestWorkflowLines($row, $add) {
		$approverName = !empty($row['approver_realname'])
			? $row['approver_realname']
			: (!empty($row['approver_id']) ? $row['approver_id'] : '');
		$approvedAt = $this->formatTimecardRequestDatetime($row['approved_at'] ?? '');
		if ($approverName !== '' || $approvedAt !== '') {
			$add($this->timecardTooltipLine('承認者', $approverName));
			$add($this->timecardTooltipLine('承認日時', $approvedAt));
		}
		$completedName = !empty($row['completed_realname'])
			? $row['completed_realname']
			: (!empty($row['completed_userid']) ? $row['completed_userid'] : '');
		$completedAt = $this->formatTimecardRequestDatetime($row['completed_at'] ?? '');
		if ($completedName !== '' || $completedAt !== '') {
			$add($this->timecardTooltipLine('処理完了者', $completedName));
			$add($this->timecardTooltipLine('処理完了日時', $completedAt));
		}
	}

	private function formatTimecardHolidayWorkBreakTime($bt) {
		$bt = trim((string)$bt);
		if ($bt === '') {
			return '';
		}
		$labels = array('0.5' => '0.5h', '1' => '1h', '1.5' => '1.5h', '2' => '2h', '2.5' => '2.5h', '3' => '3h', '3.5' => '3.5h', '4' => '4h');
		if (isset($labels[$bt])) {
			return $labels[$bt];
		}
		$minuteToHour = array(30 => '0.5h', 60 => '1h', 90 => '1.5h', 120 => '2h', 150 => '2.5h', 180 => '3h', 210 => '3.5h', 240 => '4h');
		if (ctype_digit($bt) && isset($minuteToHour[(int)$bt])) {
			return $minuteToHour[(int)$bt];
		}
		return $bt . 'h';
	}

	/** ホバー用ツールチップ（申請詳細） */
	private function requestTooltipLinesForTimecard($row) {
		$data = $this->decodeRequestDataForTimecard($row);
		$type = isset($row['type']) ? $row['type'] : '';
		$lines = array();
		$lines[] = $this->requestTypeLabelForTimecard($type);
		$lines[] = '状態: ' . $this->requestStatusLabelForTimecard($row['status'] ?? '');
		$lines[] = '申請ID: ' . intval($row['id']);

		$add = function ($line) use (&$lines) {
			if ($line !== null && $line !== '') {
				$lines[] = $line;
			}
		};

		if ($type === 'leave') {
			$add($this->timecardTooltipLine('期間（開始）', $data['start_datetime'] ?? ''));
			$add($this->timecardTooltipLine('期間（終了）', $data['end_datetime'] ?? ''));
			if (isset($data['days']) && $data['days'] !== '') {
				$add($this->timecardTooltipLine('日間', $data['days']));
			}
			$add($this->timecardTooltipLine('休暇種別', $data['leave_type'] ?? ''));
			$add($this->timecardTooltipLine('有給休暇', $data['paid_type'] ?? ''));
			$add($this->timecardTooltipLine('無給休暇', $data['unpaid_type'] ?? ''));
			$add($this->timecardTooltipLine('事由', $data['reason'] ?? ''));
			$add($this->timecardTooltipLine('注記', $data['note'] ?? ''));
		} elseif ($type === 'outing') {
			$add($this->timecardTooltipLine('日付', $data['date'] ?? ''));
			list($st, $et) = $this->timecardRequestTimeFromData($data, 'start_time', 'end_time');
			$add($this->timecardTooltipLine('開始時刻', $st));
			$add($this->timecardTooltipLine('終了時刻', $et));
			$add($this->timecardTooltipLine('行先', $data['destination'] ?? ''));
			$add($this->timecardTooltipLine('事由', $data['reason'] ?? ''));
			$add($this->timecardTooltipLine('注記', $data['note'] ?? ''));
		} elseif ($type === 'trip') {
			$add($this->timecardTooltipLine('期間（開始）', $data['start_datetime'] ?? ''));
			$add($this->timecardTooltipLine('期間（終了）', $data['end_datetime'] ?? ''));
			if (isset($data['days']) && $data['days'] !== '') {
				$add($this->timecardTooltipLine('日間', $data['days']));
			}
			$add($this->timecardTooltipLine('行先', $data['destination'] ?? ''));
			$add($this->timecardTooltipLine('事由', $data['reason'] ?? ''));
			$add($this->timecardTooltipLine('注記', $data['note'] ?? ''));
		} elseif ($type === 'holiday_work') {
			$add($this->timecardTooltipLine('日付', $data['date'] ?? ''));
			list($st, $et) = $this->timecardRequestTimeFromData($data, 'start_time', 'end_time');
			$add($this->timecardTooltipLine('開始時刻', $st));
			if (isset($data['break_time']) && $data['break_time'] !== '') {
				$add($this->timecardTooltipLine('休憩時間', $this->formatTimecardHolidayWorkBreakTime($data['break_time'])));
			}
			$add($this->timecardTooltipLine('終了時刻', $et));
			$add($this->timecardTooltipLine('事由', $data['reason'] ?? ''));
			$add($this->timecardTooltipLine('注記', $data['note'] ?? ''));
		} elseif ($type === 'overtime') {
			$add($this->timecardTooltipLine('日付', $data['date'] ?? ''));
			list($st, $et) = $this->timecardRequestTimeFromData($data, 'start_time', 'end_time');
			$add($this->timecardTooltipLine('開始時刻', $st));
			$add($this->timecardTooltipLine('終了時刻', $et));
			if (!empty($data['purpose'])) {
				$p = is_array($data['purpose']) ? implode('、', $data['purpose']) : $data['purpose'];
				$add($this->timecardTooltipLine('用途', $p));
			}
			$add($this->timecardTooltipLine('事由', $data['reason'] ?? ''));
			$add($this->timecardTooltipLine('注記', $data['note'] ?? ''));
		} elseif ($type === 'attendance_correction') {
			$add($this->timecardTooltipLine('日付', $data['date'] ?? ''));
			$add($this->timecardTooltipLine('時間', $data['time'] ?? ''));
			$add($this->timecardTooltipLine('区分', $data['correction_type'] ?? ''));
			$add($this->timecardTooltipLine('事由', $data['reason'] ?? ''));
			$add($this->timecardTooltipLine('注記', $data['note'] ?? ''));
		}

		$this->appendTimecardRequestWorkflowLines($row, $add);

		return array_values(array_filter($lines, function ($l) {
			return $l !== null && $l !== '';
		}));
	}

	function getConfigStatusByUser($userid){
		$config = new Config($this->handler);
		return $config->getConfigTimeCardByUser($userid);
	}

	/*API*/
	function get_holiday(){
		$this->authorizeTimecardManageApi();
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

	/* Public — no auth required, only returns upcoming holidays */
	function get_holiday_upcoming(){
		$days = isset($_GET['days']) ? max(1, min(365, intval($_GET['days']))) : 90;
		$limit = isset($_GET['limit']) ? max(1, min(365, intval($_GET['limit']))) : 30;
		$today = date('Y-m-d');
		$future = date('Y-m-d', strtotime("+{$days} days"));
		$query = sprintf(
			"SELECT id, date, name FROM groupware_holiday WHERE date >= '%s' AND date <= '%s' ORDER BY date ASC LIMIT %d",
			$today, $future, $limit
		);
		$hash['list'] = $this->fetchAll($query);
		return $hash;
	}

	/*API*/
	function get_lastest_holiday(){
		$this->authorizeTimecardManageApi();
		$query = "SELECT * FROM groupware_holiday ORDER BY date DESC LIMIT 1";
		$hash['list'] = $this->fetchAll($query);
		return $hash;
	}

	/*API*/
	function add_holiday_list(){
		$this->authorizeTimecardManageApi();
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
		$this->authorizeTimecardManageApi();
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
		$this->authorizeTimecardManageApi();
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
		$this->authorizeTimecardManageApi();
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


	function delete_timecard() {
		$this->authorizeTimecardManageApi();
		$id = $_POST['id'];
		if(!$id){
			$hash['status'] = 'error';
			$hash['message_code'] = 15;
			return $hash;
		}
		$query = sprintf("DELETE FROM %stimecard WHERE id = %s", DB_PREFIX, $id);
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
		$userEsc = $this->quote($userName);
		$dateEsc = $this->quote($date02);

		// 備考のみ先に保存された行も含め、当日レコードを timecard_date で特定
		$query = sprintf(
			"SELECT id, timecard_open FROM %stimecard WHERE timecard_date = '%s' AND owner = '%s'",
			DB_PREFIX,
			$dateEsc,
			$userEsc
		);
		$data = $this->fetchOne($query);
		if (!empty($data['id'])) {
			$timecard_id = (int)$data['id'];
			$existingOpen = isset($data['timecard_open']) ? trim((string)$data['timecard_open']) : '';
			if ($existingOpen !== '' && $existingOpen !== '00:00') {
				$hash['status'] = 'error';
				$hash['message_code'] = '既に出社済みです。';
				return $hash;
			}
			$sql02 = sprintf(
				"UPDATE %stimecard SET timecard_open = '%s', timecard_originalopen = '%s' WHERE id = %d",
				DB_PREFIX,
				$this->quote($hour),
				$this->quote($hour),
				$timecard_id
			);
			$this->query($sql02);
			$hash['status'] = 'success';
			$hash['message_code'] = '完了しました。';
			$hash['timecard_id'] = $timecard_id;
			$hash['timecard_open'] = $hour;
		} else {
			$sql = sprintf(
				"INSERT INTO %stimecard (`timecard_year`, `timecard_month`, `timecard_day`, `timecard_date`, `owner`, `created`, `timecard_open`, `timecard_originalopen`) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')",
				DB_PREFIX,
				$thisYear,
				$thisMonth,
				$thisDay,
				$dateEsc,
				$userEsc,
				$this->quote($date),
				$this->quote($hour),
				$this->quote($hour)
			);
			$this->query($sql);
			$timecard_id = (int)$this->insertid();
			if ($timecard_id !== 0) {
				$hash['status'] = 'success';
				$hash['timecard_id'] = $timecard_id;
				$hash['timecard_open'] = $hour;
				$hash['message_code'] = '完了しました。';
			} else {
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

	function generateStatistic(){
		$date = date('Y-m-d', strtotime('-6 month'));
		$start = date('Y-m-d', strtotime(date('Y-m', strtotime($date)) . '-' . TIMECARD_START_DATE));
		$query = sprintf("SELECT * FROM %stimecard WHERE timecard_date >= DATE('%s')", DB_PREFIX, $start);
		$data = $this->fetchAll($query);
		$result = array();
		$result_user = array();

		// Allユーザー
		foreach($data as $row) {
			if(TIMECARD_START_DATE == 1){
				$yearMonth = date('Y-n', strtotime($row['timecard_date']));
			} else{
				if(date('j', strtotime($row['timecard_date'])) < TIMECARD_START_DATE){
					$yearMonth = date('Y-n', strtotime($row['timecard_date']));
				} else{
					$yearMonth = date('Y-n', strtotime($row['timecard_date'] . ' +1 month'));
				}
			}
			if(!isset($result[$yearMonth])) {
				$result[$yearMonth] = array();
			}
			$result[$yearMonth][] = $row;
		}

		$delete_statistic_user = sprintf("DELETE FROM %sstatistic WHERE type = 'timecard_user' AND scope = 'monthly'", DB_PREFIX);
		$this->query($delete_statistic_user);
		$delete_statistic = sprintf("DELETE FROM %sstatistic WHERE type = 'timecard_all' AND scope = 'monthly'", DB_PREFIX);
		$this->query($delete_statistic);

		foreach($result as $key => $value){
			$sum = $this->statistic($value);
			$sumString = json_encode($sum);
			$name = $key;
			if(TIMECARD_START_DATE == 1){
				$time = $key.'-'.TIMECARD_START_DATE;
			} else{
				$time = date('Y-n', strtotime($key . ' -1 month')) . '-'.TIMECARD_START_DATE;
			}

			$insert_statistic = sprintf("INSERT INTO %sstatistic (type, scope, time, value, name, updated) VALUES ('%s', '%s', '%s', '%s', '%s', '%s')", DB_PREFIX, 'timecard_all', 'monthly', $time, $sumString, $name, date('Y-m-d H:i:s'));
			$this->query($insert_statistic);
		}


		// ユーザーごと
		foreach($data as $row) {
			if(TIMECARD_START_DATE == 1){
				$yearMonth = date('Y-n', strtotime($row['timecard_date']));
			} else{
				if(date('j', strtotime($row['timecard_date'])) < TIMECARD_START_DATE){
					$yearMonth = date('Y-n', strtotime($row['timecard_date']));
				} else{
					$year = date('Y', strtotime($row['timecard_date']));
					$month = date('n', strtotime($row['timecard_date']));
					$month = $month + 1;
					if($month > 12){
						$year = $year + 1;
						$month = 1;
					}
					$yearMonth = $year . '-' . $month;
				}
			}
			if(!isset($result_user[$row['owner']])) {
				$result_user[$row['owner']] = array();
			}
			if(!isset($result_user[$row['owner']][$yearMonth])) {
				$result_user[$row['owner']][$yearMonth] = array(); 
			}
			$result_user[$row['owner']][$yearMonth][] = $row;
		}
		
		
		foreach($result_user as $key => $value){
			foreach($value as $key2 => $value2){

				$sum = $this->statistic($value2);
				$name = $key2;
				if(TIMECARD_START_DATE == 1){
					$time = $key2.'-'.TIMECARD_START_DATE;
				} else{
					$time = date('Y-n', strtotime($key2 . ' -1 month')) . '-'.TIMECARD_START_DATE;
				}
				$sumString = json_encode($sum);
				
				$insert_statistic = sprintf("INSERT INTO %sstatistic (type, scope, time, value, userid, name, updated) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s')", DB_PREFIX, 'timecard_user', 'monthly', $time, $sumString, $key, $name, date('Y-m-d H:i:s'));
				$this->query($insert_statistic);
			}
		}

		
		$hash['status'] = 'success';
		$hash['message_code'] = '完了しました。';
		return $hash;
	}

	function getStatistic(){
		$this->authorizeTimecardManageApi();
		$scope = $_GET['scope'];
		$time = date('Y-m-d');
		$duration = isset($_GET['duration']) ? $_GET['duration'] : '6';
		$start = date('Y-m-d', strtotime('-' . $duration . ' month'));
		$start = date('Y-m-d', strtotime(date('Y-m', strtotime($start)) . '-' . TIMECARD_START_DATE));
		$userid = isset($_GET['userid']) && $_GET['userid'] != '' ? $_GET['userid'] : '';
		$query = sprintf("SELECT * FROM %sstatistic WHERE scope = '%s' AND time >= DATE('%s') AND time <= DATE('%s')", DB_PREFIX, $scope, $start, $time);
		if($userid != ''){
			$query .= " AND userid = '$userid'";
			$query .= " AND type = 'timecard_user'";
		} else{
			$query .= " AND type = 'timecard_all'";
		}
		
		$data = $this->fetchAll($query);
		
		if(count($data) > 0){	
			$hash['status'] = 'success';
			$hash['message_code'] = '完了しました。';
			$hash['list'] = $data;
		} else{
			$hash['status'] = 'error';
			$hash['message_code'] = 'データが見つかりません。';
		}
		return $hash;
	}

	function statistic($data) {
		$sum = array();
		$sum['timecard_time'] = 0;
		$sum['timecard_timeover'] = 0;
		$sum['timecard_timeholiday'] = 0;
		
		if (is_array($data) && count($data) > 0) {
			foreach ($data as $key => $row) {
				$date = date('Y-m-d', strtotime($row['timecard_date']));
				$checkWeekendAndHoliday = $this->checkWeekendAndHoliday($date);
				if ($checkWeekendAndHoliday) {
					if (strlen($row['timecard_close']) > 0) {
						$array = explode(':', $row['timecard_time']);
						$sum['timecard_timeholiday'] += intval($array[0]) * 60 + intval($array[1]);
					}
				} else{
					if (strlen($row['timecard_close']) > 0) {
						$array = explode(':', $row['timecard_time']);
						$sum['timecard_time'] += intval($array[0]) * 60 + intval($array[1]);
						$array = explode(':', $row['timecard_timeover']);
						$sum['timecard_timeover'] += intval($array[0]) * 60 + intval($array[1]);
					}
				}
			}
		}
		$sum['timecard_time'] = sprintf('%d:%02d', (($sum['timecard_time'] - ($sum['timecard_time'] % 60)) / 60), ($sum['timecard_time'] % 60));
		$sum['timecard_timeover'] = sprintf('%d:%02d', (($sum['timecard_timeover'] - ($sum['timecard_timeover'] % 60)) / 60), ($sum['timecard_timeover'] % 60));
		$sum['timecard_timeholiday'] = sprintf('%d:%02d', (($sum['timecard_timeholiday'] - ($sum['timecard_timeholiday'] % 60)) / 60), ($sum['timecard_timeholiday'] % 60));
		return $sum;
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

		if($open == '00:00'){
			$open = '';
		}
		if($close == '00:00'){
			$close = '';
		}
		$column = ['timecard_open', 'timecard_close', 'timecard_comment'];
		$array = [];
		foreach($_POST as $key => $value){
			if(in_array($key, $column)){
				$array[$key] = htmlspecialchars(stripcslashes(trim($value)), ENT_QUOTES, 'UTF-8');
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
			$values = implode("','", array_values($array));
			$values = "'" . $values . "'";

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

	function edit_timecard_note(){
		$date = $_POST['date'];
		$userid = $_POST['userid'];

		$column = ['timecard_comment'];
		$array = [];
		foreach($_POST as $key => $value){
			if(in_array($key, $column)){
				$array[$key] = htmlspecialchars(stripcslashes(trim($value)), ENT_QUOTES, 'UTF-8');
			}
		}
		//check if the timecard is already exists
		$query = sprintf("SELECT id FROM %stimecard WHERE timecard_date = '%s' AND owner = '%s'", DB_PREFIX, $date, $userid);
		$data = $this->fetchAll($query);
		$owner = $userid;
		$array['owner'] = $owner;

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
			$keys = implode(',', array_keys($array));
			$values = implode("','", array_values($array));
			$values = "'" . $values . "'";

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
		// foreach($data as $key => $value){
		// 	if($key == 'timecard_comment'){
		// 		$data[$key] = html_entity_decode($value);
		// 	}
		// }	
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
		$date = date('Y-m-d', strtotime($date));
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
					$array = $this->sumAdd($row['timecard_open'], $row['timecard_close'], date('Y-m-d', strtotime($row['timecard_date'])), $row['owner']);
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
		$this->authorizeTimecardManage();
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
		$this->authorizeTimecardManage();
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
		$mt = $_GET['month'];
		$yr = $_GET['year'];
		$member = isset($_GET['member']) ? $_GET['member'] : $_SESSION['userid'];
		$start = DateTime::createFromFormat('Y-m-d', "$yr-$mt-" . TIMECARD_START_DATE);
		if(TIMECARD_START_DATE != 1){
			$start->modify('-1 month');
		}
		$end = clone $start; // Clone $start to create a new DateTime object
		$end->modify('+1 month'); // Set the end date to one month after the start date
		$end->modify('-1 day'); // Subtract one day to get the correct end date

		$countDays = $end->diff($start)->days;
		$countDays = $countDays + 1;


		$query = sprintf(
			"SELECT %s FROM %s WHERE STR_TO_DATE(timecard_date, '%%Y-%%m-%%d') BETWEEN '%s' AND '%s' AND owner = '%s' ORDER BY timecard_date",
			$field,
			$this->table,
			$start->format('Y-m-d'), // Định dạng ngày bắt đầu
			$end->format('Y-m-d'),
			$member
		);
		
		$list = $this->fetchAll($query);
		if (is_array($list) && count($list) > 0) {
			$ownerRow = $this->fetchOne("SELECT userid, realname, lastname, firstname, lastname_after_married FROM ".DB_PREFIX."user WHERE userid = '".$this->quote($member)."'");
			$displayName = $this->timecardUserDisplayName(is_array($ownerRow) ? $ownerRow : array('realname' => $member));

			$requestsByDate = $this->buildRequestsByDateForTimecard(
				$member,
				$start->format('Y-m-d'),
				$end->format('Y-m-d')
			);

			$csv = $yr.'年'.$mt.'月'."\n";
			$csv .= '"氏名","'.$this->timecardCsvEscapeCell($displayName).'"'."\n";
			$csv .= '"日付","出社","退社","勤務時間","時間外","休日出勤","申請","備考"'."\n";

			$week = array('日', '月', '火', '水', '木', '金', '土');
			$data = array();
			foreach ($list as $row) {
				$dkey = isset($row['timecard_date']) ? substr((string) $row['timecard_date'], 0, 10) : '';
				if ($dkey !== '') {
					$data[$dkey] = $row;
				}
			}
			$sum = 0;
			$sum_over = 0;
			$sum_holiday = 0;
			$dt =0;

			for ($i = 0; $i < $countDays; $i++) {
				$dateCurrent = clone $start;
				$dateCurrent->modify('+'.$i.' day');
				$date = $dateCurrent->format('Y-m-d');
				$dt = $dateCurrent->format('j');
				$smt = $dateCurrent->format('n');
				$syr = $dateCurrent->format('Y');
				$weekday = $dateCurrent->format('w');

				$checkholiday = $this->checkWeekendAndHoliday($date);
				$dayRow = isset($data[$date]) && is_array($data[$date]) ? $data[$date] : array();
				$tcOpen = isset($dayRow['timecard_open']) ? $dayRow['timecard_open'] : '';
				$tcClose = isset($dayRow['timecard_close']) ? $dayRow['timecard_close'] : '';
				$tcTime = isset($dayRow['timecard_time']) ? $dayRow['timecard_time'] : '';
				$tcOver = isset($dayRow['timecard_timeover']) ? $dayRow['timecard_timeover'] : '';
				$tcComment = isset($dayRow['timecard_comment']) ? $dayRow['timecard_comment'] : '';

				if (strlen($tcTime) > 0 && $checkholiday) {
					$array = explode(':', $tcTime);
					$sum_holiday += intval($array[0]) * 60 + intval($array[1]);
				} elseif (strlen($tcTime) > 0) {
					$array = explode(':', $tcTime);
					$sum += intval($array[0]) * 60 + intval($array[1]);
				}
				if (strlen($tcOver) > 0) {
					$array = explode(':', $tcOver);
					$sum_over += intval($array[0]) * 60 + intval($array[1]);
				}
				$csv .= $this->timecardCsvFormatRow(array(
					$smt . '/' . $dt . ' (' . $week[$weekday] . ')',
					$tcOpen,
					$tcClose,
					$checkholiday ? '' : $tcTime,
					$tcOver,
					$checkholiday ? $tcTime : '',
					$this->timecardCsvRequestsCell($requestsByDate, $date),
					$tcComment,
				));
			}
			$csv .= '"勤務時間合計","'.sprintf('%d:%02d', (($sum - ($sum % 60)) / 60), ($sum % 60)).'"'."\n";
			$csv .= '"時間外合計","'.sprintf('%d:%02d', (($sum_over - ($sum_over % 60)) / 60), ($sum_over % 60)).'"'."\n";
			$csv .= '"休日出勤合計","'.sprintf('%d:%02d', (($sum_holiday - ($sum_holiday % 60)) / 60), ($sum_holiday % 60)).'"'."\n";

			header('Content-Disposition: attachment; filename='.$member.'_timecard'.date('Ymd').'.csv');
			header('Content-Type: application/octet-stream; name='.$member.'_timecard'.date('Ymd').'.csv');
			echo mb_convert_encoding($csv, 'SJIS', 'UTF-8');
			exit();
		} else {
			$this->died('データが見つかりません。');
		}

	}

	function group() {
		$this->authorizeTimecardManage();
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


		$data = $this->fetchAll("SELECT userid, realname, lastname, firstname, lastname_after_married FROM ".DB_PREFIX."user WHERE user_group = ".intval($_GET['group'])." ORDER BY user_order,id");
		$hash['user'] = array();
		if (is_array($data) && count($data) > 0) {
			foreach ($data as $row) {
				$hash['user'][$row['userid']] = $this->timecardUserDisplayName($row);
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
			foreach($hash['list'] as $key => $value){
				$hash['list'][$key]['holiday'] = $this->checkWeekendAndHoliday($value['timecard_date']);
			}
		}
		$hash['group'] = $this->findGroup();
		$config = new Config($this->handler);
		$hash['config'] = $config->configure('timecard');
		return $hash;

	}

	function findOwner($owner) {
		if (strlen($owner) > 0) {
			if($owner != $_SESSION['userid']){
				$this->authorizeTimecardManage();
			}
			$result = $this->fetchOne("SELECT userid, realname, user_group, lastname, firstname, lastname_after_married FROM ".DB_PREFIX."user WHERE userid = '".$this->quote($owner)."'");
			if (count($result) <= 0) {
				$this->died('選択されたユーザーは存在しません。');
			}
		} else {
			$result = $this->fetchOne("SELECT userid, realname, user_group, lastname, firstname, lastname_after_married FROM ".DB_PREFIX."user WHERE userid = '".$this->quote($_SESSION['userid'])."'");
			if (!is_array($result) || count($result) <= 0) {
				$result = array(
					'userid' => $_SESSION['userid'],
					'realname' => $_SESSION['realname'],
					'user_group' => $_SESSION['group'],
					'lastname' => '',
					'firstname' => '',
					'lastname_after_married' => ''
				);
			}
		}
		$result['display_name'] = $this->timecardUserDisplayName($result);
		$data = $this->fetchAll("SELECT userid, realname, lastname, firstname, lastname_after_married FROM ".DB_PREFIX."user WHERE user_group = ".intval($result['user_group'])." ORDER BY user_order,id");
		$user = array();
		if (is_array($data) && count($data) > 0) {
			foreach ($data as $row) {
				$user[$row['userid']] = $this->timecardUserDisplayName($row);
			}
		}
		return array('owner'=>$result, 'user'=>$user);

	}

	function findOwnerApi($owner) {
		if (strlen($owner) > 0) {
			if($owner != $_SESSION['userid']){
				$this->authorizeTimecardManageApi();
			}
			$result = $this->fetchOne("SELECT userid, realname, user_group, lastname, firstname, lastname_after_married FROM ".DB_PREFIX."user WHERE userid = '".$this->quote($owner)."'");
			if (count($result) <= 0) {
				$this->diedApi('選択されたユーザーは存在しません。');
			}
		} else {
			$result = $this->fetchOne("SELECT userid, realname, user_group, lastname, firstname, lastname_after_married FROM ".DB_PREFIX."user WHERE userid = '".$this->quote($_SESSION['userid'])."'");
			if (!is_array($result) || count($result) <= 0) {
				$result = array(
					'userid' => $_SESSION['userid'],
					'realname' => $_SESSION['realname'],
					'user_group' => $_SESSION['group'],
					'lastname' => '',
					'firstname' => '',
					'lastname_after_married' => ''
				);
			}
		}
		$result['display_name'] = $this->timecardUserDisplayName($result);
		$data = $this->fetchAll("SELECT userid, realname, lastname, firstname, lastname_after_married FROM ".DB_PREFIX."user WHERE user_group = ".intval($result['user_group'])." ORDER BY user_order,id");
		$user = array();
		if (is_array($data) && count($data) > 0) {
			foreach ($data as $row) {
				$user[$row['userid']] = $this->timecardUserDisplayName($row);
			}
		}
		return array('owner'=>$result, 'user'=>$user);

	}


	
}

?>
