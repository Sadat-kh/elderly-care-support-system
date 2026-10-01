<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(2); // Family

$user_id = (int)$_SESSION['user_id'];
$requested_profile_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify link and fetch profile
$query = "
    SELECT ep.*, u.name, u.email, r.room_number 
    FROM family_connections fc
    JOIN elderly_profiles ep ON fc.elderly_profile_id = ep.id
    JOIN users u ON ep.user_id = u.id
    LEFT JOIN room_assignments ra ON ra.elderly_profile_id = ep.id AND ra.end_date IS NULL
    LEFT JOIN rooms r ON r.id = ra.room_id
    WHERE fc.family_user_id = ? AND fc.status = 'approved'
";
$params = [$user_id];

if ($requested_profile_id > 0) {
    $query .= " AND ep.id = ?";
    $params[] = $requested_profile_id;
} else {
    $query .= " LIMIT 1";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$profile = $stmt->fetch();

if (!$profile) {
    $pageTitle = 'Profile Not Found';
    require_once '../includes/header.php';
    echo '<div class="container mt-5"><div class="alert alert-danger">Elderly profile not found or access denied.</div></div>';
    require_once '../includes/footer.php';
    exit;
}

$pageTitle = 'My Elderly - ' . sanitize($profile['name']);
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width: 800px;">
    <div class="elderly-page-header">
        <h1><?php echo sanitize($profile['name']); ?></h1>
        <p class="mb-0 text-white-50">Resident Profile Overview</p>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h5 class="card-title text-primary border-bottom pb-2 mb-3">General Information</h5>
            <div class="row mb-2">
                <div class="col-sm-4 text-muted">Room:</div>
                <div class="col-sm-8 fw-semibold"><?php echo $profile['room_number'] ? 'Room ' . sanitize($profile['room_number']) : 'No room assigned'; ?></div>
            </div>
            <div class="row mb-2">
                <div class="col-sm-4 text-muted">Date of Birth:</div>
                <div class="col-sm-8"><?php echo $profile['date_of_birth'] ? date('M j, Y', strtotime($profile['date_of_birth'])) : 'Not specified'; ?></div>
            </div>
            <div class="row mb-2">
                <div class="col-sm-4 text-muted">Gender:</div>
                <div class="col-sm-8"><?php echo $profile['gender'] ? sanitize(ucfirst($profile['gender'])) : 'Not specified'; ?></div>
            </div>
            <div class="row mb-2">
                <div class="col-sm-4 text-muted">Blood Group:</div>
                <div class="col-sm-8"><?php echo $profile['blood_group'] ? sanitize($profile['blood_group']) : 'Not specified'; ?></div>
            </div>
        </div>
    </div>
    
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h5 class="card-title text-primary border-bottom pb-2 mb-3">Care & Dietary</h5>
            <div class="row mb-2">
                <div class="col-sm-4 text-muted">Dietary Requirements:</div>
                <div class="col-sm-8"><?php echo $profile['dietary_requirements'] ? nl2br(sanitize($profile['dietary_requirements'])) : 'None recorded'; ?></div>
            </div>
            <div class="row mb-2">
                <div class="col-sm-4 text-muted">Medical Conditions:</div>
                <div class="col-sm-8">
                    <?php if (!empty($profile['medical_conditions'])): ?>
                        <span class="text-secondary"><i class="bi bi-lock-fill"></i> Has recorded conditions (Restricted Access)</span>
                    <?php else: ?>
                        None recorded
                    <?php endif; ?>
                </div>
            </div>
            <div class="row mb-2">
                <div class="col-sm-4 text-muted">Allergies:</div>
                <div class="col-sm-8">
                    <?php if (!empty($profile['allergies'])): ?>
                        <span class="text-secondary"><i class="bi bi-lock-fill"></i> Has recorded allergies (Restricted Access)</span>
                    <?php else: ?>
                        None recorded
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <h5 class="card-title text-primary border-bottom pb-2 mb-3">Emergency Contact</h5>
            <div class="row mb-2">
                <div class="col-sm-4 text-muted">Name:</div>
                <div class="col-sm-8"><?php echo $profile['emergency_contact_name'] ? sanitize($profile['emergency_contact_name']) : 'Not specified'; ?></div>
            </div>
            <div class="row mb-2">
                <div class="col-sm-4 text-muted">Phone:</div>
                <div class="col-sm-8"><?php echo $profile['emergency_contact_phone'] ? sanitize($profile['emergency_contact_phone']) : 'Not specified'; ?></div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
