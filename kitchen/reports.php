<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../config/auth.php';

checkRole([4, 5]);
$user_id = (int)$_SESSION['user_id'];

// Filters
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$meal_type = $_GET['meal_type'] ?? 'all';
$status = $_GET['status'] ?? 'all';

// Build WHERE clauses
$where = "mn.meal_date BETWEEN :start AND :end";
$params = [':start' => $start_date, ':end' => $end_date];

if ($meal_type !== 'all') {
    $where .= " AND m.meal_type = :meal_type";
    $params[':meal_type'] = $meal_type;
}
if ($status !== 'all') {
    $where .= " AND m.preparation_status = :status";
    $params[':status'] = $status;
}

// 1. Overall Metrics
$stmt = $pdo->prepare("
    SELECT SUM(m.prepared_qty) as total_prepared
    FROM meals m
    JOIN menus mn ON mn.id = m.menu_id
    WHERE $where
");
$stmt->execute($params);
$total_prepared = (int)$stmt->fetch()['total_prepared'];

$dist_where = "imn.meal_date BETWEEN :start AND :end";
$dist_params = [':start' => $start_date, ':end' => $end_date];
if ($meal_type !== 'all') {
    $dist_where .= " AND im.meal_type = :meal_type";
    $dist_params[':meal_type'] = $meal_type;
}

$stmt2 = $pdo->prepare("
    SELECT COUNT(*) as total_distributed 
    FROM meal_distributions md 
    JOIN meals im ON im.id = md.meal_id 
    JOIN menus imn ON imn.id = im.menu_id 
    WHERE md.status IN ('delivered', 'served') AND $dist_where
");
$stmt2->execute($dist_params);
$total_distributed = (int)$stmt2->fetch()['total_distributed'];

// 2. Wastage Summary
$waste_where = "meal_date BETWEEN :start AND :end";
$waste_params = [':start' => $start_date, ':end' => $end_date];
if ($meal_type !== 'all') {
    $waste_where .= " AND meal_type = :meal_type";
    $waste_params[':meal_type'] = $meal_type;
}
$stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) as total_waste FROM kitchen_wastage WHERE $waste_where");
$stmt->execute($waste_params);
$total_waste = (float)$stmt->fetch()['total_waste'];

// 3. Special/Dietary Meals Count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as special_meals
    FROM meals m
    JOIN menus mn ON mn.id = m.menu_id
    WHERE $where AND m.dietary_tags IS NOT NULL AND m.dietary_tags != ''
");
$stmt->execute($params);
$special_meals = (int)$stmt->fetch()['special_meals'];

// 4. Inventory Status
$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN quantity > 0 AND quantity <= low_stock_threshold THEN 1 ELSE 0 END) as low_stock
    FROM kitchen_inventory
");
$stmt->execute();
$inventory_metrics = $stmt->fetch();


$pageTitle = 'Kitchen Reports';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header">
        <h1>Kitchen Reports</h1>
        <p class="mb-0 text-white-50">Operational metrics and consumption summaries.</p>
    </div>

    <!-- Filters -->
    <div class="card dashboard-card mb-4 bg-light">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Start Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo sanitize($start_date); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">End Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo sanitize($end_date); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">Meal Type</label>
                    <select name="meal_type" class="form-select form-select-sm">
                        <option value="all" <?php echo $meal_type == 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="breakfast" <?php echo $meal_type == 'breakfast' ? 'selected' : ''; ?>>Breakfast</option>
                        <option value="lunch" <?php echo $meal_type == 'lunch' ? 'selected' : ''; ?>>Lunch</option>
                        <option value="dinner" <?php echo $meal_type == 'dinner' ? 'selected' : ''; ?>>Dinner</option>
                        <option value="snack" <?php echo $meal_type == 'snack' ? 'selected' : ''; ?>>Snack</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">Prep Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="not_started" <?php echo $status == 'not_started' ? 'selected' : ''; ?>>Not Started</option>
                        <option value="preparing" <?php echo $status == 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                        <option value="ready" <?php echo $status == 'ready' ? 'selected' : ''; ?>>Ready</option>
                        <option value="distributed" <?php echo $status == 'distributed' ? 'selected' : ''; ?>>Distributed</option>
                        <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-brand btn-sm w-100">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <!-- KPIs -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <h6 class="text-muted text-uppercase fw-bold mb-3">Total Prepared</h6>
                    <h2 class="display-5 fw-bold text-primary mb-0"><?php echo $total_prepared; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <h6 class="text-muted text-uppercase fw-bold mb-3">Total Distributed</h6>
                    <h2 class="display-5 fw-bold text-success mb-0"><?php echo $total_distributed; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card h-100 border-start border-4 border-danger">
                <div class="card-body text-center">
                    <h6 class="text-muted text-uppercase fw-bold mb-3">Wastage (Portions)</h6>
                    <h2 class="display-5 fw-bold text-danger mb-0"><?php echo $total_waste; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card h-100 border-start border-4 border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted text-uppercase fw-bold mb-3">Special Meals</h6>
                    <h2 class="display-5 fw-bold text-info mb-0"><?php echo $special_meals; ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Inventory Snapshot -->
    <h3 class="elderly-section-title">Current Inventory Snapshot</h3>
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card dashboard-card h-100 border-start border-4 border-warning">
                <div class="card-body">
                    <h5 class="card-title fw-bold mb-3">Low Stock Alerts</h5>
                    <div class="d-flex align-items-center justify-content-between">
                        <h2 class="mb-0 text-warning"><?php echo $inventory_metrics['low_stock']; ?> <span class="fs-6 text-muted">items</span></h2>
                        <?php if ((int)$_SESSION['role_id'] === 4): ?>
                            <a href="inventory.php" class="btn btn-outline-warning btn-sm">Manage</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card dashboard-card h-100 border-start border-4 border-danger">
                <div class="card-body">
                    <h5 class="card-title fw-bold mb-3">Out of Stock</h5>
                    <div class="d-flex align-items-center justify-content-between">
                        <h2 class="mb-0 text-danger"><?php echo $inventory_metrics['out_of_stock']; ?> <span class="fs-6 text-muted">items</span></h2>
                        <?php if ((int)$_SESSION['role_id'] === 4): ?>
                            <a href="inventory.php" class="btn btn-outline-danger btn-sm">Manage</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
