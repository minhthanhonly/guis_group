-- BELSのZEH等級: 「0 : 該当なし（非ZEH）」を選択肢から削除したため、0 の値を未選択 (NULL) に戻す
-- 画面側は 0 を未選択として扱うので、この SQL は既存データの整理用（実行は任意・何度実行しても同じ結果）
UPDATE `groupware_parent_projects` SET `bels_zeh_grade` = NULL WHERE `bels_zeh_grade` = '0';
UPDATE `groupware_projects` SET `bels_zeh_grade` = NULL WHERE `bels_zeh_grade` = '0';
