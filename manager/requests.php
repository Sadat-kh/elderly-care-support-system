<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
checkRole(5);

$user_id = (int)$_SESSION['user_id'];
$success = '';
$error   = '';

// ── POST: Update request status ───────────────────────────────────────────────
// SRS lifecycle: open → in_progress → completed → closed | rejected | cancelled
$valid_transitions = [
    'open'        => ['in_progress', 'rejected'],
    'in_progress' => ['completed',  'rejected'],
    'completed'   => ['closed'],
    'rejected'    => [],
    'cancelled'   => [],
    'closed'      => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sr_action'])) {
    $sr_id      = (int)($_POST['sr_id']    ?? 0);
    $new_status = trim($_POST['new_status'] ?? '');

    if ($sr_id > 0 && $new_status !== '') {
        $stmt = $pdo->prepare("
            SELECT sr.*, ep.id AS profile_id, ep.user_id AS resident_user_id,
                   u.name AS resident_name
            FROM service_requests sr
            JOIN elderly_profiles ep ON ep.id = sr.elderly_profile_id
            JOIN users u             ON u.id  = ep.user_id
            WHERE sr.id = ?
        ");
        $stmt->execute([$sr_id]);
        $sr = $stmt->fetch();

        if (!$sr) {
            $error = 'Request not found.';
        } elseif (!isset($valid_transitions[$sr['status']]) || !in_array($new_status, $valid_transitions[$sr['status']], true)) {
            $error = "Cannot move from '{$sr['status']}' to '{$new_status}'.";
        } else {
            $pdo->prepare("UPDATE service_requests SET status = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$new_status, $sr_id]);

            $details = "Changed status from {$sr['status']} to {$new_status} on request #{$sr_id} ({$sr['title']})";
            audit_log($pdo, $user_id, 'update_service_request', 'service_requests', $sr_id, $details);

            // Notify the resident of the status change
            notify_user($pdo, (int)$sr['resident_user_id'],
                "Request Update: {$sr['title']}",
                "Your {$sr['request_type']} request has been updated to: " . ucfirst($new_status) . '.',
                'info'
            );

            $success = "Request #{$sr_id} status updated to '{$new_status}'. Resident notified.";
        }
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$filter_type     = trim($_GET['type']     ?? '');
$filter_status   = trim($_GET['status']   ?? '');
$filter_priority = trim($_GET['priority'] ?? '');
$filter_resident = (int)($_GET['resident'] ?? 0);

$where  = ['1=1'];
$params = [];
if ($filter_type !== '') {
    $where[]  = 'sr.request_type = ?';
    $params[] = $filter_type;
}
if ($filter_status !== '') {
    $where[]  = 'sr.status = ?';
    $params[] = $filter_status;
}
if ($filter_priority !== '') {
    $where[]  = 'sr.priority = ?';
    $params[] = $filter_priority;
}
if ($filter_resident > 0) {
    $where[]  = 'ep.id = ?';
    $params[] = $filter_resident;
}
$where_sql = implode(' AND ', $where);

$requests = $pdo->prepare("
    SELECT sr.id, sr.request_type, sr.title, sr.description, sr.priority,
           sr.status, sr.created_at, sr.updated_at,
           u_res.name   AS resident_name,
           u_req.name   AS requested_by_name,
           ep.id        AS profile_id,
           r.room_number
    FROM service_requests sr
    JOIN elderly_profiles ep ON ep.id = sr.elderly_profile_id
    JOIN users u_res          ON u_res.id = ep.user_id
    JOIN users u_req          ON u_req.id = sr.requested_by
    LEFT JOIN room_assignments ra ON ra.elderly_profile_id = ep.id AND ra.end_date IS NULL
    LEFT JOIN rooms r             ON r.id = ra.room_id
    WHERE {$where_sql}
    ORDER BY
        CASE sr.status WHEN 'open' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END,
        CASE sr.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END,
        sr.created_at DESC
");
$requests->execute($params);
$rows = $requests->fetchAll();

// Filter options
$types      = ['housekeeping','medical','dietary','maintenance','general','transportation','other'];
$statuses   = ['open','in_progress','completed','closed','rejected','cancelled'];
$priorities = ['urgent','high','medium','low'];
$residents_list = $pdo->query("
    SELECT ep.id, u.name FROM elderly_profiles ep
    JOIN users u ON u.id = ep.user_id ORDER BY u.name
")->fetchAll();

// Count by status for summary
$status_counts = array_fill_keys($statuses, 0);
$all_for_count = $pdo->query("SELECT status, COUNT(*) AS cnt FROM service_requests GROUP BY status")->fetchAll();
foreach ($all_for_count as $c) {
    if (isset($status_counts[$c['status']])) $status_counts[$c['status']] = (int)$c['cnt'];
}

$priority_badge = ['urgent'=>'bg-danger','high'=>'bg-warning text-dark','medium'=>'bg-info text-dark','low'=>'bg-secondary'];
$status_next    = [
    'open'        => ['in_progress'=>'Mark In Progress', 'rejected'=>'Reject'],
    'in_progress' => ['completed'=>'Mark Completed',    'rejected'=>'Reject'],
    'completed'   => ['closed'=>'Close'],
];

$pageTitle = 'Service Requests';
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width:1150px;">
    <div class="elderly-page-header">
        <h1>Service Requests</h1>
        <p class="mb-0 text-white-50">Unified view of all resident service requests — approve, progress and close</p>
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

    <!-- Status summary strip -->
    <div class="row g-2 mb-4">
        <?php foreach (['open'=>'warning','in_progress'=>'primary','completed'=>'success','closed'=>'secondary','rejected'=>'danger'] as $st=>$col): ?>
        <div class="col">
            <a href="?status=<?php echo $st; ?>" class="text-decoration-none">
                <div class="card border-0 shadow-sm text-center py-2">
                    <div class="fw-bold text-<?php echo $col; ?>" style="font-size:1.4rem;"><?php echo $status_counts[$st]; ?></div>
                    <small class="text-muted"><?php echo ucwords(str_replace('_',' ',$st)); ?></small>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label mb-1">Resident</label>
                    <select name="resident" class="form-select">
                        <option value="">All</option>
                        <?php foreach ($residents_list as $res): ?>
                        <option value="<?php echo $res['id']; ?>" <?php echo $filter_resident==$res['id']?'selected':'';?>>
                            <?php echo sanitize($res['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Type</label>
                    <select name="type" class="form-select">
                        <option value="">All types</option>
                        <?php foreach ($types as $t): ?>
                        <option value="<?php echo $t; ?>" <?php echo $filter_type===$t?'selected':'';?>>
                            <?php echo ucfirst($t); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All statuses</option>
                        <?php foreach ($statuses as $st): ?>
                        <option value="<?php echo $st; ?>" <?php echo $filter_status===$st?'selected':'';?>>
                            <?php echo ucwords(str_replace('_',' ',$st)); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Priority</label>
                    <select name="priority" class="form-select">
                        <option value="">All</option>
                        <?php foreach ($priorities as $p): ?>
                        <option value="<?php echo $p; ?>" <?php echo $filter_priority===$p?'selected':'';?>>
                            <?php echo ucfirst($p); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-brand w-100">Filter</button>
                    <?php if ($filter_type||$filter_status||$filter_priority||$filter_resident): ?>
                    <a href="requests.php" class="btn btn-outline-secondary w-100">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <p class="text-muted mb-3">Showing <strong><?php echo count($rows); ?></strong> request(s)</p>

    <?php if (empty($rows)): ?>
        <div class="elderly-empty">
            <p>No service requests found matching your filters.</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle bg-white shadow-sm rounded">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Resident / Room</th>
                    <th>Type</th>
                    <th>Title</th>
                    <th class="text-center">Priority</th>
                    <th class="text-center">Status</th>
                    <th>Submitted</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $sr): ?>
                <tr>
                    <td class="text-muted"><?php echo (int)$sr['id']; ?></td>
                    <td>
                        <strong><?php echo sanitize($sr['resident_name']); ?></strong>
                        <?php if ($sr['room_number']): ?>
                            <br><small class="text-muted">Room <?php echo sanitize($sr['room_number']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-light text-dark border"><?php echo sanitize(ucfirst($sr['request_type'])); ?></span></td>
                    <td>
                        <?php echo sanitize($sr['title']); ?>
                        <?php if ($sr['description']): ?>
                            <br><small class="text-muted"><?php echo sanitize(mb_substr($sr['description'],0,70)).(mb_strlen($sr['description'])>70?'…':''); ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <span class="badge <?php echo $priority_badge[$sr['priority']] ?? 'bg-secondary'; ?>">
                            <?php echo sanitize(ucfirst($sr['priority'])); ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="status-badge status-<?php echo sanitize(str_replace('_','-',$sr['status'])); ?>">
                            <?php echo sanitize(ucwords(str_replace('_',' ',$sr['status']))); ?>
                        </span>
                    </td>
                    <td><small><?php echo date('M j, Y', strtotime($sr['created_at'])); ?></small></td>
                    <td class="text-end">
                        <?php if (isset($status_next[$sr['status']])): ?>
                        <div class="d-flex gap-1 justify-content-end flex-wrap">
                            <?php foreach ($status_next[$sr['status']] as $ns => $label): ?>
                            <form method="POST">
                                <input type="hidden" name="sr_id"      value="<?php echo (int)$sr['id']; ?>">
                                <input type="hidden" name="new_status" value="<?php echo sanitize($ns); ?>">
                                <button type="submit" name="sr_action"
                                        class="btn btn-sm <?php echo $ns==='rejected' ? 'btn-outline-danger' : ($ns==='completed' ? 'btn-success' : 'btn-outline-primary'); ?>">
                                    <?php echo sanitize($label); ?>
                                </button>
                            </form>
                            <?php endforeach; ?>
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
