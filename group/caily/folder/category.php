<?php

require_once('../application/loader.php');
$view->heading('カテゴリ管理', $_GET['type']);
$pagination = new Pagination(array('type'=>$_GET['type']));
$type = array('forum'=>'お知らせページ', 'addressbook'=>'アドレス帳', 'bookmark'=>'ブックマーク', 'facility'=>'施設予約', 'project'=>'プロジェクト');
if (strlen($_GET['type']) > 0) {
?>
<!-- Content -->
<div class="container-xxl flex-grow-1 container-p-y">
	
	<div class="card" id="option-block">
		<div class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-6">
				<h4 class="card-title mb-0"><span id="timecard_title">カテゴリ管理</h4>
            </div>
			<div class="col-md-6 d-flex justify-content-end gap-4">

				<div><a href="../<?=$_GET['type']?>/"" class="btn btn-info"><?=$type[$_GET['type']]?>に戻る</a></div>
				<div><a href="categoryadd.php?type=<?=$_GET['type']?>" class="btn btn-primary">カテゴリ追加</a></div>
				
			</div>
        </div>
		<div class="card-body">

<table class="table table-bordered table-striped mt-12 mb-12">
	<tr><th><?=$pagination->sortby('folder_caption', 'カテゴリ名')?></th>
	<th><?=$pagination->sortby('folder_name', '登録者')?></th>
	<th><?=$pagination->sortby('folder_date', '登録日')?></th>
	<th><?=$pagination->sortby('folder_order', '順序')?></th>
	<th class="listlink">&nbsp;</th><tr>
<?php
	if (is_array($hash['list']) && count($hash['list']) > 0) {
		foreach ($hash['list'] as $row) {
?>
	<tr><td><a href="categoryview.php?id=<?=$row['id']?>"><?=$row['folder_caption']?></a>&nbsp;</td>
	<td><?=$row['folder_name']?>&nbsp;</td>
	<td><?=date('Y/m/d H:i:s', strtotime($row['folder_date']))?>&nbsp;</td>
	<td><?=$row['folder_order']?>&nbsp;</td>
	<td><a href="categoryedit.php?id=<?=$row['id']?>" class="btn btn-primary">編集</a>&nbsp;</td>
<?php
		}
	}
	echo '</table>';
	$view->pagination($pagination, $hash['count']);
} else {
$type = array('forum'=>'フォーラム', 'addressbook'=>'アドレス帳', 'bookmark'=>'ブックマーク', 'facility'=>'施設予約', 'project'=>'プロジェクト');
?>
<!-- <h1>カテゴリ管理</h1>
<ul class="operate">
	<li><a href="../administration.php">管理画面トップに戻る</a></li>
</ul>
<ul class="itemlink">
	<li><a href="category.php?type=forum"><img src="../images/arrownext.gif" />フォーラム</a></li>
	<li><a href="category.php?type=bookmark"><img src="../images/arrownext.gif" />ブックマーク</a></li>
	<li><a href="category.php?type=project"><img src="../images/arrownext.gif" />プロジェクト</a></li>
	<li><a href="category.php?type=addressbook"><img src="../images/arrownext.gif" />アドレス帳</a></li>
</ul>
 -->
<?php } ?>
</div>
</div>
</div>
<!-- / Content -->
<?php
$view->footing();
?>