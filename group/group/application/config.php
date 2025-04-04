<?php
/*
 * Copyright(c) 2009 limitlink,Inc. All Rights Reserved.
 * http://limitlink.jp/
 * 文字コード UTF-8
 */

/**
 * アプリケーション設定
 */
//アプリケーション
define('APP_TYPE', 'group');

/**
 * 制限設定
 */
//表示件数
define('APP_LIMIT', '10');
//最大表示件数
define('APP_LIMITMAX', '1000');
//アップロードファイル
define('APP_FILESIZE', '10000000');
define('APP_EXTENSION', 'exe');

/**
 * 認証設定
 */
//有効期限
define('APP_EXPIRE', '9999999');
//アイドルタイム
define('APP_IDLE', '9999999');

/**
 * パス設定
 */
//アプリケーションディレクトリ
define('DIR_PATH', dirname(__FILE__).'/');
//モデルディレクトリ
define('DIR_MODEL', DIR_PATH.'model/');
//ビューディレクトリ
define('DIR_VIEW', DIR_PATH.'view/');
//ライブラリディレクトリ
define('DIR_LIBRARY', DIR_PATH.'library/');
//ファイルディレクトリ
define('DIR_UPLOAD', DIR_PATH.'upload/');

/**
 * データベース設定
 */
//データベースの種類
define('DB_STORAGE', 'mysql');
//データベースのホスト名
//define('DB_HOSTNAME', 'mysql653.db.sakura.ne.jp');
define('DB_HOSTNAME', 'localhost');
//データベース名
define('DB_DATABASE', 'guis2_db');
//データベースユーザー名
define('DB_USERNAME', 'root');
// define('DB_USERNAME', 'guis2');
//データベースパスワード
// define('DB_PASSWORD', 'URtMzWthwqB5');
define('DB_PASSWORD', '');
//テーブル接頭辞
define('DB_PREFIX', 'groupware_');
//データベースポート番号
define('DB_PORT', '5432');
//データベース文字コード設定
define('DB_CHARSET', false);
//データベースファイル
define('DB_FILE', DIR_PATH.'database/group.sqlite2');
//郵便番号データファイル
define('DB_POSTCODE', DIR_PATH.'database/KEN_ALL.CSV');

define('ROOT', '/');

error_reporting(E_ERROR | E_PARSE);
?>