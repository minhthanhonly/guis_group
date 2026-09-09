<?php
/**
 * Gzip PHP responses (HTML / JSON) behind nginx.
 *
 * kanri.guis.co.jp uses nginx in front of PHP. Many nginx configs clear
 * HTTP_ACCEPT_ENCODING for FastCGI so upstream skips compression — while
 * nginx itself may also leave application/json / dynamic HTML uncompressed.
 * Therefore: if Accept-Encoding is missing, still gzip (all modern browsers OK).
 */
if (defined('CAILY_OUTPUT_COMPRESSION_STARTED')) {
    return;
}
define('CAILY_OUTPUT_COMPRESSION_STARTED', 1);

if (headers_sent()) {
    return;
}
if (!extension_loaded('zlib') || !function_exists('gzencode')) {
    header('X-CAILY-Compress: no-zlib');
    return;
}

$accept = isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? (string) $_SERVER['HTTP_ACCEPT_ENCODING'] : '';
// nginx often sets Accept-Encoding empty for PHP-FPM; fall back to client hint
if ($accept === '' && !empty($_SERVER['HTTP_X_ACCEPT_ENCODING'])) {
    $accept = (string) $_SERVER['HTTP_X_ACCEPT_ENCODING'];
}
if ($accept === '') {
    // Assume gzip — browsers and MCP always send Accept-Encoding: gzip
    $accept = 'gzip';
}
if (stripos($accept, 'gzip') === false && stripos($accept, 'deflate') === false) {
    header('X-CAILY-Compress: no-accept');
    return;
}

/**
 * Manual buffer gzip — more reliable than zlib.output_compression / ob_gzhandler
 * when ini_set is disabled or nginx strips encoding negotiation.
 */
ob_start(function ($buffer) {
    if ($buffer === '' || $buffer === false) {
        return $buffer;
    }
    // Small JSON APIs (timer/config/todo) are often <512B; still try gzip.
    // Skip only tiny payloads where gzip header overhead cannot win.
    if (strlen($buffer) < 256) {
        return $buffer;
    }
    // Avoid compressing non-text if Content-Type already set to binary
    if (function_exists('headers_list')) {
        foreach (headers_list() as $h) {
            if (stripos($h, 'Content-Encoding:') === 0) {
                return $buffer;
            }
            if (stripos($h, 'Content-Type:') === 0) {
                $type = strtolower(substr($h, 13));
                if (strpos($type, 'text/') === false
                    && strpos($type, 'json') === false
                    && strpos($type, 'javascript') === false
                    && strpos($type, 'xml') === false
                    && strpos($type, 'svg') === false
                ) {
                    return $buffer;
                }
            }
        }
    }

    $gzipped = gzencode($buffer, 6);
    if ($gzipped === false) {
        return $buffer;
    }
    // Tiny JSON (empty todo [], idle timer) often grows with gzip framing — skip.
    if (strlen($gzipped) >= strlen($buffer)) {
        if (!headers_sent()) {
            header('X-CAILY-Compress: skip-no-gain');
        }
        return $buffer;
    }

    if (!headers_sent()) {
        header_remove('Content-Length');
        header('Content-Encoding: gzip');
        header('Vary: Accept-Encoding', false);
        header('X-CAILY-Compress: manual');
    }
    return $gzipped;
});
