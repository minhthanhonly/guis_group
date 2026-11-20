-- タスク完了日時 (actual_end_date) の補完: 完了済みで未設定のタスクのみ（再実行しても安全）
-- 1) 最後に「ステータス変更 → completed」したログ日時
-- 2) なければ最後に「進捗変更 → 100」したログ日時
-- 3) それもなければ作成日時（完了状態で作成されたタスク: 連絡タスク等）
--
-- task_logs.time は TIMESTAMP 型なので接続のタイムゾーンで値が変わる。
-- tasks の日時はアプリ (Asia/Tokyo) の時刻なので、JST で読む。

SET time_zone = '+09:00';

UPDATE `groupware_tasks` t
JOIN (
    SELECT task_id, MAX(`time`) AS done_at
    FROM `groupware_task_logs`
    WHERE action = 'status_changed' AND value2 = 'completed'
    GROUP BY task_id
) l ON l.task_id = t.id
SET t.actual_end_date = l.done_at
WHERE t.status = 'completed' AND t.actual_end_date IS NULL;

UPDATE `groupware_tasks` t
JOIN (
    SELECT task_id, MAX(`time`) AS done_at
    FROM `groupware_task_logs`
    WHERE action = 'progress_updated' AND value2 = '100'
    GROUP BY task_id
) l ON l.task_id = t.id
SET t.actual_end_date = l.done_at
WHERE t.status = 'completed' AND t.actual_end_date IS NULL;

UPDATE `groupware_tasks`
SET actual_end_date = created_at
WHERE status = 'completed' AND actual_end_date IS NULL AND created_at IS NOT NULL;
