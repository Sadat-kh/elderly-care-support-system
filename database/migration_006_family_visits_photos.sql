CREATE TABLE IF NOT EXISTS visits (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    elderly_profile_id INT(10) UNSIGNED NOT NULL,
    family_user_id INT(10) UNSIGNED NOT NULL,
    visit_date DATE NOT NULL,
    visit_time TIME NOT NULL,
    purpose VARCHAR(255) DEFAULT NULL,
    status ENUM('requested', 'confirmed', 'completed', 'cancelled') DEFAULT 'requested',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (elderly_profile_id) REFERENCES elderly_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (family_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS photos (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    elderly_profile_id INT(10) UNSIGNED NOT NULL,
    uploaded_by INT(10) UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    caption TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (elderly_profile_id) REFERENCES elderly_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);
