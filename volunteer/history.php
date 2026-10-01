<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(8);

$user_id = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id FROM volunteers WHERE user_id = ?");
$stmt->execute([$user_id]);
$vol_id = $stmt->fetchColumn();

if (!$vol_id) {
    header("Location: " . volunteer_url('index.php'));
    exit;
}

// Fetch completed/cancelled assignments
$stmt = $pdo->prepare("
    SELECT * FROM volunteer_assignments 
    WHERE volunteer_id = ? AND status IN ('completed', 'cancelled')
    ORDER BY assigned_date DESC
");
$stmt->execute([$vol_id]);
$assignments = $stmt->fetchAll();

$pageTitle = 'Participation History';
require_once '../includes/header.php';
?>
<div class="elderly-page">
    <div class="elderly-page-header">
        <h1>Participation History</h1>
        <p class="mb-0 text-white-50">Your past volunteer assignments</p>
    </div>
    
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body p-0">
            <?php if (empty($assignments)): ?>
                <div class="p-5 text-center text-muted">
                    <h5>No history yet</h5>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Task</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $a): ?>
                                <tr>
                                    <td class="ps-4">
                                        <strong><?php echo sanitize($a['task_title']); ?></strong><br>
                                        <small class="text-muted"><?php echo sanitize($a['task_description']); ?></small>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($a['assigned_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $a['status'] === 'completed' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst(sanitize($a['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
