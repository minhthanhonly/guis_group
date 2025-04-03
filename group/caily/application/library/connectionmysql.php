<?php
/*
 * Copyright(c) 2009 limitlink,Inc. All Rights Reserved.
 * http://limitlink.jp/
 * 文字コードUTF-8
 */

class Connection {

    public $handler;

    function __construct() {
        $this->handler = mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);

        if ($this->handler) {
            if (defined('DB_CHARSET') && DB_CHARSET) {
                mysqli_set_charset($this->handler, 'utf8');
            }
        } else {
            die('データベース接続に失敗しました: ' . mysqli_connect_error());
        }
    }

    function close() {
        if ($this->handler) {
            return mysqli_close($this->handler);
        } else {
            die('データベースハンドラが見つかりません。');
        }
    }

    function query($query) {
        if ($this->handler) {
            $result = mysqli_query($this->handler, $query);
            if (!$result) {
                die('クエリエラー: ' . mysqli_error($this->handler));
            }
            return $result;
        } else {
            die('データベースハンドラが見つかりません。 '. $query);
        }
    }

    function fetchAll($query) {
        if ($this->handler) {
            $response = $this->query($query);
            $data = array();
            while ($row = mysqli_fetch_assoc($response)) {
                $data[] = $row;
            }
            return $data;
        } else {
            die('データベースハンドラが見つかりません。 '. $query);
        }
    }

    function fetchLimit($query, $offset = 0, $limit = 20) {
        $query .= sprintf(" LIMIT %d, %d", $offset, $limit);
        return $this->fetchAll($query);
    }

    function fetchOne($query) {
        if ($this->handler) {
            $response = $this->query($query);
            $data = mysqli_fetch_assoc($response);
            return is_array($data) ? $data : array();
        } else {
            die('データベースハンドラが見つかりません。' . $query);
        }
    }

    function fetchCount($table, $where = "", $field = "*") {
        $query = sprintf("SELECT COUNT(%s) AS count FROM %s %s", $field, $table, $where);
        if ($this->handler) {
            $response = $this->query($query);
            $row = mysqli_fetch_assoc($response);
            return $row["count"] ?? false;
        } else {
            die('データベースハンドラが見つかりません。' . $query);
        }
    }


    public function update_query($query) {
        if ($this->handler) {
            $this->query($query);
            return mysqli_affected_rows($this->handler);
        } else {
            die('データベースハンドラが見つかりません。' . $query);
        }
    }

    function insertid() {
        if ($this->handler) {
            return mysqli_insert_id($this->handler);
        } else {
            die('データベースハンドラが見つかりません。');
        }
    }

    function table() {
        $query = "SHOW TABLES FROM " . DB_DATABASE;
        if ($this->handler) {
            $response = $this->query($query);
            $array = array();
            while ($row = mysqli_fetch_assoc($response)) {
                $array[] = $row["Tables_in_" . DB_DATABASE];
            }
            return $array;
        } else {
            die('データベースハンドラが見つかりません。' . $query);
        }
    }

    function quote($string) {
        if ($this->handler) {
            return mysqli_real_escape_string($this->handler, $string);
        } else {
            die('Quoteエラー: データベースハンドラが見つかりません。');
        }
    }
}
?>