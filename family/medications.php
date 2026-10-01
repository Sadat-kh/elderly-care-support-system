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
    $pageTitle = 'Medications Not Found';
    require_once '../includes/header.php';
    echo '<div class="container mt-5"><div class="alert alert-danger">Elderly profile not found or access denied.</div></div>';
    require_once '../includes/footer.php';
    exit;
}

$profile_id = (int)$profile['profile_id'];

// Get active medications summary
$stmt = $pdo->prepare("
    SELECT * FROM medications WHERE elderly_profile_id = ? AND active = 1 ORDER BY time_slot ASC
");
$stmt->execute([$profile_id]);
$medications = $stmt->fetchAll();

// Get recent events (last 7 days)
$e_stmt = $pdo->prepare("
    SELECT me.*, m.name, m.time_slot
    FROM medication_events me
    JOIN medications m ON me.medication_id = m.id
    WHERE m.elderly_profile_id = ? AND me.event_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY me.event_date DESC, m.time_slot ASC
");
$e_stmt->execute([$profile_id]);
$events = $e_stmt->fetchAll();

$pageTitle = 'Medication Status - ' . sanitize($profile['name']);
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width: 900px;">
    <div class="elderly-page-header">
        <h1>Medication Status</h1>
        <p class="mb-0 text-white-50">Adherence summary for <?php echo sanitize($profile['name']); ?></p>
    </div>

    <!-- Active Medications Schedule -->
    <h4 class="mb-3 text-secondary border-bottom pb-2">Current Schedule</h4>
    <?php if (empty($medications)): ?>
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <p class="text-muted mb-0">No active medications scheduled.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-3 mb-4">
            <?php foreach ($medications as $med): ?>
            <div class="col-md-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <h5 class="card-title text-primary mb-1"><?php echo sanitize($med['name']); ?></h5>
                            <span class="badge bg-light text-dark border"><i class="bi bi-clock"></i> <?php echo date('g:i A', strtotime($med['time_slot'])); ?></span>
                        </div>
                        <p class="mb-0 text-muted small"><strong>Frequency:</strong> <?php echo sanitize($med['frequency']); ?></p>
                        <?php if (!empty($med['instructions'])): ?>
                            <p class="mb-0 mt-2 small text-secondary fst-italic">"<?php echo sanitize($med['instructions']); ?>"</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Recent Logs -->
    <h4 class="mb-3 text-secondary border-bottom pb-2">Recent Logs (Last 7 Days)</h4>
    <?php if (empty($events)): ?>
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <p class="text-muted mb-0">No recent medication logs found.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle bg-white shadow-sm rounded">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Medication</th>
                        <th>Scheduled</th>
                        <th>Status</th>
                        <th>Logged At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?php echo date('M j, Y', strtotime($event['event_date'])); ?></td>
                        <td><strong><?php echo sanitize($event['name']); ?></strong></td>
                        <td><?php echo date('g:i A', strtotime($event['time_slot'])); ?></td>
                        <td>
                            <?php 
                            $status_class = match($event['status']) {
                                'taken' => 'bg-success',
                                'missed' => 'bg-danger',
                                'skipped' => 'bg-warning text-dark',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?php echo $status_class; ?>"><?php echo sanitize(ucfirst($event['status'])); ?></span>
                        </td>
                        <td>
                            <?php echo $event['taken_at'] ? date('M j, g:i A', strtotime($event['taken_at'])) : '<span class="text-muted">—</span>'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
