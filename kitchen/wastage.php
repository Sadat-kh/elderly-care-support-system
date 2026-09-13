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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'log_waste') {
    $meal_id = (int)($_POST['meal_id'] ?? 0);
    $waste_qty = (float)($_POST['waste_qty'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    
    if ($meal_id <= 0 || $waste_qty <= 0 || empty($reason)) {
        $error = "Meal, valid positive quantity, and reason are required.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Get meal details and validate bounds
            $stmt = $pdo->prepare("
                SELECT m.title, m.meal_type, mn.meal_date, m.prepared_qty,
                (SELECT COUNT(*) FROM meal_distributions md WHERE md.meal_id = m.id AND md.status IN ('delivered', 'served')) as distributed_qty,
                (SELECT COALESCE(SUM(quantity), 0) FROM kitchen_wastage kw WHERE kw.meal_date = mn.meal_date AND kw.meal_type = m.meal_type AND kw.item_name = m.title) as already_wasted
                FROM meals m
                JOIN menus mn ON mn.id = m.menu_id
                WHERE m.id = ? FOR UPDATE
            ");
            $stmt->execute([$meal_id]);
            $meal = $stmt->fetch();
            
            if (!$meal) {
                $error = "Meal not found.";
            } else {
                $max_waste_allowed = max(0, $meal['prepared_qty'] - $meal['distributed_qty'] - $meal['already_wasted']);
                
                if ($waste_qty > $max_waste_allowed) {
                    $error = "Validation Error: Waste quantity ($waste_qty) exceeds remaining available portions ($max_waste_allowed) for this meal.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO kitchen_wastage (meal_date, meal_type, item_name, quantity, unit, reason, logged_by) VALUES (?, ?, ?, ?, 'portions', ?, ?)");
                    $stmt->execute([$meal['meal_date'], $meal['meal_type'], $meal['title'], $waste_qty, $reason, $user_id]);
                    $waste_id = $pdo->lastInsertId();
                    
                    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, 'log_wastage', 'kitchen_wastage', ?, ?, ?)");
                    $stmt->execute([$user_id, $waste_id, "Logged $waste_qty portions wasted for {$meal['title']}", $_SERVER['REMOTE_ADDR']]);
                    
                    $success = "Wastage logged successfully.";
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
    }
}

// Fetch meals for the selected date to log waste
$stmt = $pdo->prepare("
    SELECT 
        m.id, m.title, m.meal_type, m.prepared_qty,
        (SELECT COUNT(*) FROM meal_distributions md WHERE md.meal_id = m.id AND md.status IN ('delivered', 'served')) as distributed_qty,
        (SELECT COALESCE(SUM(quantity), 0) FROM kitchen_wastage kw WHERE kw.meal_date = mn.meal_date AND kw.meal_type = m.meal_type AND kw.item_name = m.title) as already_wasted
    FROM meals m
    JOIN menus mn ON mn.id = m.menu_id
    WHERE mn.meal_date = ?
    ORDER BY m.serving_time ASC
");
$stmt->execute([$date_filter]);
$meals = $stmt->fetchAll();

// Fetch daily/weekly/monthly summaries
$stmt = $pdo->prepare("SELECT SUM(quantity) as qty FROM kitchen_wastage WHERE meal_date = ?");
$stmt->execute([date('Y-m-d')]);
$daily_waste = (float)$stmt->fetch()['qty'];

$stmt = $pdo->prepare("SELECT SUM(quantity) as qty FROM kitchen_wastage WHERE meal_date >= DATE_SUB(?, INTERVAL 7 DAY)");
$stmt->execute([date('Y-m-d')]);
$weekly_waste = (float)$stmt->fetch()['qty'];

$stmt = $pdo->prepare("SELECT SUM(quantity) as qty FROM kitchen_wastage WHERE meal_date >= DATE_SUB(?, INTERVAL 30 DAY)");
$stmt->execute([date('Y-m-d')]);
$monthly_waste = (float)$stmt->fetch()['qty'];

// Fetch wastage records for the selected date
$stmt = $pdo->prepare("
    SELECT kw.*, u.name as staff_name 
    FROM kitchen_wastage kw
    JOIN users u ON u.id = kw.logged_by
    WHERE kw.meal_date = ?
    ORDER BY kw.created_at DESC
");
$stmt->execute([$date_filter]);
$wastage_records = $stmt->fetchAll();


$pageTitle = 'Food Wastage';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Food Wastage</h1>
                <p class="mb-0 text-white-50">Log and track unconsumed or spoiled meals.</p>
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

    <!-- Summary Metrics -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card dashboard-card border-start border-4 border-danger">
                <div class="card-body">
                    <h6 class="text-muted fw-bold text-uppercase">Today's Waste</h6>
                    <h3 class="mb-0 text-danger"><?php echo $daily_waste; ?> <span class="fs-6 text-muted">portions</span></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card dashboard-card border-start border-4 border-warning">
                <div class="card-body">
                    <h6 class="text-muted fw-bold text-uppercase">Last 7 Days</h6>
                    <h3 class="mb-0 text-warning"><?php echo $weekly_waste; ?> <span class="fs-6 text-muted">portions</span></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card dashboard-card border-start border-4 border-secondary">
                <div class="card-body">
                    <h6 class="text-muted fw-bold text-uppercase">Last 30 Days</h6>
                    <h3 class="mb-0 text-dark"><?php echo $monthly_waste; ?> <span class="fs-6 text-muted">portions</span></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Log Waste Form & History -->
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card dashboard-card h-100">
                <div class="card-header bg-light border-bottom">
                    <h5 class="mb-0 fw-bold">Log New Wastage</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($meals)): ?>
                        <p class="text-muted">No meals are scheduled for the selected date.</p>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="log_waste">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Select Meal *</label>
                                <select name="meal_id" class="form-select" id="meal_select" required>
                                    <option value="">-- Choose Meal --</option>
                                    <?php foreach ($meals as $meal): 
                                        $remaining = max(0, $meal['prepared_qty'] - $meal['distributed_qty'] - $meal['already_wasted']);
                                    ?>
                                        <option value="<?php echo $meal['id']; ?>" data-max="<?php echo $remaining; ?>">
                                            <?php echo sanitize($meal['title']); ?> (<?php echo ucfirst($meal['meal_type']); ?>) - <?php echo $remaining; ?> remaining
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Waste Quantity (Portions) *</label>
                                <input type="number" step="0.5" min="0.5" name="waste_qty" id="waste_qty_input" class="form-control" required>
                                <div class="form-text text-danger d-none" id="waste_warning">Warning: Exceeds remaining portions.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Reason *</label>
                                <textarea name="reason" class="form-control" rows="2" required placeholder="e.g. Unconsumed by residents, spoiled in prep..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-brand w-100">Log Wastage</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-7">
            <div class="card dashboard-card h-100">
                <div class="card-header bg-light border-bottom">
                    <h5 class="mb-0 fw-bold">Wastage Records (<?php echo sanitize($date_filter); ?>)</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($wastage_records)): ?>
                        <div class="p-4 text-center text-muted">No wastage logged for this date.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2">Item / Meal</th>
                                        <th class="py-2">Quantity</th>
                                        <th class="py-2">Reason</th>
                                        <th class="py-2">Logged By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($wastage_records as $wr): ?>
                                        <tr>
                                            <td class="px-4 py-3 fw-semibold text-brand">
                                                <?php echo sanitize($wr['item_name']); ?>
                                                <div class="small text-muted fw-normal text-capitalize"><?php echo sanitize($wr['meal_type']); ?></div>
                                            </td>
                                            <td class="py-3 text-danger fw-bold">
                                                <?php echo floatval($wr['quantity']) . ' ' . sanitize($wr['unit']); ?>
                                            </td>
                                            <td class="py-3 text-muted small">
                                                <?php echo sanitize($wr['reason']); ?>
                                            </td>
                                            <td class="py-3 small">
                                                <i class="fas fa-user-circle text-muted"></i> <?php echo sanitize($wr['staff_name']); ?>
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
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mealSelect = document.getElementById('meal_select');
    const qtyInput = document.getElementById('waste_qty_input');
    const warning = document.getElementById('waste_warning');
    
    if (mealSelect && qtyInput) {
        function validateQty() {
            const selectedOpt = mealSelect.options[mealSelect.selectedIndex];
            if (!selectedOpt || selectedOpt.value === "") return;
            
            const maxAllowed = parseFloat(selectedOpt.getAttribute('data-max')) || 0;
            const entered = parseFloat(qtyInput.value) || 0;
            
            if (entered > maxAllowed) {
                warning.classList.remove('d-none');
                qtyInput.classList.add('is-invalid');
            } else {
                warning.classList.add('d-none');
                qtyInput.classList.remove('is-invalid');
            }
        }
        
        mealSelect.addEventListener('change', validateQty);
        qtyInput.addEventListener('input', validateQty);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
