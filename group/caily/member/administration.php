<?php

require_once('application/loader.php');
$view->heading('管理画面', 'administration');
?>
<h1>管理画面</h1>
<?php
if (file_exists('setup.php')) {
	echo $view->error($hash['error']);
?>
<form class="content" method="post" action="">
	<input type="submit" value="　削除　" />
</form>
<?php
}
?>
<ul class="itemlink">
	<li><img src="images/arrownext.gif" /><a href="user/">ユーザー管理</a></li>
<?php
if ($view->authorize('administrator')) {
	echo '<li><img src="images/arrownext.gif" /><a href="group/">グループ管理</a></li>';
}
?>
	<li><img src="images/arrownext.gif" />カテゴリ管理</li>

		<li class="item"><img src="images/arrownext.gif" /><a href="folder/category.php?type=forum">フォーラム</a></li>
		<li class="item"><img src="images/arrownext.gif" /><a href="folder/category.php?type=bookmark">ブックマーク</a></li>
		<li class="item"><img src="images/arrownext.gif" /><a href="folder/category.php?type=project">プロジェクト</a></li>
		<li class="item"><img src="images/arrownext.gif" /><a href="folder/category.php?type=addressbook">アドレス帳</a></li>

	<li><img src="images/arrownext.gif" />タイムカード管理</li>
	
		<li class="item"><img src="images/arrownext.gif" /><a href="timecard/config.php">タイムカード管理</a></li>
		<li class="item"><img src="images/arrownext.gif" /><a href="timecard/holiday.php">休日設定</a></li>
		<li class="item"><img src="images/arrownext.gif" /><a href="timecard/group.php">グループ</a></li>
<?php
if ($view->authorize('administrator') && file_exists('administration')) {
	echo '<li><img src="images/arrownext.gif" /><a href="administration/">データベース管理</a></li>';
	echo '<li><img src="images/arrownext.gif" /><a href="administration/upload.php">アップロードファイル管理</a></li>';
}
?>
	<li><img src="images/arrownext.gif" /><a href="folder/category.php?type=forum">CSV出力</a></li>
</ul>
<?php
$view->footing();
?>