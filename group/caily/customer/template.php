<?php
require_once('../application/loader.php');
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="customer_import_template.csv"');
$bom = "\xEF\xBB\xBF";
echo $bom;
$fp = fopen('php://output', 'w');
$headers = array(
    'category_id',
    'company_name',
    'company_name_kana',
    'name',
    'name_kana',
    'branch',
    'department',
    'position',
    'title',
    'tel',
    'fax',
    'email',
    'zip',
    'address1',
    'address2',
    'status',
    'guis_department',
    'memo'
);
fputcsv($fp, $headers);
fputcsv($fp, array(
    '1',
    'サンプル会社',
    'サンプルガイシャ',
    '山田太郎',
    'ヤマダタロウ',
    '本社',
    '営業部',
    '部長',
    '様',
    '03-1234-5678',
    '03-1234-5679',
    'sample@example.com',
    '100-0001',
    '東京都千代田区',
    '1-1-1',
    '1',
    '1',
    'メモ'
));
fclose($fp);
exit;
