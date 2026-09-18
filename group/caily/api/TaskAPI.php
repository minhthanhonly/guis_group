<?php
require_once __DIR__ . '/loader.php';

$controller->requiring();
require_once __DIR__ . '/guis_plus_auth.php';

class TaskAPI {
    private $table;

    public function __construct() {
        $this->table = DB_PREFIX . 'guis_plus_tasks';
    }

    public function handleRequest() {
        $authError = GuisPlusApiAuth::assertSecretOrFail();
        if ($authError) {
            return $authError;
        }

        $method = $_GET['method'] ?? $_POST['method'] ?? '';
        switch ($method) {
            case 'create':
                return $this->create();
            case 'list':
                return $this->listTasks();
            case 'update':
                return $this->update();
            case 'find_by_email':
                return $this->findByEmail();
            default:
                http_response_code(400);
                return ['success' => false, 'error' => 'Invalid method'];
        }
    }

    private function getUserId() {
        return trim((string) ($_GET['user_id'] ?? $_POST['user_id'] ?? ''));
    }

    private function param($key, $default = '') {
        if (isset($_POST[$key])) {
            return $_POST[$key];
        }
        if (isset($_GET[$key])) {
            return $_GET[$key];
        }
        return $default;
    }

    private function validateUser($user_id) {
        if ($user_id === '') {
            return null;
        }
        $appModel = new ApplicationModel();
        $appModel->connect();
        $uidEsc = $appModel->quote($user_id);
        $user = $appModel->fetchOne(
            "SELECT userid, realname FROM "
            . DB_PREFIX . "user WHERE userid = '" . $uidEsc
            . "' AND (`is_suspend` = '' OR `is_suspend` IS NULL OR is_suspend = '0') LIMIT 1"
        );
        $appModel->close();
        return $user ?: null;
    }

    private function db() {
        $model = new ApplicationModel();
        $model->connect();
        return $model;
    }

    private function mapPriorityToTodo($priority) {
        $p = strtolower(trim((string) $priority));
        if ($p === 'high' || $p === '2') {
            return 2;
        }
        if ($p === 'low' || $p === '0') {
            return 0;
        }
        return 1;
    }

    private function formatTask($row) {
        if (!$row || !is_array($row)) {
            return null;
        }
        $paths = [];
        if (!empty($row['attachment_paths'])) {
            $decoded = json_decode($row['attachment_paths'], true);
            if (is_array($decoded)) {
                $paths = $decoded;
            }
        }
        $todoId = isset($row['todo_id']) ? (int) $row['todo_id'] : 0;
        $url = null;
        if ($todoId > 0) {
            $url = '/todo/view.php?id=' . $todoId;
        }
        return [
            'id' => (int) ($row['id'] ?? 0),
            'task_id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'customer' => (string) ($row['customer'] ?? ''),
            'priority' => (string) ($row['priority'] ?? 'normal'),
            'due_date' => (string) ($row['due_date'] ?? ''),
            'category' => (string) ($row['category'] ?? ''),
            'status' => (string) ($row['status'] ?? 'open'),
            'source' => (string) ($row['source'] ?? 'email'),
            'account_id' => (string) ($row['account_id'] ?? ''),
            'mailbox' => (string) ($row['mailbox'] ?? 'INBOX'),
            'email_uid' => (string) ($row['email_uid'] ?? ''),
            'attachment_paths' => $paths,
            'todo_id' => $todoId,
            'url' => $url,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function createTodo($model, $user, $title, $description, $priority, $dueDate) {
        $todoTable = DB_PREFIX . 'todo';
        $now = date('Y-m-d H:i:s');
        $owner = $model->quote($user['userid']);
        $realname = $model->quote((string) ($user['realname'] ?? ''));
        $titleEsc = $model->quote(mb_substr((string) $title, 0, 1000));
        $commentEsc = $model->quote(mb_substr((string) $description, 0, 10000));
        $prio = (int) $this->mapPriorityToTodo($priority);
        $term = '';
        $noterm = 1;
        if ($dueDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            $term = $dueDate;
            $noterm = 0;
        }
        $termEsc = $model->quote($term);
        $sql = "INSERT INTO {$todoTable}
            (folder_id, todo_parent, todo_title, todo_link, todo_name, todo_term, todo_noterm, todo_priority, todo_comment, todo_complete, todo_sort, todo_completedate, todo_user, created, owner)
            VALUES (0, 0, '{$titleEsc}', '', '{$realname}', '{$termEsc}', {$noterm}, {$prio}, '{$commentEsc}', 0, 0, '', '', '{$now}', '{$owner}')";
        $model->query($sql);
        $row = $model->fetchOne(
            "SELECT id FROM {$todoTable} WHERE owner = '{$owner}' AND created = '{$now}' ORDER BY id DESC LIMIT 1"
        );
        return $row && isset($row['id']) ? (int) $row['id'] : 0;
    }

    private function create() {
        $user_id = $this->getUserId();
        if ($user_id === '') {
            http_response_code(400);
            return ['success' => false, 'error' => 'Missing user_id'];
        }
        $user = $this->validateUser($user_id);
        if (!$user) {
            http_response_code(401);
            return ['success' => false, 'error' => 'Invalid user_id'];
        }

        $title = trim((string) $this->param('title', ''));
        if ($title === '') {
            http_response_code(400);
            return ['success' => false, 'error' => 'Missing title'];
        }

        $description = (string) $this->param('description', '');
        $customer = trim((string) $this->param('customer', ''));
        $priority = trim((string) $this->param('priority', 'normal')) ?: 'normal';
        $due_date = trim((string) $this->param('due_date', ''));
        $category = trim((string) $this->param('category', ''));
        $source = trim((string) $this->param('source', 'email')) ?: 'email';
        $account_id = trim((string) $this->param('account_id', ''));
        $mailbox = trim((string) $this->param('mailbox', 'INBOX')) ?: 'INBOX';
        $email_uid = trim((string) $this->param('email_uid', ''));
        $status = trim((string) $this->param('status', 'open')) ?: 'open';

        $attachmentRaw = $this->param('attachment_paths', '[]');
        if (is_array($attachmentRaw)) {
            $attachment_paths = json_encode(array_values($attachmentRaw), JSON_UNESCAPED_UNICODE);
        } else {
            $decoded = json_decode((string) $attachmentRaw, true);
            $attachment_paths = json_encode(is_array($decoded) ? array_values($decoded) : [], JSON_UNESCAPED_UNICODE);
        }

        if ($due_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
            $due_date = '';
        }

        $model = $this->db();
        $todoId = $this->createTodo($model, $user, $title, $description, $priority, $due_date);
        $now = date('Y-m-d H:i:s');

        $sql = sprintf(
            "INSERT INTO %s
            (owner, title, description, customer, priority, due_date, category, status, source, account_id, mailbox, email_uid, attachment_paths, todo_id, created_at, updated_at)
            VALUES ('%s', '%s', '%s', '%s', '%s', %s, '%s', '%s', '%s', '%s', '%s', '%s', '%s', %s, '%s', '%s')",
            $this->table,
            $model->quote($user_id),
            $model->quote(mb_substr($title, 0, 1000)),
            $model->quote($description),
            $model->quote(mb_substr($customer, 0, 255)),
            $model->quote(mb_substr($priority, 0, 32)),
            $due_date !== '' ? ("'" . $model->quote($due_date) . "'") : 'NULL',
            $model->quote(mb_substr($category, 0, 128)),
            $model->quote(mb_substr($status, 0, 32)),
            $model->quote(mb_substr($source, 0, 32)),
            $model->quote(mb_substr($account_id, 0, 128)),
            $model->quote(mb_substr($mailbox, 0, 255)),
            $model->quote(mb_substr($email_uid, 0, 64)),
            $model->quote($attachment_paths),
            $todoId > 0 ? $todoId : 'NULL',
            $model->quote($now),
            $model->quote($now)
        );
        $model->query($sql);
        $row = $model->fetchOne(
            "SELECT * FROM {$this->table} WHERE owner = '" . $model->quote($user_id)
            . "' AND created_at = '" . $model->quote($now) . "' ORDER BY id DESC LIMIT 1"
        );
        $model->close();

        return [
            'success' => true,
            'task' => $this->formatTask($row),
        ];
    }

    private function listTasks() {
        $user_id = $this->getUserId();
        if ($user_id === '') {
            http_response_code(400);
            return ['success' => false, 'error' => 'Missing user_id'];
        }
        $user = $this->validateUser($user_id);
        if (!$user) {
            http_response_code(401);
            return ['success' => false, 'error' => 'Invalid user_id'];
        }

        $status = trim((string) $this->param('status', ''));
        $customer = trim((string) $this->param('customer', ''));

        $model = $this->db();
        $where = ["owner = '" . $model->quote($user_id) . "'"];
        if ($status !== '') {
            $where[] = "status = '" . $model->quote($status) . "'";
        }
        if ($customer !== '') {
            $where[] = "customer = '" . $model->quote($customer) . "'";
        }
        $rows = $model->fetchAll(
            "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where)
            . " ORDER BY updated_at DESC, id DESC LIMIT 500"
        );
        $model->close();

        $tasks = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $tasks[] = $this->formatTask($row);
            }
        }
        return ['success' => true, 'tasks' => $tasks];
    }

    private function update() {
        $user_id = $this->getUserId();
        $task_id = (int) $this->param('task_id', 0);
        if ($user_id === '' || $task_id <= 0) {
            http_response_code(400);
            return ['success' => false, 'error' => 'Missing user_id or task_id'];
        }
        $user = $this->validateUser($user_id);
        if (!$user) {
            http_response_code(401);
            return ['success' => false, 'error' => 'Invalid user_id'];
        }

        $allowed = ['title', 'description', 'customer', 'priority', 'due_date', 'category', 'status'];
        $sets = [];
        $model = $this->db();
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $_POST) && !array_key_exists($field, $_GET)) {
                continue;
            }
            $value = trim((string) $this->param($field, ''));
            if ($field === 'due_date') {
                if ($value === '') {
                    $sets[] = 'due_date = NULL';
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    $sets[] = "due_date = '" . $model->quote($value) . "'";
                }
                continue;
            }
            $sets[] = "{$field} = '" . $model->quote($value) . "'";
        }
        if (!$sets) {
            $model->close();
            http_response_code(400);
            return ['success' => false, 'error' => 'No fields to update'];
        }
        $sets[] = "updated_at = '" . $model->quote(date('Y-m-d H:i:s')) . "'";
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets)
            . " WHERE id = {$task_id} AND owner = '" . $model->quote($user_id) . "' LIMIT 1";
        $model->query($sql);
        $row = $model->fetchOne(
            "SELECT * FROM {$this->table} WHERE id = {$task_id} AND owner = '" . $model->quote($user_id) . "' LIMIT 1"
        );
        $model->close();
        if (!$row) {
            return ['success' => false, 'error' => 'Task not found'];
        }
        return ['success' => true, 'task' => $this->formatTask($row)];
    }

    private function findByEmail() {
        $user_id = $this->getUserId();
        $account_id = trim((string) $this->param('account_id', ''));
        $email_uid = trim((string) $this->param('email_uid', ''));
        $mailbox = trim((string) $this->param('mailbox', 'INBOX')) ?: 'INBOX';

        if ($user_id === '' || $account_id === '' || $email_uid === '') {
            http_response_code(400);
            return ['success' => false, 'error' => 'Missing user_id, account_id or email_uid'];
        }
        $user = $this->validateUser($user_id);
        if (!$user) {
            http_response_code(401);
            return ['success' => false, 'error' => 'Invalid user_id'];
        }

        $model = $this->db();
        $rows = $model->fetchAll(
            "SELECT * FROM {$this->table} WHERE owner = '" . $model->quote($user_id)
            . "' AND account_id = '" . $model->quote($account_id)
            . "' AND email_uid = '" . $model->quote($email_uid)
            . "' AND mailbox = '" . $model->quote($mailbox)
            . "' ORDER BY id ASC LIMIT 100"
        );
        $model->close();

        $tasks = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $tasks[] = $this->formatTask($row);
            }
        }
        return [
            'success' => true,
            'tasks' => $tasks,
            'found' => count($tasks) > 0,
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $api = new TaskAPI();
        $result = $api->handleRequest();
    } catch (Throwable $e) {
        http_response_code(500);
        $result = [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}
?>
