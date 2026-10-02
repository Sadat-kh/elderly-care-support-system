CREATE TABLE IF NOT EXISTS caregiver_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    caregiver_user_id INT UNSIGNED NOT NULL,
    elderly_profile_id INT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (caregiver_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (elderly_profile_id) REFERENCES elderly_profiles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (caregiver_user_id, elderly_profile_id)
);

CREATE TABLE IF NOT EXISTS observations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    elderly_profile_id INT UNSIGNED NOT NULL,
    caregiver_user_id INT UNSIGNED NOT NULL,
    observation_text TEXT NOT NULL,
    severity ENUM('routine', 'concern', 'incident') DEFAULT 'routine',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (elderly_profile_id) REFERENCES elderly_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (caregiver_user_id) REFERENCES users(id) ON DELETE CASCADE
);
