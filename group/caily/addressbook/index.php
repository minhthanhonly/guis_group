<?php

require_once('../application/loader.php');
$view->heading('アドレス帳');
$pagination = new Pagination(array('folder'=>$_GET['folder']));
?>
<div class="container-xxl flex-grow-1 container-p-y">
	<div class="card" id="option-block">
		<div class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-6">
				<h4 class="card-title mb-0"><span>アドレス帳<?=$view->caption($hash['folder'], array('all'=>'すべて表示'))?></span></h4>
			</div>
		</div>

<ul class="operate">
<?php
if ($view->permitted($hash['category'], 'add')) {
	echo '<li><a href="add.php'.$view->parameter(array('folder'=>$_GET['folder'])).'">アドレス追加</a></li>';
}
if (count($hash['list']) <= 0) {
	$attribute = ' onclick="alert(\'出力するデータがありません。\');return false;"';
}
?>
	<li><a href="csv.php<?=$view->parameter(array('sort'=>$_GET['sort'], 'desc'=>$_GET['desc'], 'search'=>$_GET['search'], 'folder'=>$_GET['folder'], 'type'=>0))?>"<?=$attribute?>>CSV出力</a></li>
</ul>
<?=$view->searchform(array('folder'=>$_GET['folder']))?>
<table class="content" cellspacing="0"><tr><td class="contentfolder">
	<?=$view->category($hash['folder'], 'addressbook')?>
</td><td>
	<table class="table" cellspacing="0">
		<tr><th><?=$pagination->sortby('addressbook_name', '名前')?></th>
		<th><?=$pagination->sortby('addressbook_postcode', '郵便番号')?></th>
		<th><?=$pagination->sortby('addressbook_address', '住所')?></th>
		<th><?=$pagination->sortby('addressbook_phone', '電話番号')?></th></tr>
<?php
if (is_array($hash['list']) && count($hash['list']) > 0) {
	foreach ($hash['list'] as $row) {
?>
		<tr><td><a href="view.php?id=<?=$row['id']?>"><?=$row['addressbook_name']?></a>&nbsp;</td>
		<td><?=$row['addressbook_postcode']?>&nbsp;</td>
		<td><?=$row['addressbook_address']?>&nbsp;</td>
		<td><?=$row['addressbook_phone']?>&nbsp;</td></tr>
<?php
	}
}
?>
	</table>
	<?=$view->pagination($pagination, $hash['count'])?>
</td></tr></table>
	</div>
</div>
<?php
$view->footing();
?>