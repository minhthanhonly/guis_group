<?php

class Backup extends ApplicationModel
{
    /** Rows per INSERT statement (phpMyAdmin extended insert style). */
    private const INSERT_BATCH_SIZE = 500;

    /** Max bytes per INSERT chunk to stay under typical max_allowed_packet limits. */
    private const MAX_INSERT_CHUNK_BYTES = 1048576;

    /** @var resource|null */
    private $gzipHandle = null;

    private function requireAdminUser()
    {
        if (($_SESSION['userid'] ?? '') !== 'admin') {
            $this->died('権限がありません。');
        }
    }

    public function info()
    {
        $this->requireAdminUser();
        $this->connect();

        $tables = $this->getDatabaseTables();
        return [
            'status' => 'ok',
            'database' => DB_DATABASE,
            'hostname' => DB_HOSTNAME,
            'table_count' => count($tables),
            'tables' => $tables,
            'generated_at' => date('Y-m-d H:i:s'),
            'gzip_available' => function_exists('gzopen'),
        ];
    }

    public function streamDownload()
    {
        $this->requireAdminUser();
        $this->connect();

        @set_time_limit(0);
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '512M');
        }

        $compress = function_exists('gzopen');
        if (isset($_GET['compress'])) {
            $compress = $_GET['compress'] === '1' || $_GET['compress'] === 'true';
        }

        $filename = DB_DATABASE . '_backup_' . date('Ymd_His') . '.sql';
        if ($compress) {
            $filename .= '.gz';
            $this->gzipHandle = @gzopen('php://output', 'wb6');
            if (!$this->gzipHandle) {
                $compress = false;
                $filename = str_replace('.gz', '', $filename);
            }
        }

        header('Content-Type: application/sql; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $this->write("-- Database backup\n");
        $this->write("-- Generated: " . date('Y-m-d H:i:s') . "\n");
        $this->write("-- Database: " . DB_DATABASE . "\n\n");
        $this->write("SET NAMES utf8mb4;\n");
        $this->write("SET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach ($this->getDatabaseTables() as $table) {
            $this->streamTableDump($table);
            $this->flushOutput();
        }

        $this->write("SET FOREIGN_KEY_CHECKS=1;\n");

        if ($this->gzipHandle) {
            gzclose($this->gzipHandle);
            $this->gzipHandle = null;
        }
        exit;
    }

    private function write($data)
    {
        if ($this->gzipHandle) {
            gzwrite($this->gzipHandle, $data);
            return;
        }
        echo $data;
    }

    private function flushOutput()
    {
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        flush();
    }

    private function getDatabaseTables()
    {
        $tables = [];
        $result = mysqli_query($this->handler, 'SHOW TABLES');
        if (!$result) {
            $this->died('テーブル一覧の取得に失敗しました。');
        }
        while ($row = mysqli_fetch_row($result)) {
            if (!empty($row[0])) {
                $tables[] = $row[0];
            }
        }
        mysqli_free_result($result);
        sort($tables);
        return $tables;
    }

    private function streamTableDump($table)
    {
        $tableEsc = str_replace('`', '``', $table);
        $createResult = mysqli_query($this->handler, "SHOW CREATE TABLE `{$tableEsc}`");
        if (!$createResult) {
            return;
        }
        $createRow = mysqli_fetch_row($createResult);
        mysqli_free_result($createResult);
        if (empty($createRow[1])) {
            return;
        }

        $this->write("DROP TABLE IF EXISTS `{$tableEsc}`;\n");
        $this->write($createRow[1] . ";\n\n");

        $dataResult = mysqli_query($this->handler, "SELECT * FROM `{$tableEsc}`");
        if (!$dataResult) {
            $this->write("\n");
            return;
        }

        $columns = [];
        while ($field = mysqli_fetch_field($dataResult)) {
            $columns[] = '`' . str_replace('`', '``', $field->name) . '`';
        }
        $columnList = implode(',', $columns);
        $insertPrefix = "INSERT INTO `{$tableEsc}` ({$columnList}) VALUES\n";

        $batchRows = [];
        $batchBytes = 0;

        while ($row = mysqli_fetch_row($dataResult)) {
            $values = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $values[] = 'NULL';
                } else {
                    $values[] = "'" . mysqli_real_escape_string($this->handler, $value) . "'";
                }
            }
            $rowSql = '(' . implode(',', $values) . ')';
            $batchRows[] = $rowSql;
            $batchBytes += strlen($rowSql) + 1;

            if (count($batchRows) >= self::INSERT_BATCH_SIZE || $batchBytes >= self::MAX_INSERT_CHUNK_BYTES) {
                $this->writeInsertBatch($insertPrefix, $batchRows);
                $batchRows = [];
                $batchBytes = 0;
            }
        }
        mysqli_free_result($dataResult);

        if (!empty($batchRows)) {
            $this->writeInsertBatch($insertPrefix, $batchRows);
        }

        $this->write("\n");
    }

    private function writeInsertBatch($insertPrefix, array $batchRows)
    {
        if (empty($batchRows)) {
            return;
        }
        $this->write($insertPrefix . implode(",\n", $batchRows) . ";\n");
    }
}
