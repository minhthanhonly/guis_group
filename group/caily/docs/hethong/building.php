<?php
require_once __DIR__ . '/_init.php';
hethong_start('building', '建物の管理');
?>

<h2>建物の管理</h2>

<h3>建物一覧</h3>
<?php hethong_figure('01-parent-list.png', '建物一覧画面の例'); ?>
<p>できることの例：</p>
<ul>
  <li>「お施主様名」を押して建物詳細を開く</li>
  <li>「建物登録」で新規作成（権限がある場合）</li>
  <li>「検索...」でキーワード検索</li>
  <li>「依頼」で すべて / 意匠 / 設備 / 3D設備 / 省エネ / その他 に絞る</li>
  <li>「お気に入り」でよく使う建物だけ表示</li>
  <li>「列の表示」で表示列を切替</li>
  <li>「件数」で子案件の数を確認</li>
  <li>一覧上の「メモ」を確認・編集</li>
  <li>行を右クリックしてクイック編集や詳細を開く</li>
</ul>
<p><strong>メリット：</strong>番号を覚えなくても建物を探せます。子案件の数も一覧で把握できます。</p>

<h3>建物登録</h3>
<?php hethong_figure('01-parent-create.png', '建物登録画面の例', 600); ?>
<p>主な入力項目（画面上 <code>*</code> は必須）：</p>
<div class="table-responsive">
<table class="table table-sm table-bordered align-middle">
  <thead class="table-light"><tr><th>項目</th><th>意味</th></tr></thead>
  <tbody>
    <tr><td>「会社名」「支店名」「担当様」</td><td>お客様側の会社・支店・担当</td></tr>
    <tr><td>「GUIS　受付者」</td><td>GUIS側の受付担当</td></tr>
    <tr><td>「依頼日」</td><td>依頼を受けた日（現在時刻ボタンあり）</td></tr>
    <tr><td>「管理番号」</td><td>社内管理番号（「生成」で自動作成可）</td></tr>
    <tr><td>「工事番号」</td><td>工事番号（ある場合）</td></tr>
    <tr><td>「お施主様名」</td><td>お施主様名・建物の呼び名</td></tr>
    <tr><td>「建物規模」「種類1」「種類2」</td><td>規模・種類</td></tr>
    <tr><td>「依頼」</td><td>意匠・設備・3D設備・省エネ など</td></tr>
    <tr><td>「資料」</td><td>受け取った資料（配置図、申請資料など）</td></tr>
  </tbody>
</table>
</div>
<p>
  顧客の新規追加や「顧客情報」の確認・編集もできます。
  <strong>注意：</strong>顧客情報の変更は、同じ顧客を使う<strong>すべての建物</strong>に影響することがあります。保存前に警告が出ます。
</p>
<p>最後に「保存」、やめるときは「キャンセル」/「戻る」。</p>

<h3>建物詳細</h3>
<?php hethong_figure('02-parent-detail.png', '建物詳細画面の例'); ?>
<ol>
  <li>建物の共通情報を確認・編集（「編集」→「保存」、権限がある場合）</li>
  <li>「メモを追加」（「重要メモ」も可）</li>
  <li>「案件依頼作成」で部署ごとの案件を追加</li>
  <li>子案件の一覧（ステータス・進捗・期限・決済など）</li>
  <li>「部署別種別工数」で工数の集計を確認</li>
  <li>「見積書」の作成・履歴</li>
  <li>タブ「添付ファイル」で資料を管理</li>
</ol>
<p><strong>メリット：</strong>建物の中心画面です。ここから案件依頼・資料・見積まで進められます。</p>

<h3>案件依頼を作成</h3>
<?php hethong_figure('03-child-create-modal.png', '案件依頼作成の画面例', 600); ?>
<p>「案件依頼作成」を押したときの主な項目：</p>
<ul>
  <li>「案件名」</li>
  <li>「部署」</li>
  <li>建物と同じ顧客を使うか、別の顧客を選ぶか</li>
  <li>「予定工程」（月・段階の予定）</li>
  <li>「開始日」「期限日(実納期)」</li>
  <li>「ステータス」「受注形態」</li>
  <li>チーム・管理者・メンバー（画面にある場合）</li>
</ul>
<p><strong>メリット：</strong>案件が建物と部署に正しく紐づき、「浮いた案件」を防げます。</p>

<?php hethong_end(); ?>
