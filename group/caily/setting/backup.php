<?php
require_once('../application/loader.php');

if (($_SESSION['userid'] ?? '') !== 'admin') {
    $message = '権限がありません。';
    require_once(DIR_VIEW . 'die.php');
    exit;
}

require_once(dirname(__DIR__) . '/application/model/backup.php');
$backupModel = new Backup();
$backupInfo = $backupModel->info();

$view->heading('データベースバックアップ');
?>
<div id="app">
    <div class="container-fluid mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 data-i18n="データベースバックアップ">データベースバックアップ</h2>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <p class="text-muted mb-4" data-i18n="データベースの全テーブルをSQLファイルとしてダウンロードできます。">
                            データベースの全テーブルをSQLファイルとしてダウンロードできます。
                        </p>

                        <dl class="row mb-0">
                            <dt class="col-sm-4" data-i18n="データベース名">データベース名</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($backupInfo['database'] ?? '', ENT_QUOTES, 'UTF-8'); ?></dd>

                            <dt class="col-sm-4" data-i18n="ホスト">ホスト</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($backupInfo['hostname'] ?? '', ENT_QUOTES, 'UTF-8'); ?></dd>

                            <dt class="col-sm-4" data-i18n="テーブル数">テーブル数</dt>
                            <dd class="col-sm-8"><?php echo (int)($backupInfo['table_count'] ?? 0); ?></dd>
                        </dl>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <span data-i18n="バックアップ対象テーブル">バックアップ対象テーブル</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th data-i18n="テーブル名">テーブル名</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (($backupInfo['tables'] ?? []) as $index => $tableName): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($tableName, ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="alert alert-warning">
                    <i class="ti tabler-alert-triangle me-1"></i>
                    <span data-i18n="バックアップファイルには機密情報が含まれます。安全な場所でのみ保管してください。">
                        バックアップファイルには機密情報が含まれます。安全な場所でのみ保管してください。
                    </span>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <a href="backup_download.php" class="btn btn-primary">
                        <i class="ti tabler-database-export me-1"></i>
                        <span data-i18n="SQLファイルをダウンロード">SQLファイルをダウンロード</span>
                        <?php if (!empty($backupInfo['gzip_available'])): ?>(.sql.gz)<?php endif; ?>
                    </a>
                    <?php if (!empty($backupInfo['gzip_available'])): ?>
                    <a href="backup_download.php?compress=0" class="btn btn-outline-secondary">
                        <span data-i18n="SQLファイルをダウンロード（非圧縮）">SQLファイルをダウンロード（非圧縮）</span>
                    </a>
                    <?php endif; ?>
                </div>
                <p class="text-muted small mt-2 mb-0" data-i18n="phpMyAdminと同様に、複数行を1つのINSERT文にまとめ、既定ではgzip圧縮して出力します。">
                    phpMyAdminと同様に、複数行を1つのINSERT文にまとめ、既定ではgzip圧縮して出力します。
                </p>
            </div>
        </div>
    </div>
</div>
<?php $view->footing(); ?>
