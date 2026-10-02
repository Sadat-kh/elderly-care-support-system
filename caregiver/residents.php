<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(3);

$user_id = (int)$_SESSION['user_id'];

// Get assigned profiles with details
$stmt = $pdo->prepare("
    SELECT 
        ep.id, ep.date_of_birth, ep.gender, ep.medical_conditions, ep.allergies,
        u.name as resident_name, u.email,
        r.room_number, r.floor,
        (SELECT COUNT(*) FROM emergency_alerts ea WHERE ea.elderly_profile_id = ep.id AND ea.status != 'resolved') as active_alerts,
        (SELECT status FROM meal_distributions md JOIN meals m ON md.meal_id = m.id JOIN menus mn ON m.menu_id = mn.id WHERE md.elderly_profile_id = ep.id AND mn.meal_date = CURDATE() ORDER BY m.meal_type DESC LIMIT 1) as latest_meal_status
    FROM elderly_profiles ep
    JOIN users u ON ep.user_id = u.id
    JOIN caregiver_assignments ca ON ca.elderly_profile_id = ep.id
    LEFT JOIN room_assignments ra ON ra.elderly_profile_id = ep.id AND ra.end_date IS NULL
    LEFT JOIN rooms r ON ra.room_id = r.id
    WHERE ca.caregiver_user_id = ? AND ca.active = 1
");
$stmt->execute([$user_id]);
$residents = $stmt->fetchAll();

$pageTitle = 'Assigned Residents';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header d-flex justify-content-between align-items-center">
        <div>
            <h1>Assigned Residents</h1>
            <p class="mb-0 text-white-50">Manage care for your assigned elderly</p>
        </div>
    </div>
    
    <?php if (empty($residents)): ?>
        <div class="elderly-empty mt-4">
            <p>You have no assigned residents yet.</p>
        </div>
    <?php else: ?>
        <div class="row g-4 mt-2">
            <?php foreach ($residents as $resident): ?>
                <?php
                $age = date_diff(date_create($resident['date_of_birth']), date_create('today'))->y;
                $has_alerts = $resident['active_alerts'] > 0;
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm border-0 <?php echo $has_alerts ? 'border-start border-danger border-4' : ''; ?>">
                        <div class="card-body">
                            <h5 class="card-title fw-bold">
                                <?php echo sanitize($resident['resident_name']); ?>
                                <?php if ($has_alerts): ?>
                                    <span class="badge bg-danger ms-2"><i class="bi bi-exclamation-triangle"></i> Alert</span>
                                <?php endif; ?>
                            </h5>
                            <p class="text-muted mb-3"><small><?php echo sanitize($resident['email']); ?></small></p>
                            
                            <ul class="list-unstyled mb-3">
                                <li><strong>Age:</strong> <?php echo $age; ?> (<?php echo sanitize(ucfirst($resident['gender'])); ?>)</li>
                                <li><strong>Room:</strong> <?php echo $resident['room_number'] ? 'Room ' . sanitize($resident['room_number']) : 'Not Assigned'; ?></li>
                                <li><strong>Latest Meal Today:</strong> 
                                    <?php 
                                    echo $resident['latest_meal_status'] 
                                        ? sanitize(ucfirst($resident['latest_meal_status'])) 
                                        : '<span class="text-muted">No meal data</span>'; 
                                    ?>
                                </li>
                            </ul>
                            
                            <div class="d-grid gap-2">
                                <a href="care_plans.php?profile_id=<?php echo $resident['id']; ?>" class="btn btn-sm btn-outline-primary">Care Plans</a>
                                <a href="medication.php?profile_id=<?php echo $resident['id']; ?>" class="btn btn-sm btn-outline-info">Medications</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
