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
    $pageTitle = 'Updates Not Found';
    require_once '../includes/header.php';
    echo '<div class="container mt-5"><div class="alert alert-danger">Elderly profile not found or access denied.</div></div>';
    require_once '../includes/footer.php';
    exit;
}

$profile_id = (int)$profile['profile_id'];

// Collect updates (Meals, Medications, Activities) for the last 30 days
$updates = [];

// 1. Meals completed
$m_stmt = $pdo->prepare("
    SELECT m.meal_type, m.title, m.created_at as timestamp, 'meal' as type
    FROM meal_assignments ma
    JOIN meals m ON ma.meal_id = m.id
    WHERE ma.elderly_profile_id = ? AND ma.assignment_status = 'active' AND m.preparation_status IN ('distributed', 'completed')
    AND m.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$m_stmt->execute([$profile_id]);
$meal_updates = $m_stmt->fetchAll();
foreach ($meal_updates as $row) {
    $updates[] = [
        'timestamp' => strtotime($row['timestamp']),
        'title' => ucfirst($row['meal_type']) . ' Served',
        'desc' => $row['title'],
        'icon' => 'egg-fried',
        'color' => 'success'
    ];
}

// 2. Medications taken
$med_stmt = $pdo->prepare("
    SELECT m.name, me.taken_at as timestamp, 'medication' as type
    FROM medication_events me
    JOIN medications m ON me.medication_id = m.id
    WHERE m.elderly_profile_id = ? AND me.status = 'taken'
    AND me.taken_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$med_stmt->execute([$profile_id]);
$med_updates = $med_stmt->fetchAll();
foreach ($med_updates as $row) {
    $updates[] = [
        'timestamp' => strtotime($row['timestamp']),
        'title' => 'Medication Taken',
        'desc' => $row['name'],
        'icon' => 'capsule',
        'color' => 'primary'
    ];
}

// 3. Activities attended
$act_stmt = $pdo->prepare("
    SELECT a.title, CONCAT(a.activity_date, ' ', a.start_time) as timestamp, 'activity' as type
    FROM activity_registrations ar
    JOIN activities a ON ar.activity_id = a.id
    WHERE ar.elderly_profile_id = ? AND ar.status = 'attended'
    AND a.activity_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$act_stmt->execute([$profile_id]);
$act_updates = $act_stmt->fetchAll();
foreach ($act_updates as $row) {
    $updates[] = [
        'timestamp' => strtotime($row['timestamp']),
        'title' => 'Attended Activity',
        'desc' => $row['title'],
        'icon' => 'people',
        'color' => 'info'
    ];
}

// Sort descending by timestamp
usort($updates, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

$pageTitle = 'Daily Updates - ' . sanitize($profile['name']);
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width: 800px;">
    <div class="elderly-page-header">
        <h1>Daily Updates</h1>
        <p class="mb-0 text-white-50">Recent activity for <?php echo sanitize($profile['name']); ?> (Last 30 Days)</p>
    </div>

    <?php if (empty($updates)): ?>
        <div class="elderly-empty">
            <p>No recent updates to show.</p>
        </div>
    <?php else: ?>
        <div class="timeline-container mt-4">
            <?php 
            $current_date = '';
            foreach ($updates as $up): 
                $date_str = date('l, M j, Y', $up['timestamp']);
                if ($date_str !== $current_date):
                    $current_date = $date_str;
            ?>
                <h5 class="mt-4 mb-3 text-secondary border-bottom pb-2"><?php echo $current_date; ?></h5>
            <?php endif; ?>
            
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-<?php echo $up['color']; ?> text-white d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px; flex-shrink: 0;">
                        <i class="bi bi-<?php echo $up['icon']; ?> fs-4"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1 fw-bold"><?php echo sanitize($up['title']); ?></h6>
                        <p class="mb-0 text-muted"><?php echo sanitize($up['desc']); ?></p>
                    </div>
                    <div class="text-muted small text-end" style="min-width: 70px;">
                        <?php echo date('g:i A', $up['timestamp']); ?>
                    </div>
                </div>
            </div>
            
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
