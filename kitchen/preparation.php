<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../config/auth.php';

checkRole(4);
$user_id = (int)$_SESSION['user_id'];
$success = '';
$error = '';

$date_filter = $_GET['date'] ?? date('Y-m-d');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_prep') {
    $meal_id = (int)($_POST['meal_id'] ?? 0);
    $prepared_qty = (int)($_POST['prepared_qty'] ?? 0);
    $status = $_POST['preparation_status'] ?? 'not_started';
    $reason = trim($_POST['overprep_reason'] ?? '');
    
    if ($prepared_qty < 0) {
        $error = "Prepared quantity cannot be negative.";
    } elseif ($meal_id > 0) {
        try {
            $pdo->beginTransaction();
            
            // Get required qty (Active assignments)
            $stmt = $pdo->prepare("SELECT COUNT(*) as req_qty FROM meal_assignments WHERE meal_id = ? AND assignment_status = 'active'");
            $stmt->execute([$meal_id]);
            $req_qty = (int)$stmt->fetch()['req_qty'];
            
            if ($prepared_qty > $req_qty && empty($reason)) {
                $error = "You are preparing more than the required amount. An explicit reason is required.";
                $pdo->rollBack();
            } else {
                // Update meal
                $stmt = $pdo->prepare("UPDATE meals SET prepared_qty = ?, preparation_status = ? WHERE id = ?");
                $stmt->execute([$prepared_qty, $status, $meal_id]);
                
                // Audit log
                $details = "Updated prep: Qty $prepared_qty, Status $status." . ($reason ? " Reason: $reason" : "");
                $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, 'update_preparation', 'meal', ?, ?, ?)");
                $stmt->execute([$user_id, $meal_id, $details, $_SERVER['REMOTE_ADDR']]);
                
                // Notification if status is READY
                if ($status === 'ready') {
                    // Notify all kitchen staff
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE role_id = 4");
                    $stmt->execute();
                    $kitchen_staff = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    $msg = "Meal #$meal_id is READY for distribution.";
                    foreach ($kitchen_staff as $staff_id) {
                        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, 'Meal Ready', ?, 'info', 'distribution.php')");
                        $stmt->execute([$staff_id, $msg]);
                    }
                }
                
                $pdo->commit();
                $success = "Preparation status updated successfully.";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch meals for the selected date
$stmt = $pdo->prepare("
    SELECT 
        m.id, m.meal_type, m.serving_time, m.title, m.dietary_tags,
        m.prepared_qty, m.preparation_status,
        (SELECT COUNT(*) FROM meal_assignments ma WHERE ma.meal_id = m.id AND ma.assignment_status = 'active') as required_qty,
        (SELECT COUNT(*) FROM meal_distributions md WHERE md.meal_id = m.id AND md.status IN ('delivered', 'served')) as distributed_qty
    FROM meals m
    JOIN menus mn ON mn.id = m.menu_id
    WHERE mn.meal_date = ?
    ORDER BY m.serving_time ASC
");
$stmt->execute([$date_filter]);
$meals = $stmt->fetchAll();

$pageTitle = 'Meal Preparation';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Meal Preparation</h1>
                <p class="mb-0 text-white-50">Manage daily kitchen production workflows.</p>
            </div>
            <form method="GET" class="d-flex">
                <input type="date" name="date" class="form-control me-2" value="<?php echo sanitize($date_filter); ?>" onchange="this.form.submit()">
            </form>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo sanitize($success); ?> <button class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?php echo sanitize($error); ?> <button class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php if (empty($meals)): ?>
        <div class="elderly-empty">
            <p>No meals are scheduled for this date.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($meals as $meal): 
                $remaining = max(0, $meal['prepared_qty'] - $meal['distributed_qty']);
                $progress_pct = $meal['required_qty'] > 0 ? min(100, round(($meal['prepared_qty'] / $meal['required_qty']) * 100)) : 0;
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card dashboard-card h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="fw-bold text-brand mb-0"><?php echo sanitize($meal['title']); ?></h5>
                                <span class="badge bg-secondary"><?php echo date('g:i A', strtotime($meal['serving_time'])); ?></span>
                            </div>
                            <p class="text-muted small mb-3 text-capitalize"><?php echo sanitize($meal['meal_type']); ?> 
                                <?php if ($meal['dietary_tags']): ?>&bull; <i class="fas fa-tag"></i> <?php echo sanitize($meal['dietary_tags']); ?><?php endif; ?>
                            </p>

                            <div class="row text-center mb-3 g-2">
                                <div class="col-4">
                                    <div class="border rounded p-2 bg-light">
                                        <div class="small text-muted">Required</div>
                                        <div class="fw-bold fs-5 text-dark"><?php echo $meal['required_qty']; ?></div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border rounded p-2 bg-light">
                                        <div class="small text-muted">Prepared</div>
                                        <div class="fw-bold fs-5 text-primary"><?php echo $meal['prepared_qty']; ?></div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border rounded p-2 bg-light">
                                        <div class="small text-muted">Remaining</div>
                                        <div class="fw-bold fs-5 <?php echo $remaining > 0 ? 'text-success' : 'text-muted'; ?>"><?php echo $remaining; ?></div>
                                    </div>
                                </div>
                            </div>

                            <form method="POST" class="mt-auto border-top pt-3">
                                <input type="hidden" name="action" value="update_prep">
                                <input type="hidden" name="meal_id" value="<?php echo $meal['id']; ?>">
                                
                                <div class="row g-2 align-items-end">
                                    <div class="col-5">
                                        <label class="form-label small fw-semibold">Status</label>
                                        <select name="preparation_status" class="form-select form-select-sm">
                                            <option value="not_started" <?php if($meal['preparation_status'] == 'not_started') echo 'selected'; ?>>Not Started</option>
                                            <option value="preparing" <?php if($meal['preparation_status'] == 'preparing') echo 'selected'; ?>>Preparing</option>
                                            <option value="ready" <?php if($meal['preparation_status'] == 'ready') echo 'selected'; ?>>Ready</option>
                                            <option value="distributed" <?php if($meal['preparation_status'] == 'distributed') echo 'selected'; ?>>Distributed</option>
                                            <option value="completed" <?php if($meal['preparation_status'] == 'completed') echo 'selected'; ?>>Completed</option>
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label small fw-semibold">Prepared Qty</label>
                                        <input type="number" name="prepared_qty" class="form-control form-control-sm prep-qty-input" min="0" value="<?php echo $meal['prepared_qty']; ?>" data-req="<?php echo $meal['required_qty']; ?>">
                                    </div>
                                    <div class="col-3">
                                        <button type="submit" class="btn btn-brand btn-sm w-100">Update</button>
                                    </div>
                                </div>
                                <div class="overprep-reason-container mt-2 d-none">
                                    <input type="text" name="overprep_reason" class="form-control form-control-sm" placeholder="Reason for over-preparation...">
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.prep-qty-input');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            const req = parseInt(this.getAttribute('data-req'), 10) || 0;
            const prep = parseInt(this.value, 10) || 0;
            const container = this.closest('form').querySelector('.overprep-reason-container');
            const reasonInput = container.querySelector('input');
            
            if (prep > req) {
                container.classList.remove('d-none');
                reasonInput.required = true;
            } else {
                container.classList.add('d-none');
                reasonInput.required = false;
                reasonInput.value = '';
            }
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
