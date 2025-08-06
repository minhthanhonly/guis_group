# TODOs

## Completed Tasks
- [x] **update_project_queries**: Update project queries to get customer_id from parent_projects table instead of projects table
- [x] **fix_join_statements**: Fix JOIN statements to properly link with parent_projects for customer information  
- [x] **update_create_method**: Remove customer_id from project create method since it's now in parent_projects
- [x] **update_update_method**: Remove customer_id from project update method since it's now in parent_projects
- [x] **test_changes**: Test the updated queries to ensure they work correctly
- [x] **quotation_modal_state_preservation**: Implement state preservation for quotation modal so form data is retained when modal is closed and reopened

## Current Tasks
- [ ] **test_quotation_modal_state**: Test the quotation modal state preservation functionality to ensure it works as expected

## Future Tasks
- [ ] **implement_quotation_edit**: Implement edit functionality for quotations
- [ ] **add_quotation_validation**: Add comprehensive validation for quotation forms
- [ ] **optimize_quotation_performance**: Optimize quotation loading and saving performance 