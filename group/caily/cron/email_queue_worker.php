<?php
// Email queue worker: xử lý bảng email_queue và gửi mail thực tế.
// Chạy bằng cron, ví dụ:
//   php cron/email_queue_worker.php

chdir(__DIR__ . '/..');

require_once __DIR__ . '/../application/config.php';
require_once __DIR__ . '/../application/library/connectionmysql.php';
require_once __DIR__ . '/../application/model/model.php';

$now = date('Y-m-d H:i:s');


class EmailQueueWorker extends Model
{
    var $table;

    function __construct()
    {
        $this->table = DB_PREFIX . 'email_queue';
        $this->connect();
    }

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
                    if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
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

    public function run($limit = 20)
    {
        // Lấy các email pending, khóa soft bằng cách set status = 'processing'
        $rows = $this->fetchAll(
            "SELECT * FROM {$this->table} WHERE status = 'pending' ORDER BY id ASC LIMIT " . intval($limit)
        );
        if (!$rows) {
            return;
        }

        foreach ($rows as $row) {
            $id = (int)$row['id'];
            // đánh dấu đang xử lý
            $this->query_update(
                ['status' => 'processing', 'updated_at' => date('Y-m-d H:i:s')],
                ['id' => $id]
            );
            $this->processOne($row);
        }
    }

    private function processOne($job)
    {
        $id = (int)$job['id'];
        $userId = $job['user_id'];
        $subject = $job['subject'];
        $body = $job['body'];
        $attempts = isset($job['attempts']) ? (int)$job['attempts'] : 0;

        $now = date('Y-m-d H:i:s');

        if (empty($userId) || trim((string)$subject) === '' || $body === null) {
            $this->query_update(
                [
                    'status' => 'failed',
                    'last_error' => 'Invalid job data',
                    'updated_at' => $now
                ],
                ['id' => $id]
            );
            return;
        }

        $userRow = $this->fetchOne("SELECT user_email, realname FROM " . DB_PREFIX . "user WHERE userid = '" . $this->quote($userId) . "'");
        if (!$userRow || empty($userRow['user_email'])) {
            $this->query_update(
                [
                    'status' => 'failed',
                    'last_error' => 'User email not found',
                    'attempts' => $attempts + 1,
                    'updated_at' => $now
                ],
                ['id' => $id]
            );
            return;
        }

        $to = $userRow['user_email'];
        $toName = isset($userRow['realname']) ? $userRow['realname'] : '';

        $from = $this->env('MAIL_FROM', '');
        if ($from === '') {
            $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $from = 'no-reply@' . $host;
        }
        $fromName = $this->env('MAIL_FROM_NAME', defined('APP_NAME') ? APP_NAME : 'System');

        $smtpHost = $this->env('SMTP_HOST', '');
        $sent = false;
        $error = '';

        if ($smtpHost) {
            if (!class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
                $autoload = __DIR__ . '/../vendor/autoload.php';
                if (file_exists($autoload)) {
                    require_once $autoload;
                }
            }
            if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
                try {
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

                    $mail->setFrom($from, $fromName);
                    $mail->addAddress($to, $toName);
                    $mail->Subject = $subject;
                    $mail->Body = $body;

                    $mail->send();
                    $sent = true;
                } catch (\Exception $e) {
                    $error = 'SMTP error: ' . $e->getMessage();
                }
            }
        }

        // Fallback gửi trực tiếp bằng mb_send_mail / mail nếu SMTP không dùng được
        if (!$sent) {
            $headers = [
                'From: ' . $from,
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit'
            ];
            $headerStr = implode("\r\n", $headers);
            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

            $ok = false;
            if (function_exists('mb_send_mail')) {
                if (function_exists('mb_language')) {
                    @mb_language('uni');
                }
                if (function_exists('mb_internal_encoding')) {
                    @mb_internal_encoding('UTF-8');
                }
                $ok = @mb_send_mail($to, $encodedSubject, $body, $headerStr);
            } else {
                $ok = @mail($to, $encodedSubject, $body, $headerStr);
            }

            if ($ok) {
                $sent = true;
            } else {
                if ($error === '') {
                    $error = 'Mail() failed';
                }
            }
        }

        if ($sent) {
            $this->query_update(
                [
                    'status' => 'sent',
                    'attempts' => $attempts + 1,
                    'last_error' => '',
                    'updated_at' => $now
                ],
                ['id' => $id]
            );
        } else {
            $this->query_update(
                [
                    'status' => $attempts + 1 >= 5 ? 'failed' : 'pending',
                    'attempts' => $attempts + 1,
                    'last_error' => $error,
                    'updated_at' => $now
                ],
                ['id' => $id]
            );
        }
    }
}

// Chạy worker
$worker = new EmailQueueWorker();
$worker->run(20);

