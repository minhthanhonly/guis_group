-- Allow 無償 (free of charge) for estimate / invoice status on projects
ALTER TABLE `groupware_projects`
  MODIFY COLUMN `estimate_status`
    ENUM('未発行','見積作成中','発行済','発行済み','承認済み','却下','調整','無償')
    DEFAULT '未発行',
  MODIFY COLUMN `invoice_status`
    ENUM('未発行','請求準備','発行済','発行済み','承認済み','却下','調整','無償')
    DEFAULT '未発行';
