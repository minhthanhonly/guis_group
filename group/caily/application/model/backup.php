<?php

class Backup extends ApplicationModel
{
    /** Rows per INSERT statement (phpMyAdmin extended insert style). */
    private const INSERT_BATCH_SIZE = 500;

    /** Max bytes per INSERT chunk to stay under typical max_allowed_packet limits. */
    private const MAX_INSERT_CHUNK_BYTES = 1048576;

    /** @var resource|null */
    private $gzipHandle = null;

    /** @var resource|null */
    private $fileHandle = null;

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

        $this->writeFullDump();

        if ($this->gzipHandle) {
            gzclose($this->gzipHandle);
            $this->gzipHandle = null;
        }
        exit;
    }

    /**
     * CLI / cron: dump DB to .sql then pack as .zip.
     * @param string|null $destinationDir Directory for the zip (default: system temp)
     * @return array{zip_path:string,zip_name:string,sql_name:string,bytes:int,table_count:int,database:string}
     */
    public function createZipBackup($destinationDir = null)
    {
        $this->connect();

        @set_time_limit(0);
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '512M');
        }

        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('ZipArchive extension is not available.');
        }

        $dir = $destinationDir !== null && $destinationDir !== ''
            ? rtrim($destinationDir, "/\\")
            : sys_get_temp_dir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create backup directory: ' . $dir);
        }
        if (!is_writable($dir)) {
            throw new RuntimeException('Backup directory is not writable: ' . $dir);
        }

        $stamp = date('Ymd_His');
        $sqlName = DB_DATABASE . '_backup_' . $stamp . '.sql';
        $zipName = DB_DATABASE . '_backup_' . $stamp . '.zip';
        $sqlPath = $dir . DIRECTORY_SEPARATOR . $sqlName;
        $zipPath = $dir . DIRECTORY_SEPARATOR . $zipName;

        $this->fileHandle = @fopen($sqlPath, 'wb');
        if (!$this->fileHandle) {
            throw new RuntimeException('Cannot open SQL file for writing: ' . $sqlPath);
        }

        $tables = [];
        try {
            $tables = $this->getDatabaseTables();
            $this->writeFullDump($tables);
        } finally {
            if ($this->fileHandle) {
                fclose($this->fileHandle);
                $this->fileHandle = null;
            }
        }

        if (!is_file($sqlPath) || filesize($sqlPath) === 0) {
            @unlink($sqlPath);
            throw new RuntimeException('SQL dump file is empty or missing.');
        }

        $zip = new ZipArchive();
        $opened = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($opened !== true) {
            @unlink($sqlPath);
            throw new RuntimeException('Cannot create zip archive (code ' . $opened . ').');
        }
        if (!$zip->addFile($sqlPath, $sqlName)) {
            $zip->close();
            @unlink($sqlPath);
            @unlink($zipPath);
            throw new RuntimeException('Failed to add SQL file into zip.');
        }
        $zip->close();
        @unlink($sqlPath);

        if (!is_file($zipPath)) {
            throw new RuntimeException('Zip file was not created.');
        }

        return [
            'zip_path' => $zipPath,
            'zip_name' => $zipName,
            'sql_name' => $sqlName,
            'bytes' => (int)filesize($zipPath),
            'table_count' => count($tables),
            'database' => DB_DATABASE,
        ];
    }

    private function writeFullDump(array $tables = null)
    {
        if ($tables === null) {
            $tables = $this->getDatabaseTables();
        }

        $this->write("-- Database backup\n");
        $this->write("-- Generated: " . date('Y-m-d H:i:s') . "\n");
        $this->write("-- Database: " . DB_DATABASE . "\n\n");
        $this->write("SET NAMES utf8mb4;\n");
        $this->write("SET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach ($tables as $table) {
            $this->streamTableDump($table);
            $this->flushOutput();
        }

        $this->write("SET FOREIGN_KEY_CHECKS=1;\n");
    }

    private function write($data)
    {
        if ($this->gzipHandle) {
            gzwrite($this->gzipHandle, $data);
            return;
        }
        if ($this->fileHandle) {
            fwrite($this->fileHandle, $data);
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
