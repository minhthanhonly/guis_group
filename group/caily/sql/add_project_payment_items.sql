-- 決済情報: 見積 / 請求 を明細（複数行）で管理する
-- 1 行 = 日付・種別（自由入力タグ）・金額（税抜）・状態
--
-- projects の旧カラム (amount, estimate_*, invoice_*, *_application_*, *_design_*) は削除しない。
-- 保存時に明細から再計算されるため、既存の集計・フィルタ・完了チェックはそのまま動く。
--
-- 旧データの種別: 通常部署は「設計費」、省エネは「申請費」「設計費」。
-- このファイルは何度実行しても同じ結果になる（既に明細がある案件・区分・種別には挿入しない）。

CREATE TABLE IF NOT EXISTS `groupware_project_payment_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` INT NOT NULL,
    `kind` VARCHAR(10) NOT NULL COMMENT 'estimate=見積, invoice=請求',
    `item_date` DATETIME NULL DEFAULT NULL,
    `item_type` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '種別タグ (例: 申請費, 設計費)',
    `amount` DECIMAL(15,2) NULL DEFAULT NULL COMMENT '税抜',
    `status` VARCHAR(20) NOT NULL DEFAULT '未発行',
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NULL DEFAULT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_project_kind` (`project_id`, `kind`, `sort_order`),
    KEY `idx_item_type` (`item_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 旧データの移行
-- 「意味のある値」= 金額 > 0、日付あり、または状態が 未発行 以外
-- ---------------------------------------------------------------------------

-- (1) 通常部署: 見積 → 種別「設計費」の明細 1 行
INSERT INTO `groupware_project_payment_items`
    (project_id, kind, item_date, item_type, amount, status, sort_order, created_at, updated_at)
SELECT p.id, 'estimate', p.estimate_date, '設計費',
       CASE WHEN p.estimate_status = '無償' THEN 0 ELSE p.amount END,
       CASE WHEN p.estimate_status = '発行済み' THEN '発行済'
            ELSE COALESCE(NULLIF(TRIM(p.estimate_status), ''), '未発行') END,
       1, NOW(), NOW()
FROM `groupware_projects` p
LEFT JOIN `groupware_departments` d ON d.id = p.department_id
WHERE (d.name IS NULL OR d.name NOT LIKE '%省エネ%')
  AND (COALESCE(p.amount, 0) > 0 OR p.estimate_date IS NOT NULL
       OR COALESCE(NULLIF(TRIM(p.estimate_status), ''), '未発行') <> '未発行')
  AND NOT EXISTS (SELECT 1 FROM `groupware_project_payment_items` i
                  WHERE i.project_id = p.id AND i.kind = 'estimate');

-- (2) 通常部署: 請求 → 種別「設計費」の明細 1 行
INSERT INTO `groupware_project_payment_items`
    (project_id, kind, item_date, item_type, amount, status, sort_order, created_at, updated_at)
SELECT p.id, 'invoice', p.invoice_date, '設計費',
       CASE WHEN p.invoice_status = '無償' THEN 0 ELSE p.invoice_amount END,
       CASE WHEN p.invoice_status = '発行済み' THEN '発行済'
            ELSE COALESCE(NULLIF(TRIM(p.invoice_status), ''), '未発行') END,
       1, NOW(), NOW()
FROM `groupware_projects` p
LEFT JOIN `groupware_departments` d ON d.id = p.department_id
WHERE (d.name IS NULL OR d.name NOT LIKE '%省エネ%')
  AND (COALESCE(p.invoice_amount, 0) > 0 OR p.invoice_date IS NOT NULL
       OR COALESCE(NULLIF(TRIM(p.invoice_status), ''), '未発行') <> '未発行')
  AND NOT EXISTS (SELECT 1 FROM `groupware_project_payment_items` i
                  WHERE i.project_id = p.id AND i.kind = 'invoice');

-- (3)-(6) 省エネ: 申請費 / 設計費 をそれぞれ種別付きの明細に。
-- 申請費が空（金額・日付・状態すべて未設定）の場合は旧の見積/請求の値を申請費として使う
-- （画面側の hydrateApplicationFromLegacy と同じ扱い）。

-- (3) 省エネ: 見積 申請費
INSERT INTO `groupware_project_payment_items`
    (project_id, kind, item_date, item_type, amount, status, sort_order, created_at, updated_at)
SELECT x.id, 'estimate', x.d, '申請費',
       CASE WHEN x.s IN ('無償', '対象外') THEN 0 ELSE x.a END,
       x.s, 1, NOW(), NOW()
FROM (
    SELECT p.id,
           CASE WHEN p.estimate_application_amount IS NULL AND p.estimate_application_date IS NULL
                     AND COALESCE(TRIM(p.estimate_application_status), '') = ''
                THEN p.amount ELSE p.estimate_application_amount END AS a,
           CASE WHEN p.estimate_application_amount IS NULL AND p.estimate_application_date IS NULL
                     AND COALESCE(TRIM(p.estimate_application_status), '') = ''
                THEN p.estimate_date ELSE p.estimate_application_date END AS d,
           REPLACE(COALESCE(NULLIF(TRIM(
               CASE WHEN p.estimate_application_amount IS NULL AND p.estimate_application_date IS NULL
                         AND COALESCE(TRIM(p.estimate_application_status), '') = ''
                    THEN p.estimate_status ELSE p.estimate_application_status END
           ), ''), '未発行'), '発行済み', '発行済') AS s
    FROM `groupware_projects` p
    JOIN `groupware_departments` dep ON dep.id = p.department_id AND dep.name LIKE '%省エネ%'
) x
WHERE (COALESCE(x.a, 0) > 0 OR x.d IS NOT NULL OR x.s <> '未発行')
  AND NOT EXISTS (SELECT 1 FROM `groupware_project_payment_items` i
                  WHERE i.project_id = x.id AND i.kind = 'estimate' AND i.item_type = '申請費');

-- (4) 省エネ: 見積 設計費
INSERT INTO `groupware_project_payment_items`
    (project_id, kind, item_date, item_type, amount, status, sort_order, created_at, updated_at)
SELECT x.id, 'estimate', x.d, '設計費',
       CASE WHEN x.s IN ('無償', '対象外') THEN 0 ELSE x.a END,
       x.s, 2, NOW(), NOW()
FROM (
    SELECT p.id, p.estimate_design_amount AS a, p.estimate_design_date AS d,
           REPLACE(COALESCE(NULLIF(TRIM(p.estimate_design_status), ''), '未発行'), '発行済み', '発行済') AS s
    FROM `groupware_projects` p
    JOIN `groupware_departments` dep ON dep.id = p.department_id AND dep.name LIKE '%省エネ%'
) x
WHERE (COALESCE(x.a, 0) > 0 OR x.d IS NOT NULL OR x.s <> '未発行')
  AND NOT EXISTS (SELECT 1 FROM `groupware_project_payment_items` i
                  WHERE i.project_id = x.id AND i.kind = 'estimate' AND i.item_type = '設計費');

-- (5) 省エネ: 請求 申請費
INSERT INTO `groupware_project_payment_items`
    (project_id, kind, item_date, item_type, amount, status, sort_order, created_at, updated_at)
SELECT x.id, 'invoice', x.d, '申請費',
       CASE WHEN x.s IN ('無償', '対象外') THEN 0 ELSE x.a END,
       x.s, 1, NOW(), NOW()
FROM (
    SELECT p.id,
           CASE WHEN p.invoice_application_amount IS NULL AND p.invoice_application_date IS NULL
                     AND COALESCE(TRIM(p.invoice_application_status), '') = ''
                THEN p.invoice_amount ELSE p.invoice_application_amount END AS a,
           CASE WHEN p.invoice_application_amount IS NULL AND p.invoice_application_date IS NULL
                     AND COALESCE(TRIM(p.invoice_application_status), '') = ''
                THEN p.invoice_date ELSE p.invoice_application_date END AS d,
           REPLACE(COALESCE(NULLIF(TRIM(
               CASE WHEN p.invoice_application_amount IS NULL AND p.invoice_application_date IS NULL
                         AND COALESCE(TRIM(p.invoice_application_status), '') = ''
                    THEN p.invoice_status ELSE p.invoice_application_status END
           ), ''), '未発行'), '発行済み', '発行済') AS s
    FROM `groupware_projects` p
    JOIN `groupware_departments` dep ON dep.id = p.department_id AND dep.name LIKE '%省エネ%'
) x
WHERE (COALESCE(x.a, 0) > 0 OR x.d IS NOT NULL OR x.s <> '未発行')
  AND NOT EXISTS (SELECT 1 FROM `groupware_project_payment_items` i
                  WHERE i.project_id = x.id AND i.kind = 'invoice' AND i.item_type = '申請費');

-- (6) 省エネ: 請求 設計費
INSERT INTO `groupware_project_payment_items`
    (project_id, kind, item_date, item_type, amount, status, sort_order, created_at, updated_at)
SELECT x.id, 'invoice', x.d, '設計費',
       CASE WHEN x.s IN ('無償', '対象外') THEN 0 ELSE x.a END,
       x.s, 2, NOW(), NOW()
FROM (
    SELECT p.id, p.invoice_design_amount AS a, p.invoice_design_date AS d,
           REPLACE(COALESCE(NULLIF(TRIM(p.invoice_design_status), ''), '未発行'), '発行済み', '発行済') AS s
    FROM `groupware_projects` p
    JOIN `groupware_departments` dep ON dep.id = p.department_id AND dep.name LIKE '%省エネ%'
) x
WHERE (COALESCE(x.a, 0) > 0 OR x.d IS NOT NULL OR x.s <> '未発行')
  AND NOT EXISTS (SELECT 1 FROM `groupware_project_payment_items` i
                  WHERE i.project_id = x.id AND i.kind = 'invoice' AND i.item_type = '設計費');
