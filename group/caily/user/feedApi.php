<?php

require_once('../application/json.php');
if ($_REQUEST['type'] == 1) {
	$attribute = ' onchange="App.permitlistApi(this, 1)"';
	$option = $hash['group'];
} else {
	$attribute = ' onchange="App.permitlistApi(this)"';
	$option = array('グループ') + $hash['group'];
}
?>
<form class="layerlist" name="userlist" onsubmit="return false">
	<div class="layerlistcaption">
		<?=$helper->selector('group', $option, $_REQUEST['group'], $attribute)?>&nbsp;
		<a href="javascript:void(0)" class="operator mt-2" onclick="App.checkall(null, 'userlist')">すべて選択</a>
	</div>
	<ul class="list-unstyled">
<?php
if (is_array($hash['list']) && count($hash['list']) > 0) {
	foreach ($hash['list'] as $row) {
?>
		<li><input type="checkbox" name="<?=$row['userid']?>" value="<?=$row['realname']?>" />
		<span class="operator" onclick="App.permitApi(this)"><?=$row['realname']?></span></li>
<?php
	}
} elseif ($_REQUEST['type'] != 1 && $_REQUEST['group'] <= 0 && is_array($hash['group']) && count($hash['group']) > 0) {
	foreach ($hash['group'] as $key => $value) {
?>
		<li><input type="checkbox" name="group:<?=$key?>" value="<?=$value?>" />
		<span class="operator" onclick="App.permitApi(this)"><?=$value?></span></li>
<?php
	}
} else {
	echo '<li>ユーザー情報はありません。</li>';
}
?>
	</ul>
	<div class="layerlistsubmit"><button class="btn btn-primary" type="button" onclick="App.permitApi()" >選択</button></div>
</form>