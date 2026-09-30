<?php
// DB backup → .zip → email attachment.
// Chạy bằng cron, ví dụ:
//   php cron/db_backup_email.php
//
// Zip được lưu tại cron/backups/ và chỉ giữ 3 ngày gần nhất.
// Env (optional):
//   BACKUP_EMAIL_TO     (default: thanhonly@gmail.com)
//   BACKUP_RETENTION_DAYS (default: 3)

chdir(__DIR__ . '/..');

require_once __DIR__ . '/../application/config.php';
require_once __DIR__ . '/../application/library/connectionmysql.php';
require_once __DIR__ . '/../application/model/model.php';
require_once __DIR__ . '/../application/model/applicationmodel.php';
require_once __DIR__ . '/../application/model/backup.php';

$__vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($__vendorAutoload)) {
    require_once $__vendorAutoload;
}
unset($__vendorAutoload);

class DbBackupEmailWorker
{
    private const BACKUP_DIR = __DIR__ . '/backups';
    private const DEFAULT_RETENTION_DAYS = 3;

    private function env($key, $default = '')
    {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        static $envCache = null;
        if ($envCache === null) {
            $envCache = [];
            $envPath = __DIR__ . '/../.env';
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                        continue;
                    }
                    list($k, $v) = explode('=', $line, 2);
                    $k = trim($k);
                    $v = trim($v);
                    if ($k !== '' && $v !== '') {
                        $envCache[$k] = $v;
                    }
                }
            }
        }
        if (isset($envCache[$key]) && $envCache[$key] !== '') {
            return $envCache[$key];
        }
        return $default;
    }

    private function log($message)
    {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        echo $line;
        @file_put_contents(__DIR__ . '/db_backup_email.log', $line, FILE_APPEND);
    }

    public function run()
    {
        $to = $this->env('BACKUP_EMAIL_TO', 'thanhonly@gmail.com');
        $retentionDays = (int)$this->env('BACKUP_RETENTION_DAYS', (string)self::DEFAULT_RETENTION_DAYS);
        if ($retentionDays < 1) {
            $retentionDays = self::DEFAULT_RETENTION_DAYS;
        }
        $backupDir = self::BACKUP_DIR;
        $zipPath = null;

        $this->log('Start DB backup email to ' . $to);

        try {
            $backup = new Backup();
            $info = $backup->createZipBackup($backupDir);
            $zipPath = $info['zip_path'];
            $sizeMb = round($info['bytes'] / 1048576, 2);

            $this->log(sprintf(
                'Zip created: %s (%s MB, %d tables, db=%s)',
                $info['zip_name'],
                $sizeMb,
                $info['table_count'],
                $info['database']
            ));

            $host = gethostname() ?: 'unknown-host';
            $appName = defined('APP_NAME') ? APP_NAME : 'CAILY';
            $subject = sprintf(
                '[%s] DB backup %s (%s)',
                $appName,
                $info['database'],
                date('Y-m-d H:i')
            );
            $body = implode("\n", [
                'Database backup completed.',
                '',
                'Database : ' . $info['database'],
                'Tables   : ' . $info['table_count'],
                'File     : ' . $info['zip_name'],
                'Size     : ' . $sizeMb . ' MB',
                'Host     : ' . $host,
                'Path     : ' . $zipPath,
                'Retention: last ' . $retentionDays . ' days',
                'Generated: ' . date('Y-m-d H:i:s'),
                '',
                'SQL dump is inside the attached zip.',
            ]);

            $this->sendMailWithAttachment($to, $subject, $body, $zipPath, $info['zip_name']);
            $this->log('Email sent successfully.');
        } catch (Throwable $e) {
            $this->log('ERROR: ' . $e->getMessage());
            try {
                $this->sendMailPlain(
                    $to,
                    '[Backup FAILED] ' . (defined('APP_NAME') ? APP_NAME : 'CAILY') . ' ' . date('Y-m-d H:i'),
                    "Database backup/email failed.\n\n" . $e->getMessage() . "\nHost: " . (gethostname() ?: 'unknown')
                );
            } catch (Throwable $mailError) {
                $this->log('ERROR sending failure notice: ' . $mailError->getMessage());
            }
            $this->pruneOldBackups($backupDir, $retentionDays);
            exit(1);
        }

        $removed = $this->pruneOldBackups($backupDir, $retentionDays);
        if ($removed > 0) {
            $this->log('Pruned ' . $removed . ' backup(s) older than ' . $retentionDays . ' day(s).');
        }

        $this->log('Done.');
    }

    /**
     * Delete *.zip in backup dir older than $retentionDays (by mtime).
     * @return int number of deleted files
     */
    private function pruneOldBackups($dir, $retentionDays)
    {
        if (!is_dir($dir)) {
            return 0;
        }
        $cutoff = time() - ($retentionDays * 86400);
        $removed = 0;
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.zip') ?: [];
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $mtime = @filemtime($file);
            if ($mtime !== false && $mtime < $cutoff) {
                if (@unlink($file)) {
                    $removed++;
                    $this->log('Deleted old backup: ' . basename($file));
                }
            }
        }
        return $removed;
    }

    private function ensurePhpMailer()
    {
        if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer', false)) {
            return;
        }

        $appRoot = dirname(__DIR__);
        $tried = [];

        $autoloadCandidates = [
            $appRoot . '/vendor/autoload.php',
            __DIR__ . '/../vendor/autoload.php',
            (defined('DIR_PATH') ? dirname(DIR_PATH) : '') . '/vendor/autoload.php',
            getcwd() . '/vendor/autoload.php',
        ];
        foreach ($autoloadCandidates as $autoload) {
            if ($autoload === '/vendor/autoload.php' || $autoload === '') {
                continue;
            }
            $tried[] = $autoload;
            if (is_file($autoload)) {
                require_once $autoload;
                if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
                    return;
                }
            }
        }

        // Fallback: load PHPMailer sources directly (no Composer autoload)
        $srcCandidates = [
            $appRoot . '/vendor/phpmailer/phpmailer/src',
            __DIR__ . '/../vendor/phpmailer/phpmailer/src',
            getcwd() . '/vendor/phpmailer/phpmailer/src',
        ];
        foreach ($srcCandidates as $srcDir) {
            $files = [
                $srcDir . '/Exception.php',
                $srcDir . '/PHPMailer.php',
                $srcDir . '/SMTP.php',
            ];
            $allExist = true;
            foreach ($files as $file) {
                $tried[] = $file;
                if (!is_file($file)) {
                    $allExist = false;
                    break;
                }
            }
            if (!$allExist) {
                continue;
            }
            foreach ($files as $file) {
                require_once $file;
            }
            if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer', false)) {
                return;
            }
        }

        throw new RuntimeException(
            'PHPMailer is not available. Checked: ' . implode(' | ', $tried)
        );
    }

    private function sendMailWithAttachment($to, $subject, $body, $attachmentPath, $attachmentName)
    {
        $from = $this->env('MAIL_FROM', '');
        if ($from === '') {
            $from = 'no-reply@' . (gethostname() ?: 'localhost');
        }
        $fromName = $this->env('MAIL_FROM_NAME', defined('APP_NAME') ? APP_NAME : 'System');
        $smtpHost = $this->env('SMTP_HOST', '');
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $fromHeader = $fromName !== ''
            ? '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>'
            : $from;

        // 1) Prefer PHPMailer SMTP when vendor is available (same as email_queue_worker)
        if ($smtpHost) {
            try {
                $this->ensurePhpMailer();
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $smtpHost;
                $mail->Port = (int)$this->env('SMTP_PORT', '587');
                $mail->SMTPAuth = $this->env('SMTP_AUTH', '1') !== '0';
                $mail->Username = $this->env('SMTP_USER', '');
                $mail->Password = $this->env('SMTP_PASS', '');
                $secure = $this->env('SMTP_SECURE', 'tls');
                if ($secure) {
                    $mail->SMTPSecure = $secure;
                }
                $mail->CharSet = 'UTF-8';
                $mail->Encoding = 'base64';
                $mail->Timeout = 120;
                $mail->setFrom($from, $fromName);
                $mail->addAddress($to);
                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->addAttachment($attachmentPath, $attachmentName);
                $mail->send();
                $this->log('Sent via PHPMailer SMTP.');
                return;
            } catch (Throwable $e) {
                $this->log('PHPMailer unavailable/failed, fallback to mail(): ' . $e->getMessage());
            }
        }

        // 2) Fallback: mail() + MIME multipart
        //    KHÔNG dùng mb_send_mail — nó encode lại body và phá multipart
        //    (Gmail hiện base64 text thay vì file đính kèm).
        if (!is_file($attachmentPath) || !is_readable($attachmentPath)) {
            throw new RuntimeException('Attachment not readable: ' . $attachmentPath);
        }
        $fileData = file_get_contents($attachmentPath);
        if ($fileData === false) {
            throw new RuntimeException('Cannot read attachment: ' . $attachmentPath);
        }

        $boundary = 'caily_backup_' . md5(uniqid((string)mt_rand(), true));
        $safeName = str_replace(["\r", "\n", '"'], '', $attachmentName);
        // MIME body: CRLF theo RFC
        $eol = "\r\n";
        $mimeBody =
            '--' . $boundary . $eol .
            'Content-Type: text/plain; charset=UTF-8' . $eol .
            'Content-Transfer-Encoding: 8bit' . $eol .
            $eol .
            str_replace(["\r\n", "\r", "\n"], $eol, $body) . $eol .
            $eol .
            '--' . $boundary . $eol .
            'Content-Type: application/zip; name="' . $safeName . '"' . $eol .
            'Content-Transfer-Encoding: base64' . $eol .
            'Content-Disposition: attachment; filename="' . $safeName . '"' . $eol .
            $eol .
            chunk_split(base64_encode($fileData), 76, $eol) .
            '--' . $boundary . '--' . $eol;

        // Headers: LF only — sendmail trên Linux dễ lỗi nếu dùng CRLF
        $headerStr = implode("\n", [
            'From: ' . $fromHeader,
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
            'Content-Transfer-Encoding: 7bit',
        ]);

        $ok = @mail($to, $encodedSubject, $mimeBody, $headerStr);
        if (!$ok) {
            throw new RuntimeException('mail() failed while sending backup attachment.');
        }
        $this->log('Sent via mail() MIME attachment.');
    }

    private function sendMailPlain($to, $subject, $body)
    {
        $from = $this->env('MAIL_FROM', '');
        if ($from === '') {
            $from = 'no-reply@' . (gethostname() ?: 'localhost');
        }
        $fromName = $this->env('MAIL_FROM_NAME', defined('APP_NAME') ? APP_NAME : 'System');
        $smtpHost = $this->env('SMTP_HOST', '');

        if ($smtpHost) {
            try {
                $this->ensurePhpMailer();
            } catch (Throwable $e) {
                // fall through to mail()/mb_send_mail
            }
            if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer', false)
                || class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $smtpHost;
                $mail->Port = (int)$this->env('SMTP_PORT', '587');
                $mail->SMTPAuth = $this->env('SMTP_AUTH', '1') !== '0';
                $mail->Username = $this->env('SMTP_USER', '');
                $mail->Password = $this->env('SMTP_PASS', '');
                $secure = $this->env('SMTP_SECURE', 'tls');
                if ($secure) {
                    $mail->SMTPSecure = $secure;
                }
                $mail->CharSet = 'UTF-8';
                $mail->setFrom($from, $fromName);
                $mail->addAddress($to);
                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->send();
                return;
            }
        }

        $headers = [
            'From: ' . $from,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        if (function_exists('mb_send_mail')) {
            @mb_send_mail($to, $encodedSubject, $body, implode("\r\n", $headers));
        } else {
            @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
        }
    }
}

$worker = new DbBackupEmailWorker();
$worker->run();
