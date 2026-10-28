<?php
require_once('../application/loader.php');

if (($_SESSION['authority'] ?? '') !== 'administrator') {
    $message = '権限がありません。';
    require_once(DIR_VIEW . 'die.php');
    exit;
}

$state = MaintenanceMode::read();
$notice = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $enabled = isset($_POST['enabled']) && (string) $_POST['enabled'] === '1';
    $messageText = isset($_POST['message']) ? (string) $_POST['message'] : '';
    $error = MaintenanceMode::save($enabled, $messageText, $_SESSION['userid'] ?? '');
    if ($error === '') {
        header('Location: maintenance.php?saved=1');
        exit;
    }
    $state = array(
        'enabled' => $enabled,
        'message' => $messageText,
        'updated_at' => $state['updated_at'] ?? '',
        'updated_by' => $state['updated_by'] ?? '',
    );
} elseif (isset($_GET['saved'])) {
    $notice = '保存しました。';
    $state = MaintenanceMode::read();
}

$view->heading('メンテナンス');
$enabledChecked = !empty($state['enabled']);
?>
<div id="app">
    <div class="container-fluid mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 data-i18n="メンテナンス">メンテナンス</h2>
                    <?php if ($enabledChecked): ?>
                        <span class="badge bg-danger" data-i18n="有効">有効</span>
                    <?php else: ?>
                        <span class="badge bg-secondary" data-i18n="無効">無効</span>
                    <?php endif; ?>
                </div>

                <?php if ($notice !== ''): ?>
                <div class="alert alert-success" role="status">
                    <span data-i18n="保存しました。"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($error !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-body">
                        <p class="text-muted" data-i18n="有効にすると、管理者以外は画面とAPIを利用できなくなります。ログイン画面は残り、管理者だけサインインできます。">
                            有効にすると、管理者以外は画面とAPIを利用できなくなります。ログイン画面は残り、管理者だけサインインできます。
                        </p>
                        <form method="post" action="maintenance.php">
                            <div class="form-check form-switch mb-4">
                                <input type="hidden" name="enabled" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="maintenance-enabled" name="enabled" value="1" <?php echo $enabledChecked ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="maintenance-enabled" data-i18n="メンテナンスモード">メンテナンスモード</label>
                            </div>
                            <div class="mb-4">
                                <label class="form-label" for="maintenance-message" data-i18n="表示メッセージ">表示メッセージ</label>
                                <textarea class="form-control" id="maintenance-message" name="message" rows="4" maxlength="500" placeholder="ただいまシステムのメンテナンスを行っています。しばらくしてから再度お試しください。"><?php echo htmlspecialchars((string) ($state['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <div class="form-text" data-i18n="空欄のときは標準のメッセージを表示します。500文字以内。">空欄のときは標準のメッセージを表示します。500文字以内。</div>
                            </div>
                            <button type="submit" class="btn btn-primary" data-i18n="保存">保存</button>
                        </form>
                    </div>
                </div>

                <?php if (!empty($state['updated_at'])): ?>
                <p class="text-muted small mb-0">
                    <span data-i18n="最終更新">最終更新</span>:
                    <?php echo htmlspecialchars((string) $state['updated_at'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if (!empty($state['updated_by'])): ?>
                        (<?php echo htmlspecialchars((string) $state['updated_by'], ENT_QUOTES, 'UTF-8'); ?>)
                    <?php endif; ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php $view->footing(); ?>
