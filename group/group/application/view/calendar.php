<?php
/*
 * Copyright(c) 2009 limitlink,Inc. All Rights Reserved.
 * http://limitlink.jp/
 * 文字コード UTF-8
 */

class Calendar{
	var $holidays = array();
	function Calendar() {
		$this->holidays = $this->getHolidays();
	}

	function initialize($data) {
		
		if (!$data['schedule_year']) {
			$data['schedule_year'] = $_GET['year'];
		}
		if (!$data['schedule_month']) {
			$data['schedule_month'] = $_GET['month'];
		}
		if (!$data['schedule_day']) {
			$data['schedule_day'] = $_GET['day'];
		}
		if (!$data['schedule_time']) {
			$data['beginhour'] = date('G');
			$data['beginminute'] = floor(date('i')/10)*10;
		} else {
			list($data['beginhour'], $data['beginminute']) = explode(':', $data['schedule_time']);
		}
		if (!$data['schedule_endtime']) {
			if (($data['beginhour'] + 1) > 23) {
				$data['endhour'] = 23;
				$data['endminute'] = 50;
			} else {
				$data['endhour'] = $data['beginhour'] + 1;
				$data['endminute'] = floor(date('i')/10)*10;
			}
		} else {
			list($data['endhour'], $data['endminute']) = explode(':', $data['schedule_endtime']);
		}
		if (strlen($data['schedule_begin']) > 0) {
			$timestamp = strtotime($data['schedule_begin']);
		} else {
			$timestamp = time();
		}
		$data['beginyear'] = date('Y', $timestamp);
		$data['beginmonth'] = date('n', $timestamp);
		$data['beginday'] = date('j', $timestamp);
		if (strlen($data['schedule_end']) > 0) {
			$timestamp = strtotime($data['schedule_end']);
			$data['endmonth'] = date('n', $timestamp);
			$data['endday'] = date('j', $timestamp);
		} else {
			if (($data['beginmonth'] + 1) > 12) {
				$data['endmonth'] = 12;
				$data['endday'] = 31;
			} else {
				$data['endmonth'] = $data['beginmonth'] + 1;
				$data['endday'] = $data['beginday'];
			}
		}
		if (!isset($data['schedule_type'])) {
			$data['schedule_type'] = 0;
		}
		if (!$data['schedule_repeat']) {
			$data['schedule_repeat'] = 'everyweek';
			$data['schedule_everyweek'] = 1;
		}
		if (!isset($data['schedule_level'])) {
			$data['schedule_level'] = 1;
		}
		return $data;

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

	function prepare($data, $year, $month, $day, $endyear, $endmonth, $endday) {

		$result = array();
		if (is_array($data) && count($data) > 0) {
			foreach ($data as $row) {
				$row['schedule_time'] = $this->tick($row['schedule_allday'], $row['schedule_time'], $row['schedule_endtime']);
				if ($row['schedule_type'] == 1) {
					if (strtotime($row["schedule_begin"]) < mktime(0, 0, 0, $month, $day, $year)) {
						$begin = mktime(0, 0, 0, $month, $day, $year);
					} else {
						$begin = strtotime($row["schedule_begin"]);
					}
					if (strtotime($row["schedule_end"]) > mktime(0, 0, 0, $endmonth, $endday, $endyear)) {
						$end = mktime(0, 0, 0, $endmonth, $endday, $endyear);
					} else {
						$end = strtotime($row["schedule_end"]);
					}
					$count = intval(($end - $begin) / (24*60*60));
					$timestamp = $begin;
					if ($row['schedule_repeat'] == 'everyday') {
						for ($i = 0; $i <= $count; $i++) {
							$result[date('j', $timestamp)][] = $row;
							$timestamp = strtotime('+1 day', $timestamp);
							if ($timestamp > $end) {
								break;
							}
						}
					} elseif ($row['schedule_repeat'] == 'everyweekday') {
						for ($i = 0; $i <= $count; $i++) {
							$weekday = date('w', $timestamp);
							if ($weekday >= 1 && $weekday <= 5 && !in_array(date('Y-m-d', $timestamp), $this->holidays)) {
								$result[date('j', $timestamp)][] = $row;
							}
							$timestamp = strtotime('+1 day', $timestamp);
							if ($timestamp > $end) {
								break;
							}
						}
					} elseif ($row['schedule_repeat'] == 'everyweek') {
						for ($i = 0; $i <= $count; $i++) {
							$weekday = date('w', $timestamp);
							if ($row['schedule_everyweek'] == $weekday) {
								$result[date('j', $timestamp)][] = $row;
								$timestamp = strtotime('+1 week', $timestamp);
							} else {
								$timestamp = strtotime('+1 day', $timestamp);
							}
							if ($timestamp > $end) {
								break;
							}
						}
					} elseif ($row['schedule_repeat'] == 'everymonth') {
						if ($row['schedule_everymonth'] == 'lastday') {
							$key = date('t', $timestamp);
						} else {
							$key = intval($row['schedule_everymonth']);
						}
						$everymonth = mktime(0, 0, 0, date('n', $timestamp), $key, date('Y', $timestamp));
						if ($everymonth >= $begin && $everymonth <= $end && date('n', $everymonth) == date('n', $timestamp)) {
							$result[$key][] = $row;
						}
					}
				} else {
					$result[$row['schedule_day']][] = $row;
				}
			}
		}
		return $result;

	}

	function timetable($data, $beginhour, $endhour, $member = '', $empty = '') {

		if (is_array($data) && count($data) > 0) {
			foreach ($data as $row) {
				$class = '';
				if ($_GET['id'] > 0 && $_GET['id'] == $row['id']) {
					$class = ' class="current"';
				}
				$parameter = $this->parameter($_GET['year'], $_GET['month'], $_GET['day'], array('id'=>$row['id'], 'member'=>$member));
				if ($row['schedule_allday'] == 1) {
					$colspan = ($endhour - $beginhour + 1) * 6;
					if ($this->permitted($row, 'public')) {
						$allday .= sprintf('<tr><td colspan="%s"><a%s href="view.php%s"%s>%s</a></td></tr>', $colspan, $class, $parameter, $this->share($row), $row['schedule_title']);
					} else {
						$allday .= sprintf('<tr><td colspan="%s"><div class="private">%s</div></td></tr>', $colspan, $row['schedule_name']);
					}
				} else {
					list($hour, $minute) = explode(':', $row['schedule_time']);
					$begin = $hour * 6 + floor($minute / 10);
					list($hour, $minute) = explode(':', $row['schedule_endtime']);
					$end = $hour * 6 + floor($minute / 10);
					if ($begin <= $end && $end <= 144) {
						$id = count($result);
						if (count($result) > 0) {
							foreach ($result as $key => $array) {
								if ($begin >= $array['lasttime']) {
									$id = $key;
									break;
								}
							}
						}
						if ($result[$id]['lasttime'] <= 0) {
							$result[$id]['lasttime'] = $beginhour * 6;
						}
						$colspan = $begin - $result[$id]['lasttime'];
						if ($colspan > 0) {
							$result[$id]['chart'] .= '<td colspan="'.$colspan.'">&nbsp;</td>';
						}
						$colspan = $end - $begin;
						if ($this->permitted($row, 'public')) {
							$result[$id]['chart'] .= sprintf('<td colspan="%s"><a%s href="view.php%s"%s>%s</a></td>', $colspan, $class, $parameter, $this->share($row), $row['schedule_title']);
						} else {
							$result[$id]['chart'] .= sprintf('<td colspan="%s"><div class="private">%s</div></td>', $colspan, $row['schedule_name']);
						}
						$result[$id]['lasttime'] = $end;
					}
				}
			}
		}
		for ($i = $beginhour; $i <= $endhour; $i++) {
			if ($i == $endhour) {
				$header .= '<th colspan="6" style="border-right:0px;">'.$i.'</th>';
			} else {
				$header .= '<th colspan="6">'.$i.'</th>';
			}
		}
		echo '<table class="timetable" cellspacing="0" border="0"><tr>'.$header.'</tr>';
		if (is_array($result) && count($result) > 0) {
			foreach ($result as $row) {
				if (strlen($row['chart']) > 0) {
					$colspan = ($endhour + 1) * 6 - $row['lasttime'];
					if ($colspan > 0) {
						$row['chart'] .= '<td colspan="'.$colspan.'">&nbsp;</td>';
					}
					echo '<tr>'.$row['chart'].'</tr>';
				}
			}
		} elseif (strlen($allday) <= 0) {
			echo '<tr><td colspan="'.(($endhour - $beginhour + 1) * 6).'" class="timetableempty">'.$empty.'&nbsp;</td></tr>';
		}
		echo $allday.'</table>';

	}

	function dated($data) {

		if ($data['schedule_type'] == 1) {
			$begin = date('Y年n月d日', strtotime($data['schedule_begin']));
			$end = date('Y年n月d日', strtotime($data['schedule_end']));
			if ($data['schedule_repeat'] == 'everyday') {
				$string = '毎日';
			} elseif ($data['schedule_repeat'] == 'everyweekday') {
				$string = '毎日(平日)';
			} elseif ($data['schedule_repeat'] == 'everyweek') {
				$week = array('日', '月', '火', '水', '木', '金', '土');
				$string = '毎週'.$week[$data['schedule_everyweek']].'曜日';
			} elseif ($data['schedule_repeat'] == 'everymonth') {
				if ($data['schedule_everymonth'] == 'lastday') {
					$string = '毎月末日';
				} else {
					$string = '毎月'.intval($data['schedule_everymonth']).'日';
				}
			}
			$string .= '&nbsp;('.$begin.'-'.$end.')';
		} else {
			$string = intval($data['schedule_year']).'年'.intval($data['schedule_month']).'月'.intval($data['schedule_day']).'日';
		}
		return $string;

	}

	function tick($allday, $time, $endtime = null, $separator = '-') {

		if ($allday != 1) {
			$array = explode(':', $time);
			$string = sprintf('%d:%02d', intval($array[0]), intval($array[1]));
			if ($endtime) {
				$array = explode(':', $endtime);
				$string .= sprintf($separator.'%d:%02d', intval($array[0]), intval($array[1]));
			}
			return $string.'&nbsp;';
		}

	}

	function parameter($year = 0, $month = 0, $day = 0, $parameter = null) {

		if ($year > 0) {
			$array['year'] = intval($year);
		}
		if ($month > 0) {
			$array['month'] = intval($month);
		}
		if ($day > 0) {
			$array['day'] = intval($day);
		}
		if ($_GET['group'] > 0) {
			$array['group'] = intval($_GET['group']);
		}
		if (strlen($_GET['member']) > 0) {
			$array['member'] = htmlspecialchars($_GET['member'], ENT_QUOTES, 'UTF-8');
		}
		if ($_GET['facility'] > 0) {
			$array['facility'] = intval($_GET['facility']);
		}
		if (is_array($parameter) && count($parameter) > 0 && is_array($array)) {
			$array = $parameter + $array;
		}
		if (is_array($array) && count($array) > 0) {
			foreach ($array as $key => $value) {
				if (strlen($value) > 0) {
					$result[] = $key.'='.htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
				}
			}
		}
		if (is_array($result) && count($result) > 0) {
			return '?'.implode('&', $result);
		}

	}

	function style($year, $month, $day, $weekday, $lastday = 31) {

		$date = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
		if ($day > 0 && $day <= $lastday && $date == date('Y-m-d')) {
			$class = ' class="today"';
		} elseif ($weekday == 0) {
			$class = ' class="sunday"';
		} elseif ($weekday == 6) {
			$class = ' class="saturday"';
		} else {
			$class = '';
		}
		if ($day > 0 && $day <= $lastday && in_array($date, $this->holidays)) {
			$class = ' class="holiday"';
		}
		return $class;

	}

	function share($data) {

		if ($data['schedule_level'] == 1) {
			$class = '';
		} else {
			$class = ' class="share"';
		}
		return $class;

	}

	function permitted($data, $level = 'public') {

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

	function selector($name, $user, $group, $owner) {

		if (is_array($user) && count($user) > 0) {
			$string .= '<optgroup label="ユーザー">';
			foreach ($user as $key => $value) {
				if ($key == $owner) {
					$selected = ' selected="selected"';
				} else {
					$selected = '';
				}
				$string .= '<option value="'.$key.'"'.$selected.'>'.$value.'</option>';
			}
			$string .= '</optgroup>';
		}
		if (is_array($group) && count($group) > 0) {
			$string .= '<optgroup label="グループ">';
			foreach ($group as $key => $value) {
				$string .= '<option value="'.$key.'">'.$value.'</option>';
			}
			$string .= '</optgroup>';
		}
		$attribute = 'this,'.intval($_GET['year']).','.intval($_GET['month']);
		if ($_GET['day'] > 0) {
			$attribute .= ','.intval($_GET['day']);
		}
		return sprintf('<select name="%s" onchange="Schedule.redirect(%s)">%s</select>', $name, $attribute, $string);

	}

}

?>
