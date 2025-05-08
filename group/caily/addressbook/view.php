<?php

require_once('../application/loader.php');
$view->script('postcode.js');
$view->heading('アドレス詳細');
if ($hash['data']['addressbook_parent'] > 0) {
	$hash['data']['addressbook_company'] = sprintf('<a href="companyview.php?id=%d">%s</a>', $hash['data']['addressbook_parent'], $hash['data']['addressbook_company']);
}
?>
<!-- Content -->
<div class="container-xxl flex-grow-1 container-p-y">
	
	<div class="card" id="option-block">
		<div class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-6">
				<h4 class="card-title mb-0"><span id="timecard_title">アドレス詳細</h4>
            </div>
			<div class="col-md-6 d-flex justify-content-end gap-4">
				<div><a href="index.php<?=$view->positive(array('folder'=>$hash['data']['folder_id']))?>" class="btn btn-info">一覧に戻る</a></div>
				<?php
				if ($view->permitted($hash['category'], 'add') && $view->permitted($hash['data'], 'edit')) {
					echo '<div><a href="edit.php?id='.$hash['data']['id'].'" class="btn btn-primary">編集</a></div>';
					echo '<div><a href="delete.php?id='.$hash['data']['id'].'" class="btn btn-danger">削除</a></div>';
				}
				?>
			</div>
        </div>
		<div class="card-body">
			<table class="table table-bordered table-striped mt-12 mb-12">
				<tr><th class="w-25">名前</th><td><?=$hash['data']['addressbook_name']?></td></tr>
				<tr><th class="w-25">かな</th><td><?=$hash['data']['addressbook_ruby']?></td></tr>
				<tr><th class="w-25">郵便番号</th><td><?=$hash['data']['addressbook_postcode']?></td></tr>
				<tr><th class="w-25">住所</th><td><?=$hash['data']['addressbook_address']?></td></tr>
				<tr><th class="w-25">住所（かな）</th><td><?=$hash['data']['addressbook_addressruby']?></td></tr>
				<tr><th class="w-25">電話番号</th><td><?=$hash['data']['addressbook_phone']?></td></tr>
				<tr><th class="w-25">FAX</th><td><?=$hash['data']['addressbook_fax']?></td></tr>
				<tr><th class="w-25">携帯電話番号</th><td><?=$hash['data']['addressbook_mobile']?></td></tr>
				<tr><th class="w-25">メールアドレス</th><td><?=$hash['data']['addressbook_email']?></td></tr>
				<tr><th class="w-25">会社名</th><td><?=$hash['data']['addressbook_company']?></td></tr>
				<tr><th class="w-25">会社名（かな）</th><td><?=$hash['data']['addressbook_companyruby']?></td></tr>
				<tr><th class="w-25">部署</th><td><?=$hash['data']['addressbook_department']?></td></tr>
				<tr><th class="w-25">役職</th><td><?=$hash['data']['addressbook_position']?></td></tr>
				<tr><th class="w-25">URL</th><td><?=$hash['data']['addressbook_url']?></td></tr>
				<tr><th class="w-25">備考</th><td><?=nl2br($hash['data']['addressbook_comment'])?></td></tr>
				<tr><th class="w-25">カテゴリ</th><td><?=$hash['folder']['folder_caption']?></td></tr>
			</table>
			<?php
			$view->property($hash['data']);
			?>
		</div>
	</div>
</div>
<!-- / Content -->
<?php

$view->footing();
?>