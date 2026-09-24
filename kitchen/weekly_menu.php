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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save_meal') {
        $meal_date    = trim($_POST['meal_date'] ?? '');
        $meal_type    = trim($_POST['meal_type'] ?? '');
        $serving_time = trim($_POST['serving_time'] ?? '');
        $title        = trim($_POST['title'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $dietary_tags = trim($_POST['dietary_tags'] ?? '');
        
        if (empty($meal_date) || empty($meal_type) || empty($title)) {
            $error = 'Date, Meal Type, and Title are required.';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Find or create menu for the date
                $stmt = $pdo->prepare("SELECT id FROM menus WHERE meal_date = ?");
                $stmt->execute([$meal_date]);
                $menu = $stmt->fetch();
                
                if (!$menu) {
                    $stmt = $pdo->prepare("INSERT INTO menus (meal_date, title, created_by) VALUES (?, ?, ?)");
                    $stmt->execute([$meal_date, "Menu for $meal_date", $user_id]);
                    $menu_id = $pdo->lastInsertId();
                } else {
                    $menu_id = $menu['id'];
                }
                
                // Check if meal type already exists for this menu
                $stmt = $pdo->prepare("SELECT id FROM meals WHERE menu_id = ? AND meal_type = ?");
                $stmt->execute([$menu_id, $meal_type]);
                $existing = $stmt->fetch();
                
                if (empty($serving_time)) {
                    $serving_time = match($meal_type) {
                        'breakfast' => '08:00:00',
                        'lunch' => '12:30:00',
                        'dinner' => '18:00:00',
                        'snack' => '15:00:00',
                        default => '12:00:00'
                    };
                }
                
                if ($existing) {
                    // Update
                    $stmt = $pdo->prepare("UPDATE meals SET serving_time = ?, title = ?, description = ?, dietary_tags = ? WHERE id = ?");
                    $stmt->execute([$serving_time, $title, $description, $dietary_tags, $existing['id']]);
                    $success = ucfirst($meal_type) . " updated successfully for $meal_date.";
                } else {
                    // Insert
                    $stmt = $pdo->prepare("INSERT INTO meals (menu_id, meal_type, serving_time, title, description, dietary_tags) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$menu_id, $meal_type, $serving_time, $title, $description, $dietary_tags]);
                    $success = ucfirst($meal_type) . " added successfully for $meal_date.";
                }
                
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_meal') {
        $meal_id = (int)($_POST['meal_id'] ?? 0);
        if ($meal_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM meals WHERE id = ?");
            $stmt->execute([$meal_id]);
            $success = 'Meal deleted successfully.';
        }
    }
}

// Setup dates for the week view (Default: current week, starting Monday)
$week_offset = isset($_GET['week']) ? (int)$_GET['week'] : 0;
$monday = new DateTime("Monday this week");
if ($week_offset !== 0) {
    $monday->modify("$week_offset weeks");
}

$days = [];
$date_strings = [];
for ($i = 0; $i < 7; $i++) {
    $d = clone $monday;
    $d->modify("+$i days");
    $days[] = [
        'day_name' => $d->format('l'),
        'date_formatted' => $d->format('M j'),
        'db_date' => $d->format('Y-m-d'),
        'is_today' => ($d->format('Y-m-d') === date('Y-m-d'))
    ];
    $date_strings[] = $d->format('Y-m-d');
}

// Fetch meals for these dates
$placeholders = str_repeat('?,', count($date_strings) - 1) . '?';
$stmt = $pdo->prepare("
    SELECT mn.meal_date, m.* 
    FROM meals m
    JOIN menus mn ON mn.id = m.menu_id
    WHERE mn.meal_date IN ($placeholders)
");
$stmt->execute($date_strings);
$all_meals = $stmt->fetchAll();

$grid = [];
foreach ($date_strings as $ds) {
    $grid[$ds] = ['breakfast' => null, 'lunch' => null, 'snack' => null, 'dinner' => null];
}
foreach ($all_meals as $meal) {
    $grid[$meal['meal_date']][$meal['meal_type']] = $meal;
}

$pageTitle = 'Weekly Menu Planner';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Weekly Menu Planner</h1>
                <p class="mb-0 text-white-50">Manage menus for the week of <?php echo $days[0]['date_formatted'] . ' - ' . $days[6]['date_formatted']; ?></p>
            </div>
            <div>
                <a href="?week=<?php echo $week_offset - 1; ?>" class="btn btn-outline-light btn-sm me-2"><i class="fas fa-chevron-left"></i> Prev Week</a>
                <?php if ($week_offset !== 0): ?>
                    <a href="?week=0" class="btn btn-outline-light btn-sm me-2">Current Week</a>
                <?php endif; ?>
                <a href="?week=<?php echo $week_offset + 1; ?>" class="btn btn-outline-light btn-sm"><i class="fas fa-chevron-right"></i> Next Week</a>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo sanitize($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo sanitize($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card dashboard-card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-bordered mb-0" style="min-width: 900px;">
                <thead class="table-light text-center">
                    <tr>
                        <th style="width: 120px;" class="py-3 bg-light">Meal</th>
                        <?php foreach ($days as $day): ?>
                            <th class="py-3 <?php echo $day['is_today'] ? 'bg-primary text-white' : ''; ?>" style="width: 14%;">
                                <div class="fw-bold"><?php echo $day['day_name']; ?></div>
                                <div class="small fw-normal <?php echo $day['is_today'] ? 'text-white-50' : 'text-muted'; ?>"><?php echo $day['date_formatted']; ?></div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (['breakfast', 'lunch', 'snack', 'dinner'] as $type): ?>
                        <tr>
                            <td class="bg-light text-center align-middle fw-semibold text-capitalize text-muted border-end">
                                <?php echo $type; ?>
                            </td>
                            <?php foreach ($days as $day): ?>
                                <?php 
                                    $meal = $grid[$day['db_date']][$type];
                                    $cell_bg = $day['is_today'] ? 'bg-light' : '';
                                ?>
                                <td class="align-top position-relative p-2 <?php echo $cell_bg; ?>">
                                    <?php if ($meal): ?>
                                        <div class="d-flex flex-column h-100">
                                            <div class="fw-bold text-brand small mb-1" style="line-height: 1.2;">
                                                <?php echo sanitize($meal['title']); ?>
                                            </div>
                                            <?php if (!empty($meal['dietary_tags'])): ?>
                                                <div class="meal-tags mb-2">
                                                    <?php foreach (array_map('trim', explode(',', $meal['dietary_tags'])) as $tag): ?>
                                                        <?php if ($tag !== ''): ?>
                                                            <span class="badge bg-info text-dark meal-tag" title="<?php echo sanitize($tag); ?>">
                                                                <?php echo sanitize($tag); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="mt-auto d-flex justify-content-between">
                                                <span class="text-muted" style="font-size: 0.75rem;"><?php echo date('g:i A', strtotime($meal['serving_time'])); ?></span>
                                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none edit-meal-btn" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#mealModal"
                                                    data-date="<?php echo $day['db_date']; ?>"
                                                    data-type="<?php echo $type; ?>"
                                                    data-title="<?php echo sanitize($meal['title']); ?>"
                                                    data-desc="<?php echo sanitize($meal['description']); ?>"
                                                    data-tags="<?php echo sanitize($meal['dietary_tags']); ?>"
                                                    data-time="<?php echo $meal['serving_time']; ?>"
                                                    data-id="<?php echo $meal['id']; ?>">
                                                    Edit
                                                </button>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="h-100 d-flex align-items-center justify-content-center" style="min-height: 80px;">
                                            <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle add-meal-btn" style="width: 32px; height: 32px; padding: 0;"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#mealModal"
                                                data-date="<?php echo $day['db_date']; ?>"
                                                data-type="<?php echo $type; ?>"
                                                title="Add <?php echo ucfirst($type); ?>">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Meal Modal -->
<div class="modal fade" id="mealModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="" method="POST" class="modal-content">
            <input type="hidden" name="action" value="save_meal">
            <input type="hidden" name="meal_date" id="modal_meal_date">
            <input type="hidden" name="meal_type" id="modal_meal_type">
            
            <div class="modal-header bg-light">
                <h5 class="modal-title text-brand fw-bold" id="mealModalLabel">Plan Meal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Planning <strong id="modal_display_type"></strong> for <strong id="modal_display_date"></strong></p>
                
                <div class="mb-3">
                    <label class="form-label fw-semibold">Meal Title / Main Item *</label>
                    <input type="text" class="form-control" name="title" id="modal_title" required placeholder="e.g. Oatmeal with Berries">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description / Sides</label>
                    <textarea class="form-control" name="description" id="modal_desc" rows="2"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Serving Time</label>
                        <input type="time" class="form-control" name="serving_time" id="modal_time">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Dietary/Allergen Tags</label>
                        <input type="text" class="form-control" name="dietary_tags" id="modal_tags" placeholder="e.g. Dairy-Free, Low-Sodium">
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <div>
                    <button type="submit" form="deleteMealForm" class="btn btn-outline-danger d-none" id="modal_delete_btn" onclick="return confirm('Delete this meal?');">Delete</button>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand">Save Meal</button>
                </div>
            </div>
        </form>
        <!-- Hidden delete form -->
        <form action="" method="POST" id="deleteMealForm">
            <input type="hidden" name="action" value="delete_meal">
            <input type="hidden" name="meal_id" id="modal_delete_id">
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mealModal = document.getElementById('mealModal');
    if (mealModal) {
        mealModal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            
            // Populate hidden logic fields
            document.getElementById('modal_meal_date').value = btn.getAttribute('data-date');
            document.getElementById('modal_meal_type').value = btn.getAttribute('data-type');
            
            // Populate display fields
            document.getElementById('modal_display_date').textContent = btn.getAttribute('data-date');
            document.getElementById('modal_display_type').textContent = btn.getAttribute('data-type').charAt(0).toUpperCase() + btn.getAttribute('data-type').slice(1);
            
            // Populate form fields (if editing)
            document.getElementById('modal_title').value = btn.getAttribute('data-title') || '';
            document.getElementById('modal_desc').value = btn.getAttribute('data-desc') || '';
            document.getElementById('modal_time').value = btn.getAttribute('data-time') || '';
            document.getElementById('modal_tags').value = btn.getAttribute('data-tags') || '';
            
            // Handle Delete button visibility
            const mealId = btn.getAttribute('data-id');
            const deleteBtn = document.getElementById('modal_delete_btn');
            if (mealId) {
                document.getElementById('modal_delete_id').value = mealId;
                deleteBtn.classList.remove('d-none');
            } else {
                document.getElementById('modal_delete_id').value = '';
                deleteBtn.classList.add('d-none');
            }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
