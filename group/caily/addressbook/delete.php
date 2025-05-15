<?php

require_once('../application/loader.php');
$view->script('postcode.js');
$view->heading('アドレス削除');
if ($hash['data']['addressbook_parent'] > 0) {
	$hash['data']['addressbook_company'] = sprintf('<a href="companyview.php?id=%d">%s</a>', $hash['data']['addressbook_parent'], $hash['data']['addressbook_company']);
}
?>
<!-- Content -->
<div class="container-xxl flex-grow-1 container-p-y">
	
	<div class="card" id="option-block">
		<div class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
			<div class="col-md-6">
				<h4 class="card-title mb-0"><span id="timecard_title">アドレス削除</h4>
            </div>
			<div class="col-md-6 d-flex justify-content-end gap-4">
				<div><a href="index.php<?=$view->positive(array('folder'=>$hash['data']['folder_id']))?>" class="btn btn-info">一覧に戻る</a></div>
			</div>
        </div>
		<div class="card-body py-12">
			<form class="content" method="post" action="">
				<?=$view->error($hash['error'], '下記のアドレスを削除します。')?>
				<table class="table table-bordered mb-4">
					<tr><th>名前</th><td><?=$hash['data']['addressbook_name']?>&nbsp;</td></tr>
					<tr><th>かな</th><td><?=$hash['data']['addressbook_ruby']?>&nbsp;</td></tr>
					<tr><th>郵便番号</th><td><?=$hash['data']['addressbook_postcode']?>&nbsp;</td></tr>
					<tr><th>住所</th><td><?=$hash['data']['addressbook_address']?>&nbsp;</td></tr>
					<tr><th>住所（かな）</th><td><?=$hash['data']['addressbook_addressruby']?>&nbsp;</td></tr>
					<tr><th>電話番号</th><td><?=$hash['data']['addressbook_phone']?>&nbsp;</td></tr>
					<tr><th>FAX</th><td><?=$hash['data']['addressbook_fax']?>&nbsp;</td></tr>
					<tr><th>携帯電話番号</th><td><?=$hash['data']['addressbook_mobile']?>&nbsp;</td></tr>
					<tr><th>メールアドレス</th><td><?=$hash['data']['addressbook_email']?>&nbsp;</td></tr>
					<tr><th>会社名</th><td><?=$hash['data']['addressbook_company']?><?=$hash['data']['addressbook_companyview']?>&nbsp;</td></tr>
					<tr><th>会社名（かな）</th><td><?=$hash['data']['addressbook_companyruby']?>&nbsp;</td></tr>
					<tr><th>部署</th><td><?=$hash['data']['addressbook_department']?>&nbsp;</td></tr>
					<tr><th>役職</th><td><?=$hash['data']['addressbook_position']?>&nbsp;</td></tr>
					<tr><th>URL</th><td><?=$hash['data']['addressbook_url']?>&nbsp;</td></tr>
					<tr><th>備考</th><td><?=nl2br($hash['data']['addressbook_comment'])?>&nbsp;</td></tr>
					<tr><th>カテゴリ</th><td><?=$hash['folder'][$hash['data']['folder_id']]?>&nbsp;</td></tr>
				</table>
				<?=$view->property($hash['data'])?>
				<div class="submit mt-4">
					<button type="submit" class="btn btn-danger">削除</button>&nbsp;
					<a class="btn btn-secondary" href='index.php<?=$view->positive(array('folder'=>$hash['data']['folder_id']))?>'>キャンセル</a>
				</div>
				<input type="hidden" name="id" value="<?=$hash['data']['id']?>" />
			</form>
		</div>
	</div>
</div>
<?php
$view->footing();
?>