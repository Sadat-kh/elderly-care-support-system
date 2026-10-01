ALTER TABLE room_change_requests
ADD COLUMN requested_by_family_id INT(10) UNSIGNED DEFAULT NULL AFTER status,
ADD CONSTRAINT fk_rcr_family_user FOREIGN KEY (requested_by_family_id) REFERENCES users(id) ON DELETE SET NULL;
