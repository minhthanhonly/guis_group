-- Add waiting_documents (資料待ち) status to child projects
-- Place after contract (請負), before in_progress (進行中)

ALTER TABLE `groupware_projects`
MODIFY COLUMN `status` enum(
  'draft',
  'open',
  'confirming',
  'quotation',
  'contract',
  'waiting_documents',
  'in_progress',
  'completed',
  'paused',
  'cancelled',
  'deleted'
) DEFAULT 'draft';
