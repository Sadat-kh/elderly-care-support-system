-- Kitchen Portal Phase 1 Migration
-- Note: In the UI, 'served' will be displayed as 'Confirmed' to match the flow PENDING -> PREPARED -> DELIVERED -> CONFIRMED.

SET NAMES utf8mb4;
SET time_zone = '+00:00';
USE elderly_care;

-- 1. Alter meal_assignments to track absences/opt-outs
ALTER TABLE meal_assignments 
ADD COLUMN assignment_status ENUM('active', 'absent', 'opt_out') NOT NULL DEFAULT 'active' 
AFTER elderly_profile_id;

-- 2. Alter meal_distributions to support full kitchen flow
ALTER TABLE meal_distributions 
MODIFY COLUMN status ENUM('pending', 'prepared', 'delivered', 'served', 'skipped') NOT NULL DEFAULT 'pending';

-- 3. Create kitchen_inventory table
CREATE TABLE IF NOT EXISTS kitchen_inventory (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_name VARCHAR(150) NOT NULL,
    category VARCHAR(80) NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    unit VARCHAR(30) NOT NULL,
    low_stock_threshold DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    last_updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create kitchen_wastage table
CREATE TABLE IF NOT EXISTS kitchen_wastage (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    meal_date DATE NOT NULL,
    meal_type ENUM('breakfast', 'lunch', 'dinner', 'snack') NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    reason TEXT NULL,
    logged_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_kitchen_wastage_logged_by (logged_by),
    CONSTRAINT fk_kitchen_wastage_user
        FOREIGN KEY (logged_by) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
