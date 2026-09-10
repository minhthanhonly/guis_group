<?php
require_once __DIR__ . '/_init.php';
hethong_start('index', '操作ガイド');
$base = hethong_base_url();
$pages = hethong_pages();
?>

<h2>はじめに</h2>
<p>
  このガイドは、日常業務で<strong>建物・案件・タスク・作業時間</strong>を扱う方向けの操作説明です。
</p>

<h3>このシステムでできること</h3>
<div class="table-responsive">
<table class="table table-sm table-bordered align-middle">
  <thead class="table-light"><tr><th>やりたいこと</th><th>メリット</th></tr></thead>
  <tbody>
    <tr><td>建物・お施主様の情報を残す</td><td>情報が散らばらず、全員が同じ内容を見られる</td></tr>
    <tr><td>部署ごとの案件に分ける</td><td>意匠・設備・省エネなど、担当範囲がはっきりする</td></tr>
    <tr><td>タスクに落とし担当を付ける</td><td>誰が何をしているか追いやすい</td></tr>
    <tr><td>作業計測で時間を記録する</td><td>案件・種別ごとの工数が分かる</td></tr>
    <tr><td>見積・請求の状態を残す</td><td>進捗とあわせて営業状況も把握できる</td></tr>
    <tr><td>図面・添付・メモを置く</td><td>資料とやり取りが案件のそばにある</td></tr>
  </tbody>
</table>
</div>

<h3>主な画面の開き方</h3>
<p>左メニューの「<strong>プロジェクト</strong>」から開けます。</p>
<div class="table-responsive">
<table class="table table-sm table-bordered align-middle">
  <thead class="table-light"><tr><th>メニュー</th><th>こんなときに</th></tr></thead>
  <tbody>
    <tr><td>「建物一覧」</td><td>建物の検索・登録・詳細を見る</td></tr>
    <tr><td>「案件一覧」</td><td>部署ごとの案件を一覧・絞り込み・Excel出力</td></tr>
    <tr><td>「タスク一覧」</td><td>複数案件のタスクや工数を見る</td></tr>
    <tr><td>「案件ガントチャート」</td><td>複数案件の予定を時間軸で見る</td></tr>
  </tbody>
</table>
</div>

<ul>
  <li>キーボード（F1 / Ctrl+K）：建物・案件・顧客をすばやく検索</li>
  <li>リスト（F3）：「Todoリスト」— 自分に割り当たったタスクと個人Todo</li>
  <li>画面右下：作業計測中なら「作業計測中」と停止ボタンが表示されます</li>
</ul>

<h3>目次</h3>
<div class="row g-3">
<?php foreach ($pages as $p): if ($p['id'] === 'index') continue; ?>
  <div class="col-md-6">
    <a class="card hethong-toc-card h-100" href="<?= htmlspecialchars($base . $p['file'], ENT_QUOTES, 'UTF-8') ?>">
      <div class="card-body py-3">
        <strong><?= htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8') ?></strong>
      </div>
    </a>
  </div>
<?php endforeach; ?>
</div>

<p class="text-muted small mt-4 mb-0">
  対象は主に「プロジェクト」まわりです。システム管理やタイムカード、高度な統計は本ガイドの範囲外です。
</p>

<?php hethong_end(); ?>
