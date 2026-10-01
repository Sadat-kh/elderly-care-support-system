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
    $pageTitle = 'Meals Not Found';
    require_once '../includes/header.php';
    echo '<div class="container mt-5"><div class="alert alert-danger">Elderly profile not found or access denied.</div></div>';
    require_once '../includes/footer.php';
    exit;
}

$profile_id = (int)$profile['profile_id'];

// Get all meals for today and future (or recent)
$m_stmt = $pdo->prepare("
    SELECT m.*, ma.assignment_status 
    FROM meal_assignments ma 
    JOIN meals m ON ma.meal_id = m.id 
    WHERE ma.elderly_profile_id = ? 
    ORDER BY DATE(m.created_at) DESC, m.serving_time ASC
    LIMIT 20
");
$m_stmt->execute([$profile_id]);
$meals = $m_stmt->fetchAll();

$pageTitle = 'Meals - ' . sanitize($profile['name']);
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width: 900px;">
    <div class="elderly-page-header">
        <h1>Meals</h1>
        <p class="mb-0 text-white-50">Meal schedule for <?php echo sanitize($profile['name']); ?></p>
    </div>

    <?php if (empty($meals)): ?>
        <div class="elderly-empty">
            <p>No meals scheduled yet.</p>
        </div>
    <?php else: ?>
        <div class="row g-4 mt-2">
            <?php foreach ($meals as $meal): ?>
            <div class="col-md-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title text-primary mb-0"><?php echo sanitize($meal['title']); ?></h5>
                            <span class="badge bg-info text-dark rounded-pill"><?php echo sanitize(ucfirst($meal['meal_type'])); ?></span>
                        </div>
                        <h6 class="card-subtitle mb-3 text-muted">
                            <i class="bi bi-calendar"></i> <?php echo date('M j, Y', strtotime($meal['created_at'])); ?> 
                            <i class="bi bi-clock ms-2"></i> <?php echo date('g:i A', strtotime($meal['serving_time'])); ?>
                        </h6>
                        <p class="card-text small"><?php echo sanitize($meal['description']); ?></p>
                        
                        <?php if (!empty($meal['dietary_tags'])): ?>
                            <div class="mb-3">
                                <?php foreach (explode(',', $meal['dietary_tags']) as $tag): ?>
                                    <span class="badge bg-light text-secondary border border-secondary me-1"><?php echo sanitize(trim($tag)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-white border-top-0 pt-0 pb-3 d-flex justify-content-between align-items-center">
                        <div class="small">
                            <strong>Status:</strong> 
                            <span class="text-secondary"><?php echo sanitize(str_replace('_', ' ', ucfirst($meal['preparation_status']))); ?></span>
                        </div>
                        <div class="small">
                            <strong>Assignment:</strong> 
                            <span class="badge <?php echo $meal['assignment_status'] === 'active' ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                <?php echo sanitize(ucfirst($meal['assignment_status'])); ?>
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
