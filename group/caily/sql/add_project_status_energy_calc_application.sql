-- Add 省エネ-only project statuses:
-- calculation_completed (計算終了), application_in_progress (申請作業中)
-- Place after in_progress (進行中), before waiting_invoice (請求待ち)

ALTER TABLE `groupware_projects`
MODIFY COLUMN `status` enum(
  'draft',
  'open',
  'confirming',
  'quotation',
  'contract',
  'waiting_documents',
  'in_progress',
  'calculation_completed',
  'application_in_progress',
  'waiting_invoice',
  'completed',
  'paused',
  'cancelled',
  'deleted'
) DEFAULT 'draft';
