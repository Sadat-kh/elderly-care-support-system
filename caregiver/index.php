<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(3); // Caregiver

$user_id = (int)$_SESSION['user_id'];

// Get assigned profiles
$stmt = $pdo->prepare("
    SELECT ep.id
    FROM elderly_profiles ep
    JOIN caregiver_assignments ca ON ca.elderly_profile_id = ep.id
    WHERE ca.caregiver_user_id = ? AND ca.active = 1
");
$stmt->execute([$user_id]);
$assigned_profile_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($assigned_profile_ids)) {
    $in_clause = '0';
} else {
    $in_clause = implode(',', $assigned_profile_ids);
}

// 1. Assigned residents count
$residents_count = count($assigned_profile_ids);

// 2. Pending Meds today
$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM medication_events me
    JOIN medications m ON me.medication_id = m.id
    WHERE m.elderly_profile_id IN ($in_clause) 
      AND me.event_date = CURDATE() 
      AND me.status = 'pending'
");
$pending_meds = (int)$stmt->fetchColumn();

// 3. Pending care plans (active)
$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM care_plans 
    WHERE elderly_profile_id IN ($in_clause) AND active = 1
");
$active_care_plans = (int)$stmt->fetchColumn();

// 4. Active Emergency Alerts
$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM emergency_alerts 
    WHERE elderly_profile_id IN ($in_clause) AND status != 'resolved'
");
$active_emergencies = (int)$stmt->fetchColumn();

$pageTitle = 'Caregiver Dashboard';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header">
        <h1>Dashboard</h1>
        <p class="mb-0 text-white-50">Overview of your assigned care tasks</p>
    </div>
    
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card h-100 shadow-sm border-0 border-start border-primary border-4">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Assigned Residents</h6>
                    <h3 class="mb-0"><?php echo $residents_count; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 shadow-sm border-0 border-start border-warning border-4">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Pending Meds (Today)</h6>
                    <h3 class="mb-0"><?php echo $pending_meds; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 shadow-sm border-0 border-start border-info border-4">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Active Care Plans</h6>
                    <h3 class="mb-0"><?php echo $active_care_plans; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 shadow-sm border-0 border-start border-danger border-4">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Active Emergencies</h6>
                    <h3 class="mb-0 <?php echo $active_emergencies > 0 ? 'text-danger' : ''; ?>"><?php echo $active_emergencies; ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-people text-primary"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="tasks.php" class="btn btn-outline-primary text-start"><i class="bi bi-list-check"></i> View Daily Tasks</a>
                        <a href="medication.php" class="btn btn-outline-primary text-start"><i class="bi bi-capsule"></i> Manage Medications</a>
                        <a href="residents.php" class="btn btn-outline-primary text-start"><i class="bi bi-person-lines-fill"></i> View Assigned Residents</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
