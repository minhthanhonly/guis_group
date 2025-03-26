<?php
/*
 * Copyright(c) 2009 limitlink,Inc. All Rights Reserved.
 * http://limitlink.jp/
 * 文字コード UTF-8
 */
require_once('../application/loader.php');
$view->heading('グループ編集', 'administration');
?>
<h1>Sửa Nhóm</h1>
<ul class="operate">
	<li><a href="index.php"> Quay lại Nhóm </a></li>
	<li><a href="delete.php?id=<?=$hash['data']['id']?>"> Xóa </a></li>
</ul>
<form class="content" method="post" action="">
	<?=$view->error($hash['error'])?>
<table class="form" cellspacing="0">
		<tr><th>Tên nhóm<span class="necessary">(Bắt buộc)</span></th><td><input type="text" name="group_name" class="inputvalue" value="<?=$hash['data']['group_name']?>" /></td></tr>
		<tr>
		  <th>Sắp xếp</th><td><input type="text" name="group_order" class="inputnumeric" value="<?=$hash['data']['group_order']?>" /></td></tr>
		<tr><th>Phân quyền<?=$view->explain('groupadd')?></th><td><?=$view->permit($hash['data'], 'add')?></td></tr>
		<tr><th>Tùy chọn sửa<?=$view->explain('groupedit')?></th><td><?=$view->permit($hash['data'], 'edit')?></td></tr>
	</table>
	<div class="submit">
		<input type="submit" value="　sửa　" />&nbsp;
		<input type="button" value="Hủy bỏ" onclick="location.href='index.php'" />
	</div>
	<input type="hidden" name="id" value="<?=$hash['data']['id']?>" />
</form>
<?php
$view->footing();
?>