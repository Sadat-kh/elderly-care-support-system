<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(2); // Family

$user_id = (int)$_SESSION['user_id'];
$success = '';
$error = '';

// Get linked elderly profile
$stmt = $pdo->prepare("
    SELECT ep.id as profile_id, u.name as elderly_name 
    FROM family_connections fc
    JOIN elderly_profiles ep ON fc.elderly_profile_id = ep.id
    JOIN users u ON ep.user_id = u.id
    WHERE fc.family_user_id = ? AND fc.status = 'approved'
");
$stmt->execute([$user_id]);
$connection = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_visit']) && $connection) {
    $visit_date = trim($_POST['visit_date'] ?? '');
    $visit_time = trim($_POST['visit_time'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    
    if (empty($visit_date) || empty($visit_time)) {
        $error = 'Date and Time are required.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO visits (elderly_profile_id, family_user_id, visit_date, visit_time, purpose) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$connection['profile_id'], $user_id, $visit_date, $visit_time, $purpose ?: null]);
        $success = 'Visit request submitted successfully.';
    }
}

$visits = [];
if ($connection) {
    $stmt = $pdo->prepare("
        SELECT * FROM visits 
        WHERE family_user_id = ? AND elderly_profile_id = ?
        ORDER BY visit_date DESC, visit_time DESC
    ");
    $stmt->execute([$user_id, $connection['profile_id']]);
    $visits = $stmt->fetchAll();
}

$pageTitle = 'Visits';
require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h1 class="mb-0">Visits</h1>
            <p class="text-muted">Schedule and manage visits with <?php echo $connection ? sanitize($connection['elderly_name']) : 'your linked resident'; ?></p>
        </div>
    </div>

    <?php if (!$connection): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> No Linked Residents. You must have an approved connection to schedule visits.
        </div>
    <?php else: ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo sanitize($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title text-primary"><i class="bi bi-calendar-plus"></i> Request Visit</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="visit_date" class="form-label">Date *</label>
                                <input type="date" class="form-control" id="visit_date" name="visit_date" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="visit_time" class="form-label">Time *</label>
                                <input type="time" class="form-control" id="visit_time" name="visit_time" required>
                            </div>
                            <div class="mb-3">
                                <label for="purpose" class="form-label">Purpose (Optional)</label>
                                <input type="text" class="form-control" id="purpose" name="purpose" maxlength="255" placeholder="e.g. Birthday Celebration">
                            </div>
                            <button type="submit" name="request_visit" class="btn btn-primary w-100">Submit Request</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <h5 class="text-primary mb-3"><i class="bi bi-clock-history"></i> Visit History</h5>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <?php if (empty($visits)): ?>
                            <p class="text-muted mb-0">No visit requests found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Purpose</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($visits as $visit): 
                                            $status_class = match($visit['status']) {
                                                'requested' => 'warning',
                                                'confirmed' => 'success',
                                                'completed' => 'primary',
                                                'cancelled' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo date('M j, Y', strtotime($visit['visit_date'])); ?></strong><br>
                                                    <small class="text-muted"><?php echo date('g:i A', strtotime($visit['visit_time'])); ?></small>
                                                </td>
                                                <td><?php echo sanitize($visit['purpose'] ?: '-'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $status_class; ?>">
                                                        <?php echo ucfirst($visit['status']); ?>
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
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
