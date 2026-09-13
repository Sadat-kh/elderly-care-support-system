<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../config/auth.php';

// Only Kitchen staff (role_id = 4)
checkRole([4, 5]);

$user_id = (int)$_SESSION['user_id'];
$name    = $_SESSION['name'] ?? 'Kitchen Staff';

$hour = (int)date('G');
if ($hour < 12) {
    $greeting = "Good morning";
} elseif ($hour < 17) {
    $greeting = "Good afternoon";
} else {
    $greeting = "Good evening";
}

$today = date('Y-m-d');
$displayDate = date('l, F j, Y');

// 1. Unread notifications
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$user_id]);
$unread_notifications = (int)$stmt->fetch()['total'];

// 2. Meal Counts (Dynamic from active assignments for today)
$meal_counts = [
    'breakfast' => 0,
    'lunch' => 0,
    'dinner' => 0,
    'snack' => 0
];
$stmt = $pdo->prepare("
    SELECT m.meal_type, COUNT(ma.id) as count
    FROM meal_assignments ma
    JOIN meals m ON m.id = ma.meal_id
    JOIN menus mn ON mn.id = m.menu_id
    WHERE mn.meal_date = ? AND ma.assignment_status = 'active'
    GROUP BY m.meal_type
");
$stmt->execute([$today]);
while ($row = $stmt->fetch()) {
    $meal_counts[$row['meal_type']] = (int)$row['count'];
}

// 3. Special Meal Count (Dietary tagged meals for today)
$stmt = $pdo->prepare("
    SELECT COUNT(ma.id) as count
    FROM meal_assignments ma
    JOIN meals m ON m.id = ma.meal_id
    JOIN menus mn ON mn.id = m.menu_id
    WHERE mn.meal_date = ? 
      AND ma.assignment_status = 'active'
      AND m.dietary_tags IS NOT NULL 
      AND m.dietary_tags != ''
");
$stmt->execute([$today]);
$special_meals_count = (int)$stmt->fetch()['count'];

// 4. Meal Distribution Progress for Today
$progress = [
    'prepared' => 0,
    'delivered' => 0,
    'served' => 0,
    'pending' => 0
];
$stmt = $pdo->prepare("
    SELECT md.status, COUNT(md.id) as count
    FROM meal_distributions md
    JOIN meals m ON m.id = md.meal_id
    JOIN menus mn ON mn.id = m.menu_id
    WHERE mn.meal_date = ?
    GROUP BY md.status
");
$stmt->execute([$today]);
while ($row = $stmt->fetch()) {
    $progress[$row['status']] = (int)$row['count'];
}

$total_distributions = array_sum($progress);
$remaining_meals = $total_distributions - ($progress['delivered'] + $progress['served']);

// 5. Low-stock alerts (Inventory items below threshold)
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM kitchen_inventory WHERE quantity <= low_stock_threshold");
$stmt->execute();
$low_stock_count = (int)$stmt->fetch()['total'];

// 6. Dietary Alerts (Residents with allergies)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM elderly_profiles 
    WHERE allergies IS NOT NULL AND allergies != ''
");
$stmt->execute();
$dietary_alerts_count = (int)$stmt->fetch()['total'];


$pageTitle = 'Kitchen Dashboard';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1><?php echo $greeting; ?>, <?php echo sanitize($name); ?></h1>
                <p class="mb-0 text-white-50"><?php echo $displayDate; ?> &mdash; Kitchen Overview</p>
            </div>
            <?php if ($unread_notifications > 0): ?>
                <a href="#" class="btn btn-warning position-relative flex-shrink-0 ms-3">
                    <i class="fas fa-bell"></i> Alerts
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?php echo $unread_notifications; ?>
                    </span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Active Meal Counts -->
    <h3 class="elderly-section-title">Today's Meal Counts</h3>
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <h5 class="card-title text-muted mb-2">Breakfast</h5>
                    <h2 class="display-4 fw-bold text-brand"><?php echo $meal_counts['breakfast']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <h5 class="card-title text-muted mb-2">Lunch</h5>
                    <h2 class="display-4 fw-bold text-brand"><?php echo $meal_counts['lunch']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <h5 class="card-title text-muted mb-2">Dinner</h5>
                    <h2 class="display-4 fw-bold text-brand"><?php echo $meal_counts['dinner']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <h5 class="card-title text-muted mb-2">Special/Dietary</h5>
                    <h2 class="display-4 fw-bold text-accent"><?php echo $special_meals_count; ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Preparation & Distribution Progress -->
    <h3 class="elderly-section-title">Preparation & Distribution</h3>
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card dashboard-card h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase fw-bold mb-3">Prepared</h6>
                    <div class="d-flex align-items-center">
                        <h2 class="mb-0 me-2 text-success"><?php echo $progress['prepared']; ?></h2>
                        <span class="text-muted">meals</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card dashboard-card h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase fw-bold mb-3">Delivered / Confirmed</h6>
                    <div class="d-flex align-items-center">
                        <h2 class="mb-0 me-2 text-primary"><?php echo $progress['delivered'] + $progress['served']; ?></h2>
                        <span class="text-muted">meals</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card dashboard-card h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase fw-bold mb-3">Remaining to Prep/Deliver</h6>
                    <div class="d-flex align-items-center">
                        <h2 class="mb-0 me-2 text-warning"><?php echo max(0, $remaining_meals); ?></h2>
                        <span class="text-muted">meals</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts & Quick Links -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card dashboard-card h-100 border-start border-4 border-danger">
                <div class="card-body">
                    <h5 class="card-title mb-3">Dietary & Safety Alerts</h5>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0 text-danger"><?php echo $dietary_alerts_count; ?></h3>
                            <span class="text-muted small">Residents with allergies logged</span>
                        </div>
                        <?php if ((int)$_SESSION['role_id'] === 4): ?>
                            <a href="dietary.php" class="btn btn-outline-danger btn-sm">View Details</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card dashboard-card h-100 border-start border-4 border-warning">
                <div class="card-body">
                    <h5 class="card-title mb-3">Inventory Alerts</h5>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0 text-warning"><?php echo $low_stock_count; ?></h3>
                            <span class="text-muted small">Items low on stock</span>
                        </div>
                        <?php if ((int)$_SESSION['role_id'] === 4): ?>
                            <a href="inventory.php" class="btn btn-outline-warning btn-sm">Check Inventory</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once '../includes/footer.php'; ?>
