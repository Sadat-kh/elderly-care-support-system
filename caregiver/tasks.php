<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(3);

$user_id = (int)$_SESSION['user_id'];

// Get assigned profiles
$stmt = $pdo->prepare("SELECT elderly_profile_id FROM caregiver_assignments WHERE caregiver_user_id = ? AND active = 1");
$stmt->execute([$user_id]);
$assigned_profiles = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($assigned_profiles)) {
    $in_clause = '0';
} else {
    $in_clause = implode(',', $assigned_profiles);
}

// 1. Care Plans (Active)
$stmt = $pdo->query("
    SELECT cp.id, cp.title, cp.category, u.name as resident_name, ep.id as profile_id
    FROM care_plans cp
    JOIN elderly_profiles ep ON cp.elderly_profile_id = ep.id
    JOIN users u ON ep.user_id = u.id
    WHERE cp.elderly_profile_id IN ($in_clause) AND cp.active = 1
    ORDER BY u.name, cp.category
");
$tasks_care = $stmt->fetchAll();

// 2. Medications (Today)
$stmt = $pdo->query("
    SELECT me.id, me.status, me.taken_at, m.name as med_name, m.time_slot, u.name as resident_name, ep.id as profile_id
    FROM medication_events me
    JOIN medications m ON me.medication_id = m.id
    JOIN elderly_profiles ep ON m.elderly_profile_id = ep.id
    JOIN users u ON ep.user_id = u.id
    WHERE m.elderly_profile_id IN ($in_clause) AND me.event_date = CURDATE()
    ORDER BY m.time_slot ASC
");
$tasks_meds = $stmt->fetchAll();

// 3. Activities (Today)
$stmt = $pdo->query("
    SELECT ar.id, ar.status, a.title, a.activity_date, u.name as resident_name, ep.id as profile_id
    FROM activity_registrations ar
    JOIN activities a ON ar.activity_id = a.id
    JOIN elderly_profiles ep ON ar.elderly_profile_id = ep.id
    JOIN users u ON ep.user_id = u.id
    WHERE ar.elderly_profile_id IN ($in_clause) AND DATE(a.activity_date) = CURDATE()
    ORDER BY a.activity_date ASC
");
$tasks_activities = $stmt->fetchAll();

$pageTitle = 'Daily Tasks';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header">
        <h1>Daily Tasks</h1>
        <p class="mb-0 text-white-50">Overview of today's care, medications, and activities for assigned residents</p>
    </div>
    
    <div class="row g-4 mt-2">
        <!-- Medications -->
        <div class="col-md-12 col-lg-4">
            <h4 class="text-primary mb-3"><i class="bi bi-capsule"></i> Medications Today</h4>
            <?php if (empty($tasks_meds)): ?>
                <div class="card border-0 shadow-sm"><div class="card-body text-muted">No medications scheduled for today.</div></div>
            <?php else: ?>
                <div class="list-group shadow-sm">
                    <?php foreach ($tasks_meds as $med): ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1 fw-bold"><?php echo sanitize($med['med_name']); ?></h6>
                                <small class="text-muted"><?php echo date('h:i A', strtotime($med['time_slot'])); ?></small>
                            </div>
                            <p class="mb-1 text-muted small"><i class="bi bi-person"></i> <?php echo sanitize($med['resident_name']); ?></p>
                            <?php 
                            $badge = match($med['status']) {
                                'taken' => 'bg-success',
                                'pending' => 'bg-warning text-dark',
                                'missed' => 'bg-danger',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?php echo $badge; ?>"><?php echo ucfirst($med['status']); ?></span>
                            <?php if ($med['status'] === 'pending'): ?>
                                <a href="medication.php?profile_id=<?php echo $med['profile_id']; ?>" class="btn btn-sm btn-outline-primary ms-2 py-0" style="font-size: 0.75rem;">Manage</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Care Plans -->
        <div class="col-md-12 col-lg-4">
            <h4 class="text-primary mb-3"><i class="bi bi-clipboard-check"></i> Care Plan Support</h4>
            <?php if (empty($tasks_care)): ?>
                <div class="card border-0 shadow-sm"><div class="card-body text-muted">No active care plans.</div></div>
            <?php else: ?>
                <div class="list-group shadow-sm">
                    <?php foreach ($tasks_care as $care): ?>
                        <div class="list-group-item">
                            <h6 class="mb-1 fw-bold"><?php echo sanitize($care['title']); ?></h6>
                            <p class="mb-1 text-muted small"><i class="bi bi-person"></i> <?php echo sanitize($care['resident_name']); ?></p>
                            <?php if ($care['category']): ?>
                                <span class="badge bg-light text-dark border"><?php echo ucfirst(sanitize($care['category'])); ?></span>
                            <?php endif; ?>
                            <a href="care_plans.php?profile_id=<?php echo $care['profile_id']; ?>" class="btn btn-sm btn-outline-info ms-2 py-0" style="font-size: 0.75rem;">Details</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Activities -->
        <div class="col-md-12 col-lg-4">
            <h4 class="text-primary mb-3"><i class="bi bi-calendar-event"></i> Activities Today</h4>
            <?php if (empty($tasks_activities)): ?>
                <div class="card border-0 shadow-sm"><div class="card-body text-muted">No activities scheduled for today.</div></div>
            <?php else: ?>
                <div class="list-group shadow-sm">
                    <?php foreach ($tasks_activities as $act): ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1 fw-bold"><?php echo sanitize($act['title']); ?></h6>
                                <small class="text-muted"><?php echo date('h:i A', strtotime($act['activity_date'])); ?></small>
                            </div>
                            <p class="mb-1 text-muted small"><i class="bi bi-person"></i> <?php echo sanitize($act['resident_name']); ?></p>
                            <?php 
                            $badge = match($act['status']) {
                                'attended' => 'bg-success',
                                'registered' => 'bg-primary',
                                'missed' => 'bg-danger',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?php echo $badge; ?>"><?php echo ucfirst($act['status']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
