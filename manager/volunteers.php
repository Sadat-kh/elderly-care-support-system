<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
checkRole(5);

$user_id = (int)$_SESSION['user_id'];
$success = '';
$error = '';

// ── POST Actions ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Approve/Reject Application
    if (isset($_POST['update_application'])) {
        $app_id = (int)$_POST['application_id'];
        $status = $_POST['new_status'];
        if (in_array($status, ['approved', 'rejected'], true)) {
            $pdo->prepare("UPDATE volunteer_applications SET status=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")
                ->execute([$status, $user_id, $app_id]);
            
            // If approved, ensure volunteer profile exists
            if ($status === 'approved') {
                $app = $pdo->prepare("SELECT user_id FROM volunteer_applications WHERE id=?");
                $app->execute([$app_id]);
                $v_user_id = $app->fetchColumn();
                
                $pdo->prepare("INSERT IGNORE INTO volunteers (user_id, status) VALUES (?, 'active')")->execute([$v_user_id]);
            }
            
            audit_log($pdo, $user_id, "application_{$status}", 'volunteer_applications', $app_id, "Application {$status}");
            $success = "Application " . ucfirst($status) . ".";
        }
    }
    
    // 2. Change Volunteer Status
    if (isset($_POST['update_volunteer_status'])) {
        $vol_id = (int)$_POST['volunteer_id'];
        $status = $_POST['new_status'];
        if (in_array($status, ['active', 'inactive'], true)) {
            $pdo->prepare("UPDATE volunteers SET status=? WHERE id=?")->execute([$status, $vol_id]);
            audit_log($pdo, $user_id, 'update_volunteer', 'volunteers', $vol_id, "Status set to {$status}");
            $success = "Volunteer status updated.";
        }
    }
    
    // 3. Update Assignment Status
    if (isset($_POST['update_assignment'])) {
        $assignment_id = (int)$_POST['assignment_id'];
        $status = $_POST['new_status'];
        if (in_array($status, ['assigned', 'in_progress', 'completed', 'cancelled'], true)) {
            $pdo->prepare("UPDATE volunteer_assignments SET status=? WHERE id=?")->execute([$status, $assignment_id]);
            audit_log($pdo, $user_id, 'update_assignment', 'volunteer_assignments', $assignment_id, "Assignment set to {$status}");
            $success = "Assignment status updated.";
        }
    }
}

// ── Data Fetching ────────────────────────────────────────────────────────────

// 1. Pending Applications
$pending_apps = $pdo->query("
    SELECT va.*, u.name, u.email 
    FROM volunteer_applications va
    JOIN users u ON u.id = va.user_id
    WHERE va.status = 'pending'
    ORDER BY va.created_at ASC
")->fetchAll();

// 2. Active Volunteers
$volunteers = $pdo->query("
    SELECT v.*, u.name, u.email,
           (SELECT COUNT(*) FROM volunteer_assignments WHERE volunteer_id = v.id AND status = 'completed') as completed_tasks
    FROM volunteers v
    JOIN users u ON u.id = v.user_id
    ORDER BY v.joined_at DESC
")->fetchAll();

// 3. Current Assignments (not completed/cancelled)
$active_assignments = $pdo->query("
    SELECT a.*, u.name as volunteer_name
    FROM volunteer_assignments a
    JOIN volunteers v ON v.id = a.volunteer_id
    JOIN users u ON u.id = v.user_id
    WHERE a.status IN ('assigned', 'in_progress')
    ORDER BY a.assigned_date ASC
")->fetchAll();

$pageTitle = 'Volunteers';
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width:1100px;">
    <div class="elderly-page-header">
        <h1>Volunteers</h1>
        <p class="mb-0 text-white-50">Manage applications and oversee volunteer assignments</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo sanitize($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Pending Applications -->
        <div class="col-lg-12">
            <h2 class="elderly-section-title">Pending Applications <span class="badge bg-danger ms-2"><?php echo count($pending_apps); ?></span></h2>
            
            <?php if (empty($pending_apps)): ?>
                <div class="card border-0 shadow-sm mb-4"><div class="card-body text-muted text-center py-4">No pending volunteer applications.</div></div>
            <?php else: ?>
                <div class="table-responsive mb-4">
                    <table class="table table-hover align-middle bg-white shadow-sm rounded">
                        <thead class="table-light">
                            <tr>
                                <th>Applicant</th>
                                <th>Motivation / Experience</th>
                                <th>Applied</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_apps as $app): ?>
                            <tr>
                                <td>
                                    <strong><?php echo sanitize($app['name']); ?></strong><br>
                                    <small class="text-muted"><?php echo sanitize($app['email']); ?></small>
                                </td>
                                <td>
                                    <div style="max-width: 400px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <strong>M:</strong> <small class="text-muted"><?php echo sanitize($app['motivation']); ?></small><br>
                                        <strong>E:</strong> <small class="text-muted"><?php echo sanitize($app['experience']); ?></small>
                                    </div>
                                </td>
                                <td><small><?php echo date('M j, Y', strtotime($app['created_at'])); ?></small></td>
                                <td class="text-end">
                                    <form method="POST" class="d-inline-block">
                                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                        <button type="submit" name="update_application" value="1" class="btn btn-sm btn-success">Approve</button>
                                        <input type="hidden" name="new_status" value="approved" disabled id="ns_app_<?php echo $app['id']; ?>">
                                    </form>
                                    <form method="POST" class="d-inline-block ms-1" onsubmit="return confirm('Reject this application?');">
                                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                        <input type="hidden" name="new_status" value="rejected">
                                        <button type="submit" name="update_application" value="1" class="btn btn-sm btn-outline-danger">Reject</button>
                                    </form>
                                    <script>
                                        // A bit of vanilla JS to set the correct value for the first form button since we can't use two submit buttons with the same name and different values reliably without JS in older browsers, but actually we can just use hidden inputs correctly.
                                        document.forms[document.forms.length-2].addEventListener('submit', function() { this.querySelector('input[name="new_status"]').disabled = false; });
                                    </script>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Active Assignments -->
        <div class="col-lg-6">
            <h2 class="elderly-section-title">Active Assignments</h2>
            <?php if (empty($active_assignments)): ?>
                <div class="card border-0 shadow-sm"><div class="card-body text-muted text-center py-4">No active assignments.</div></div>
            <?php else: ?>
                <div class="list-group shadow-sm">
                    <?php foreach ($active_assignments as $assign): ?>
                    <div class="list-group-item list-group-item-action p-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h6 class="mb-0 text-truncate" style="max-width:250px;"><?php echo sanitize($assign['task_title']); ?></h6>
                            <span class="badge bg-<?php echo $assign['status'] === 'assigned' ? 'warning' : 'primary'; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $assign['status'])); ?>
                            </span>
                        </div>
                        <p class="mb-2 small text-muted text-truncate" style="max-width:100%;"><?php echo sanitize($assign['task_description']); ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                            <small>
                                <strong><?php echo sanitize($assign['volunteer_name']); ?></strong><br>
                                <span class="text-muted">Due: <?php echo date('M j', strtotime($assign['assigned_date'])); ?></span>
                            </small>
                            <form method="POST" class="d-flex gap-1">
                                <input type="hidden" name="assignment_id" value="<?php echo $assign['id']; ?>">
                                <select name="new_status" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                    <option value="assigned" <?php if($assign['status']=='assigned') echo 'selected'; ?>>Assigned</option>
                                    <option value="in_progress" <?php if($assign['status']=='in_progress') echo 'selected'; ?>>In Progress</option>
                                    <option value="completed">Complete</option>
                                    <option value="cancelled">Cancel</option>
                                </select>
                                <input type="hidden" name="update_assignment" value="1">
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Volunteer Directory -->
        <div class="col-lg-6">
            <h2 class="elderly-section-title">Volunteer Directory</h2>
            <?php if (empty($volunteers)): ?>
                <div class="card border-0 shadow-sm"><div class="card-body text-muted text-center py-4">No volunteers found.</div></div>
            <?php else: ?>
                <div class="list-group shadow-sm">
                    <?php foreach ($volunteers as $vol): ?>
                    <div class="list-group-item p-3 <?php echo $vol['status']==='inactive' ? 'bg-light' : ''; ?>">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0 <?php echo $vol['status']==='inactive' ? 'text-muted' : ''; ?>">
                                <?php echo sanitize($vol['name']); ?>
                            </h6>
                            <span class="badge bg-<?php echo $vol['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($vol['status']); ?>
                            </span>
                        </div>
                        <p class="mb-1 small">
                            <strong>Skills:</strong> <?php echo sanitize($vol['skills'] ?? 'None specified'); ?><br>
                            <strong>Avail:</strong> <?php echo sanitize($vol['availability'] ?? 'None specified'); ?>
                        </p>
                        <div class="d-flex justify-content-between align-items-end mt-2">
                            <small class="text-muted"><?php echo (int)$vol['completed_tasks']; ?> completed tasks</small>
                            <form method="POST">
                                <input type="hidden" name="volunteer_id" value="<?php echo $vol['id']; ?>">
                                <input type="hidden" name="new_status" value="<?php echo $vol['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                <button type="submit" name="update_volunteer_status" class="btn btn-sm btn-outline-secondary">
                                    Mark <?php echo $vol['status'] === 'active' ? 'Inactive' : 'Active'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
