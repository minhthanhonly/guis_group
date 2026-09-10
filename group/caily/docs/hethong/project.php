<?php
require_once __DIR__ . '/_init.php';
hethong_start('project', '案件の管理');
?>

<h2>案件の管理</h2>

<h3>案件一覧</h3>
<?php hethong_figure('03-project-list.png', '案件一覧画面の例'); ?>
<p>多くの方が毎日使う画面です。</p>
<p><strong>絞り込む前に：</strong></p>
<ol>
  <li>ナビの「部署」を選ぶ</li>
  <li>ステータスボタン：受付 / 納期検討 / 仮受 / 見積 / 請負 / 資料待ち / 進行中 / 完了 / 一時停止 / 中止</li>
  <li>必要なら「高度なフィルター」（開始月、見積・請求の状態、優先度、期限までの日数、チーム、キーワードなど）</li>
  <li>便利な切替：
    <ul>
      <li>「私の案件」：自分に関係する案件だけ</li>
      <li>「完了・中止案件等も表示」：完了や中止も含める</li>
    </ul>
  </li>
</ol>

<div class="table-responsive">
<table class="table table-sm table-bordered align-middle">
  <thead class="table-light"><tr><th>ボタン / 機能</th><th>内容</th></tr></thead>
  <tbody>
    <tr><td>星「お気に入り」</td><td>よく開く案件をマーク</td></tr>
    <tr><td>「並べ替え」</td><td>期限・予定などで並び替え</td></tr>
    <tr><td>「列の表示」</td><td>必要な列だけ表示</td></tr>
    <tr><td>「Excel出力」</td><td>表をExcelで書き出し</td></tr>
    <tr><td>「案件を編集」</td><td>詳細を開かず一部項目を素早く変更</td></tr>
    <tr><td>黄色い「UIガイド」</td><td>一覧上のボタン説明ツアー</td></tr>
  </tbody>
</table>
</div>
<p><strong>メリット：</strong>部署全体の期限・見積状況・一時停止などを一覧で把握できます。</p>

<h3>案件詳細</h3>
<?php hethong_figure('04-project-detail.png', '案件詳細（概要）画面の例'); ?>
<p>上部タブ：</p>
<div class="table-responsive">
<table class="table table-sm table-bordered align-middle">
  <thead class="table-light"><tr><th>タブ</th><th>内容</th></tr></thead>
  <tbody>
    <tr><td>「概要」</td><td>案件情報、決済、メモ、コメント、種別工数など</td></tr>
    <tr><td>「タスク」</td><td>細かい作業と作業計測</td></tr>
    <tr><td>「ガントチャート」</td><td>タスクを時間軸で表示</td></tr>
    <tr><td>「添付ファイル」</td><td>案件の添付</td></tr>
  </tbody>
</table>
</div>

<p>概要タブでよく行うこと：</p>
<ul>
  <li>親建物の情報確認と「建物詳細」へのリンク</li>
  <li>「編集」→「保存」で案件情報を更新（権限がある場合）</li>
  <li>「ステータス」「進捗率」「開始日」「期限」「予定工程」の変更</li>
  <li>「CAILY納期」「GUIS納期」と「納品済み」の記録</li>
  <li>メンバー・チーム・管理者の追加、または「案件に参加」</li>
  <li>案件情報のコピー、案件の複製（ボタンがある場合）</li>
  <li>「コメント」でのやり取り</li>
</ul>

<h3>よくあるステータスの流れ</h3>
<p>
  受付 → 納期検討 → 仮受 → 見積 → 請負 → 資料待ち → 進行中 → 完了<br>
  分岐：一時停止、中止
</p>

<?php hethong_end(); ?>
