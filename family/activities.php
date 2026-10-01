<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(2); // Family

$user_id = (int)$_SESSION['user_id'];
$requested_profile_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify link and fetch profile
$query = "
    SELECT ep.id as profile_id, u.name 
    FROM family_connections fc
    JOIN elderly_profiles ep ON fc.elderly_profile_id = ep.id
    JOIN users u ON ep.user_id = u.id
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
    $pageTitle = 'Activities Not Found';
    require_once '../includes/header.php';
    echo '<div class="container mt-5"><div class="alert alert-danger">Elderly profile not found or access denied.</div></div>';
    require_once '../includes/footer.php';
    exit;
}

$profile_id = (int)$profile['profile_id'];

// Get activities for this resident
$stmt = $pdo->prepare("
    SELECT a.*, ar.status as registration_status, ar.created_at as registered_at
    FROM activity_registrations ar
    JOIN activities a ON ar.activity_id = a.id
    WHERE ar.elderly_profile_id = ?
    ORDER BY a.activity_date DESC, a.start_time DESC
");
$stmt->execute([$profile_id]);
$activities = $stmt->fetchAll();

$pageTitle = 'Activities - ' . sanitize($profile['name']);
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width: 900px;">
    <div class="elderly-page-header">
        <h1>Activities</h1>
        <p class="mb-0 text-white-50">Activity schedule and history for <?php echo sanitize($profile['name']); ?></p>
    </div>

    <?php if (empty($activities)): ?>
        <div class="elderly-empty">
            <p>No activity registrations found.</p>
        </div>
    <?php else: ?>
        <div class="row g-4 mt-2">
            <?php foreach ($activities as $act): ?>
            <div class="col-md-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title text-primary mb-0"><?php echo sanitize($act['title']); ?></h5>
                        </div>
                        <h6 class="card-subtitle mb-3 text-muted">
                            <i class="bi bi-calendar"></i> <?php echo date('M j, Y', strtotime($act['activity_date'])); ?> 
                            <i class="bi bi-clock ms-2"></i> <?php echo date('g:i A', strtotime($act['start_time'])); ?>
                        </h6>
                        <p class="card-text small mb-2"><?php echo sanitize($act['description']); ?></p>
                        <div class="small text-secondary mb-3">
                            <i class="bi bi-geo-alt"></i> Location: <?php echo sanitize($act['location']); ?>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top-0 pt-0 pb-3 d-flex justify-content-between align-items-center">
                        <div class="small">
                            <strong>Status:</strong> 
                            <?php 
                            $status_class = match($act['registration_status']) {
                                'registered' => 'bg-info text-dark',
                                'attended' => 'bg-success',
                                'cancelled' => 'bg-danger',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?php echo $status_class; ?>">
                                <?php echo sanitize(ucfirst($act['registration_status'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
