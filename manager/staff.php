<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
checkRole(5);

$user_id = (int)$_SESSION['user_id'];
$success = '';
$error   = '';

// ── POST: Add new staff account ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $role_id  = (int)($_POST['role_id'] ?? 0);
    $password = trim($_POST['password'] ?? '');

    $allowed_roles = [3, 4]; // Caregiver, Kitchen only — Manager/Admin managed elsewhere
    if (empty($name) || empty($email) || empty($password) || !in_array($role_id, $allowed_roles, true)) {
        $error = 'All fields are required and role must be Caregiver or Kitchen.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'That email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users (role_id, name, email, password_hash, active_status) VALUES (?,?,?,?,1)")
                ->execute([$role_id, $name, $email, $hash]);
            $new_id = (int)$pdo->lastInsertId();
            audit_log($pdo, $user_id, 'add_staff', 'users', $new_id, "Added staff: {$name} (role_id={$role_id})");
            $success = "Staff account for {$name} created successfully.";
        }
    }
}

// ── POST: Toggle active_status ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $target_id  = (int)($_POST['target_id']  ?? 0);
    $new_status = (int)($_POST['new_status'] ?? 0);
    if ($target_id > 0 && in_array($new_status, [0,1], true)) {
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ? AND role_id IN (3,4)");
        $stmt->execute([$target_id]);
        $tgt = $stmt->fetch();
        if ($tgt) {
            $pdo->prepare("UPDATE users SET active_status = ? WHERE id = ?")->execute([$new_status, $target_id]);
            $action  = $new_status ? 'reactivate_staff' : 'deactivate_staff';
            $details = ($new_status ? 'Reactivated' : 'Deactivated') . " staff: {$tgt['name']}";
            audit_log($pdo, $user_id, $action, 'users', $target_id, $details);
            $success = "Staff account " . ($new_status ? 'reactivated' : 'deactivated') . ".";
        } else {
            $error = 'Staff member not found.';
        }
    }
}

// ── Fetch staff grouped by role ───────────────────────────────────────────────
$staff_list = $pdo->query("
    SELECT u.id, u.name, u.email, u.active_status, u.created_at,
           r.name AS role_name, u.role_id
    FROM users u
    JOIN roles r ON r.id = u.role_id
    WHERE u.role_id IN (3,4)
    ORDER BY u.role_id ASC, u.name ASC
")->fetchAll();

$roles_map = $pdo->query("SELECT id, name FROM roles WHERE id IN (3,4) ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'Staff Management';
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width:1000px;">
    <div class="elderly-page-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1>Staff Management</h1>
                <p class="mb-0 text-white-50">Caregivers and Kitchen staff accounts</p>
            </div>
            <button class="btn btn-light btn-sm flex-shrink-0 ms-3" data-bs-toggle="collapse" data-bs-target="#addStaffForm">
                + Add Staff
            </button>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo sanitize($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo sanitize($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Add Staff Form (collapsed by default) -->
    <div class="collapse mb-4" id="addStaffForm">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3">New Staff Account</h2>
                <form method="POST" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="Full name">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required placeholder="email@example.com">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Role *</label>
                        <select name="role_id" class="form-select" required>
                            <option value="">Select…</option>
                            <?php foreach ($roles_map as $rid => $rname): ?>
                            <option value="<?php echo $rid; ?>"><?php echo sanitize($rname); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" required minlength="6" placeholder="Min 6 chars">
                    </div>
                    <div class="col-12">
                        <button type="submit" name="add_staff" class="btn btn-brand">Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (empty($staff_list)): ?>
        <div class="elderly-empty">
            <p>No staff accounts found.</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle bg-white shadow-sm rounded">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th class="text-center">Role</th>
                    <th class="text-center">Status</th>
                    <th>Joined</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staff_list as $s): ?>
                <tr class="<?php echo $s['active_status'] ? '' : 'table-secondary opacity-75'; ?>">
                    <td><strong><?php echo sanitize($s['name']); ?></strong></td>
                    <td><?php echo sanitize($s['email']); ?></td>
                    <td class="text-center">
                        <span class="badge <?php echo $s['role_id'] == 3 ? 'bg-info text-dark' : 'bg-warning text-dark'; ?>">
                            <?php echo sanitize($s['role_name']); ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="status-badge <?php echo $s['active_status'] ? 'status-approved' : 'status-rejected'; ?>">
                            <?php echo $s['active_status'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td><small><?php echo date('M j, Y', strtotime($s['created_at'])); ?></small></td>
                    <td class="text-end">
                        <form method="POST" onsubmit="return confirm('<?php echo $s['active_status'] ? 'Deactivate' : 'Reactivate'; ?> this staff member?')">
                            <input type="hidden" name="target_id"  value="<?php echo (int)$s['id']; ?>">
                            <input type="hidden" name="new_status" value="<?php echo $s['active_status'] ? 0 : 1; ?>">
                            <button type="submit" name="toggle_status"
                                    class="btn btn-sm <?php echo $s['active_status'] ? 'btn-outline-danger' : 'btn-outline-success'; ?>">
                                <?php echo $s['active_status'] ? 'Deactivate' : 'Reactivate'; ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
