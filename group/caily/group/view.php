<?php

require_once('../application/loader.php');
$view->heading('グループ詳細', 'administration');
$add = array('許可', '登録者のみ');
if ($hash['data']['add_level'] == 2) {
	$add[2] = $view->permitlist($hash['data'], 'add');
}
?>
<h1> Nhóm khác </h1>
<ul class="operate">
  <li><a href="index.php"> Quay lại Nhóm </a></li>
<?php
if ($view->permitted($hash['data'], 'edit')) {
	echo '<li><a href="edit.php?id='.$hash['data']['id'].'">編集</a></li>';
	echo '<li><a href="delete.php?id='.$hash['data']['id'].'">削除</a></li>';
}
?>
</ul>
<table class="view" cellspacing="0">
<tr><th>Tên nhóm</th><td><?=$hash['data']['group_name']?>&nbsp;</td></tr>
	<tr><th>Sắp xếp</th><td><?=$hash['data']['group_order']?>&nbsp;</td></tr>
	<tr><th>Phân quyền</th><td><?=$add[$hash['data']['add_level']]?>&nbsp;</td></tr>
</table>
<?php
$view->property($hash['data']);
$view->footing();
?>