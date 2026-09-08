-- Parent structure type + functional feature flags for branch-spec matching
ALTER TABLE `groupware_parent_projects`
  ADD COLUMN `structure_type` varchar(20) DEFAULT NULL COMMENT 'W|S_RC' AFTER `construction_city`,
  ADD COLUMN `spec_features` varchar(500) DEFAULT NULL COMMENT 'CSV: mb_water_heater,fire_water_tank,steel_stairs,gh_l_type' AFTER `structure_type`;

ALTER TABLE `groupware_branch_spec_rules`
  ADD COLUMN `match_feature` varchar(64) DEFAULT NULL COMMENT 'optional feature key required on parent' AFTER `match_scale_pattern`;
