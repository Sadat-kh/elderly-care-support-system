<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../config/auth.php';

checkRole([4, 5]);
$user_id = (int)$_SESSION['user_id'];
$success = '';
$error = '';

$date_filter = $_GET['date'] ?? date('Y-m-d');
$meal_filter = (int)($_GET['meal_id'] ?? 0);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $distribution_id = (int)($_POST['distribution_id'] ?? 0);
    
    if ($distribution_id > 0) {
        try {
            $pdo->beginTransaction();
            
            // Lock the row for update to check ownership securely
            $stmt = $pdo->prepare("SELECT distributed_by, status FROM meal_distributions WHERE id = ? FOR UPDATE");
            $stmt->execute([$distribution_id]);
            $dist = $stmt->fetch();
            
            if (!$dist) {
                $error = "Distribution record not found.";
            } else {
                if ($action === 'claim') {
                    if ($dist['distributed_by'] !== null && $dist['distributed_by'] != $user_id) {
                        $error = "This distribution is already claimed by another staff member.";
                    } else {
                        $stmt = $pdo->prepare("UPDATE meal_distributions SET distributed_by = ?, status = 'prepared' WHERE id = ?");
                        $stmt->execute([$user_id, $distribution_id]);
                        
                        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, 'claim_distribution', 'meal_distribution', ?, 'Claimed and marked prepared', ?)");
                        $stmt->execute([$user_id, $distribution_id, $_SERVER['REMOTE_ADDR']]);
                        
                        $success = "Distribution claimed successfully.";
                    }
                } elseif ($action === 'update_status') {
                    $new_status = $_POST['status'] ?? '';
                    if ($dist['distributed_by'] != $user_id) {
                        $error = "Access denied: You do not own this distribution record.";
                    } elseif (!in_array($new_status, ['pending', 'prepared', 'delivered', 'served', 'skipped'])) {
                        $error = "Invalid status.";
                    } else {
                        $served_at = ($new_status === 'served') ? date('Y-m-d H:i:s') : null;
                        
                        if ($served_at) {
                            $stmt = $pdo->prepare("UPDATE meal_distributions SET status = ?, served_at = ? WHERE id = ?");
                            $stmt->execute([$new_status, $served_at, $distribution_id]);
                        } else {
                            $stmt = $pdo->prepare("UPDATE meal_distributions SET status = ? WHERE id = ?");
                            $stmt->execute([$new_status, $distribution_id]);
                        }
                        
                        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, 'update_distribution_status', 'meal_distribution', ?, ?, ?)");
                        $stmt->execute([$user_id, $distribution_id, "Updated status to $new_status", $_SERVER['REMOTE_ADDR']]);
                        
                        $success = "Status updated to " . ($new_status === 'served' ? 'Confirmed' : ucfirst($new_status)) . ".";
                    }
                }
            }
            if (empty($error)) {
                $pdo->commit();
            } else {
                $pdo->rollBack();
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Database error: " . $e->getMessage();
        }
    } elseif ($action === 'release_claim' && (int)$_SESSION['role_id'] === 5) {
        $dist_id = (int)($_POST['distribution_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("UPDATE meal_distributions SET distributed_by = NULL WHERE id = ?");
            $stmt->execute([$dist_id]);
            
            $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, 'release_claim', 'meal_distributions', ?, 'Manager released claim', ?)");
            $stmt->execute([$user_id, $dist_id, $_SERVER['REMOTE_ADDR']]);
            
            $success = "Claim released successfully. Kitchen staff can now reclaim it.";
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch meals for filter dropdown
$stmt = $pdo->prepare("
    SELECT m.id, m.title, m.meal_type, m.serving_time 
    FROM meals m 
    JOIN menus mn ON mn.id = m.menu_id 
    WHERE mn.meal_date = ? 
    ORDER BY m.serving_time ASC
");
$stmt->execute([$date_filter]);
$meals_dropdown = $stmt->fetchAll();

// Auto-select first meal if not set
if ($meal_filter === 0 && count($meals_dropdown) > 0) {
    $meal_filter = $meals_dropdown[0]['id'];
}

$distributions = [];
if ($meal_filter > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            md.id, md.status, md.served_at, md.distributed_by,
            u.name as resident_name,
            ep.dietary_requirements, ep.allergies,
            r.room_number,
            staff.name as staff_name
        FROM meal_distributions md
        JOIN elderly_profiles ep ON ep.id = md.elderly_profile_id
        JOIN users u ON u.id = ep.user_id
        LEFT JOIN room_assignments ra ON ra.elderly_profile_id = ep.id AND ra.end_date IS NULL
        LEFT JOIN rooms r ON r.id = ra.room_id
        LEFT JOIN users staff ON staff.id = md.distributed_by
        WHERE md.meal_id = ?
        ORDER BY r.room_number ASC, u.name ASC
    ");
    $stmt->execute([$meal_filter]);
    $distributions = $stmt->fetchAll();
}

$pageTitle = 'Meal Distribution';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Meal Distribution</h1>
                <p class="mb-0 text-white-50">Track delivery and confirmation for residents.</p>
            </div>
            <form method="GET" class="d-flex align-items-center gap-2">
                <input type="date" name="date" class="form-control" value="<?php echo sanitize($date_filter); ?>" onchange="this.form.submit()">
                <select name="meal_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">-- Select Meal --</option>
                    <?php foreach ($meals_dropdown as $m): ?>
                        <option value="<?php echo $m['id']; ?>" <?php echo $meal_filter == $m['id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($m['title']); ?> (<?php echo sanitize($m['meal_type']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo sanitize($success); ?> <button class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?php echo sanitize($error); ?> <button class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php if ($meal_filter === 0): ?>
        <div class="elderly-empty">
            <p>Please select a meal from the dropdown above.</p>
        </div>
    <?php elseif (empty($distributions)): ?>
        <div class="elderly-empty">
            <p>No distributions found for this meal.</p>
            <p class="text-muted small">This usually means no active meal assignments existed when the distribution records were generated.</p>
        </div>
    <?php else: ?>
        <div class="card dashboard-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4 py-3">Room</th>
                                <th class="py-3">Resident</th>
                                <th class="py-3">Dietary Info</th>
                                <th class="py-3">Assigned Staff</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($distributions as $dist): 
                                $is_owner = ($dist['distributed_by'] == $user_id);
                                $is_claimed = ($dist['distributed_by'] !== null);
                            ?>
                                <tr>
                                    <td class="px-4 py-3 fw-bold text-muted">
                                        <?php echo sanitize($dist['room_number'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="py-3 fw-semibold text-brand">
                                        <?php echo sanitize($dist['resident_name']); ?>
                                    </td>
                                    <td class="py-3">
                                        <?php if ($dist['allergies']): ?>
                                            <span class="badge bg-danger mb-1" title="<?php echo sanitize($dist['allergies']); ?>"><i class="fas fa-exclamation-triangle"></i> Allergy</span>
                                        <?php endif; ?>
                                        <?php if ($dist['dietary_requirements']): ?>
                                            <span class="badge bg-info text-dark" title="<?php echo sanitize($dist['dietary_requirements']); ?>"><i class="fas fa-tag"></i> Dietary</span>
                                        <?php endif; ?>
                                        <?php if (!$dist['allergies'] && !$dist['dietary_requirements']): ?>
                                            <span class="text-muted small">Standard</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3">
                                        <?php if ($is_claimed): ?>
                                            <span class="text-muted small"><i class="fas fa-user-circle"></i> <?php echo sanitize($dist['staff_name']); ?></span>
                                            <?php if ((int)$_SESSION['role_id'] === 5): ?>
                                                <form method="POST" class="d-inline ms-2">
                                                    <input type="hidden" name="action" value="release_claim">
                                                    <input type="hidden" name="distribution_id" value="<?php echo $dist['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0" title="Manager Override: Release Claim"><i class="fas fa-times"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ((int)$_SESSION['role_id'] === 4): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="claim">
                                                <input type="hidden" name="distribution_id" value="<?php echo $dist['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">Claim</button>
                                            </form>
                                            <?php else: ?>
                                                <span class="text-muted small">Unclaimed</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if ($is_owner): ?>
                                            <form method="POST" class="d-flex align-items-center gap-2">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="distribution_id" value="<?php echo $dist['id']; ?>">
                                                <select name="status" class="form-select form-select-sm" style="width: 130px;" onchange="this.form.submit()">
                                                    <option value="pending" <?php echo $dist['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="prepared" <?php echo $dist['status'] == 'prepared' ? 'selected' : ''; ?>>Prepared</option>
                                                    <option value="delivered" <?php echo $dist['status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                    <option value="served" <?php echo $dist['status'] == 'served' ? 'selected' : ''; ?>>Confirmed</option>
                                                    <option value="skipped" <?php echo $dist['status'] == 'skipped' ? 'selected' : ''; ?>>Skipped</option>
                                                </select>
                                                <?php if ($dist['status'] == 'served' && $dist['served_at']): ?>
                                                    <span class="text-success small ms-2"><i class="fas fa-check"></i> <?php echo date('g:i A', strtotime($dist['served_at'])); ?></span>
                                                <?php endif; ?>
                                            </form>
                                        <?php else: ?>
                                            <?php 
                                            $badge_class = 'bg-secondary';
                                            if ($dist['status'] == 'delivered') $badge_class = 'bg-primary';
                                            if ($dist['status'] == 'served') $badge_class = 'bg-success';
                                            if ($dist['status'] == 'skipped') $badge_class = 'bg-warning text-dark';
                                            $display_status = $dist['status'] == 'served' ? 'Confirmed' : ucfirst($dist['status']);
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo $display_status; ?></span>
                                            <?php if (!$is_claimed): ?>
                                                <span class="text-muted small ms-2">(Must claim to update)</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
