<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../config/auth.php';

// Only Kitchen staff (role_id = 4)
checkRole(4);

$today = date('Y-m-d');
$displayDate = date('l, F j, Y');

// Fetch today's menu
$stmt = $pdo->prepare("SELECT id, title, notes FROM menus WHERE meal_date = ? LIMIT 1");
$stmt->execute([$today]);
$menu = $stmt->fetch();

$meals = [];
if ($menu) {
    $stmt = $pdo->prepare("SELECT * FROM meals WHERE menu_id = ? ORDER BY FIELD(meal_type, 'breakfast', 'lunch', 'dinner', 'snack'), serving_time ASC");
    $stmt->execute([$menu['id']]);
    $meals = $stmt->fetchAll();
}

// Group meals by type
$grouped_meals = [
    'breakfast' => [],
    'lunch' => [],
    'dinner' => [],
    'snack' => []
];

foreach ($meals as $meal) {
    if (array_key_exists($meal['meal_type'], $grouped_meals)) {
        $grouped_meals[$meal['meal_type']][] = $meal;
    }
}

$pageTitle = 'Daily Menu - ' . $displayDate;
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1>Daily Menu</h1>
                <p class="mb-0 text-white-50"><?php echo $displayDate; ?></p>
            </div>
            <a href="weekly_menu.php" class="btn btn-outline-light btn-elderly">Edit in Weekly Planner</a>
        </div>
    </div>

    <?php if (!$menu): ?>
        <div class="elderly-empty">
            <p class="mb-1">No menu has been planned for today.</p>
            <p class="text-muted small">Please use the Weekly Planner to set up today's meals.</p>
            <a href="weekly_menu.php" class="btn btn-brand mt-3">Go to Weekly Planner</a>
        </div>
    <?php else: ?>
        
        <?php if (!empty($menu['title']) || !empty($menu['notes'])): ?>
            <div class="alert alert-secondary mb-4">
                <?php if (!empty($menu['title'])): ?>
                    <strong><?php echo sanitize($menu['title']); ?></strong><br>
                <?php endif; ?>
                <?php if (!empty($menu['notes'])): ?>
                    <?php echo nl2br(sanitize($menu['notes'])); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php foreach (['breakfast', 'lunch', 'dinner', 'snack'] as $type): ?>
            <h3 class="elderly-section-title text-capitalize mt-4"><?php echo $type; ?></h3>
            
            <?php if (empty($grouped_meals[$type])): ?>
                <div class="card dashboard-card mb-4 bg-light">
                    <div class="card-body text-center text-muted py-4">
                        No <?php echo $type; ?> planned for today.
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4 mb-4">
                    <?php foreach ($grouped_meals[$type] as $meal): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card dashboard-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title fw-bold mb-0 text-brand"><?php echo sanitize($meal['title']); ?></h5>
                                        <span class="badge bg-secondary"><?php echo date('g:i A', strtotime($meal['serving_time'])); ?></span>
                                    </div>
                                    <p class="card-text text-muted small mb-3">
                                        <?php echo sanitize($meal['description']) ?: 'No description provided.'; ?>
                                    </p>
                                    <?php if (!empty($meal['dietary_tags'])): ?>
                                        <div class="mt-auto meal-tags">
                                            <?php foreach (array_map('trim', explode(',', $meal['dietary_tags'])) as $tag): ?>
                                                <?php if ($tag !== ''): ?>
                                                    <span class="badge bg-info text-dark meal-tag">
                                                        <?php echo sanitize($tag); ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

    <?php endif; ?>

</div>

<?php require_once '../includes/footer.php'; ?>
