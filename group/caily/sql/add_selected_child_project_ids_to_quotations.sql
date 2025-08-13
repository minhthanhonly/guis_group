-- Add selected_child_project_ids column to quotations table
-- This column stores comma-separated list of child project IDs selected for the quotation

ALTER TABLE `quotations` 
ADD COLUMN `selected_child_project_ids` TEXT NULL COMMENT 'Comma-separated list of selected child project IDs' 
AFTER `parent_project_id`;

-- Update existing records to populate selected_child_project_ids based on quotation_items
UPDATE `quotations` q 
SET `selected_child_project_ids` = (
    SELECT GROUP_CONCAT(DISTINCT qi.project_id ORDER BY qi.project_id) 
    FROM `quotation_items` qi 
    WHERE qi.quotation_id = q.id 
    AND qi.project_id IS NOT NULL 
    AND qi.project_id != ''
) 
WHERE q.selected_child_project_ids IS NULL 
OR q.selected_child_project_ids = '';
