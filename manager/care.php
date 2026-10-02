<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(5);

// ── Filters ───────────────────────────────────────────────────────────────────
$filter_resident = (int)($_GET['resident'] ?? 0);
$filter_category = trim($_GET['category'] ?? '');
$filter_active   = $_GET['active'] ?? 'all'; // all | active | inactive

$where = ['1=1'];
$params = [];

if ($filter_resident > 0) {
    $where[]  = 'ep.id = ?';
    $params[] = $filter_resident;
}
if ($filter_category !== '') {
    $where[]  = 'cp.category = ?';
    $params[] = $filter_category;
}
if ($filter_active === 'active') {
    $where[] = 'cp.active = 1';
} elseif ($filter_active === 'inactive') {
    $where[] = 'cp.active = 0';
}

$where_sql = implode(' AND ', $where);

// ── Data ──────────────────────────────────────────────────────────────────────
$care_plans = $pdo->prepare("
    SELECT cp.id, cp.title, cp.description, cp.category, cp.active, cp.created_at,
           ep.id AS profile_id, u.name AS resident_name,
           r.room_number,
           cr_user.name AS created_by_name
    FROM care_plans cp
    JOIN elderly_profiles ep ON ep.id = cp.elderly_profile_id
    JOIN users u              ON u.id  = ep.user_id
    LEFT JOIN room_assignments ra ON ra.elderly_profile_id = ep.id AND ra.end_date IS NULL
    LEFT JOIN rooms r             ON r.id = ra.room_id
    LEFT JOIN users cr_user       ON cr_user.id = cp.created_by
    WHERE {$where_sql}
    ORDER BY u.name ASC, cp.created_at DESC
");
$care_plans->execute($params);
$plans = $care_plans->fetchAll();

// Residents list for filter dropdown
$residents_list = $pdo->query("
    SELECT ep.id, u.name FROM elderly_profiles ep
    JOIN users u ON u.id = ep.user_id
    ORDER BY u.name
")->fetchAll();

// Categories for filter
$categories = $pdo->query(
    "SELECT DISTINCT category FROM care_plans WHERE category IS NOT NULL ORDER BY category"
)->fetchAll(PDO::FETCH_COLUMN);

// Summary counts
$stmt = $pdo->query("SELECT COUNT(*) FROM care_plans WHERE active=1");
$active_count = (int)$stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM care_plans WHERE active=0");
$inactive_count = (int)$stmt->fetchColumn();

// Fetch recent observations
$obs_stmt = $pdo->prepare("
    SELECT o.*, u.name as resident_name, c.name as caregiver_name
    FROM observations o
    JOIN elderly_profiles ep ON o.elderly_profile_id = ep.id
    JOIN users u ON ep.user_id = u.id
    JOIN users c ON o.caregiver_user_id = c.id
    ORDER BY o.created_at DESC
    LIMIT 50
");
$obs_stmt->execute();
$observations = $obs_stmt->fetchAll();

$pageTitle = 'Care Plans';
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width:1050px;">
    <div class="elderly-page-header">
        <h1>Care Plans Overview</h1>
        <p class="mb-0 text-white-50">Read-only oversight of all resident care plans</p>
    </div>

    <!-- Summary -->
    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <div class="dashboard-card h-100">
                <div class="dashboard-card-label">Total Plans</div>
                <div class="dashboard-card-value"><?php echo $active_count + $inactive_count; ?></div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="dashboard-card h-100">
                <div class="dashboard-card-label">Active</div>
                <div class="dashboard-card-value text-success"><?php echo $active_count; ?></div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="dashboard-card h-100 dashboard-card--muted">
                <div class="dashboard-card-label">Inactive</div>
                <div class="dashboard-card-value"><?php echo $inactive_count; ?></div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1">Resident</label>
                    <select name="resident" class="form-select">
                        <option value="">All residents</option>
                        <?php foreach ($residents_list as $res): ?>
                        <option value="<?php echo $res['id']; ?>" <?php echo $filter_resident == $res['id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($res['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo sanitize($cat); ?>" <?php echo $filter_category === $cat ? 'selected' : ''; ?>>
                            <?php echo sanitize(ucfirst($cat)); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Status</label>
                    <select name="active" class="form-select">
                        <option value="all"      <?php echo $filter_active==='all'      ? 'selected':''?>  >All</option>
                        <option value="active"   <?php echo $filter_active==='active'   ? 'selected':''?>  >Active</option>
                        <option value="inactive" <?php echo $filter_active==='inactive' ? 'selected':''?>  >Inactive</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-brand w-100">Filter</button>
                    <?php if ($filter_resident || $filter_category || $filter_active !== 'all'): ?>
                    <a href="care.php" class="btn btn-outline-secondary w-100">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Observations -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0"><i class="bi bi-eye text-primary"></i> Recent Caregiver Observations</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Resident</th>
                            <th>Caregiver</th>
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
                                        <div style="max-width: 300px; white-space: pre-wrap; font-size: 0.9rem;"><?php echo sanitize($obs['note']); ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <h4 class="mb-3">Care Plans List</h4>
    <p class="text-muted mb-3">Showing <strong><?php echo count($plans); ?></strong> plan(s)</p>

    <?php if (empty($plans)): ?>
        <div class="elderly-empty">
            <p>No care plans found matching your filters.</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle bg-white shadow-sm rounded">
            <thead class="table-light">
                <tr>
                    <th>Resident</th>
                    <th>Room</th>
                    <th>Plan Title</th>
                    <th>Category</th>
                    <th class="text-center">Status</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plans as $p): ?>
                <tr>
                    <td><strong><?php echo sanitize($p['resident_name']); ?></strong></td>
                    <td><?php echo $p['room_number'] ? 'Room ' . sanitize($p['room_number']) : '<span class="text-muted">—</span>'; ?></td>
                    <td>
                        <?php echo sanitize($p['title']); ?>
                        <?php if ($p['description']): ?>
                            <br><small class="text-muted"><?php echo sanitize(mb_substr($p['description'], 0, 80)) . (mb_strlen($p['description']) > 80 ? '…' : ''); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p['category']): ?>
                            <span class="badge bg-light text-dark border"><?php echo sanitize(ucfirst($p['category'])); ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <span class="status-badge <?php echo $p['active'] ? 'status-approved' : 'status-cancelled'; ?>">
                            <?php echo $p['active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td><small><?php echo date('M j, Y', strtotime($p['created_at'])); ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
