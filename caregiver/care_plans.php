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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $profile_id = (int)$_POST['elderly_profile_id'];
    
    // Validate assignment
    if (!in_array($profile_id, $assigned_profiles)) {
        $error = "You are not assigned to this resident.";
    } else {
        if ($_POST['action'] === 'create_plan') {
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $category = trim($_POST['category']);
            
            if ($title) {
                $stmt = $pdo->prepare("INSERT INTO care_plans (elderly_profile_id, title, description, category, created_by) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$profile_id, $title, $description, $category, $user_id])) {
                    $success = "Care plan created successfully.";
                } else {
                    $error = "Failed to create care plan.";
                }
            }
        } elseif ($_POST['action'] === 'toggle_plan') {
            $plan_id = (int)$_POST['plan_id'];
            $active = (int)$_POST['active'];
            // Make sure the plan belongs to an assigned resident
            $stmt = $pdo->prepare("UPDATE care_plans SET active = ? WHERE id = ? AND elderly_profile_id IN ($in_clause)");
            if ($stmt->execute([$active, $plan_id])) {
                $success = "Care plan status updated.";
            } else {
                $error = "Failed to update care plan.";
            }
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

// Filter by profile if provided
$filter_profile_id = isset($_GET['profile_id']) ? (int)$_GET['profile_id'] : null;
$where_clause = "cp.elderly_profile_id IN ($in_clause)";
$params = [];
if ($filter_profile_id && in_array($filter_profile_id, $assigned_profiles)) {
    $where_clause .= " AND cp.elderly_profile_id = ?";
    $params[] = $filter_profile_id;
}

// Fetch care plans
$stmt = $pdo->prepare("
    SELECT cp.*, u.name as resident_name, u_creator.name as creator_name
    FROM care_plans cp
    JOIN elderly_profiles ep ON cp.elderly_profile_id = ep.id
    JOIN users u ON ep.user_id = u.id
    LEFT JOIN users u_creator ON cp.created_by = u_creator.id
    WHERE $where_clause
    ORDER BY cp.created_at DESC
");
$stmt->execute($params);
$care_plans = $stmt->fetchAll();

$pageTitle = 'Care Plans';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header d-flex justify-content-between align-items-center">
        <div>
            <h1>Care & Support Plans</h1>
            <p class="mb-0 text-white-50">Manage care plans for assigned residents</p>
        </div>
        <button class="btn btn-light fw-bold" data-bs-toggle="modal" data-bs-target="#newPlanModal">
            <i class="bi bi-plus-lg"></i> New Care Plan
        </button>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body bg-light">
            <form method="GET" class="d-flex gap-2">
                <select name="profile_id" class="form-select w-auto">
                    <option value="">All Assigned Residents</option>
                    <?php foreach ($residents as $r): ?>
                        <option value="<?php echo $r['id']; ?>" <?php echo $filter_profile_id === $r['id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($r['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        </div>
    </div>

    <?php if (empty($care_plans)): ?>
        <div class="elderly-empty">
            <p>No care plans found.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($care_plans as $cp): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm border-0 <?php echo $cp['active'] ? 'border-start border-success border-4' : 'border-start border-secondary border-4 opacity-75'; ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title fw-bold mb-0"><?php echo sanitize($cp['title']); ?></h5>
                                <?php if ($cp['active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </div>
                            <h6 class="text-primary mb-3"><i class="bi bi-person"></i> <?php echo sanitize($cp['resident_name']); ?></h6>
                            
                            <?php if ($cp['category']): ?>
                                <p class="text-muted small mb-2"><i class="bi bi-tag"></i> Category: <?php echo sanitize(ucfirst($cp['category'])); ?></p>
                            <?php endif; ?>
                            
                            <p class="card-text text-muted" style="white-space: pre-wrap; font-size: 0.95rem;"><?php echo sanitize($cp['description']); ?></p>
                            
                            <hr>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">By: <?php echo sanitize($cp['creator_name'] ?? 'System'); ?></small>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_plan">
                                    <input type="hidden" name="elderly_profile_id" value="<?php echo $cp['elderly_profile_id']; ?>">
                                    <input type="hidden" name="plan_id" value="<?php echo $cp['id']; ?>">
                                    <input type="hidden" name="active" value="<?php echo $cp['active'] ? '0' : '1'; ?>">
                                    <button type="submit" class="btn btn-sm <?php echo $cp['active'] ? 'btn-outline-danger' : 'btn-outline-success'; ?>">
                                        <?php echo $cp['active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- New Plan Modal -->
<div class="modal fade" id="newPlanModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="create_plan">
            <div class="modal-header">
                <h5 class="modal-title">Create Care Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Resident <span class="text-danger">*</span></label>
                    <select name="elderly_profile_id" class="form-select" required>
                        <option value="">Select resident...</option>
                        <?php foreach ($residents as $r): ?>
                            <option value="<?php echo $r['id']; ?>" <?php echo $filter_profile_id === $r['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($r['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. Daily Mobility Support">
                </div>
                <div class="mb-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="mobility">Mobility</option>
                        <option value="nutrition">Nutrition</option>
                        <option value="medical">Medical</option>
                        <option value="hygiene">Hygiene</option>
                        <option value="other" selected>Other</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description / Instructions</label>
                    <textarea name="description" class="form-control" rows="4" placeholder="Detail the care steps..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Plan</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
