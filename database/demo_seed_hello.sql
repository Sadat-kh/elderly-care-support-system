-- =========================================================================
-- Elderly Portal — Duplicate Demo Dataset for hello@gmai.com (user_id = 6)
-- Target user: hello  |  user_id = 6
-- =========================================================================

USE elderly_care;
SET NAMES utf8mb4;

-- Create profile if not exists
INSERT IGNORE INTO elderly_profiles (user_id) VALUES (6);
SET @new_uid = 6;
SET @new_pid = (SELECT id FROM elderly_profiles WHERE user_id = @new_uid);

-- 1. Enriched Profile
UPDATE elderly_profiles
SET
    date_of_birth          = '1948-02-14',
    gender                 = 'Female',
    blood_group            = 'O+',
    medical_conditions     = 'Osteoarthritis, Mild Asthma',
    allergies              = 'Sulfa drugs',
    dietary_requirements   = 'Low-carb, dairy-free',
    mobility_notes         = 'Uses a walker on bad days.',
    emergency_contact_name = 'Sarah Hello (Daughter)',
    emergency_contact_phone= '+880-1711-223344'
WHERE id = @new_pid;

-- Room assignment (Room 305 - which is id 27)
INSERT INTO room_assignments (elderly_profile_id, room_id, assigned_by, start_date)
VALUES (@new_pid, 27, 1, CURDATE());

UPDATE rooms SET status='occupied' WHERE id = 27;

-- Meal assignments & distributions
INSERT INTO meal_assignments (meal_id, elderly_profile_id, assignment_status)
SELECT id, @new_pid, 'active' FROM meals WHERE menu_id IN (SELECT id FROM menus WHERE meal_date=CURDATE());

INSERT INTO meal_distributions (meal_id, elderly_profile_id, status)
SELECT id, @new_pid, 'pending' FROM meals WHERE menu_id IN (SELECT id FROM menus WHERE meal_date=CURDATE());

-- Service Requests
INSERT INTO service_requests (elderly_profile_id, requested_by, request_type, title, description, priority, status, created_at, updated_at) VALUES
(@new_pid, @new_uid, 'housekeeping',  'Extra blanket for the room', 'The nights have become cooler. I would appreciate an extra blanket please.', 'low', 'in_progress', '2026-09-10 09:00:00', '2026-09-11 11:30:00'),
(@new_pid, @new_uid, 'medical',       'Appointment with physiotherapist', 'My lower back pain has worsened. Would like to see the physiotherapist as soon as possible.', 'high', 'open', '2026-09-12 14:00:00', '2026-09-12 14:00:00'),
(@new_pid, @new_uid, 'dietary',       'Request for evening light snack', 'I sometimes feel hungry at 9 PM. May I have a small fruit or biscuit in the evening?', 'low', 'completed', '2026-09-05 18:30:00', '2026-09-07 10:00:00'),
(@new_pid, @new_uid, 'maintenance',   'Window latch is loose', 'The latch on my room window does not close properly. Could someone fix it?', 'medium', 'completed', '2026-09-01 08:00:00', '2026-09-03 15:00:00'),
(@new_pid, @new_uid, 'general',       'Request for larger font TV remote label', 'The buttons on the remote are too small to read. Could I get a label overlay with larger text?', 'low', 'in_progress', '2026-09-13 10:00:00', '2026-09-13 10:00:00');

-- Care Plans
INSERT INTO care_plans (elderly_profile_id, title, description, category, active, created_by, created_at) VALUES
(@new_pid, 'Evening Relaxation Routine', 'Wind down with 10 minutes of gentle breathing exercises before bed. Avoid screens after 9 PM. Keep a glass of water on the bedside table.', 'wellness', 1, 1, '2026-08-20 09:00:00'),
(@new_pid, 'Diabetic Meal Monitoring', 'Eat at regular intervals — no more than 4 hours between meals. Track blood sugar before breakfast and 2 hours after lunch. Report any readings above 8.5 mmol/L to the duty nurse immediately.', 'health', 1, 1, '2026-08-15 11:00:00'),
(@new_pid, 'Back Pain Management Plan', 'Apply warm compress to lower back for 15 minutes twice daily. Avoid sitting for more than 45 minutes at a stretch. Use the provided lumbar support cushion when seated. Report acute pain (>7/10) immediately.', 'physiotherapy', 1, 1, '2026-09-01 10:00:00'),
(@new_pid, 'Social Engagement', 'Participate in at least one communal activity per week. Encouraged to join Sunday religious gathering and the monthly music sessions. Family visits are welcome every Saturday 14:00–16:00.', 'social', 1, 1, '2026-07-10 09:00:00');

-- Medications
INSERT INTO medications (elderly_profile_id, name, dosage, frequency, time_slot, instructions, active, created_at) VALUES
(@new_pid, 'Losartan 50mg',   '1 tablet', 'Once daily',  '08:30:00', 'Take in the morning with or without food. For blood pressure management. Do not skip.', 1, '2026-07-01 10:00:00'),
(@new_pid, 'Omeprazole 20mg', '1 capsule','Once daily',  '07:30:00', 'Take 30 minutes before breakfast. Protects the stomach lining.', 1, '2026-08-01 10:00:00'),
(@new_pid, 'Calcium + D3',    '1 tablet', 'Once daily',  '14:00:00', 'Take after lunch with a full glass of water. For bone health.', 1, '2026-07-15 10:00:00');

SET @new_med1 = LAST_INSERT_ID() - 2;
SET @new_med2 = LAST_INSERT_ID() - 1;
SET @new_med3 = LAST_INSERT_ID();

INSERT IGNORE INTO medication_events (medication_id, event_date, status, taken_at, created_at) VALUES
(@new_med1, CURDATE(), 'taken', DATE_SUB(NOW(), INTERVAL 6 HOUR), CURDATE()),
(@new_med2, CURDATE(), 'taken', DATE_SUB(NOW(), INTERVAL 7 HOUR), CURDATE()),
(@new_med3, CURDATE(), 'pending', NULL, CURDATE());

INSERT IGNORE INTO medication_events (medication_id, event_date, status, taken_at, created_at) VALUES
(@new_med1, DATE_SUB(CURDATE(),INTERVAL 1 DAY), 'taken', DATE_SUB(NOW(), INTERVAL 30 HOUR), DATE_SUB(CURDATE(),INTERVAL 1 DAY)),
(@new_med2, DATE_SUB(CURDATE(),INTERVAL 1 DAY), 'taken', DATE_SUB(NOW(), INTERVAL 31 HOUR), DATE_SUB(CURDATE(),INTERVAL 1 DAY)),
(@new_med3, DATE_SUB(CURDATE(),INTERVAL 1 DAY), 'taken', DATE_SUB(NOW(), INTERVAL 29 HOUR), DATE_SUB(CURDATE(),INTERVAL 1 DAY));

-- Messages
INSERT INTO messages (sender_id, receiver_id, subject, body, is_read, created_at) VALUES
(1, @new_uid, 'Welcome to the Elderly Care Portal', 'Dear hello,\n\nWelcome to the Elderly Care & Support Platform. Your profile has been set up and your room has been assigned.\n\nPlease take a moment to review your care plan and medication schedule. If you have any questions, do not hesitate to use the "Ask for Assistance" feature.\n\nWarm regards,\nFacility Management', 1, '2026-09-01 09:00:00'),
(1, @new_uid, 'Upcoming Health Talk — Monday 14 September', 'Dear hello,\n\nWe would like to invite you to a Health Talk on Diabetes Management this Monday (September 14) at 10:00 AM in the Conference Room.\n\nThe session will be led by our visiting dietitian and will cover practical tips for managing blood sugar through daily diet and lifestyle. Light refreshments will be served.\n\nKindly let us know if you will be attending.\n\nBest regards,\nActivities Team', 0, '2026-09-12 14:00:00'),
(1, @new_uid, 'Reminder: Physiotherapy Assessment Scheduled', 'Dear hello,\n\nThis is a reminder that your physiotherapy assessment for lower back pain has been scheduled for Monday, 14 September at 2:30 PM in the Medical Wing (Room M-02).\n\nPlease wear comfortable clothing and avoid heavy meals beforehand.\n\nIf you need to reschedule, please inform the front desk at least 2 hours in advance.\n\nCare Team', 0, '2026-09-13 08:30:00'),
(@new_uid, 1, 'Re: Physiotherapy Assessment', 'Thank you for letting me know. I will be there on Monday. Please let the physiotherapist know about my lower back issue — the pain is mainly on the left side.\n\nhello', 1, '2026-09-13 09:15:00');

-- Invoices + Payments
INSERT INTO invoices (elderly_profile_id, invoice_number, amount, description, due_date, status, created_at) VALUES
(@new_pid, 'INV-2026-0006', 15000.00, 'Room & Care Fee — August 2026', '2026-08-31', 'paid',    '2026-08-01 09:00:00'),
(@new_pid, 'INV-2026-0007', 15000.00, 'Room & Care Fee — July 2026',  '2026-07-31', 'paid',    '2026-07-01 09:00:00'),
(@new_pid, 'INV-2026-0008',  2500.00, 'Physiotherapy Sessions — August 2026 (5 sessions)', '2026-08-31', 'paid', '2026-08-15 10:00:00'),
(@new_pid, 'INV-2026-0009',  1200.00, 'Medication Supplements — September 2026', '2026-09-30', 'unpaid', '2026-09-05 10:00:00');

SET @inv6 = (SELECT id FROM invoices WHERE invoice_number='INV-2026-0006');
SET @inv7 = (SELECT id FROM invoices WHERE invoice_number='INV-2026-0007');
SET @inv8 = (SELECT id FROM invoices WHERE invoice_number='INV-2026-0008');

INSERT INTO payments (invoice_id, amount, payment_method, paid_at, notes, created_at) VALUES
(@inv6, 15000.00, 'Bank Transfer', '2026-08-05 11:00:00', 'Transferred by family. Ref: TXN-AUG-0016', '2026-08-05 11:00:00'),
(@inv7, 15000.00, 'Cash',          '2026-07-07 10:30:00', 'Cash payment received at front desk by resident.', '2026-07-07 10:30:00'),
(@inv8,  1500.00, 'Bank Transfer', '2026-08-20 09:00:00', 'Partial payment — first instalment.', '2026-08-20 09:00:00'),
(@inv8,  1000.00, 'Cash',          '2026-08-28 14:00:00', 'Remaining balance settled in cash.', '2026-08-28 14:00:00');

-- Transportation Requests
INSERT INTO transportation_requests (elderly_profile_id, destination, pickup_date, pickup_time, purpose, status, approved_by, notes, created_at) VALUES
(@new_pid, 'National Eye Hospital, Dhaka', '2026-09-20', '09:30:00', 'Quarterly eye check-up with Dr. Hossain (Ophthalmology)', 'pending', NULL, NULL, '2026-09-13 11:00:00'),
(@new_pid, 'Square Hospital, Dhaka', '2026-09-05', '08:00:00', 'Endocrinology follow-up for diabetes management (Dr. Kamal)', 'completed', 1, 'Resident returned safely at 13:30. Next appointment in 3 months.', '2026-09-02 10:00:00'),
(@new_pid, 'Dhaka Bank ATM — Mirpur Branch', '2026-08-20', '10:00:00', 'Monthly cash withdrawal for personal expenses', 'completed', 1, NULL, '2026-08-18 09:00:00'),
(@new_pid, 'Daughter''s Residence', '2026-08-28', '14:00:00', 'Family gathering', 'completed', 1, 'Resident returned at 21:00. Family escorted him back.', '2026-08-25 10:00:00');

-- Room Change Requests
SET @room305 = 27;
SET @room102 = (SELECT id FROM rooms WHERE room_number='102');
SET @room202 = (SELECT id FROM rooms WHERE room_number='202');

INSERT INTO room_change_requests (elderly_profile_id, current_room_id, preferred_room_id, reason, status, reviewed_by, reviewed_at, created_at) VALUES
(@new_pid, @room305, @room202, 'I would prefer a premium room on the second floor for better ventilation and quieter environment.', 'rejected', 1, '2026-08-10 14:00:00', '2026-08-08 10:00:00'),
(@new_pid, @room305, @room102, 'I prefer being on the first floor.', 'pending', NULL, NULL, '2026-09-12 09:00:00');

-- Activity Registrations
INSERT IGNORE INTO activity_registrations (activity_id, elderly_profile_id, status, created_at) VALUES
(10, @new_pid, 'registered', '2026-09-13 10:00:00'),  -- Monday Morning Walk (07:30)
(11, @new_pid, 'registered', '2026-09-13 10:00:00'),  -- Health Talk (10:00)
(12, @new_pid, 'registered', '2026-09-13 10:00:00'),  -- Music & Sing-Along (15:30 Tue)
(9, @new_pid, 'registered', '2026-09-13 10:00:00');   -- Evening Movie Screening (17:00)

-- Notifications
INSERT INTO notifications (user_id, title, message, type, is_read, link, created_at) VALUES
(@new_uid, 'New Message from Management', 'You have a new message regarding your physiotherapy assessment scheduled for Monday. Please check your Messages.', 'info', 0, '../elderly/messages.php', '2026-09-13 08:35:00'),
(@new_uid, 'Room Change Request Received', 'Your room change request has been received and is under review by management. You will be notified once a decision is made.', 'info', 0, '../elderly/room_change.php', '2026-09-12 09:05:00'),
(@new_uid, 'Invoice Generated', 'A new invoice (INV-2026-0009) for ৳1,200 has been issued. Due date: 30 September 2026.', 'warning', 0, '../elderly/payments.php', '2026-09-05 10:10:00'),
(@new_uid, 'Transportation Request Approved', 'Your transportation request to Dhaka Medical College Hospital on 15 Sep has been approved. The vehicle will depart at 09:00 AM from the main entrance.', 'info', 0, '../elderly/transportation.php', '2026-09-13 12:00:00'),
(@new_uid, 'Activity Reminder: Evening Movie Screening', 'Reminder: Evening Movie Screening starts at 5:00 PM today in the Common Room. Popcorn and juice will be provided!', 'info', 0, '../elderly/activities.php', '2026-09-13 16:00:00'),
(@new_uid, 'Care Plan Updated', 'Your Back Pain Management Plan has been updated by the care team. Please review the new instructions in My Care Plans.', 'info', 0, '../elderly/care.php', '2026-09-01 10:05:00');

-- Family Connections
INSERT IGNORE INTO users (role_id, name, email, password_hash, active_status) VALUES
(2, 'Sarah Hello (Daughter)', 'sarah.hello.family@example.bd', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

SET @family_uid2 = (SELECT id FROM users WHERE email='sarah.hello.family@example.bd');

INSERT IGNORE INTO family_connections (elderly_profile_id, family_user_id, relationship, status, created_at) VALUES
(@new_pid, @family_uid2, 'Daughter', 'approved', '2026-07-01 09:00:00');

INSERT INTO messages (sender_id, receiver_id, subject, body, is_read, created_at) VALUES
(@family_uid2, @new_uid, 'Thinking of you', 'Dear Mum,\n\nI hope you are feeling better today. We are all thinking of you. I will try to visit this Saturday during family visiting hours.\n\nPlease make sure you are taking your medications on time. The nurses tell me you have been doing very well.\n\nLove,\nSarah', 0, '2026-09-11 20:00:00');

-- Emergency Alerts
INSERT INTO emergency_alerts (elderly_profile_id, alert_type, message, status, resolved_by, resolved_at, created_at) VALUES
(@new_pid, 'medical', 'Resident reported chest discomfort. Nurse called immediately.', 'resolved', 1, '2026-08-22 10:15:00', '2026-08-22 10:05:00');
