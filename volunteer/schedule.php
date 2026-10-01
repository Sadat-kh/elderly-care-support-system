<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(8);

$user_id = (int)$_SESSION['user_id'];

// Get volunteer ID
$stmt = $pdo->prepare("SELECT id FROM volunteers WHERE user_id = ? AND status = 'active'");
$stmt->execute([$user_id]);
$vol_id = $stmt->fetchColumn();

if (!$vol_id) {
    header("Location: " . volunteer_url('index.php'));
    exit;
}

// Fetch active assignments
$stmt = $pdo->prepare("
    SELECT * FROM volunteer_assignments 
    WHERE volunteer_id = ? AND status IN ('assigned', 'in_progress')
    ORDER BY assigned_date ASC
");
$stmt->execute([$vol_id]);
$assignments = $stmt->fetchAll();

$pageTitle = 'My Schedule';
require_once '../includes/header.php';
?>
<div class="elderly-page">
    <div class="elderly-page-header">
        <h1>My Schedule</h1>
        <p class="mb-0 text-white-50">Your upcoming and active assignments</p>
    </div>
    
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body p-0">
            <?php if (empty($assignments)): ?>
                <div class="p-5 text-center text-muted">
                    <h5>No active assignments</h5>
                    <p class="mb-0">Check back later or update your availability in your profile.</p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($assignments as $a): ?>
                        <div class="list-group-item p-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="mb-0 fw-semibold text-primary"><?php echo sanitize($a['task_title']); ?></h5>
                                <span class="badge bg-<?php echo $a['status'] === 'assigned' ? 'warning' : 'info'; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', sanitize($a['status']))); ?>
                                </span>
                            </div>
                            <p class="text-muted mb-2"><?php echo sanitize($a['task_description']); ?></p>
                            <small class="text-muted"><strong>Date:</strong> <?php echo date('M j, Y', strtotime($a['assigned_date'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
