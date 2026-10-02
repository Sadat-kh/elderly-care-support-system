<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php'; // For notify_user
checkRole(3);

$user_id = (int)$_SESSION['user_id'];
$success = '';
$error = '';

// Handle alert resolution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alert_id']) && isset($_POST['action']) && $_POST['action'] === 'resolve') {
    $alert_id = (int)$_POST['alert_id'];
    
    // We allow caregivers to resolve ANY active alert
    $stmt = $pdo->prepare("SELECT ea.id, ep.user_id as resident_user_id FROM emergency_alerts ea JOIN elderly_profiles ep ON ea.elderly_profile_id = ep.id WHERE ea.id = ? AND ea.status != 'resolved'");
    $stmt->execute([$alert_id]);
    $alert = $stmt->fetch();
    
    if ($alert) {
        $update = $pdo->prepare("UPDATE emergency_alerts SET status = 'resolved', resolved_at = CURRENT_TIMESTAMP, resolved_by = ? WHERE id = ?");
        if ($update->execute([$user_id, $alert_id])) {
            $success = "Emergency alert resolved successfully.";
            
            // Notify resident
            notify_user($pdo, (int)$alert['resident_user_id'], 
                "Emergency Alert Resolved", 
                "Your emergency alert has been marked as resolved by a caregiver.", 
                'emergency.php'
            );
        } else {
            $error = "Failed to resolve alert.";
        }
    } else {
        $error = "Alert not found or already resolved.";
    }
}

// Fetch all active emergency alerts
// (Caregivers see ALL active alerts to ensure fast response)
$stmt = $pdo->prepare("
    SELECT ea.*, u.name as resident_name, r.room_number
    FROM emergency_alerts ea
    JOIN elderly_profiles ep ON ea.elderly_profile_id = ep.id
    JOIN users u ON ep.user_id = u.id
    LEFT JOIN room_assignments ra ON ra.elderly_profile_id = ep.id AND ra.end_date IS NULL
    LEFT JOIN rooms r ON ra.room_id = r.id
    WHERE ea.status != 'resolved'
    ORDER BY ea.created_at DESC
");
$stmt->execute();
$active_alerts = $stmt->fetchAll();

// Fetch recently resolved alerts (last 10)
$stmt = $pdo->prepare("
    SELECT ea.*, u.name as resident_name, r.room_number, res_user.name as resolver_name
    FROM emergency_alerts ea
    JOIN elderly_profiles ep ON ea.elderly_profile_id = ep.id
    JOIN users u ON ep.user_id = u.id
    LEFT JOIN room_assignments ra ON ra.elderly_profile_id = ep.id AND ra.end_date IS NULL
    LEFT JOIN rooms r ON ra.room_id = r.id
    LEFT JOIN users res_user ON ea.resolved_by = res_user.id
    WHERE ea.status = 'resolved'
    ORDER BY ea.resolved_at DESC
    LIMIT 10
");
$stmt->execute();
$resolved_alerts = $stmt->fetchAll();

$pageTitle = 'Emergency Alerts';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header">
        <h1>Emergency Alerts</h1>
        <p class="mb-0 text-white-50">View and resolve all active emergency alerts</p>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <h4 class="mb-3 text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Active Alerts</h4>
    <?php if (empty($active_alerts)): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill"></i> No active emergency alerts.
        </div>
    <?php else: ?>
        <div class="row g-4 mb-5">
            <?php foreach ($active_alerts as $alert): ?>
                <div class="col-md-6">
                    <div class="card h-100 shadow border-danger border-2">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title fw-bold text-danger mb-0">Emergency!</h5>
                                <span class="badge bg-danger pulse-badge">Active</span>
                            </div>
                            <h6 class="mb-2"><i class="bi bi-person"></i> <?php echo sanitize($alert['resident_name']); ?></h6>
                            <p class="fw-bold fs-5 mb-2"><i class="bi bi-door-open"></i> <?php echo $alert['room_number'] ? 'Room ' . sanitize($alert['room_number']) : 'Location Unknown'; ?></p>
                            
                            <?php if ($alert['message']): ?>
                                <p class="card-text text-muted mb-3"><?php echo sanitize($alert['message']); ?></p>
                            <?php endif; ?>
                            
                            <hr>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-danger fw-bold">Triggered: <?php echo date('M j, g:i A', strtotime($alert['created_at'])); ?></small>
                                <form method="POST" onsubmit="return confirm('Are you sure this emergency is resolved?');">
                                    <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                    <input type="hidden" name="action" value="resolve">
                                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Mark Resolved</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h4 class="mb-3"><i class="bi bi-clock-history"></i> Recently Resolved</h4>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Resident</th>
                            <th>Room</th>
                            <th>Triggered</th>
                            <th>Resolved</th>
                            <th>Resolved By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($resolved_alerts)): ?>
                            <tr><td colspan="5" class="text-center py-4">No recent resolved alerts.</td></tr>
                        <?php else: ?>
                            <?php foreach ($resolved_alerts as $alert): ?>
                                <tr>
                                    <td><strong><?php echo sanitize($alert['resident_name']); ?></strong></td>
                                    <td><?php echo $alert['room_number'] ? 'Room ' . sanitize($alert['room_number']) : '—'; ?></td>
                                    <td><small class="text-muted"><?php echo date('M j, g:i A', strtotime($alert['created_at'])); ?></small></td>
                                    <td><small class="text-success"><?php echo date('M j, g:i A', strtotime($alert['resolved_at'])); ?></small></td>
                                    <td><small><?php echo sanitize($alert['resolver_name'] ?? 'Unknown'); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes pulse-badge {
    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
    70% { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
}
.pulse-badge {
    animation: pulse-badge 2s infinite;
}
</style>

<?php require_once '../includes/footer.php'; ?>
