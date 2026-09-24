-- =========================================================================
-- Elderly Portal — Full Demo Dataset (Persistent, DO NOT DELETE)
-- Target user: sadat  |  user_id = 2  |  elderly_profile_id = 1
-- Covers EVERY sub-page in the Elderly portal "More" dropdown
-- =========================================================================

USE elderly_care;
SET NAMES utf8mb4;

-- =========================================================================
-- SECTION 1 — ELDERLY PROFILE (fill in the blanks for profile_id=1)
-- =========================================================================
UPDATE elderly_profiles
SET
    date_of_birth          = '1951-06-18',
    gender                 = 'Male',
    blood_group            = 'B+',
    medical_conditions     = 'Type 2 Diabetes, Mild Hypertension, Chronic Lower Back Pain',
    allergies              = 'Penicillin',
    dietary_requirements   = 'Diabetic-friendly, low-sodium, small frequent meals',
    mobility_notes         = 'Walks with a cane. Manages stairs slowly but independently.',
    emergency_contact_name = 'Karim Uddin (Son)',
    emergency_contact_phone= '+880-1700-112233'
WHERE id = 1;

-- =========================================================================
-- SECTION 2 — SERVICE REQUESTS  (varied statuses)
-- Dashboard shows status IN ('open','in_progress') — need at least 2 of those
-- Requests page shows ALL statuses
-- =========================================================================
INSERT INTO service_requests
    (elderly_profile_id, requested_by, request_type, title, description, priority, status, created_at, updated_at)
VALUES
    -- Already exists: id=1 "Light bulb needs replacing" (open) — keep it
    -- New ones:
    (1, 2, 'housekeeping',  'Extra blanket for the room',
     'The nights have become cooler. I would appreciate an extra blanket please.',
     'low', 'in_progress',
     '2026-09-10 09:00:00', '2026-09-11 11:30:00'),

    (1, 2, 'medical',       'Appointment with physiotherapist',
     'My lower back pain has worsened. Would like to see the physiotherapist as soon as possible.',
     'high', 'open',
     '2026-09-12 14:00:00', '2026-09-12 14:00:00'),

    (1, 2, 'dietary',       'Request for evening light snack',
     'I sometimes feel hungry at 9 PM. May I have a small fruit or biscuit in the evening?',
     'low', 'completed',
     '2026-09-05 18:30:00', '2026-09-07 10:00:00'),

    (1, 2, 'maintenance',   'Window latch is loose',
     'The latch on my room window does not close properly. Could someone fix it?',
     'medium', 'completed',
     '2026-09-01 08:00:00', '2026-09-03 15:00:00'),

    (1, 2, 'general',       'Request for larger font TV remote label',
     'The buttons on the remote are too small to read. Could I get a label overlay with larger text?',
     'low', 'in_progress',
     '2026-09-13 10:00:00', '2026-09-13 10:00:00');

-- =========================================================================
-- SECTION 3 — CARE PLANS  (profile already has 2; add more variety)
-- =========================================================================
INSERT INTO care_plans
    (elderly_profile_id, title, description, category, active, created_by, created_at)
VALUES
    (1, 'Evening Relaxation Routine',
     'Wind down with 10 minutes of gentle breathing exercises before bed. Avoid screens after 9 PM. Keep a glass of water on the bedside table.',
     'wellness', 1, 1, '2026-08-20 09:00:00'),

    (1, 'Diabetic Meal Monitoring',
     'Eat at regular intervals — no more than 4 hours between meals. Track blood sugar before breakfast and 2 hours after lunch. Report any readings above 8.5 mmol/L to the duty nurse immediately.',
     'health', 1, 1, '2026-08-15 11:00:00'),

    (1, 'Back Pain Management Plan',
     'Apply warm compress to lower back for 15 minutes twice daily. Avoid sitting for more than 45 minutes at a stretch. Use the provided lumbar support cushion when seated. Report acute pain (>7/10) immediately.',
     'physiotherapy', 1, 1, '2026-09-01 10:00:00'),

    (1, 'Social Engagement',
     'Participate in at least one communal activity per week. Encouraged to join Sunday religious gathering and the monthly music sessions. Family visits are welcome every Saturday 14:00–16:00.',
     'social', 1, 1, '2026-07-10 09:00:00');

-- =========================================================================
-- SECTION 4 — MEDICATIONS  (profile already has 3; add more realistic ones)
-- =========================================================================
INSERT INTO medications
    (elderly_profile_id, name, dosage, frequency, time_slot, instructions, active, created_at)
VALUES
    (1, 'Losartan 50mg',   '1 tablet', 'Once daily',  '08:30:00',
     'Take in the morning with or without food. For blood pressure management. Do not skip.', 1, '2026-07-01 10:00:00'),

    (1, 'Omeprazole 20mg', '1 capsule','Once daily',  '07:30:00',
     'Take 30 minutes before breakfast. Protects the stomach lining.', 1, '2026-08-01 10:00:00'),

    (1, 'Calcium + D3',    '1 tablet', 'Once daily',  '14:00:00',
     'Take after lunch with a full glass of water. For bone health.', 1, '2026-07-15 10:00:00');

-- Medication events for today and past 3 days
-- (Existing meds are id 1-3 for profile_id=1; new ones will be 4-6)
SET @new_med1 = LAST_INSERT_ID() - 2;  -- Losartan
SET @new_med2 = LAST_INSERT_ID() - 1;  -- Omeprazole
SET @new_med3 = LAST_INSERT_ID();      -- Calcium+D3

-- Today's events
INSERT IGNORE INTO medication_events (medication_id, event_date, status, taken_at, created_at) VALUES
    (1, CURDATE(), 'taken',    DATE_SUB(NOW(), INTERVAL 6 HOUR), CURDATE()),
    (2, CURDATE(), 'taken',    DATE_SUB(NOW(), INTERVAL 5 HOUR), CURDATE()),
    (3, CURDATE(), 'pending',  NULL, CURDATE()),
    (@new_med1, CURDATE(), 'taken', DATE_SUB(NOW(), INTERVAL 6 HOUR), CURDATE()),
    (@new_med2, CURDATE(), 'taken', DATE_SUB(NOW(), INTERVAL 7 HOUR), CURDATE()),
    (@new_med3, CURDATE(), 'pending', NULL, CURDATE());

-- Yesterday's events
INSERT IGNORE INTO medication_events (medication_id, event_date, status, taken_at, created_at) VALUES
    (1, DATE_SUB(CURDATE(),INTERVAL 1 DAY), 'taken', DATE_SUB(NOW(), INTERVAL 30 HOUR), DATE_SUB(CURDATE(),INTERVAL 1 DAY)),
    (2, DATE_SUB(CURDATE(),INTERVAL 1 DAY), 'taken', DATE_SUB(NOW(), INTERVAL 31 HOUR), DATE_SUB(CURDATE(),INTERVAL 1 DAY)),
    (3, DATE_SUB(CURDATE(),INTERVAL 1 DAY), 'taken', DATE_SUB(NOW(), INTERVAL 29 HOUR), DATE_SUB(CURDATE(),INTERVAL 1 DAY));

-- =========================================================================
-- SECTION 5 — MESSAGES  (inbox from caregiver + admin)
-- messages table: sender_id, receiver_id, subject, body, is_read
-- Caregiver = role_id 3, there's a user sayem (user_id=3, role_id=1 — wrong)
-- Admin user_id=1. Use admin as sender; messages go TO user_id=2 (sadat)
-- =========================================================================
INSERT INTO messages (sender_id, receiver_id, subject, body, is_read, created_at) VALUES
    (1, 2,
     'Welcome to the Elderly Care Portal',
     'Dear Sadat,\n\nWelcome to the Elderly Care & Support Platform. Your profile has been set up and your room (101) has been assigned.\n\nPlease take a moment to review your care plan and medication schedule. If you have any questions, do not hesitate to use the "Ask for Assistance" feature.\n\nWarm regards,\nFacility Management',
     1, '2026-09-01 09:00:00'),

    (1, 2,
     'Upcoming Health Talk — Monday 14 September',
     'Dear Sadat,\n\nWe would like to invite you to a Health Talk on Diabetes Management this Monday (September 14) at 10:00 AM in the Conference Room.\n\nThe session will be led by our visiting dietitian and will cover practical tips for managing blood sugar through daily diet and lifestyle. Light refreshments will be served.\n\nKindly let us know if you will be attending.\n\nBest regards,\nActivities Team',
     0, '2026-09-12 14:00:00'),

    (1, 2,
     'Reminder: Physiotherapy Assessment Scheduled',
     'Dear Sadat,\n\nThis is a reminder that your physiotherapy assessment for lower back pain has been scheduled for Monday, 14 September at 2:30 PM in the Medical Wing (Room M-02).\n\nPlease wear comfortable clothing and avoid heavy meals beforehand.\n\nIf you need to reschedule, please inform the front desk at least 2 hours in advance.\n\nCare Team',
     0, '2026-09-13 08:30:00'),

    (2, 1,
     'Re: Physiotherapy Assessment',
     'Thank you for letting me know. I will be there on Monday. Please let the physiotherapist know about my lower back issue — the pain is mainly on the left side.\n\nSadat',
     1, '2026-09-13 09:15:00');

-- =========================================================================
-- SECTION 6 — INVOICES + PAYMENTS  (realistic billing history)
-- Existing: INV-2026-0001 (15000, unpaid) for profile_id=1
-- Add more invoices with different statuses + payment records
-- =========================================================================
INSERT INTO invoices
    (elderly_profile_id, invoice_number, amount, description, due_date, status, created_at)
VALUES
    (1, 'INV-2026-0002', 15000.00,
     'Room & Care Fee — August 2026', '2026-08-31', 'paid',    '2026-08-01 09:00:00'),

    (1, 'INV-2026-0003', 15000.00,
     'Room & Care Fee — July 2026',  '2026-07-31', 'paid',    '2026-07-01 09:00:00'),

    (1, 'INV-2026-0004',  2500.00,
     'Physiotherapy Sessions — August 2026 (5 sessions)', '2026-08-31', 'paid', '2026-08-15 10:00:00'),

    (1, 'INV-2026-0005',  1200.00,
     'Medication Supplements — September 2026', '2026-09-30', 'unpaid', '2026-09-05 10:00:00');

-- Payment records against the paid invoices
SET @inv2 = (SELECT id FROM invoices WHERE invoice_number='INV-2026-0002');
SET @inv3 = (SELECT id FROM invoices WHERE invoice_number='INV-2026-0003');
SET @inv4 = (SELECT id FROM invoices WHERE invoice_number='INV-2026-0004');

INSERT INTO payments (invoice_id, amount, payment_method, paid_at, notes, created_at) VALUES
    (@inv2, 15000.00, 'Bank Transfer', '2026-08-05 11:00:00',
     'Transferred by family (Karim Uddin). Ref: TXN-AUG-0015', '2026-08-05 11:00:00'),

    (@inv3, 15000.00, 'Cash',          '2026-07-07 10:30:00',
     'Cash payment received at front desk by resident.', '2026-07-07 10:30:00'),

    (@inv4,  1500.00, 'Bank Transfer', '2026-08-20 09:00:00',
     'Partial payment — first instalment.', '2026-08-20 09:00:00'),

    (@inv4,  1000.00, 'Cash',          '2026-08-28 14:00:00',
     'Remaining balance settled in cash.', '2026-08-28 14:00:00');

-- =========================================================================
-- SECTION 7 — TRANSPORTATION REQUESTS  (varied statuses)
-- Existing: id=1 (pending, Dhaka Medical College, 2026-09-15)
-- =========================================================================
INSERT INTO transportation_requests
    (elderly_profile_id, destination, pickup_date, pickup_time, purpose, status, approved_by, notes, created_at)
VALUES
    (1, 'National Eye Hospital, Dhaka',
     '2026-09-20', '09:30:00',
     'Quarterly eye check-up with Dr. Hossain (Ophthalmology)',
     'pending', NULL, NULL, '2026-09-13 11:00:00'),

    (1, 'Square Hospital, Dhaka',
     '2026-09-05', '08:00:00',
     'Endocrinology follow-up for diabetes management (Dr. Kamal)',
     'completed', 1, 'Resident returned safely at 13:30. Next appointment in 3 months.', '2026-09-02 10:00:00'),

    (1, 'Dhaka Bank ATM — Mirpur Branch',
     '2026-08-20', '10:00:00',
     'Monthly cash withdrawal for personal expenses',
     'completed', 1, NULL, '2026-08-18 09:00:00'),

    (1, 'Son''s Residence — Dhanmondi',
     '2026-08-28', '14:00:00',
     'Family gathering — Eid celebration',
     'completed', 1, 'Resident returned at 21:00. Family escorted him back.', '2026-08-25 10:00:00');

-- =========================================================================
-- SECTION 8 — ROOM CHANGE REQUESTS  (past + one pending)
-- =========================================================================
SET @room101 = (SELECT id FROM rooms WHERE room_number='101');
SET @room202 = (SELECT id FROM rooms WHERE room_number='202');
SET @room305 = (SELECT id FROM rooms WHERE room_number='305');

INSERT INTO room_change_requests
    (elderly_profile_id, current_room_id, preferred_room_id, reason, status, reviewed_by, reviewed_at, created_at)
VALUES
    (1, @room101, @room202,
     'I would prefer a premium room on the second floor for better ventilation and quieter environment.',
     'rejected', 1, '2026-08-10 14:00:00', '2026-08-08 10:00:00'),

    (1, @room101, @room305,
     'The room on the third floor has a better garden view which would be good for my mood and mental wellbeing.',
     'pending', NULL, NULL, '2026-09-12 09:00:00');

-- =========================================================================
-- SECTION 9 — ACTIVITY REGISTRATIONS  (upcoming activities for profile_id=1)
-- Ensure future-time activities are registered so "Next Activity" widget fires
-- =========================================================================
-- Register for upcoming Monday activities (Sep 14)
INSERT IGNORE INTO activity_registrations (activity_id, elderly_profile_id, status, created_at)
VALUES
    (10, 1, 'registered', '2026-09-13 10:00:00'),  -- Monday Morning Walk (07:30)
    (11, 1, 'registered', '2026-09-13 10:00:00'),  -- Health Talk (10:00)
    (12, 1, 'registered', '2026-09-13 10:00:00');  -- Music & Sing-Along (15:30 Tue)

-- Make sure existing today's activity "Evening Movie Screening" (17:00) stays registered
INSERT IGNORE INTO activity_registrations (activity_id, elderly_profile_id, status)
VALUES (9, 1, 'registered');

-- =========================================================================
-- SECTION 10 — NOTIFICATIONS  (richer inbox for user_id=2)
-- =========================================================================
INSERT INTO notifications (user_id, title, message, type, is_read, link, created_at) VALUES
    (2, 'New Message from Management',
     'You have a new message regarding your physiotherapy assessment scheduled for Monday. Please check your Messages.',
     'info', 0, '../elderly/messages.php', '2026-09-13 08:35:00'),

    (2, 'Room Change Request Received',
     'Your room change request (Room 101 → Room 305) has been received and is under review by management. You will be notified once a decision is made.',
     'info', 0, '../elderly/room_change.php', '2026-09-12 09:05:00'),

    (2, 'Invoice Generated',
     'A new invoice (INV-2026-0005) for ৳1,200 has been issued for Medication Supplements — September 2026. Due date: 30 September 2026.',
     'warning', 0, '../elderly/payments.php', '2026-09-05 10:10:00'),

    (2, 'Transportation Request Approved',
     'Your transportation request to Dhaka Medical College Hospital on 15 Sep has been approved. The vehicle will depart at 09:00 AM from the main entrance.',
     'info', 0, '../elderly/transportation.php', '2026-09-13 12:00:00'),

    (2, 'Activity Reminder: Evening Movie Screening',
     'Reminder: Evening Movie Screening starts at 5:00 PM today in the Common Room. Popcorn and juice will be provided!',
     'info', 0, '../elderly/activities.php', '2026-09-13 16:00:00'),

    (2, 'Care Plan Updated',
     'Your Back Pain Management Plan has been updated by the care team. Please review the new instructions in My Care Plans.',
     'info', 0, '../elderly/care.php', '2026-09-01 10:05:00');

-- =========================================================================
-- SECTION 11 — FAMILY CONNECTIONS  (so messages page shows a family contact)
-- Add a family user first, then connect to profile_id=1
-- =========================================================================
INSERT IGNORE INTO users (role_id, name, email, password_hash, active_status) VALUES
    (2, 'Karim Uddin (Son)', 'karim.uddin.family@example.bd',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

SET @family_uid = (SELECT id FROM users WHERE email='karim.uddin.family@example.bd');

INSERT IGNORE INTO family_connections
    (elderly_profile_id, family_user_id, relationship, status, created_at)
VALUES
    (1, @family_uid, 'Son', 'approved', '2026-07-01 09:00:00');

-- Message from family member to sadat
INSERT INTO messages (sender_id, receiver_id, subject, body, is_read, created_at) VALUES
    (@family_uid, 2,
     'Thinking of you, Baba',
     'Dear Baba,\n\nI hope you are feeling better today. We are all thinking of you. I will try to visit this Saturday during family visiting hours.\n\nPlease make sure you are taking your medications on time. The nurses tell me you have been doing very well.\n\nLove,\nKarim',
     0, '2026-09-11 20:00:00');

-- =========================================================================
-- SECTION 12 — EMERGENCY ALERTS  (1 past resolved, makes history non-empty)
-- =========================================================================
INSERT INTO emergency_alerts
    (elderly_profile_id, alert_type, message, status, resolved_by, resolved_at, created_at)
VALUES
    (1, 'medical',
     'Resident reported chest discomfort. Nurse called immediately.',
     'resolved', 1, '2026-08-22 10:15:00', '2026-08-22 10:05:00');

-- =========================================================================
-- END OF ELDERLY PORTAL DEMO SEED — DO NOT DELETE
-- =========================================================================
