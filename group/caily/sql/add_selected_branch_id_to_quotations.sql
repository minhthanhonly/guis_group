-- Add selected_branch_id column to quotations table
ALTER TABLE quotations ADD COLUMN selected_branch_id INT(11) NULL COMMENT 'Selected branch ID for quotation' AFTER parent_project_id;

-- Add index for selected_branch_id
CREATE INDEX idx_quotations_selected_branch_id ON quotations(selected_branch_id);
