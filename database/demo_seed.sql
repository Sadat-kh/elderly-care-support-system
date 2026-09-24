-- =========================================================================
-- Elderly Care & Support Platform — Persistent Demo / Presentation Dataset
-- Created: 2026-09-13  (DO NOT DELETE — required for faculty demo)
-- =========================================================================

USE elderly_care;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================================
-- SECTION 1 — ROOMS  (expand from 6 → 20)
-- =========================================================================
INSERT IGNORE INTO rooms (room_number, floor, capacity, room_type, status) VALUES
    ('102', '1', 1, 'standard',  'occupied'),
    ('103', '1', 2, 'shared',    'occupied'),
    ('104', '1', 1, 'standard',  'occupied'),
    ('105', '1', 1, 'standard',  'occupied'),
    ('201', '2', 1, 'standard',  'occupied'),
    ('202', '2', 1, 'premium',   'occupied'),
    ('203', '2', 1, 'standard',  'occupied'),
    ('204', '2', 2, 'shared',    'occupied'),
    ('205', '2', 1, 'premium',   'occupied'),
    ('301', '3', 1, 'standard',  'occupied'),
    ('302', '3', 1, 'standard',  'occupied'),
    ('303', '3', 1, 'premium',   'occupied'),
    ('304', '3', 1, 'standard',  'occupied'),
    ('305', '3', 1, 'standard',  'available');

-- =========================================================================
-- SECTION 2 — USERS  (15 new elderly residents + 1 extra kitchen staff)
-- All passwords = "password123" (bcrypt)
-- =========================================================================
INSERT IGNORE INTO users (role_id, name, email, password_hash, active_status) VALUES
    -- Elderly residents (role_id = 1)
    (1, 'Rahela Begum',       'rahela.begum@facility.bd',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
    (1, 'Abdul Karim',        'abdul.karim@facility.bd',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
    (1, 'Farida Khanam',      'farida.khanam@facility.bd',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
    (1, 'Mohammad Hossain',   'md.hossain@facility.bd',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
    (1, 'Nurun Nahar',        'nurun.nahar@facility.bd',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
    (1, 'Abul Bashar',        'abul.bashar@facility.bd',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
    (1, 'Salma Akter',        'salma.akter@facility.bd',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
    (1, 'Jamal Uddin',        'jamal.uddin@facility.bd',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
    (1, 'Hasina Sultana',     'hasina.sultana@facility.bd',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
    (1, 'Rahim Mia',          'rahim.mia@facility.bd',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
    (1, 'Kulsum Bibi',        'kulsum.bibi@facility.bd',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
    (1, 'Azizur Rahman',      'azizur.rahman@facility.bd',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
    (1, 'Monowara Khatun',    'monowara.khatun@facility.bd', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
    (1, 'Sirajul Islam',      'sirajul.islam@facility.bd',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
    (1, 'Taslima Begum',      'taslima.begum@facility.bd',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
    -- Additional Kitchen Staff (role_id = 4)
    (4, 'Roksana Kitchen',    'roksana.kitchen@facility.bd', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- =========================================================================
-- SECTION 3 — ELDERLY PROFILES  (15 varied residents)
-- =========================================================================
-- We reference newly inserted user IDs via subquery on email
INSERT IGNORE INTO elderly_profiles
    (user_id, date_of_birth, gender, blood_group, medical_conditions, allergies, dietary_requirements, mobility_notes, emergency_contact_name, emergency_contact_phone)
VALUES
    -- Rahela Begum — standard diet, no allergy
    ((SELECT id FROM users WHERE email='rahela.begum@facility.bd'),
     '1948-04-12', 'Female', 'O+',
     'Mild Arthritis, High Blood Pressure',
     NULL,
     'Low-sodium, low-fat',
     'Walks independently', 'Kamal Begum', '+880-1711-100001'),

    -- Abdul Karim — diabetic diet
    ((SELECT id FROM users WHERE email='abdul.karim@facility.bd'),
     '1945-08-20', 'Male', 'B+',
     'Type 2 Diabetes, Chronic Kidney Disease Stage 2',
     NULL,
     'Diabetic-friendly, low-potassium, low-phosphorus',
     'Uses walking frame', 'Rashed Karim', '+880-1711-100002'),

    -- Farida Khanam — soft food due to dental issues, nut allergy
    ((SELECT id FROM users WHERE email='farida.khanam@facility.bd'),
     '1940-11-05', 'Female', 'A-',
     'Severe Osteoporosis, Mild Dementia',
     'Tree nuts (almonds, cashews)',
     'Soft food only, high-calcium',
     'Wheelchair user, needs escort', 'Nasrin Farida', '+880-1711-100003'),

    -- Mohammad Hossain — standard diet
    ((SELECT id FROM users WHERE email='md.hossain@facility.bd'),
     '1950-02-14', 'Male', 'AB+',
     'Hypertension, Benign Prostatic Hyperplasia',
     NULL,
     'Low-sodium',
     'Walks with cane', 'Ruhul Hossain', '+880-1711-100004'),

    -- Nurun Nahar — gluten intolerance
    ((SELECT id FROM users WHERE email='nurun.nahar@facility.bd'),
     '1952-07-30', 'Female', 'O-',
     'Celiac Disease, Hypothyroidism',
     'Gluten (wheat, barley, rye)',
     'Gluten-free diet only',
     'Walks independently, slight balance issues', 'Shafiqul Nahar', '+880-1711-100005'),

    -- Abul Bashar — soft food (post-stroke swallowing difficulty)
    ((SELECT id FROM users WHERE email='abul.bashar@facility.bd'),
     '1943-09-18', 'Male', 'B-',
     'Post-ischemic Stroke, Dysphagia, Atrial Fibrillation',
     NULL,
     'Soft food, pureed where needed, thickened liquids',
     'Wheelchair, requires feeding assistance', 'Selim Bashar', '+880-1711-100006'),

    -- Salma Akter — standard diet, lactose intolerant
    ((SELECT id FROM users WHERE email='salma.akter@facility.bd'),
     '1955-01-22', 'Female', 'A+',
     'Osteoarthritis, Mild Depression',
     'Dairy (lactose intolerance)',
     'Dairy-free, high-fiber',
     'Walks independently', 'Dilara Akter', '+880-1711-100007'),

    -- Jamal Uddin — standard diet
    ((SELECT id FROM users WHERE email='jamal.uddin@facility.bd'),
     '1947-06-10', 'Male', 'O+',
     'Chronic Obstructive Pulmonary Disease (COPD), Mild Heart Failure',
     NULL,
     'Low-sodium, small frequent meals',
     'Limited mobility, uses lift for stairs', 'Mizan Uddin', '+880-1711-100008'),

    -- Hasina Sultana — shellfish allergy, standard diet
    ((SELECT id FROM users WHERE email='hasina.sultana@facility.bd'),
     '1949-03-25', 'Female', 'B+',
     'Type 2 Diabetes, Hyperlipidemia',
     'Shellfish (shrimp, prawn)',
     'Diabetic-friendly, low-cholesterol',
     'Walks independently', 'Tariqul Sultana', '+880-1711-100009'),

    -- Rahim Mia — soft food (poor dentition)
    ((SELECT id FROM users WHERE email='rahim.mia@facility.bd'),
     '1938-12-08', 'Male', 'AB-',
     'Advanced Parkinson''s Disease, Malnutrition Risk',
     NULL,
     'Soft food, high-calorie, high-protein supplements',
     'Wheelchair, needs full assistance for meals', 'Hasan Mia', '+880-1711-100010'),

    -- Kulsum Bibi — standard diet
    ((SELECT id FROM users WHERE email='kulsum.bibi@facility.bd'),
     '1953-05-15', 'Female', 'O+',
     'Rheumatoid Arthritis, Hypertension',
     NULL,
     'Anti-inflammatory diet, omega-3 rich foods',
     'Walks with cane, hand grip limited', 'Faruk Bibi', '+880-1711-100011'),

    -- Azizur Rahman — penicillin allergy (medical, dietary standard)
    ((SELECT id FROM users WHERE email='azizur.rahman@facility.bd'),
     '1946-10-02', 'Male', 'A+',
     'Ischemic Heart Disease, Type 2 Diabetes',
     'Penicillin (medical allergy — note for kitchen: no issue with food)',
     'Diabetic-friendly, cardiac diet, low-fat',
     'Walks slowly, gets tired quickly', 'Bashir Rahman', '+880-1711-100012'),

    -- Monowara Khatun — standard diet
    ((SELECT id FROM users WHERE email='monowara.khatun@facility.bd'),
     '1951-08-19', 'Female', 'B+',
     'Glaucoma, Mild Cognitive Impairment',
     NULL,
     'Standard balanced diet, finger foods preferred',
     'Limited vision, guided walking', 'Jahangir Khatun', '+880-1711-100013'),

    -- Sirajul Islam — TEMPORARILY ABSENT (hospital visit)
    ((SELECT id FROM users WHERE email='sirajul.islam@facility.bd'),
     '1944-04-27', 'Male', 'O+',
     'Hip Fracture Recovery (post-surgical), Type 2 Diabetes',
     NULL,
     'High-protein, high-calcium for bone recovery, diabetic-friendly',
     'Bed-bound, full care required', 'Lutfur Islam', '+880-1711-100014'),

    -- Taslima Begum — opted out today (family visit)
    ((SELECT id FROM users WHERE email='taslima.begum@facility.bd'),
     '1956-02-08', 'Female', 'A+',
     'Mild Hypertension, Anxiety Disorder',
     NULL,
     'Low-sodium, low-caffeine',
     'Walks independently', 'Sharmin Begum', '+880-1711-100015');

-- =========================================================================
-- SECTION 4 — ROOM ASSIGNMENTS  (assign each new resident to a room)
-- =========================================================================
-- Helper: get profile IDs
SET @p_rahela   = (SELECT ep.id FROM elderly_profiles ep JOIN users u ON u.id=ep.user_id WHERE u.email='rahela.begum@facility.bd');
SET @p_karim    = (SELECT ep.id FROM elderly_profiles ep JOIN users u ON u.id=ep.user_id WHERE u.email='abdul.karim@facility.bd');
SET @p_farida   = (SELECT ep.id FROM elderly_profiles ep JOIN users u ON u.id=ep.user_id WHERE u.email='farida.khanam@facility.bd');
SET @p_hossain  = (SELECT ep.id FROM elderly_profiles ep JOIN users u ON u.id=ep.user_id WHERE u.email='md.hossain@facility.bd');
SET @p_nahar    = (SELECT ep.id FROM elderly_profiles ep JOIN users u ON u.id=ep.user_id WHERE u.email='nurun.nahar@facility.bd');
SET @p_bashar   = (SELECT ep.id FROM elderly_profiles ep JOIN users u ON u.id=ep.user_id WHERE u.email='abul.bashar@facility.bd');
SET @p_salma    = (SELECT ep.id FROM elderly_profiles ep JOIN users u ON u.id=ep.user_id WHERE u.email='salma.akter@facility.bd');
SET @p_jamal    = (SELECT ep.id FROM elderly_profiles ep JOIN users u ON u.id=ep.user_id WHERE u.email='jamal.uddin@facility.bd');
SET @p_hasina   = (SELECT ep.id FROM elderly_profiles ep JOIN users u ON u.id=ep.user_id WHERE u.email='hasina.sultana@facility.bd');
SET @p_rahim    = (SELECT ep.id FROM elderly_profiles ep JOIN users u ON u.id=ep.user_id WHERE u.email='rahim.mia@facility.bd');
SET @p_kulsum   = (SELECT ep.id FROM elderly_profiles ep JOIN users u ON u.id=ep.user_id WHERE u.email='kulsum.bibi@facility.bd');
SET @p_azizur   = (SELECT ep.id FROM elderly_profiles ep JOIN users u ON u.id=ep.user_id WHERE u.email='azizur.rahman@facility.bd');
SET @p_mono     = (SELECT ep.id FROM elderly_profiles ep JOIN users u ON u.id=ep.user_id WHERE u.email='monowara.khatun@facility.bd');
SET @p_sirajul  = (SELECT ep.id FROM elderly_profiles ep JOIN users u ON u.id=ep.user_id WHERE u.email='sirajul.islam@facility.bd');
SET @p_taslima  = (SELECT ep.id FROM elderly_profiles ep JOIN users u ON u.id=ep.user_id WHERE u.email='taslima.begum@facility.bd');

-- Room IDs
SET @r102 = (SELECT id FROM rooms WHERE room_number='102');
SET @r103 = (SELECT id FROM rooms WHERE room_number='103');
SET @r104 = (SELECT id FROM rooms WHERE room_number='104' LIMIT 1);
SET @r105 = (SELECT id FROM rooms WHERE room_number='105' LIMIT 1);
SET @r201 = (SELECT id FROM rooms WHERE room_number='201');
SET @r202 = (SELECT id FROM rooms WHERE room_number='202');
SET @r203 = (SELECT id FROM rooms WHERE room_number='203');
SET @r204 = (SELECT id FROM rooms WHERE room_number='204');
SET @r205 = (SELECT id FROM rooms WHERE room_number='205');
SET @r301 = (SELECT id FROM rooms WHERE room_number='301');
SET @r302 = (SELECT id FROM rooms WHERE room_number='302');
SET @r303 = (SELECT id FROM rooms WHERE room_number='303');
SET @r304 = (SELECT id FROM rooms WHERE room_number='304');

INSERT IGNORE INTO room_assignments (room_id, elderly_profile_id, assigned_by, start_date) VALUES
    (@r102, @p_rahela,   1, '2026-07-01'),
    (@r103, @p_karim,    1, '2026-06-15'),
    (@r103, @p_farida,   1, '2026-08-01'),
    (@r104, @p_hossain,  1, '2026-05-20'),
    (@r105, @p_nahar,    1, '2026-07-10'),
    (@r201, @p_bashar,   1, '2026-04-01'),
    (@r202, @p_salma,    1, '2026-09-01'),
    (@r203, @p_jamal,    1, '2026-06-01'),
    (@r204, @p_hasina,   1, '2026-08-15'),
    (@r204, @p_rahim,    1, '2026-03-01'),
    (@r205, @p_kulsum,   1, '2026-07-20'),
    (@r301, @p_azizur,   1, '2026-05-01'),
    (@r302, @p_mono,     1, '2026-08-10'),
    (@r303, @p_sirajul,  1, '2026-06-01'),
    (@r304, @p_taslima,  1, '2026-09-05');

-- =========================================================================
-- SECTION 5 — MENUS + MEALS  (Today + full current week Mon-Sun)
-- Week of 2026-09-07 to 2026-09-13
-- Today = 2026-09-13 (Sunday) — handle UNIQUE constraint on menus.meal_date
-- =========================================================================

-- Delete placeholder yesterday menu if it has no meals (safe cleanup of empty records)
DELETE FROM menus WHERE meal_date = '2026-09-12' AND id NOT IN (SELECT DISTINCT menu_id FROM meals);

-- Insert menus for each day of the current week
INSERT IGNORE INTO menus (meal_date, title, notes, created_by) VALUES
    ('2026-09-07', 'Weekly Menu — Monday, Sep 7',   'Nutritionally balanced weekly plan. Soft-food variants prepared separately for designated residents.', 8),
    ('2026-09-08', 'Weekly Menu — Tuesday, Sep 8',  'Nutritionally balanced weekly plan. Gluten-free options available on request.', 8),
    ('2026-09-09', 'Weekly Menu — Wednesday, Sep 9','Nutritionally balanced weekly plan. High-protein focus mid-week.', 8),
    ('2026-09-10', 'Weekly Menu — Thursday, Sep 10','Nutritionally balanced weekly plan.', 8),
    ('2026-09-11', 'Weekly Menu — Friday, Sep 11',  'Nutritionally balanced weekly plan. Special fish curry for lunch.', 8),
    ('2026-09-12', 'Weekly Menu — Saturday, Sep 12','Nutritionally balanced weekly plan.', 8),
    ('2026-09-13', 'Weekly Menu — Sunday, Sep 13 (Today)', 'Today''s menu. All meals confirmed by Head Chef. Soft food and dietary variants prepared.', 8);

-- -------------------------------------------------------------------------
-- Helper vars for menu IDs
-- -------------------------------------------------------------------------
SET @menu_mon = (SELECT id FROM menus WHERE meal_date='2026-09-07');
SET @menu_tue = (SELECT id FROM menus WHERE meal_date='2026-09-08');
SET @menu_wed = (SELECT id FROM menus WHERE meal_date='2026-09-09');
SET @menu_thu = (SELECT id FROM menus WHERE meal_date='2026-09-10');
SET @menu_fri = (SELECT id FROM menus WHERE meal_date='2026-09-11');
SET @menu_sat = (SELECT id FROM menus WHERE meal_date='2026-09-12');
SET @menu_sun = (SELECT id FROM menus WHERE meal_date='2026-09-13');

-- -------------------------------------------------------------------------
-- MONDAY meals
-- -------------------------------------------------------------------------
INSERT IGNORE INTO meals (menu_id, meal_type, serving_time, title, description, dietary_tags, preparation_status, prepared_qty) VALUES
    (@menu_mon,'breakfast','07:45:00','Semolina Porridge with Dates',    'Smooth semolina cooked with full-cream milk, sweetened with dates and a pinch of cardamom. Served warm.','high-fiber, soft-food-friendly','completed',18),
    (@menu_mon,'lunch',   '12:30:00','Lentil Dal with Steamed Rice',     'Red lentil dal slow-cooked with cumin and turmeric, served with fluffy steamed basmati rice and a side of sautéed greens.','low-sodium, vegan, soft-food-friendly','completed',18),
    (@menu_mon,'dinner',  '18:00:00','Chicken Stew with Mashed Potato',  'Tender chicken pieces simmered in a light herb broth with carrots and potatoes. Mashed potato on the side.','low-sodium, soft-food-friendly','completed',18),
    (@menu_mon,'snack',   '15:00:00','Banana & Yogurt Cup',              'Sliced ripe banana served with plain yogurt and a drizzle of honey.','diabetic-friendly, soft-food-friendly','completed',18);

-- -------------------------------------------------------------------------
-- TUESDAY meals
-- -------------------------------------------------------------------------
INSERT IGNORE INTO meals (menu_id, meal_type, serving_time, title, description, dietary_tags, preparation_status, prepared_qty) VALUES
    (@menu_tue,'breakfast','07:45:00','Whole-Wheat Toast with Boiled Egg','Two slices of whole-wheat toast with a soft-boiled egg and a glass of orange juice.','high-protein','completed',17),
    (@menu_tue,'lunch',   '12:30:00','Fish Curry with Brown Rice',        'Rui fish fillet cooked in a light tomato-based curry, served with nutty brown rice and steamed broccoli.','low-sodium, high-protein','completed',17),
    (@menu_tue,'dinner',  '18:00:00','Vegetable Khichuri',               'A comforting one-pot dish of rice, red lentils and mixed vegetables. Easy to digest, naturally gluten-free.','gluten-free, vegan, soft-food-friendly','completed',17),
    (@menu_tue,'snack',   '15:00:00','Mixed Fruit Platter',              'Seasonal fruit selection: papaya, watermelon, guava. Vitamin-rich and hydrating.','diabetic-friendly, vegan','completed',17);

-- -------------------------------------------------------------------------
-- WEDNESDAY meals
-- -------------------------------------------------------------------------
INSERT IGNORE INTO meals (menu_id, meal_type, serving_time, title, description, dietary_tags, preparation_status, prepared_qty) VALUES
    (@menu_wed,'breakfast','07:45:00','Oatmeal with Banana and Honey',   'Rolled oats cooked with skimmed milk, topped with banana slices and a teaspoon of honey.','diabetic-friendly, high-fiber, soft-food-friendly','completed',19),
    (@menu_wed,'lunch',   '12:30:00','Grilled Chicken Breast with Salad','Boneless grilled chicken breast seasoned with herbs, served with mixed garden salad and a lemon dressing.','high-protein, low-fat, gluten-free','completed',19),
    (@menu_wed,'dinner',  '18:00:00','Minced Beef & Vegetable Soup',     'Hearty soup of lean minced beef with diced carrots, peas and tomatoes in a clear broth. Bread roll on the side.','high-protein, low-sodium','completed',19),
    (@menu_wed,'snack',   '15:00:00','Steamed Corn on the Cob',          'One ear of sweet corn, lightly steamed, with a pinch of salt.','vegan, gluten-free','completed',19);

-- -------------------------------------------------------------------------
-- THURSDAY meals
-- -------------------------------------------------------------------------
INSERT IGNORE INTO meals (menu_id, meal_type, serving_time, title, description, dietary_tags, preparation_status, prepared_qty) VALUES
    (@menu_thu,'breakfast','07:45:00','Rice Porridge (Congee) with Ginger','Plain congee cooked until silky smooth with a hint of ginger. Easy to eat and gentle on digestion.','soft-food-friendly, gluten-free','completed',16),
    (@menu_thu,'lunch',   '12:30:00','Prawn & Vegetable Stir-Fry with Rice','NOTE: Shellfish — not served to residents with shellfish allergy. Plated with jasmine rice.','high-protein, low-fat','completed',16),
    (@menu_thu,'dinner',  '18:00:00','Dhal Soup with Soft Bread',        'Blended yellow lentil soup enriched with coconut milk. Served with soft white bread.','vegan, soft-food-friendly, high-fiber','completed',16),
    (@menu_thu,'snack',   '15:00:00','Peanut Butter Toast',              'Whole-wheat toast with a thin spread of peanut butter. NOTE: Contains peanuts.','high-protein','completed',16);

-- -------------------------------------------------------------------------
-- FRIDAY meals
-- -------------------------------------------------------------------------
INSERT IGNORE INTO meals (menu_id, meal_type, serving_time, title, description, dietary_tags, preparation_status, prepared_qty) VALUES
    (@menu_fri,'breakfast','07:45:00','Semolina Halwa with Milk',        'Sweet semolina cooked with ghee, raisins and warm milk. A favourite Friday morning treat.','soft-food-friendly','completed',17),
    (@menu_fri,'lunch',   '12:30:00','Special Hilsa Fish Curry with Rice','Traditional mustard-infused Hilsa curry, a weekly special. Naturally oily, rich in Omega-3. Served with steamed rice.','high-omega3, gluten-free','completed',17),
    (@menu_fri,'dinner',  '18:00:00','Lamb Stew with Mashed Sweet Potato','Slow-cooked diced lamb with root vegetables in a tomato-herb broth. Served with mashed sweet potato.','high-protein, soft-food-friendly','completed',17),
    (@menu_fri,'snack',   '15:00:00','Date & Walnut Biscuit',            'Freshly baked soft biscuits with chopped dates and walnuts. Naturally sweetened.','high-fiber, soft-food-friendly','completed',17);

-- -------------------------------------------------------------------------
-- SATURDAY meals
-- -------------------------------------------------------------------------
INSERT IGNORE INTO meals (menu_id, meal_type, serving_time, title, description, dietary_tags, preparation_status, prepared_qty) VALUES
    (@menu_sat,'breakfast','07:45:00','Paratha with Egg Curry',          'Soft layered whole-wheat paratha served with a mild egg curry.','high-protein','completed',15),
    (@menu_sat,'lunch',   '12:30:00','Mixed Vegetable Curry with Rice',  'Seasonal vegetables in a spiced tomato gravy, served with steamed basmati rice.','vegan, gluten-free','completed',15),
    (@menu_sat,'dinner',  '18:00:00','Chicken Biryani (Light)',          'Mildly spiced chicken biryani cooked with aromatic basmati rice. Lower oil variant for residents.','high-protein','completed',15),
    (@menu_sat,'snack',   '15:00:00','Mango Lassi',                      'Chilled blended mango with plain yogurt. Served in individual cups.','probiotic, soft-food-friendly','completed',15);

-- -------------------------------------------------------------------------
-- TODAY (SUNDAY 2026-09-13) meals — main focus for the demo
-- -------------------------------------------------------------------------
INSERT IGNORE INTO meals (menu_id, meal_type, serving_time, title, description, dietary_tags, preparation_status, prepared_qty) VALUES
    (@menu_sun,'breakfast','07:45:00','Oatmeal with Banana',             'Warm rolled oats cooked with full-cream milk, topped with fresh banana slices and a drizzle of pure honey. High in fibre and heart-healthy.','diabetic-friendly, high-fiber, soft-food-friendly','ready',16),
    (@menu_sun,'lunch',   '12:30:00','Grilled Tilapia with Steamed Rice','Oven-grilled tilapia fillet marinated in lemon and herbs, served with jasmine rice and steamed mixed vegetables.','low-sodium, high-protein, gluten-free','ready',16),
    (@menu_sun,'dinner',  '18:00:00','Clear Chicken Soup with Bread Roll','Nourishing clear chicken broth with soft-cooked vegetables and shredded chicken. Served with a fresh whole-wheat bread roll.','low-sodium, soft-food-friendly','not_started',0),
    (@menu_sun,'snack',   '15:00:00','Seasonal Fruit Cup',               'A colourful mix of fresh seasonal fruits: watermelon, papaya, and green grapes. Vitamin-rich and naturally hydrating.','diabetic-friendly, vegan, gluten-free','ready',16);

-- =========================================================================
-- SECTION 6 — MEAL ASSIGNMENTS  (Today's meals → 13 active, 1 absent, 1 opt-out)
-- =========================================================================
SET @meal_sun_bfast  = (SELECT id FROM meals WHERE menu_id=@menu_sun AND meal_type='breakfast');
SET @meal_sun_lunch  = (SELECT id FROM meals WHERE menu_id=@menu_sun AND meal_type='lunch');
SET @meal_sun_dinner = (SELECT id FROM meals WHERE menu_id=@menu_sun AND meal_type='dinner');
SET @meal_sun_snack  = (SELECT id FROM meals WHERE menu_id=@menu_sun AND meal_type='snack');

-- Active assignments (13 residents)
INSERT IGNORE INTO meal_assignments (meal_id, elderly_profile_id, assignment_status) VALUES
    (@meal_sun_bfast, @p_rahela,  'active'),
    (@meal_sun_bfast, @p_karim,   'active'),
    (@meal_sun_bfast, @p_farida,  'active'),
    (@meal_sun_bfast, @p_hossain, 'active'),
    (@meal_sun_bfast, @p_nahar,   'active'),
    (@meal_sun_bfast, @p_bashar,  'active'),
    (@meal_sun_bfast, @p_salma,   'active'),
    (@meal_sun_bfast, @p_jamal,   'active'),
    (@meal_sun_bfast, @p_hasina,  'active'),
    (@meal_sun_bfast, @p_rahim,   'active'),
    (@meal_sun_bfast, @p_kulsum,  'active'),
    (@meal_sun_bfast, @p_azizur,  'active'),
    (@meal_sun_bfast, @p_mono,    'active'),
    (@meal_sun_bfast, @p_sirajul, 'absent'),     -- temporarily in hospital
    (@meal_sun_bfast, @p_taslima, 'opt_out');    -- family taking her out for lunch today

INSERT IGNORE INTO meal_assignments (meal_id, elderly_profile_id, assignment_status) VALUES
    (@meal_sun_lunch, @p_rahela,  'active'),
    (@meal_sun_lunch, @p_karim,   'active'),
    (@meal_sun_lunch, @p_farida,  'active'),
    (@meal_sun_lunch, @p_hossain, 'active'),
    (@meal_sun_lunch, @p_nahar,   'active'),
    (@meal_sun_lunch, @p_bashar,  'active'),
    (@meal_sun_lunch, @p_salma,   'active'),
    (@meal_sun_lunch, @p_jamal,   'active'),
    (@meal_sun_lunch, @p_hasina,  'active'),
    (@meal_sun_lunch, @p_rahim,   'active'),
    (@meal_sun_lunch, @p_kulsum,  'active'),
    (@meal_sun_lunch, @p_azizur,  'active'),
    (@meal_sun_lunch, @p_mono,    'active'),
    (@meal_sun_lunch, @p_sirajul, 'absent'),
    (@meal_sun_lunch, @p_taslima, 'opt_out');

INSERT IGNORE INTO meal_assignments (meal_id, elderly_profile_id, assignment_status) VALUES
    (@meal_sun_dinner, @p_rahela,  'active'),
    (@meal_sun_dinner, @p_karim,   'active'),
    (@meal_sun_dinner, @p_farida,  'active'),
    (@meal_sun_dinner, @p_hossain, 'active'),
    (@meal_sun_dinner, @p_nahar,   'active'),
    (@meal_sun_dinner, @p_bashar,  'active'),
    (@meal_sun_dinner, @p_salma,   'active'),
    (@meal_sun_dinner, @p_jamal,   'active'),
    (@meal_sun_dinner, @p_hasina,  'active'),
    (@meal_sun_dinner, @p_rahim,   'active'),
    (@meal_sun_dinner, @p_kulsum,  'active'),
    (@meal_sun_dinner, @p_azizur,  'active'),
    (@meal_sun_dinner, @p_mono,    'active'),
    (@meal_sun_dinner, @p_sirajul, 'absent'),
    (@meal_sun_dinner, @p_taslima, 'active');   -- back by dinner

INSERT IGNORE INTO meal_assignments (meal_id, elderly_profile_id, assignment_status) VALUES
    (@meal_sun_snack, @p_rahela,  'active'),
    (@meal_sun_snack, @p_karim,   'active'),
    (@meal_sun_snack, @p_farida,  'active'),
    (@meal_sun_snack, @p_hossain, 'active'),
    (@meal_sun_snack, @p_nahar,   'active'),
    (@meal_sun_snack, @p_bashar,  'active'),
    (@meal_sun_snack, @p_salma,   'active'),
    (@meal_sun_snack, @p_jamal,   'active'),
    (@meal_sun_snack, @p_hasina,  'active'),
    (@meal_sun_snack, @p_rahim,   'active'),
    (@meal_sun_snack, @p_kulsum,  'active'),
    (@meal_sun_snack, @p_azizur,  'active'),
    (@meal_sun_snack, @p_mono,    'active'),
    (@meal_sun_snack, @p_sirajul, 'absent'),
    (@meal_sun_snack, @p_taslima, 'opt_out');

-- =========================================================================
-- SECTION 7 — MEAL DISTRIBUTIONS  (realistic workflow statuses for today)
-- Breakfast: mostly served/delivered (morning is done)
-- Lunch: mix of prepared + delivered (in progress right now)
-- Snack: pending (afternoon not yet)
-- Dinner: pending (evening not yet)
-- Kitchen staff user_id = 8 (Test Kitchen Staff Demo)
-- =========================================================================

-- BREAKFAST distributions (all 13 active residents)
INSERT IGNORE INTO meal_distributions (meal_id, elderly_profile_id, status, distributed_by, served_at) VALUES
    (@meal_sun_bfast, @p_rahela,  'served',    8, '2026-09-13 08:05:00'),
    (@meal_sun_bfast, @p_karim,   'served',    8, '2026-09-13 08:10:00'),
    (@meal_sun_bfast, @p_farida,  'served',    8, '2026-09-13 08:15:00'),
    (@meal_sun_bfast, @p_hossain, 'served',    8, '2026-09-13 08:12:00'),
    (@meal_sun_bfast, @p_nahar,   'served',    8, '2026-09-13 08:18:00'),
    (@meal_sun_bfast, @p_bashar,  'delivered', 8, NULL),
    (@meal_sun_bfast, @p_salma,   'served',    8, '2026-09-13 08:22:00'),
    (@meal_sun_bfast, @p_jamal,   'served',    8, '2026-09-13 08:25:00'),
    (@meal_sun_bfast, @p_hasina,  'served',    8, '2026-09-13 08:20:00'),
    (@meal_sun_bfast, @p_rahim,   'delivered', 8, NULL),
    (@meal_sun_bfast, @p_kulsum,  'served',    8, '2026-09-13 08:30:00'),
    (@meal_sun_bfast, @p_azizur,  'served',    8, '2026-09-13 08:28:00'),
    (@meal_sun_bfast, @p_mono,    'served',    8, '2026-09-13 08:35:00');

-- LUNCH distributions (mid-morning prep, currently delivering)
INSERT IGNORE INTO meal_distributions (meal_id, elderly_profile_id, status, distributed_by) VALUES
    (@meal_sun_lunch, @p_rahela,  'delivered', 8),
    (@meal_sun_lunch, @p_karim,   'prepared',  8),
    (@meal_sun_lunch, @p_farida,  'delivered', 8),
    (@meal_sun_lunch, @p_hossain, 'prepared',  8),
    (@meal_sun_lunch, @p_nahar,   'prepared',  8),
    (@meal_sun_lunch, @p_bashar,  'prepared',  8),
    (@meal_sun_lunch, @p_salma,   'prepared',  8),
    (@meal_sun_lunch, @p_jamal,   'pending',   NULL),
    (@meal_sun_lunch, @p_hasina,  'pending',   NULL),
    (@meal_sun_lunch, @p_rahim,   'pending',   NULL),
    (@meal_sun_lunch, @p_kulsum,  'pending',   NULL),
    (@meal_sun_lunch, @p_azizur,  'pending',   NULL),
    (@meal_sun_lunch, @p_mono,    'pending',   NULL);

-- SNACK distributions (all pending — afternoon)
INSERT IGNORE INTO meal_distributions (meal_id, elderly_profile_id, status, distributed_by) VALUES
    (@meal_sun_snack, @p_rahela,  'pending', NULL),
    (@meal_sun_snack, @p_karim,   'pending', NULL),
    (@meal_sun_snack, @p_farida,  'pending', NULL),
    (@meal_sun_snack, @p_hossain, 'pending', NULL),
    (@meal_sun_snack, @p_nahar,   'pending', NULL),
    (@meal_sun_snack, @p_bashar,  'pending', NULL),
    (@meal_sun_snack, @p_salma,   'pending', NULL),
    (@meal_sun_snack, @p_jamal,   'pending', NULL),
    (@meal_sun_snack, @p_hasina,  'pending', NULL),
    (@meal_sun_snack, @p_rahim,   'pending', NULL),
    (@meal_sun_snack, @p_kulsum,  'pending', NULL),
    (@meal_sun_snack, @p_azizur,  'pending', NULL),
    (@meal_sun_snack, @p_mono,    'pending', NULL);

-- =========================================================================
-- SECTION 8 — KITCHEN INVENTORY  (15 items, varied stock levels)
-- 3 deliberately below threshold, 1 near expiry
-- =========================================================================
INSERT IGNORE INTO kitchen_inventory (item_name, category, quantity, unit, low_stock_threshold, expiry_date, supplier) VALUES
    -- Healthy stock
    ('Basmati Rice',          'Grains',       45.00, 'kg',      10.00, '2027-03-01',  'Agro Fresh Supplies'),
    ('Red Lentils',           'Legumes',      18.00, 'kg',       5.00, '2027-01-15',  'Desh Pulses Ltd'),
    ('Whole-Wheat Flour',     'Grains',       22.00, 'kg',       8.00, '2026-12-20',  'Agro Fresh Supplies'),
    ('Rolled Oats',           'Grains',       12.00, 'kg',       3.00, '2027-02-10',  'NutriGrain Co.'),
    ('Sunflower Oil',         'Oils & Fats',  15.00, 'litre',    4.00, '2027-06-30',  'Pure Oil Distributors'),
    ('Chicken (Frozen)',      'Protein',      30.00, 'kg',       8.00, '2026-09-25',  'Fresh Farms Poultry'),
    ('Tilapia Fillet (Fresh)','Protein',       8.00, 'kg',       3.00, '2026-09-15',  'River Fresh Fish Co.'),  -- near expiry! (2 days)
    ('Full-Cream Milk',       'Dairy',        20.00, 'litre',    5.00, '2026-09-16',  'Dairygate Farm'),
    ('Plain Yogurt',          'Dairy',         6.00, 'kg',       2.00, '2026-09-17',  'Dairygate Farm'),
    ('Fresh Vegetables (Mix)','Vegetables',   14.00, 'kg',       5.00, '2026-09-16',  'Green Garden Farms'),
    -- Below threshold (Low Stock Alert will fire)
    ('Turmeric Powder',       'Spices',        0.80, 'kg',       1.00, '2027-05-01',  'Spice World BD'),    -- BELOW threshold
    ('Cumin Powder',          'Spices',        0.50, 'kg',       1.00, '2027-04-15',  'Spice World BD'),    -- BELOW threshold
    ('Brown Sugar',           'Sweeteners',    1.20, 'kg',       2.00, '2027-08-01',  'Sweetlife Ltd'),     -- BELOW threshold
    -- More healthy stock
    ('Salt (Iodized)',        'Condiments',    5.00, 'kg',       1.00, '2028-01-01',  'National Salt Co.'),
    ('Bananas',               'Fruits',       80.00, 'piece',   20.00, '2026-09-15',  'Local Market');

-- =========================================================================
-- SECTION 9 — KITCHEN WASTAGE LOGS  (past week entries)
-- =========================================================================
-- logged_by = 8 (Kitchen Staff Demo user)
INSERT IGNORE INTO kitchen_wastage (meal_date, meal_type, item_name, quantity, unit, reason, logged_by) VALUES
    ('2026-09-07', 'breakfast', 'Semolina Porridge with Dates',    2.00, 'portions', 'Two residents did not wake up in time for breakfast service window.',                                 8),
    ('2026-09-08', 'lunch',     'Fish Curry with Brown Rice',       1.00, 'portions', 'One resident had a medical appointment and meal was not consumed before it cooled.',                8),
    ('2026-09-09', 'dinner',    'Minced Beef & Vegetable Soup',     3.00, 'portions', 'Overestimated portion count. Three extra servings prepared due to initial headcount error.',        8),
    ('2026-09-10', 'breakfast', 'Rice Porridge (Congee) with Ginger',1.00,'portions', 'Resident refused meal due to reported loss of appetite. Nutritionist notified.',                   8),
    ('2026-09-11', 'lunch',     'Special Hilsa Fish Curry with Rice',2.00,'portions', 'Two servings left over after distribution. Meal deteriorated before second service attempt.',       8),
    ('2026-09-12', 'snack',     'Mango Lassi',                      4.00, 'portions', 'Blender batch too large. Four extra cups prepared. Cannot store safely beyond 2 hours.',           8),
    ('2026-09-13', 'breakfast', 'Oatmeal with Banana',              2.00, 'portions', 'Two residents marked absent/opt-out after breakfast was plated. Disposed per hygiene protocol.',   8);

-- =========================================================================
-- SECTION 10 — NOTIFICATIONS  (for kitchen staff user_id=8)
-- =========================================================================
-- Low-stock alerts
INSERT IGNORE INTO notifications (user_id, title, message, type, is_read, link) VALUES
    (8, 'Low Stock Alert',  'Turmeric Powder is critically low (0.80 kg remaining, threshold: 1.00 kg). Please reorder immediately.',                                        'warning', 0, 'inventory.php'),
    (8, 'Low Stock Alert',  'Cumin Powder is critically low (0.50 kg remaining, threshold: 1.00 kg). Please reorder immediately.',                                          'warning', 0, 'inventory.php'),
    (8, 'Low Stock Alert',  'Brown Sugar is below threshold (1.20 kg remaining, threshold: 2.00 kg). Please reorder soon.',                                                 'warning', 0, 'inventory.php'),
    (8, 'Near Expiry Item', 'Tilapia Fillet (Fresh) expires on 2026-09-15 (in 2 days). Ensure it is used today or tomorrow, or disposed of safely.',                        'danger',  0, 'inventory.php'),
    (8, 'Meal Ready',       'Breakfast (Oatmeal with Banana) is READY for distribution. Please begin rounds for 13 active residents.',                                      'info',    1, 'distribution.php'),
    (8, 'Meal Ready',       'Lunch (Grilled Tilapia with Steamed Rice) is READY for distribution. 13 active assignments pending.',                                          'info',    0, 'distribution.php'),
    (8, 'Meal Ready',       'Afternoon Snack (Seasonal Fruit Cup) is prepared and READY. Please begin distribution at 15:00.',                                              'info',    0, 'distribution.php'),
    (8, 'Absent/Opt-Out Update', '2 residents (Sirajul Islam — absent; Taslima Begum — opt-out) have been removed from today''s active meal count. Meal counts updated.',  'info',    1, 'distribution.php');

-- Also notify the existing test user (user_id = 2 / sadat) about meals and activities
INSERT IGNORE INTO notifications (user_id, title, message, type, is_read, link) VALUES
    (2, 'Today''s Menu Available', 'Sunday''s menu is ready: Oatmeal breakfast, Grilled Tilapia lunch, Fruit Cup snack, and Chicken Soup dinner.', 'info', 0, '../elderly/meals.php'),
    (2, 'Breakfast Confirmed',     'Your breakfast (Oatmeal with Banana) was confirmed as served at 8:05 AM. Enjoy your morning!',                 'info', 1, '../elderly/meals.php');

-- =========================================================================
-- SECTION 11 — ADDITIONAL CARE DATA for existing test user (user_id=2/sadat)
-- Makes the Elderly portal richer
-- =========================================================================
-- Activities for the week
INSERT IGNORE INTO activities (title, description, activity_date, start_time, end_time, location, max_participants, created_by) VALUES
    ('Gentle Morning Stretch',     'Light stretching and breathing exercises for all mobility levels. Mat provided.',                  '2026-09-13', '07:00:00', '07:30:00', 'Garden Hall',        20, 1),
    ('Sunday Religious Gathering', 'Weekly communal gathering for prayers and reflection. All faiths welcome.',                        '2026-09-13', '09:30:00', '10:30:00', 'Community Room A',   30, 1),
    ('Art & Craft Workshop',       'Watercolour painting session. Materials provided. Great for mindfulness and creativity.',          '2026-09-13', '11:00:00', '12:00:00', 'Activity Room B',    15, 1),
    ('Family Visiting Hour',       'Designated visiting hour for families. Residents may receive guests in the common lounge.',        '2026-09-13', '14:00:00', '16:00:00', 'Main Lounge',        NULL,1),
    ('Evening Movie Screening',    'Classic Bangladeshi film screening. Popcorn and juice provided.',                                  '2026-09-13', '17:00:00', '19:00:00', 'Common Room',        NULL,1),
    ('Monday Morning Walk',        'Guided walk around the garden with a physiotherapist. Wheelchairs also welcome.',                  '2026-09-14', '07:30:00', '08:15:00', 'Garden',              12, 1),
    ('Health Talk: Diabetes Mgmt', 'A 45-minute talk by our visiting dietitian on managing blood sugar through diet and lifestyle.',    '2026-09-14', '10:00:00', '10:45:00', 'Conference Room',    25, 1),
    ('Music & Sing-Along',         'Monthly sing-along session with live harmonium and tabla. Request your favourite songs!',          '2026-09-15', '15:30:00', '16:30:00', 'Garden Hall',        NULL,1);

-- Register test user (profile_id=1 for user_id=2) for today's activities
SET @existing_profile_id = (SELECT id FROM elderly_profiles WHERE user_id = 2 LIMIT 1);

INSERT IGNORE INTO activity_registrations (activity_id, elderly_profile_id, status)
SELECT id, @existing_profile_id, 'registered'
FROM activities WHERE title IN ('Gentle Morning Stretch','Sunday Religious Gathering','Art & Craft Workshop','Family Visiting Hour','Evening Movie Screening');

-- Register a few new residents for today's activities too (makes the counts realistic)
INSERT IGNORE INTO activity_registrations (activity_id, elderly_profile_id, status)
SELECT a.id, @p_rahela, 'registered'
FROM activities a WHERE a.title IN ('Gentle Morning Stretch','Art & Craft Workshop','Evening Movie Screening');

INSERT IGNORE INTO activity_registrations (activity_id, elderly_profile_id, status)
SELECT a.id, @p_karim, 'registered'
FROM activities a WHERE a.title IN ('Sunday Religious Gathering','Evening Movie Screening');

INSERT IGNORE INTO activity_registrations (activity_id, elderly_profile_id, status)
SELECT a.id, @p_hossain, 'registered'
FROM activities a WHERE a.title IN ('Gentle Morning Stretch','Health Talk: Diabetes Mgmt');

-- =========================================================================
-- SECTION 12 — MEAL ASSIGNMENTS for existing profile (user_id=2 → profile_id=1)
-- So the Elderly dashboard shows meal count > 0
-- =========================================================================
SET @ep1 = (SELECT id FROM elderly_profiles WHERE user_id=2 LIMIT 1);

INSERT IGNORE INTO meal_assignments (meal_id, elderly_profile_id, assignment_status) VALUES
    (@meal_sun_bfast,  @ep1, 'active'),
    (@meal_sun_lunch,  @ep1, 'active'),
    (@meal_sun_dinner, @ep1, 'active'),
    (@meal_sun_snack,  @ep1, 'active');

-- Distribution for existing profile (breakfast confirmed)
INSERT IGNORE INTO meal_distributions (meal_id, elderly_profile_id, status, distributed_by, served_at) VALUES
    (@meal_sun_bfast, @ep1, 'served', 8, '2026-09-13 08:07:00'),
    (@meal_sun_lunch, @ep1, 'prepared', 8, NULL),
    (@meal_sun_snack, @ep1, 'pending', NULL, NULL);

-- =========================================================================
-- Restore FK checks
-- =========================================================================
SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================================
-- END OF DEMO SEED — DO NOT DELETE
-- =========================================================================
