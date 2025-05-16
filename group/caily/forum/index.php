<?php

require_once('../application/loader.php');
$view->heading('お知らせ');
$pagination = new Pagination(array('folder'=>$_GET['folder']));
if (strlen($_GET['folder']) <= 0 || $_GET['folder'] == 'all') {
	$current['all'] = ' class="current"';
} else {
	$current[intval($_GET['folder'])] = ' class="current"';
}
?>

<div class="container-xxl flex-grow-1 container-p-y">
	<div class="card" id="option-block">
		<div
			class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-6">
				<h4 class="card-title mb-0">
					<span>お知らせ <?=$view->caption($hash['folder'], array(0=>'全般'))?></span></h4>
			</div>
			<div class="col-md-6">
				<div class="d-flex justify-content-end">
					<div class="col-md-6">
					<?=$view->searchform(array('folder'=>$_GET['folder']))?>
					</div>
					<div class="col-md-6">
						<ul class="operate d-flex gap-2 list-unstyled justify-content-end">
							<?php
							if (!isset($hash['category']) || $view->permitted($hash['category'], 'add')) {
								echo '<li><a class="btn btn-primary" href="add.php' . $view->parameter(array('folder' => $_GET['folder'])) . '">スレッド作成</a></li>';
							}
							?>
						</ul>
					</div>
				</div>
			</div>
		</div>
		<div class="card-body">
			<div class="container py-12">
				<div class="row">
					<div class="col-md-3 col-lg-2">
						<div class="folder">
							<h5 class="foldercaption">カテゴリ</h5>
								<ul class="folderlist">
									<li<?=$current[0]?>><a href="index.php?folder=0">全般</a></li>
						<?php
						if (is_array($hash['folder']) && count($hash['folder']) > 0) {
							foreach ($hash['folder'] as $key => $value) {
								echo '<li'.$current[$key].'><a href="index.php?folder='.$key.'">'.$value.'</a></li>';
							}
						}
						echo '</ul>';
						if ($view->authorize('administrator', 'manager', 'editor')) {
							echo '<div class="folderoperate"><a class="btn btn-label-primary" href="../folder/category.php?type=forum">カテゴリ設定</a></div>';
						}
						?>
							</div>
						</div>
					<div class="col-md-9 col-lg-10">
						<table class="list visited table table-bordered table-striped" cellspacing="0">
							<tr><th><?=$pagination->sortby('forum_title', 'タイトル')?></th>
							<th><?=$pagination->sortby('forum_name', '名前')?></th>
							<th><?=$pagination->sortby('forum_node', 'コメント')?></th>
							<th><?=$pagination->sortby('forum_lastupdate', '最終更新日')?></th>
					<?php
					if (is_array($hash['list']) && count($hash['list']) > 0) {
						foreach ($hash['list'] as $row) {
					?>
							<tr><td><?php 
							if(date('Y-m-d H:i:s', strtotime($row['created'])) > date('Y-m-d H:i:s', strtotime('-1 week'))){
								echo '<span class="badge bg-label-info me-2">NEW</span>';
							}
							?><a href="view.php?id=<?=$row['id']?>"><?=$row['forum_title']?></a>&nbsp;</td>
							<td><?=$row['forum_name']?>&nbsp;</td>
							<td><?=intval($row['forum_node'])?>&nbsp;</td>
							<td><?=date('Y/m/d H:i:s', strtotime($row['forum_lastupdate']))?>&nbsp;</td></tr>
					<?php
						}
					}
					?>
						</table>
							<?=$view->pagination($pagination, $hash['count']);?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<?php
$view->footing();
?>