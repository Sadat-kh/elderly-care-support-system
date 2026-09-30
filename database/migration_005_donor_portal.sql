ALTER TABLE `donations` 
ADD COLUMN `donor_user_id` INT(10) UNSIGNED NULL AFTER `campaign_id`;
