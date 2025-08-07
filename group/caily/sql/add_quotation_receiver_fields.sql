-- Add new receiver fields to quotations table
ALTER TABLE `quotations` 
ADD COLUMN `receiver_tel` varchar(50) DEFAULT NULL AFTER `receiver_contact`,
ADD COLUMN `receiver_fax` varchar(50) DEFAULT NULL AFTER `receiver_tel`,
ADD COLUMN `receiver_registration_number` varchar(100) DEFAULT NULL AFTER `receiver_fax`;
