<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
checkRole(5);

$user_id = (int)$_SESSION['user_id'];
$success = '';
$error   = '';

// ── Handle deactivate / reactivate ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $target_user_id = (int)($_POST['target_user_id'] ?? 0);
    $new_status     = (int)($_POST['new_status'] ?? 0);

    if ($target_user_id > 0 && in_array($new_status, [0, 1], true)) {
        // Confirm the target is actually an Elderly user (role_id=1) — safety guard
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role_id = 1");
        $stmt->execute([$target_user_id]);
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE users SET active_status = ? WHERE id = ?");
            $stmt->execute([$new_status, $target_user_id]);
            audit_log($pdo, $user_id, $new_status ? 'reactivate_resident' : 'deactivate_resident',
                      'users', $target_user_id,
                      ($new_status ? 'Reactivated' : 'Deactivated') . " resident user_id={$target_user_id}");
            $success = $new_status === 1 ? 'Resident account activated.' : 'Resident account deactivated.';
        } else {
            $error = 'Invalid resident.';
        }
    }
}

// ── Search/filter ────────────────────────────────────────────────────────────
$search      = trim($_GET['q'] ?? '');
$filter_status = $_GET['status'] ?? 'all'; // all | active | inactive

$where_clauses = ["u.role_id = 1"];
$params = [];

if ($search !== '') {
    $where_clauses[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($filter_status === 'active') {
    $where_clauses[] = "u.active_status = 1";
} elseif ($filter_status === 'inactive') {
    $where_clauses[] = "u.active_status = 0";
}

$where_sql = implode(' AND ', $where_clauses);

// ── Fetch all residents ──────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT
        u.id         AS user_id,
        u.name,
        u.email,
        u.active_status,
        u.created_at AS joined_at,
        ep.id        AS profile_id,
        ep.date_of_birth,
        ep.gender,
        ep.medical_conditions,
        ep.allergies,
        -- current room
        r.room_number,
        r.floor,
        r.room_type,
        -- active care plans count
        (SELECT COUNT(*) FROM care_plans cp WHERE cp.elderly_profile_id = ep.id AND cp.active = 1) AS care_plan_count,
        -- active medications count
        (SELECT COUNT(*) FROM medications med WHERE med.elderly_profile_id = ep.id AND med.active = 1) AS med_count
    FROM users u
    LEFT JOIN elderly_profiles ep ON ep.user_id = u.id
    LEFT JOIN room_assignments ra ON ra.elderly_profile_id = ep.id AND ra.end_date IS NULL
    LEFT JOIN rooms r ON r.id = ra.room_id
    WHERE {$where_sql}
    ORDER BY u.name ASC
");
$stmt->execute($params);
$residents = $stmt->fetchAll();

$total_shown = count($residents);

$pageTitle = 'Resident Management';
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width: 1100px;">
    <div class="elderly-page-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1>Resident Management</h1>
                <p class="mb-0 text-white-50">View and manage all registered residents</p>
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

    <!-- Filter / Search Bar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label mb-1">Search</label>
                    <input type="text" name="q" class="form-control" placeholder="Name or email…"
                           value="<?php echo sanitize($search); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="all"      <?php echo $filter_status === 'all'      ? 'selected' : ''; ?>>All</option>
                        <option value="active"   <?php echo $filter_status === 'active'   ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive / Deactivated</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-brand w-100">Search</button>
                    <?php if ($search || $filter_status !== 'all'): ?>
                        <a href="residents.php" class="btn btn-outline-secondary w-100">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <p class="text-muted mb-3">Showing <strong><?php echo $total_shown; ?></strong> resident(s)</p>

    <?php if (count($residents) === 0): ?>
        <div class="elderly-empty">
            <p>No residents found matching your search.</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle bg-white shadow-sm rounded">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Room</th>
                    <th>Gender / DOB</th>
                    <th>Care</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($residents as $r): ?>
                <tr class="<?php echo $r['active_status'] ? '' : 'table-secondary opacity-75'; ?>">
                    <td>
                        <strong><?php echo sanitize($r['name']); ?></strong><br>
                        <small class="text-muted"><?php echo sanitize($r['email']); ?></small>
                        <?php if ($r['allergies']): ?>
                            <br><span class="badge bg-danger mt-1" style="font-size:0.65rem;">⚠ Allergy noted</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r['room_number']): ?>
                            <span class="fw-semibold">Room <?php echo sanitize($r['room_number']); ?></span>
                            <br><small class="text-muted">Floor <?php echo sanitize($r['floor']); ?> · <?php echo sanitize(ucfirst($r['room_type'])); ?></small>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo sanitize(ucfirst($r['gender'] ?? '—')); ?>
                        <?php if ($r['date_of_birth']): ?>
                            <br><small class="text-muted"><?php echo date('M j, Y', strtotime($r['date_of_birth'])); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int)$r['care_plan_count'] > 0): ?>
                            <span class="badge bg-success"><?php echo $r['care_plan_count']; ?> plan(s)</span>
                        <?php else: ?>
                            <span class="text-muted small">None</span>
                        <?php endif; ?>
                        <?php if ((int)$r['med_count'] > 0): ?>
                            <br><span class="badge bg-info text-dark mt-1"><?php echo $r['med_count']; ?> med(s)</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($r['active_status']): ?>
                            <span class="status-badge status-approved">Active</span>
                        <?php else: ?>
                            <span class="status-badge status-rejected">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="resident_edit.php?id=<?php echo (int)$r['profile_id']; ?>"
                               class="btn btn-sm btn-outline-primary">Edit</a>
                            <!-- Deactivate / Reactivate -->
                            <form method="POST" onsubmit="return confirm('<?php echo $r['active_status'] ? 'Deactivate this resident?' : 'Reactivate this resident?'; ?>')">
                                <input type="hidden" name="toggle_status"    value="1">
                                <input type="hidden" name="target_user_id"   value="<?php echo (int)$r['user_id']; ?>">
                                <input type="hidden" name="new_status"       value="<?php echo $r['active_status'] ? 0 : 1; ?>">
                                <button type="submit" class="btn btn-sm <?php echo $r['active_status'] ? 'btn-outline-danger' : 'btn-outline-success'; ?>">
                                    <?php echo $r['active_status'] ? 'Deactivate' : 'Reactivate'; ?>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
