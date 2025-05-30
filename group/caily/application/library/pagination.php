<?php


class Pagination {
	
	var $parameter = array();
	var $recordlimit = APP_LIMIT;
	var $maxlimit = APP_LIMITMAX;
	
	function __construct($array = null) {
		
		if (is_array($array)) {
			$this->parameter = $array;
		}
	
	}
	
	function create($count) {
		if($count <= 0) {
			return '';
		}
		$result = array();
		if ($_REQUEST['page'] > 0) {
			$page = intval($_REQUEST['page']);
		} else {
			$page = 1;
		}
		$previous = $page - 1;
		if ($previous < 1) {
			$previous = 1;
		}
		$next = $page + 1;
		if ($_REQUEST['limit'] > 0 && $_REQUEST['limit'] <= $this->maxlimit) {
			$limit = intval($_REQUEST['limit']);
		} else {
			$limit = $this->recordlimit;
		}
		$pagecount = ceil($count/$limit);
		if ($pagecount <= 1) {
			return '';
		}
		if ($previous > 1) {
			$result[0] = $this->createlink('前ページ', $previous, $_REQUEST['sort'], $_REQUEST['desc']);
		}
		if ($page <= 5 || $pagecount <= 10) {
			$begin = 1;
			$end = $pagecount;
			if ($pagecount > 10) {
				$end = 10;
			}
		} elseif (($pagecount - $page) <= 5) {
			$begin = $pagecount - 9;
			$end = $pagecount;
		} else {
			$begin = $page - 4;
			$end = $page + 5;
		}
		$array = array();
		for ($i = $begin; $i <= $end; $i++) {
			$class = '';
			if ($i == $page) {
				$class = 'active';
			}
			$array[] = $this->createlink($i, $i, $_REQUEST['sort'], $_REQUEST['desc'], $class);
		}
		$result[1] = implode('', $array);
		if (strlen($result[1]) <= 0) {
			$result[1] = $this->createlink(1, 1, $_REQUEST['sort'], $_REQUEST['desc'], 'active');
		}
		if ($next <= $pagecount) {
			$result[2] = $this->createlink('次ページ', $next, $_REQUEST['sort'], $_REQUEST['desc']);
		}
		return implode('',$result);
	
	}
	
	function createlink($caption, $page, $sort, $desc, $class = '') {
		
		$result = array();
		if (isset($page)) {
			$result[] = 'page='.intval($page);
		}
		if ($sort) {
			$result[] = 'sort='.htmlspecialchars($sort, ENT_QUOTES, 'UTF-8');
		}
		if (isset($desc)) {
			$result[] = 'desc='.intval($desc);
		}
		if ($_REQUEST['limit']) {
			$result[] = 'limit='.intval($_REQUEST['limit']);
		}
		if ($_REQUEST['search']) {
			$result[] = 'search='.htmlspecialchars($_REQUEST['search'], ENT_QUOTES, 'UTF-8');
		}
		if (count($this->parameter) > 0) {
			foreach ($this->parameter as $key => $value) {
				if (strlen($value) > 0) {
					$result[] = $key.'='.htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
				}
			}
		}
		if (count($result) > 0) {
			$parameter = '?'.implode('&', $result);
		}
		return sprintf('<div class="page-item %s"><a href="%s%s" class="page-link waves-effect" >%s</a></div>', $class, $_SERVER['SCRIPT_NAME'], $parameter, $caption);
	
	}

	function createAnchor($caption, $page, $sort, $desc, $class = '') {
		
		$result = array();
		if (isset($page)) {
			$result[] = 'page='.intval($page);
		}
		if ($sort) {
			$result[] = 'sort='.htmlspecialchars($sort, ENT_QUOTES, 'UTF-8');
		}
		if (isset($desc)) {
			$result[] = 'desc='.intval($desc);
		}
		if ($_REQUEST['limit']) {
			$result[] = 'limit='.intval($_REQUEST['limit']);
		}
		if ($_REQUEST['search']) {
			$result[] = 'search='.htmlspecialchars($_REQUEST['search'], ENT_QUOTES, 'UTF-8');
		}
		if (count($this->parameter) > 0) {
			foreach ($this->parameter as $key => $value) {
				if (strlen($value) > 0) {
					$result[] = $key.'='.htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
				}
			}
		}
		if (count($result) > 0) {
			$parameter = '?'.implode('&', $result);
		}
		return sprintf('<a href="%s%s">%s</a></li>', $_SERVER['SCRIPT_NAME'], $parameter, $caption);
	
	}
	
	function sortby($sort, $caption = '') {
		
		if ($sort == $_REQUEST['sort']) {
			if ($_REQUEST['desc'] == 1) {
				$image = '<span class="sortby">▼</span>';
				$desc = 0;
			} else {
				$image = '<span class="sortby">▲</span>';
				$desc = 1;
			}
		} else {
			$image = '';
			$desc = $_REQUEST['desc'];
		}
		return $this->createAnchor($caption.$image, 1, $sort, $desc);
	
	}
	
	function limit($attribute = '') {
		
		$option = array(10=>10,20=>20,30=>30,40=>40,50=>50,60=>60,70=>70,80=>80,90=>90,100=>100);
		if ($_REQUEST['limit'] > 0 && $_REQUEST['limit'] <= $this->maxlimit) {
			$limit = intval($_REQUEST['limit']);
		} else {
			$limit = $this->recordlimit;
		}
		$string = '';
		foreach ($option as $key => $value) {
			if ($key == $limit) {
				$string .= '<option value="'.$key.'" selected="selected">'.$value.'</option>';
			} else {
				$string .= '<option value="'.$key.'">'.$value.'</option>';
			}
		}
		return '<select class="form-select" id="limit" name="limit"'.$attribute.'>'.$string.'</select>';
		
	}

}
?>