<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(3);

$user_id = (int)$_SESSION['user_id'];
$success = '';
$error = '';

// Check assigned residents
$stmt = $pdo->prepare("SELECT elderly_profile_id FROM caregiver_assignments WHERE caregiver_user_id = ? AND active = 1");
$stmt->execute([$user_id]);
$assigned_profiles = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($assigned_profiles)) {
    $in_clause = '0';
} else {
    $in_clause = implode(',', $assigned_profiles);
}

// Handle observation submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'log_observation') {
    $profile_id = (int)$_POST['elderly_profile_id'];
    $severity = $_POST['severity'];
    $note = trim($_POST['observation_text']);
    
    if (!in_array($profile_id, $assigned_profiles)) {
        $error = "You are not assigned to this resident.";
    } elseif (empty($note) || empty($severity)) {
        $error = "Please fill out all fields.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO observations (elderly_profile_id, caregiver_user_id, observation_text, severity) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$profile_id, $user_id, $note, $severity])) {
            $success = "Observation logged successfully.";
        } else {
            $error = "Failed to log observation.";
        }
    }
}

// Fetch residents for dropdown
$stmt = $pdo->prepare("
    SELECT ep.id, u.name 
    FROM elderly_profiles ep 
    JOIN users u ON ep.user_id = u.id 
    WHERE ep.id IN ($in_clause)
    ORDER BY u.name
");
$stmt->execute();
$residents = $stmt->fetchAll();

// Fetch past observations (only for assigned residents)
$stmt = $pdo->prepare("
    SELECT o.*, u.name as resident_name, c.name as caregiver_name
    FROM observations o
    JOIN elderly_profiles ep ON o.elderly_profile_id = ep.id
    JOIN users u ON ep.user_id = u.id
    JOIN users c ON o.caregiver_user_id = c.id
    WHERE o.elderly_profile_id IN ($in_clause)
    ORDER BY o.created_at DESC
");
$stmt->execute();
$observations = $stmt->fetchAll();

$pageTitle = 'Observations & Care Notes';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header d-flex justify-content-between align-items-center">
        <div>
            <h1>Observations</h1>
            <p class="mb-0 text-white-50">Log and view notes for your assigned residents</p>
        </div>
        <button class="btn btn-light fw-bold" data-bs-toggle="modal" data-bs-target="#newObservationModal">
            <i class="bi bi-plus-lg"></i> Log Observation
        </button>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0"><i class="bi bi-journal-text text-primary"></i> Recent Observations</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Resident</th>
                            <th>Logged By</th>
                            <th>Severity</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($observations)): ?>
                            <tr><td colspan="5" class="text-center py-4">No observations logged yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($observations as $obs): ?>
                                <tr>
                                    <td><small class="text-muted"><?php echo date('M j, Y h:i A', strtotime($obs['created_at'])); ?></small></td>
                                    <td><strong><?php echo sanitize($obs['resident_name']); ?></strong></td>
                                    <td><small><?php echo sanitize($obs['caregiver_name']); ?></small></td>
                                    <td>
                                        <?php 
                                        $badge = match($obs['severity']) {
                                            'routine' => 'bg-info text-dark',
                                            'concern' => 'bg-warning text-dark',
                                            'incident' => 'bg-danger',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?php echo $badge; ?>"><?php echo ucfirst($obs['severity']); ?></span>
                                    </td>
                                    <td>
                                        <div style="max-width: 300px; white-space: pre-wrap; font-size: 0.9rem;"><?php echo sanitize($obs['observation_text']); ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- New Observation Modal -->
<div class="modal fade" id="newObservationModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="log_observation">
            <div class="modal-header">
                <h5 class="modal-title">Log Observation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Resident <span class="text-danger">*</span></label>
                    <select name="elderly_profile_id" class="form-select" required>
                        <option value="">Select resident...</option>
                        <?php foreach ($residents as $r): ?>
                            <option value="<?php echo $r['id']; ?>">
                                <?php echo sanitize($r['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Severity</label>
                    <select name="severity" class="form-select" required>
                        <option value="routine" selected>Routine - General note</option>
                        <option value="concern">Concern - Needs monitoring</option>
                        <option value="incident">Incident - Immediate attention needed</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Observation Details <span class="text-danger">*</span></label>
                    <textarea name="observation_text" class="form-control" rows="5" required placeholder="Describe what you observed..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Observation</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
