<?php
require_once __DIR__ . '/_init.php';
hethong_start('notes', 'メモ・コメント');
?>

<h2>メモ・コメント・お気に入り</h2>

<div class="table-responsive">
<table class="table table-sm table-bordered align-middle">
  <thead class="table-light"><tr><th>機能</th><th>使い方</th></tr></thead>
  <tbody>
    <tr>
      <td>「メモ」（建物・案件・一覧）</td>
      <td>社内メモ。「重要メモ」にできる場合があります</td>
    </tr>
    <tr>
      <td>「CAILYメモ」/「GUISメモ」</td>
      <td>CAILY側・GUIS側でメモを分けて残す。項目に紐づけられることもあります</td>
    </tr>
    <tr>
      <td>案件の「コメント」</td>
      <td>スレッド形式のやり取り</td>
    </tr>
    <tr>
      <td>星「お気に入り」</td>
      <td>よく使う建物・案件をマーク。「お気に入りのみ」で絞り込み</td>
    </tr>
  </tbody>
</table>
</div>

<p class="mb-0">短い連絡はコメント、長く残したい社内メモはメモ、という使い分けがわかりやすいです。</p>

<?php hethong_end(); ?>
