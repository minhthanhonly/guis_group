<?php
require_once('../application/loader.php');
$view->heading('変更履歴');

$changelogPath = dirname(__DIR__) . '/docs/CHANGELOG.md';
$raw = is_readable($changelogPath) ? file_get_contents($changelogPath) : '';

/**
 * Very small Markdown subset → HTML for CHANGELOG.md (no DB, no Composer).
 * Supports: # ## ###, ---, **bold**, bullet lists, paragraphs, links.
 */
function caily_changelog_md_to_html($md) {
    $md = str_replace(["\r\n", "\r"], "\n", (string)$md);
    // Drop the trailing "記録の書き方" maintenance section from the public page
    if (preg_match('/\n---\n\n## 記録の書き方/u', $md, $m, PREG_OFFSET_CAPTURE)) {
        $md = substr($md, 0, $m[0][1]);
    }

    $lines = explode("\n", $md);
    $html = [];
    $inList = false;
    $para = [];

    $flushPara = function () use (&$html, &$para) {
        if (empty($para)) {
            return;
        }
        $text = trim(implode(' ', $para));
        $para = [];
        if ($text === '') {
            return;
        }
        $html[] = '<p>' . caily_changelog_inline($text) . '</p>';
    };
    $closeList = function () use (&$html, &$inList) {
        if ($inList) {
            $html[] = '</ul>';
            $inList = false;
        }
    };

    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '---') {
            $flushPara();
            $closeList();
            $html[] = '<hr class="my-4">';
            continue;
        }
        if (preg_match('/^(#{1,3})\s+(.+)$/u', $trim, $hm)) {
            $flushPara();
            $closeList();
            $level = strlen($hm[1]);
            $tag = 'h' . $level;
            $cls = $level === 1 ? 'mb-3' : ($level === 2 ? 'mt-4 mb-2 h4' : 'mt-3 mb-2 h6 text-body');
            $html[] = '<' . $tag . ' class="' . $cls . '">' . caily_changelog_inline($hm[2]) . '</' . $tag . '>';
            continue;
        }
        if (preg_match('/^[-*]\s+(.+)$/u', $trim, $lm)) {
            $flushPara();
            if (!$inList) {
                $html[] = '<ul class="mb-3">';
                $inList = true;
            }
            $html[] = '<li>' . caily_changelog_inline($lm[1]) . '</li>';
            continue;
        }
        if ($trim === '') {
            $flushPara();
            $closeList();
            continue;
        }
        $closeList();
        $para[] = $trim;
    }
    $flushPara();
    $closeList();

    return implode("\n", $html);
}

function caily_changelog_inline($text) {
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $text);
    $text = preg_replace(
        '/\[(.+?)\]\((https?:\/\/[^\s\)]+)\)/u',
        '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>',
        $text
    );
    return $text;
}

$bodyHtml = $raw !== ''
    ? caily_changelog_md_to_html($raw)
    : '<div class="alert alert-warning">変更履歴ファイルが見つかりません。</div>';
?>

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
        <div>
            <h4 class="mb-1"><span data-i18n="変更履歴">変更履歴</span></h4>
            <p class="text-muted mb-0 small">
                現在のバージョン：<strong>v<?= htmlspecialchars(APP_VERSION, ENT_QUOTES, 'UTF-8') ?></strong>
            </p>
        </div>
        <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i><span data-i18n="戻る">戻る</span>
        </a>
    </div>

    <div class="card">
        <div class="card-body changelog-content">
            <?= $bodyHtml ?>
        </div>
    </div>
</div>

<style>
.changelog-content h1 { font-size: 1.35rem; }
.changelog-content h2 {
    border-bottom: 1px solid var(--bs-border-color);
    padding-bottom: 0.4rem;
}
.changelog-content ul { padding-left: 1.25rem; }
.changelog-content li { margin-bottom: 0.35rem; }
</style>

<?php
$view->footing();
?>
