<?php
require_once __DIR__ . '/_init.php';
hethong_start('tips', '日常の使い方');
?>

<h2>日常の使い方</h2>

<ol>
  <li><strong>一日の始め：</strong>「案件一覧」→ 部署を選択 →「私の案件」、または F3「マイタスク」</li>
  <li><strong>作業前：</strong>該当タスクを開き、作業計測を開始</li>
  <li><strong>休憩・終業：</strong>タイマーを止める（画面隅のウィジェット、または行の終了ボタン）</li>
  <li><strong>新しい依頼：</strong>「建物詳細」→「案件依頼作成」→ 部署を指定 → その後タスクを作成</li>
  <li><strong>期限が近いとき：</strong>案件一覧の「7日以内」「期限超過」などのフィルター</li>
  <li><strong>報告用：</strong>絞り込み後に「Excel出力」</li>
  <li><strong>一覧が初めてのとき：</strong>「UIガイド」で各ボタンの説明を見る</li>
  <li><strong>すばやく探す：</strong>F1 / Ctrl+K</li>
</ol>

<p>
  <strong>権限について：</strong>編集・削除・新規・決済変更などのボタンは、権限がある人にだけ表示されます。
  見えない場合は不具合ではなく、管理者・部署の責任者に確認してください。
</p>

<h3>画面ラベル早見表</h3>
<div class="table-responsive">
<table class="table table-sm table-bordered align-middle">
  <thead class="table-light"><tr><th>画面上の表示</th><th>短い意味</th></tr></thead>
  <tbody>
    <tr><td>建物一覧 / 建物登録 / 建物詳細</td><td>建物の一覧・登録・詳細</td></tr>
    <tr><td>案件一覧 / 案件詳細 / 案件依頼作成</td><td>案件の一覧・詳細・建物からの作成</td></tr>
    <tr><td>お施主様名 / 管理番号 / 工事番号</td><td>お施主名・管理番号・工事番号</td></tr>
    <tr><td>依頼（意匠・設備・省エネ…）</td><td>依頼の種類</td></tr>
    <tr><td>ステータス / 進捗 / 予定工程</td><td>状態・進捗・工程予定</td></tr>
    <tr><td>タスク / 種別 / 優先度</td><td>作業・種別・優先度</td></tr>
    <tr><td>工数 / 作業計測中 / 作業時間を開始・終了</td><td>時間合計・計測中・開始・終了</td></tr>
    <tr><td>マイタスク / Todoリスト</td><td>自分のタスク / 個人リスト</td></tr>
    <tr><td>図面 / 添付ファイル / ガントチャート</td><td>図面・添付・ガント</td></tr>
    <tr><td>決済情報 / 見積 / 請求 / 見積書</td><td>決済・見積・請求・見積書</td></tr>
    <tr><td>入金管理</td><td>入金の管理</td></tr>
    <tr><td>メモ / コメント / お気に入り</td><td>メモ・コメント・お気に入り</td></tr>
    <tr><td>高度なフィルター / Excel出力 / 列の表示</td><td>詳細絞込・Excel・列切替</td></tr>
    <tr><td>案件を編集 / UIガイド</td><td>クイック編集・画面ガイド</td></tr>
    <tr><td>保存 / キャンセル / 戻る / 編集 / 削除</td><td>保存・取消・戻る・編集・削除</td></tr>
  </tbody>
</table>
</div>

<h3>初めての方向けの手順</h3>
<pre class="hethong-flow mb-0">1) 建物登録
2) 建物詳細 → 案件依頼作成（部署ごと）
3) 各案件 → タスク作成
4) 作業 → 作業計測
5) 進捗・ステータス・納期を更新
6) 必要に応じて見積・請求を入力
7) 図面・添付・メモを追加
8) 条件がそろったら案件を完了</pre>

<?php hethong_end(); ?>
