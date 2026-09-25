<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
checkRole(5);

$user_id = (int)$_SESSION['user_id'];
$success = '';
$error   = '';

// ── POST: Acknowledge or Resolve an alert ─────────────────────────────────────
$valid_actions = ['active' => ['acknowledged', 'resolved'], 'acknowledged' => ['resolved']];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alert_action'])) {
    $alert_id  = (int)($_POST['alert_id']     ?? 0);
    $new_status = trim($_POST['alert_action'] ?? '');

    if ($alert_id > 0) {
        $stmt = $pdo->prepare("SELECT id, status, alert_type, message, elderly_profile_id FROM emergency_alerts WHERE id = ?");
        $stmt->execute([$alert_id]);
        $alert = $stmt->fetch();

        if (!$alert) {
            $error = 'Alert not found.';
        } elseif (!isset($valid_actions[$alert['status']]) || !in_array($new_status, $valid_actions[$alert['status']], true)) {
            $error = "Cannot mark an alert that is already '{$alert['status']}' as '{$new_status}'.";
        } else {
            if ($new_status === 'resolved') {
                // Set resolved_by + resolved_at on final close
                $pdo->prepare("UPDATE emergency_alerts SET status = 'resolved', resolved_by = ?, resolved_at = NOW() WHERE id = ?")
                    ->execute([$user_id, $alert_id]);
            } else {
                // acknowledged — status change only (no separate column for this in schema)
                $pdo->prepare("UPDATE emergency_alerts SET status = ? WHERE id = ?")
                    ->execute([$new_status, $alert_id]);
            }

            audit_log($pdo, $user_id, "alert_{$new_status}", 'emergency_alerts', $alert_id,
                      "Marked alert #{$alert_id} ({$alert['alert_type']}) as {$new_status}");

            $success = "Emergency alert #" . $alert_id . " marked as " . $new_status . ".";
        }
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$filter_status = trim($_GET['status'] ?? 'all');
$filter_type   = trim($_GET['type']   ?? '');

$where  = ['1=1'];
$params = [];
if ($filter_status !== 'all') {
    $where[]  = 'ea.status = ?';
    $params[] = $filter_status;
}
if ($filter_type !== '') {
    $where[]  = 'ea.alert_type = ?';
    $params[] = $filter_type;
}
$where_sql = implode(' AND ', $where);

$alerts = $pdo->prepare("
    SELECT ea.*,
           u_res.name  AS resident_name,
           ep.id       AS profile_id,
           r.room_number,
           u_resp.name AS resolver_name
    FROM emergency_alerts ea
    LEFT JOIN elderly_profiles ep ON ep.id = ea.elderly_profile_id
    LEFT JOIN users u_res         ON u_res.id = ep.user_id
    LEFT JOIN room_assignments ra ON ra.elderly_profile_id = ep.id AND ra.end_date IS NULL
    LEFT JOIN rooms r             ON r.id = ra.room_id
    LEFT JOIN users u_resp        ON u_resp.id = ea.resolved_by
    WHERE {$where_sql}
    ORDER BY
        CASE ea.status WHEN 'active' THEN 1 WHEN 'acknowledged' THEN 2 ELSE 3 END,
        ea.created_at DESC
");
$alerts->execute($params);
$alert_rows = $alerts->fetchAll();

// Status counts
$counts_raw = $pdo->query("SELECT status, COUNT(*) AS cnt FROM emergency_alerts GROUP BY status")->fetchAll();
$counts = ['active'=>0,'acknowledged'=>0,'resolved'=>0];
foreach ($counts_raw as $c) {
    if (isset($counts[$c['status']])) $counts[$c['status']] = (int)$c['cnt'];
}

// Alert type options from real data
$alert_types = $pdo->query("SELECT DISTINCT alert_type FROM emergency_alerts ORDER BY alert_type")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Emergency Alerts';
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width:1000px;">
    <div class="elderly-page-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1>Emergency Alerts</h1>
                <p class="mb-0 text-white-50">Acknowledge and resolve alerts triggered by residents</p>
            </div>
            <?php if ($counts['active'] > 0): ?>
                <span class="badge bg-danger fs-6 flex-shrink-0 ms-3" style="padding:0.6rem 1rem;">
                    🚨 <?php echo $counts['active']; ?> ACTIVE
                </span>
            <?php endif; ?>
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

    <!-- Status summary -->
    <div class="row g-3 mb-4">
        <?php foreach (['active'=>'danger','acknowledged'=>'warning','resolved'=>'success'] as $st=>$col): ?>
        <div class="col-md-4">
            <a href="?status=<?php echo $st; ?>" class="text-decoration-none">
                <div class="dashboard-card h-100 text-center <?php echo $counts[$st]===0?'dashboard-card--muted':''; ?>">
                    <div class="dashboard-card-label"><?php echo ucfirst($st); ?></div>
                    <div class="dashboard-card-value text-<?php echo $col; ?>"><?php echo $counts[$st]; ?></div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="all"          <?php echo $filter_status==='all'          ?'selected':'';?>>All</option>
                        <option value="active"       <?php echo $filter_status==='active'       ?'selected':'';?>>Active</option>
                        <option value="acknowledged" <?php echo $filter_status==='acknowledged' ?'selected':'';?>>Acknowledged</option>
                        <option value="resolved"     <?php echo $filter_status==='resolved'     ?'selected':'';?>>Resolved</option>
                    </select>
                </div>
                <?php if (!empty($alert_types)): ?>
                <div class="col-md-4">
                    <label class="form-label mb-1">Type</label>
                    <select name="type" class="form-select">
                        <option value="">All types</option>
                        <?php foreach ($alert_types as $t): ?>
                        <option value="<?php echo sanitize($t); ?>" <?php echo $filter_type===$t?'selected':'';?>>
                            <?php echo sanitize(ucfirst($t)); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-4 d-flex gap-2">
                    <button class="btn btn-brand w-100">Filter</button>
                    <?php if ($filter_status!=='all'||$filter_type): ?>
                    <a href="emergency.php" class="btn btn-outline-secondary w-100">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($alert_rows)): ?>
        <div class="elderly-empty">
            <?php if ($filter_status==='active'): ?>
                <p>✓ No active emergency alerts. All clear.</p>
            <?php else: ?>
                <p>No emergency alerts found.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
    <div class="d-flex flex-column gap-3">
        <?php foreach ($alert_rows as $a): ?>
        <?php
            $border = $a['status']==='active' ? 'border-danger' : ($a['status']==='acknowledged' ? 'border-warning' : 'border-success');
            $bg     = $a['status']==='active' ? 'bg-danger bg-opacity-10' : '';
        ?>
        <div class="card border-0 shadow-sm border-start border-4 <?php echo $border; ?> <?php echo $bg; ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge <?php echo $a['status']==='active'?'bg-danger':($a['status']==='acknowledged'?'bg-warning text-dark':'bg-success'); ?>">
                                <?php echo sanitize(ucfirst($a['status'])); ?>
                            </span>
                            <span class="badge bg-light text-dark border"><?php echo sanitize(ucfirst($a['alert_type'])); ?></span>
                            <?php if ($a['room_number']): ?>
                                <small class="text-muted">Room <?php echo sanitize($a['room_number']); ?></small>
                            <?php endif; ?>
                        </div>
                        <p class="mb-1 fw-semibold"><?php echo sanitize($a['message']); ?></p>
                        <small class="text-muted">
                            Resident: <strong><?php echo sanitize($a['resident_name'] ?? 'Unknown'); ?></strong>
                            &mdash; Triggered: <?php echo date('M j, Y g:i A', strtotime($a['created_at'])); ?>
                            <?php if ($a['resolver_name']): ?>
                                &mdash; Resolved by: <strong><?php echo sanitize($a['resolver_name']); ?></strong>
                            <?php endif; ?>
                            <?php if ($a['resolved_at']): ?>
                                &mdash; Resolved: <?php echo date('M j, Y g:i A', strtotime($a['resolved_at'])); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                    <div class="d-flex gap-2 flex-shrink-0 ms-3">
                        <?php if ($a['status'] === 'active'): ?>
                            <form method="POST">
                                <input type="hidden" name="alert_id"    value="<?php echo (int)$a['id']; ?>">
                                <input type="hidden" name="alert_action" value="acknowledged">
                                <button type="submit" class="btn btn-warning btn-sm">Acknowledge</button>
                            </form>
                            <form method="POST">
                                <input type="hidden" name="alert_id"    value="<?php echo (int)$a['id']; ?>">
                                <input type="hidden" name="alert_action" value="resolved">
                                <button type="submit" class="btn btn-success btn-sm">Resolve</button>
                            </form>
                        <?php elseif ($a['status'] === 'acknowledged'): ?>
                            <form method="POST">
                                <input type="hidden" name="alert_id"    value="<?php echo (int)$a['id']; ?>">
                                <input type="hidden" name="alert_action" value="resolved">
                                <button type="submit" class="btn btn-success btn-sm">Resolve</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted small">Resolved</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
