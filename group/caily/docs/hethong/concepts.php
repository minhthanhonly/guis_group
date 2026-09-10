<?php
require_once __DIR__ . '/_init.php';
hethong_start('concepts', '用語と関係');
?>

<h2>用語と関係</h2>
<p>
  ひとつの<strong>建物</strong>の中に複数の<strong>案件</strong>があり、各案件には複数の<strong>タスク</strong>があります。
  タスクごとに<strong>作業計測</strong>で時間を記録できます。
</p>

<div class="table-responsive">
<table class="table table-sm table-bordered align-middle">
  <thead class="table-light"><tr><th>画面上の名称</th><th>わかりやすい意味</th><th>例</th></tr></thead>
  <tbody>
    <tr>
      <td><strong>建物</strong></td>
      <td>建物・お施主様まわりの共通情報</td>
      <td>お客様A邸の工事一式</td>
    </tr>
    <tr>
      <td><strong>案件</strong></td>
      <td>建物に属する部署ごとの依頼・仕事の単位</td>
      <td>意匠、設備、省エネ など</td>
    </tr>
    <tr>
      <td><strong>案件依頼</strong></td>
      <td>建物画面から子案件を作るときの呼び方</td>
      <td>「設備へ案件依頼を作る」</td>
    </tr>
    <tr>
      <td><strong>タスク</strong></td>
      <td>案件の中の細かい作業</td>
      <td>平面図作成、チェック、修正</td>
    </tr>
    <tr>
      <td><strong>工数</strong></td>
      <td>その作業に記録された合計時間</td>
      <td>3時間30分</td>
    </tr>
    <tr>
      <td><strong>作業計測</strong></td>
      <td>いまタイマーで時間を測っている状態</td>
      <td>作業中のストップウォッチ</td>
    </tr>
  </tbody>
</table>
</div>

<h3>関係のイメージ</h3>
<p class="hethong-flow mb-0">建物（建物）
  └─ 案件（案件）
       └─ タスク（タスク）
            └─ 作業計測（時間の記録）</p>

<p class="mt-3 mb-0">
  まず建物を登録し、そこから案件依頼を作り、案件の中でタスクを切って作業する、という流れが基本です。
</p>

<?php hethong_end(); ?>
