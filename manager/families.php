<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(5);

$user_id = (int)$_SESSION['user_id'];
$success = '';
$error   = '';

// ── Handle approve/reject of family connection ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['connection_action'])) {
    $conn_id = (int)($_POST['connection_id'] ?? 0);
    $action  = $_POST['connection_action'] ?? '';

    if ($conn_id > 0 && in_array($action, ['approved', 'rejected'], true)) {
        $stmt = $pdo->prepare("SELECT id, status FROM family_connections WHERE id = ?");
        $stmt->execute([$conn_id]);
        $conn = $stmt->fetch();

        if ($conn && $conn['status'] === 'pending') {
            $pdo->prepare("UPDATE family_connections SET status = ? WHERE id = ?")
                ->execute([$action, $conn_id]);
            $success = 'Family connection ' . ($action === 'approved' ? 'approved' : 'rejected') . '.';
        } else {
            $error = 'Connection not found or already actioned.';
        }
    }
}

// ── Fetch all family connections ──────────────────────────────────────────────
$stmt = $pdo->query("
    SELECT
        fc.id,
        fc.relationship,
        fc.status,
        fc.created_at,
        -- Resident
        ep.id        AS profile_id,
        u_elder.name AS resident_name,
        u_elder.email AS resident_email,
        -- Family member
        u_family.id   AS family_user_id,
        u_family.name AS family_name,
        u_family.email AS family_email,
        -- Resident room
        r.room_number
    FROM family_connections fc
    JOIN elderly_profiles ep ON ep.id = fc.elderly_profile_id
    JOIN users u_elder        ON u_elder.id  = ep.user_id
    JOIN users u_family       ON u_family.id = fc.family_user_id
    LEFT JOIN room_assignments ra ON ra.elderly_profile_id = ep.id AND ra.end_date IS NULL
    LEFT JOIN rooms r             ON r.id = ra.room_id
    ORDER BY fc.status = 'pending' DESC, fc.created_at DESC
");
$connections = $stmt->fetchAll();

$pending_count  = count(array_filter($connections, fn($c) => $c['status'] === 'pending'));
$approved_count = count(array_filter($connections, fn($c) => $c['status'] === 'approved'));

$pageTitle = 'Family Management';
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width: 1000px;">
    <div class="elderly-page-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1>Family Management</h1>
                <p class="mb-0 text-white-50">Family–resident connections and approval status</p>
            </div>
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

    <!-- Summary Badges -->
    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <div class="dashboard-card h-100">
                <div class="dashboard-card-label">Total Connections</div>
                <div class="dashboard-card-value"><?php echo count($connections); ?></div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="dashboard-card h-100 <?php echo $pending_count > 0 ? '' : 'dashboard-card--muted'; ?>">
                <div class="dashboard-card-label">Pending Approval</div>
                <div class="dashboard-card-value <?php echo $pending_count > 0 ? 'text-warning' : ''; ?>">
                    <?php echo $pending_count; ?>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="dashboard-card h-100">
                <div class="dashboard-card-label">Approved</div>
                <div class="dashboard-card-value text-success"><?php echo $approved_count; ?></div>
            </div>
        </div>
    </div>

    <?php if (count($connections) === 0): ?>
        <div class="elderly-empty">
            <p>No family connections have been registered yet.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle bg-white shadow-sm rounded">
                <thead class="table-light">
                    <tr>
                        <th>Family Member</th>
                        <th>Relationship</th>
                        <th>Resident</th>
                        <th>Room</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($connections as $c): ?>
                    <tr>
                        <td>
                            <strong><?php echo sanitize($c['family_name']); ?></strong><br>
                            <small class="text-muted"><?php echo sanitize($c['family_email']); ?></small>
                        </td>
                        <td><?php echo sanitize(ucfirst($c['relationship'])); ?></td>
                        <td>
                            <strong><?php echo sanitize($c['resident_name']); ?></strong><br>
                            <small class="text-muted"><?php echo sanitize($c['resident_email']); ?></small>
                        </td>
                        <td>
                            <?php echo $c['room_number']
                                ? 'Room ' . sanitize($c['room_number'])
                                : '<span class="text-muted">—</span>'; ?>
                        </td>
                        <td class="text-center">
                            <span class="status-badge status-<?php echo sanitize($c['status']); ?>">
                                <?php echo sanitize(ucfirst($c['status'])); ?>
                            </span>
                            <br><small class="text-muted"><?php echo date('M j, Y', strtotime($c['created_at'])); ?></small>
                        </td>
                        <td class="text-end">
                            <?php if ($c['status'] === 'pending'): ?>
                            <div class="d-flex gap-1 justify-content-end">
                                <form method="POST">
                                    <input type="hidden" name="connection_id"     value="<?php echo (int)$c['id']; ?>">
                                    <input type="hidden" name="connection_action" value="approved">
                                    <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="connection_id"     value="<?php echo (int)$c['id']; ?>">
                                    <input type="hidden" name="connection_action" value="rejected">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                </form>
                            </div>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
