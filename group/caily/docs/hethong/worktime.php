<?php
require_once __DIR__ . '/_init.php';
hethong_start('worktime', '作業計測');
?>

<h2>作業計測</h2>

<h3>開始と終了</h3>
<div class="table-responsive">
<table class="table table-sm table-bordered align-middle">
  <thead class="table-light"><tr><th>場所</th><th>操作</th></tr></thead>
  <tbody>
    <tr><td>案件の「タスク」タブ</td><td>行の「作業時間を開始」/「作業時間を終了」</td></tr>
    <tr><td>「タスク一覧」</td><td>同様。「作業計測中のタスクのみ」で絞り込み可</td></tr>
    <tr><td>「マイタスク」</td><td>割り当たったタスク上で計測</td></tr>
    <tr><td>画面隅のウィジェット</td><td>計測中の表示と「作業時間を終了」</td></tr>
  </tbody>
</table>
</div>

<h3>覚えやすいルール</h3>
<ul>
  <li>通常、<strong>1人につき同時に動くタイマーは1つ</strong>です。別タスクを開始すると、前の計測は止まります。</li>
  <li>「工数」は記録済みの合計時間です。権限があれば「工数を編集」で時・分を直せます。</li>
  <li>工数は案件・種別・人ごとの集計に使われます（建物詳細やタスク一覧など）。</li>
</ul>
<p><strong>メリット：</strong>「今日どれだけやったか」を手計算しなくて済みます。実績の把握にも役立ちます。</p>

<?php hethong_end(); ?>
