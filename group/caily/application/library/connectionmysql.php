<?php
/*
 * Copyright(c) 2009 limitlink,Inc. All Rights Reserved.
 * http://limitlink.jp/
 * 文字コードUTF-8
 */

class DatabaseException extends RuntimeException
{
}

class Connection {

    public $handler;

    private function fail($message)
    {
        error_log('Database error: ' . $message);
        throw new DatabaseException($message);
    }

    function __construct() {
        $this->handler = mysqli_connect(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);

        if ($this->handler) {
            if (defined('DB_CHARSET') && DB_CHARSET) {
                if (!mysqli_set_charset($this->handler, DB_CHARSET)) {
                    $this->fail('Failed to set character set: ' . mysqli_error($this->handler));
                }
            }
        } else {
            $this->fail('データベース接続に失敗しました: ' . mysqli_connect_error());
        }
    }

    function close() {
        if ($this->handler) {
            return mysqli_close($this->handler);
        }
        return false;
    }

    function query($query) {
        if (!$this->handler) {
            $this->fail('データベースハンドラが見つかりません。');
        }
        $result = mysqli_query($this->handler, $query);
        if (!$result) {
            $this->fail('クエリエラー: ' . mysqli_error($this->handler));
        }
        return $result;
    }

    function fetchAll($query) {
        $response = $this->query($query);
        $data = array();
        while ($row = mysqli_fetch_assoc($response)) {
            $data[] = $row;
        }
        return $data;
    }

    function fetchLimit($query, $offset = 0, $limit = 20) {
        $query .= sprintf(" LIMIT %d, %d", $offset, $limit);
        return $this->fetchAll($query);
    }

    function fetchOne($query) {
        $response = $this->query($query);
        $data = mysqli_fetch_assoc($response);
        return is_array($data) ? $data : array();
    }

    function fetchCount($table, $where = "", $field = "*") {
        $query = sprintf("SELECT COUNT(%s) AS count FROM %s %s", $field, $table, $where);
        $response = $this->query($query);
        $row = mysqli_fetch_assoc($response);
        return $row["count"] ?? false;
    }


    public function update_query($query) {
        $this->query($query);
        return mysqli_affected_rows($this->handler);
    }

    function insertid() {
        if (!$this->handler) {
            $this->fail('データベースハンドラが見つかりません。');
        }
        return mysqli_insert_id($this->handler);
    }

    function table() {
        $query = "SHOW TABLES FROM " . DB_DATABASE;
        $response = $this->query($query);
        $array = array();
        while ($row = mysqli_fetch_assoc($response)) {
            $array[] = $row["Tables_in_" . DB_DATABASE];
        }
        return $array;
    }

    function quote($string) {
        if (!$this->handler) {
            $this->fail('Quoteエラー: データベースハンドラが見つかりません。');
        }
        return mysqli_real_escape_string($this->handler, $string);
    }
}
?>
