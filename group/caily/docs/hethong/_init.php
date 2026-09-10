<?php
/**
 * 操作ガイド共通初期化（ログイン必須・アプリレイアウト）
 */
require_once __DIR__ . '/../../application/loader.php';

if (!isset($root) || $root === '') {
    $root = '/';
}

/** @var array<int, array{id:string,file:string,title:string}> */
function hethong_pages() {
    return [
        ['id' => 'index',     'file' => 'index.php',     'title' => 'はじめに'],
        ['id' => 'concepts',  'file' => 'concepts.php',  'title' => '用語と関係'],
        ['id' => 'building',  'file' => 'building.php',  'title' => '建物の管理'],
        ['id' => 'project',   'file' => 'project.php',   'title' => '案件の管理'],
        ['id' => 'task',      'file' => 'task.php',      'title' => 'タスク'],
        ['id' => 'worktime',  'file' => 'worktime.php',  'title' => '作業計測'],
        ['id' => 'files',     'file' => 'files.php',     'title' => '添付・ガント'],
        ['id' => 'payment',   'file' => 'payment.php',   'title' => '決済情報'],
        ['id' => 'notes',     'file' => 'notes.php',     'title' => 'メモ・コメント'],
        ['id' => 'tips',      'file' => 'tips.php',      'title' => '日常の使い方'],
    ];
}

function hethong_base_url() {
    global $root;
    return rtrim((string)$root, '/') . '/docs/hethong/';
}

function hethong_img_url($file) {
    global $root;
    return rtrim((string)$root, '/') . '/docs/images/guide/' . ltrim((string)$file, '/');
}

function hethong_img_path($file) {
    return dirname(__DIR__) . '/images/guide/' . basename((string)$file);
}

/**
 * 画面キャプチャ（ファイルがあるときだけ表示）
 *
 * @param string     $file    images/guide 配下のファイル名
 * @param string     $caption キャプション（任意）
 * @param int|string|null $width 表示幅（任意）。例: 600, '600px', '50%'。未指定時は既定の最大幅
 */
function hethong_figure($file, $caption = '', $width = null) {
    $path = hethong_img_path($file);
    if (!is_readable($path)) {
        return;
    }
    $src = htmlspecialchars(hethong_img_url($file), ENT_QUOTES, 'UTF-8');
    $cap = htmlspecialchars((string)$caption, ENT_QUOTES, 'UTF-8');

    $maxWidth = hethong_figure_max_width($width);
    $styleAttr = $maxWidth !== ''
        ? ' style="max-width:' . htmlspecialchars($maxWidth, ENT_QUOTES, 'UTF-8') . '"'
        : '';

    echo '<figure class="hethong-figure"' . $styleAttr . '>';
    echo '<img src="' . $src . '" alt="' . $cap . '" loading="lazy" class="hethong-figure-img">';
    if ($cap !== '') {
        echo '<figcaption class="hethong-figure-cap">' . $cap . '</figcaption>';
    }
    echo '</figure>';
}

/**
 * @param int|string|null $width
 * @return string CSS max-width 値（空ならインライン指定なし）
 */
function hethong_figure_max_width($width) {
    if ($width === null || $width === '') {
        return '';
    }
    if (is_int($width) || (is_string($width) && ctype_digit(trim($width)))) {
        $px = (int)$width;
        return $px > 0 ? $px . 'px' : '';
    }
    $w = trim((string)$width);
    if (preg_match('/^(\d+(\.\d+)?)(px|%|rem|em)$/i', $w)) {
        return $w;
    }
    return '';
}

function hethong_start($pageId, $heading) {
    global $view;
    $view->heading($heading);
    $GLOBALS['hethong_active'] = $pageId;
    echo '<div class="container-xxl flex-grow-1 container-p-y hethong-wrap">';
    echo '<div class="row g-4">';
    echo '<aside class="col-lg-3">';
    require __DIR__ . '/_nav.php';
    echo '</aside>';
    echo '<div class="col-lg-9">';
    echo '<div class="card"><div class="card-body hethong-content">';
}

function hethong_end() {
    $pages = hethong_pages();
    $active = isset($GLOBALS['hethong_active']) ? $GLOBALS['hethong_active'] : 'index';
    $idx = 0;
    foreach ($pages as $i => $p) {
        if ($p['id'] === $active) {
            $idx = $i;
            break;
        }
    }
    $prev = $idx > 0 ? $pages[$idx - 1] : null;
    $next = $idx < count($pages) - 1 ? $pages[$idx + 1] : null;
    $base = hethong_base_url();

    echo '</div></div>'; // card-body / card

    echo '<div class="d-flex flex-wrap justify-content-between gap-2 mt-3">';
    if ($prev) {
        echo '<a class="btn btn-outline-secondary btn-sm" href="' . htmlspecialchars($base . $prev['file'], ENT_QUOTES, 'UTF-8') . '">';
        echo '<i class="fa fa-arrow-left me-1"></i>' . htmlspecialchars($prev['title'], ENT_QUOTES, 'UTF-8');
        echo '</a>';
    } else {
        echo '<span></span>';
    }
    if ($next) {
        echo '<a class="btn btn-primary btn-sm" href="' . htmlspecialchars($base . $next['file'], ENT_QUOTES, 'UTF-8') . '">';
        echo htmlspecialchars($next['title'], ENT_QUOTES, 'UTF-8') . '<i class="fa fa-arrow-right ms-1"></i>';
        echo '</a>';
    }
    echo '</div>';

    echo '</div></div></div>'; // col / row / container

    echo <<<'CSS'
<style>
.hethong-wrap .hethong-nav .nav-link { color: var(--bs-body-color); border-radius: .375rem; padding: .4rem .75rem; }
.hethong-wrap .hethong-nav .nav-link.active { background: var(--bs-primary); color: #fff; }
.hethong-wrap .hethong-nav .nav-link:hover:not(.active) { background: rgba(var(--bs-primary-rgb), .08); }
.hethong-content h2 { font-size: 1.2rem; margin-top: 1.5rem; margin-bottom: .75rem; padding-bottom: .35rem; border-bottom: 1px solid var(--bs-border-color); }
.hethong-content h2:first-child { margin-top: 0; }
.hethong-content h3 { font-size: 1.05rem; margin-top: 1.25rem; margin-bottom: .5rem; }
.hethong-content table { font-size: .9rem; }
.hethong-figure { margin: 1rem 0 1.5rem; max-width: 1100px; }
.hethong-figure-img { display: block; width: 100%; max-width: 100%; height: auto; border: 1px solid var(--bs-border-color); border-radius: .5rem; background: #fff; image-rendering: auto; }
.hethong-figure-cap { font-size: .85rem; color: var(--bs-secondary-color); margin-top: .5rem; }
.hethong-toc-card a { text-decoration: none; }
.hethong-toc-card:hover { border-color: var(--bs-primary); }
.hethong-flow { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .85rem; white-space: pre-wrap; background: var(--bs-tertiary-bg); border-radius: .5rem; padding: 1rem; }
.hethong-content .table-responsive{
    margin-bottom: 1rem;
}
</style>
CSS;

    global $view;
    $view->footing();
}
