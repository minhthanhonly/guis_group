-- Storage (ファイル共有): company roots GUIS / CAILY
-- Existing items at virtual root (storage_folder = 0) are moved into GUIS.

ALTER TABLE `groupware_storage`
  ADD COLUMN `company_root` VARCHAR(8) DEFAULT NULL AFTER `storage_folder`,
  ADD UNIQUE KEY `uk_storage_company_root` (`company_root`);

INSERT INTO `groupware_storage` (
  `storage_folder`, `company_root`, `storage_type`, `storage_title`, `storage_name`,
  `storage_comment`, `storage_date`, `storage_file`, `storage_size`, `is_protected`,
  `add_level`, `add_group`, `add_user`, `public_level`, `public_group`, `public_user`,
  `edit_level`, `edit_group`, `edit_user`, `owner`, `created`
)
SELECT
  0, 'GUIS', 'folder', 'GUIS', 'システム',
  '', NOW(), '', '', 0,
  0, '', '', 0, '', '',
  2, '', '', 'admin', NOW()
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `groupware_storage` WHERE `company_root` = 'GUIS' LIMIT 1
);

INSERT INTO `groupware_storage` (
  `storage_folder`, `company_root`, `storage_type`, `storage_title`, `storage_name`,
  `storage_comment`, `storage_date`, `storage_file`, `storage_size`, `is_protected`,
  `add_level`, `add_group`, `add_user`, `public_level`, `public_group`, `public_user`,
  `edit_level`, `edit_group`, `edit_user`, `owner`, `created`
)
SELECT
  0, 'CAILY', 'folder', 'CAILY', 'システム',
  '', NOW(), '', '', 0,
  0, '', '', 0, '', '',
  2, '', '', 'admin', NOW()
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `groupware_storage` WHERE `company_root` = 'CAILY' LIMIT 1
);

UPDATE `groupware_storage` s
INNER JOIN (
  SELECT `id` FROM `groupware_storage` WHERE `company_root` = 'GUIS' LIMIT 1
) g ON 1 = 1
SET s.`storage_folder` = g.`id`
WHERE s.`storage_folder` = 0
  AND (s.`company_root` IS NULL OR s.`company_root` = '');
