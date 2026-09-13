-- Migration 003: Kitchen Phase 2 Schema Updates
-- Extends the meals, meal_distributions, and kitchen_inventory tables to support Phase 2 workflows

-- 1. Meals Table adjustments
ALTER TABLE `meals`
ADD COLUMN `preparation_status` ENUM('not_started', 'preparing', 'ready', 'distributed', 'completed') NOT NULL DEFAULT 'not_started' AFTER `dietary_tags`,
ADD COLUMN `prepared_qty` INT NOT NULL DEFAULT 0 AFTER `preparation_status`;

-- 2. Meal Distributions Adjustments (Ownership enforcement)
ALTER TABLE `meal_distributions`
ADD COLUMN `distributed_by` INT(10) UNSIGNED NULL AFTER `status`;

ALTER TABLE `meal_distributions`
ADD CONSTRAINT `fk_md_distributed_by` FOREIGN KEY (`distributed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- 3. Kitchen Inventory Adjustments
ALTER TABLE `kitchen_inventory`
ADD COLUMN `expiry_date` DATE NULL AFTER `low_stock_threshold`,
ADD COLUMN `supplier` VARCHAR(150) NULL AFTER `expiry_date`;
