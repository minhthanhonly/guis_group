-- Add waiting_invoice (請求待ち) status to child projects
-- Place after in_progress (進行中), before completed (完了)

ALTER TABLE `groupware_projects`
MODIFY COLUMN `status` enum(
  'draft',
  'open',
  'confirming',
  'quotation',
  'contract',
  'waiting_documents',
  'in_progress',
  'waiting_invoice',
  'completed',
  'paused',
  'cancelled',
  'deleted'
) DEFAULT 'draft';
