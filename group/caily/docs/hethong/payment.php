<?php
require_once __DIR__ . '/_init.php';
hethong_start('payment', '決済情報');
?>

<h2>決済情報</h2>

<h3>案件詳細の決済ブロック</h3>
<?php hethong_figure('07-payment-panel.png', '案件詳細の決済情報の例', 600); ?>
<p>「決済情報」は大きく次の2つです。</p>

<p><strong>見積</strong></p>
<ul>
  <li>見積日、税抜金額、消費税10%、税込合計</li>
  <li>見積番号、状態：未発行 / 発行済 / 無償</li>
</ul>

<p><strong>請求</strong></p>
<ul>
  <li>請求日、金額（「見積と同額」ボタンあり）、証憑番号</li>
  <li>状態：未発行 / 請求準備 / 発行済 / 無償</li>
</ul>

<p>「決済備考」と「履歴」もあります。</p>
<p>
  案件を<strong>完了</strong>にするとき、見積・請求が<strong>発行済</strong>または<strong>無償</strong>であることが求められる場合があります（システムの設定による）。
</p>

<p><strong>メリット：</strong>技術の進捗と、見積・請求の状態を同じ案件で追えます。</p>

<?php hethong_end(); ?>
