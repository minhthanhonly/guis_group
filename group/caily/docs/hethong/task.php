<?php
require_once __DIR__ . '/_init.php';
hethong_start('task', 'タスク');
?>

<h2>タスク</h2>
<p>案件を開き、タブ「タスク」へ進みます。</p>
<?php hethong_figure('05-project-task.png', '案件のタスク画面の例'); ?>

<h3>できること</h3>
<ul>
  <li>件数・合計工数・完了数・期限超過の概要を確認</li>
  <li>ステータスで絞り込み、「自分のタスクのみ」をオン</li>
  <li>「新規タスク」で作成</li>
  <li>「既定タスク追加」で部署のテンプレートを追加（設定がある場合）</li>
  <li>表上で直接編集：名称、優先度、種別、ステータス、期限、担当、作業比重、工数、メモ</li>
  <li>行ごとに作業計測（「作業計測」のページ参照）</li>
  <li>削除（確認ダイアログあり）</li>
</ul>

<h3>タスクのステータス</h3>
<div class="table-responsive">
<table class="table table-sm table-bordered align-middle">
  <thead class="table-light"><tr><th>ステータス</th><th>意味</th></tr></thead>
  <tbody>
    <tr><td>未開始</td><td>まだ着手していない</td></tr>
    <tr><td>進行中</td><td>作業中</td></tr>
    <tr><td>確認中</td><td>確認待ち</td></tr>
    <tr><td>一時停止</td><td>いったん止めている</td></tr>
    <tr><td>完了</td><td>終わった</td></tr>
    <tr><td>キャンセル</td><td>取り消し</td></tr>
  </tbody>
</table>
</div>

<h3>よく使う種別</h3>
<p>新規作成 / 修正(エラー) / 修正(変更) / チェック / 連絡 / 検討 / 相談・会議</p>
<p><strong>メリット：</strong>細かい手順がはっきりし、種別ごとの工数集計にもつながります。</p>

<h3>作業比重</h3>
<p>タスク一覧の「作業比重」は、<strong>そのタスクが案件全体の中でどれくらいの重みを持つか</strong>を表す数字です。</p>

<p><strong>何のための項目か</strong></p>
<ul>
  <li>1つの案件に複数のタスクがあるとき、「どれが主作業で、どれが付帯作業か」を数字で残す</li>
  <li>チェックを入れて図面リストに載せるタスクでは、案件金額を分けるときの<strong>配分の重み</strong>として使われます</li>
  <li>あとから統計や実績を見るとき、「どの作業にどれだけ寄与したか」を比べやすくします</li>
</ul>

<p><strong>画面での使い方</strong></p>
<ol>
  <li>チェック「図面リストに追加」をオンにする（対象になるタスクの場合）</li>
  <li>横の数字に作業比重を入れる（例：主な図面作業なら大きめ、軽い確認なら小さめ）</li>
  <li>必要に応じて、配分の目安となる％が表示されます</li>
</ol>

<div class="table-responsive">
<table class="table table-sm table-bordered align-middle">
  <thead class="table-light"><tr><th>考え方</th><th>例</th></tr></thead>
  <tbody>
    <tr><td>重い作業・枚数が多い</td><td>作業比重を大きくする（例：3・5）</td></tr>
    <tr><td>軽い作業・補助的</td><td>作業比重を小さくする（例：1）</td></tr>
    <tr><td>統計しない</td><td>チェックを外す（「—」表示）</td></tr>
  </tbody>
</table>
</div>

<p><strong>メリット：</strong>工数（かかった時間）とは別に、「仕事の重み」を共有できます。金額配分や実績の見方も、感覚ではなく同じ基準で揃えやすくなります。</p>
<p class="small text-muted">※ 工数は「実際に測った時間」、作業比重は「このタスクの相対的な重さ」です。混ぜて考えないようにしてください。</p>

<h3>マイタスク</h3>
<?php hethong_figure('05-task-todo.png', 'マイタスク画面の例'); ?>
<p>「Todoリスト」（F3）→ タブ「マイタスク」：</p>
<ul>
  <li>複数案件にわたって、自分に割り当たったタスクを一覧</li>
  <li>「受領」/「受領済み」で受領確認</li>
  <li>作業計測、期限確認、関連案件・図面への移動</li>
</ul>
<p>タブ「カスタムTodo」は案件に紐づかない個人のリマインダーです（期限・並び替え可）。</p>

<?php hethong_end(); ?>
