<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(5);

$filter_resident = (int)($_GET['resident'] ?? 0);
$filter_status   = trim($_GET['status'] ?? '');

// ── Per-resident medication adherence summary ─────────────────────────────────
// Count taken/missed/pending events grouped by resident
$adherence = $pdo->query("
    SELECT
        ep.id AS profile_id,
        u.name AS resident_name,
        r.room_number,
        COUNT(DISTINCT m.id)                                              AS total_meds,
        SUM(CASE WHEN me.status = 'taken'   THEN 1 ELSE 0 END)           AS taken_count,
        SUM(CASE WHEN me.status = 'missed'  THEN 1 ELSE 0 END)           AS missed_count,
        SUM(CASE WHEN me.status = 'pending' THEN 1 ELSE 0 END)           AS pending_count,
        SUM(CASE WHEN me.status = 'skipped' THEN 1 ELSE 0 END)           AS skipped_count,
        COUNT(me.id)                                                       AS total_events
    FROM elderly_profiles ep
    JOIN users u ON u.id = ep.user_id
    JOIN medications m  ON m.elderly_profile_id = ep.id AND m.active = 1
    LEFT JOIN medication_events me ON me.medication_id = m.id
    LEFT JOIN room_assignments ra  ON ra.elderly_profile_id = ep.id AND ra.end_date IS NULL
    LEFT JOIN rooms r              ON r.id = ra.room_id
    GROUP BY ep.id, u.name, r.room_number
    ORDER BY missed_count DESC, u.name ASC
")->fetchAll();

// ── Detailed medication events (filtered) ─────────────────────────────────────
$where  = ['m.active = 1'];
$params = [];
if ($filter_resident > 0) {
    $where[]  = 'ep.id = ?';
    $params[] = $filter_resident;
}
if ($filter_status !== '') {
    $where[]  = 'me.status = ?';
    $params[] = $filter_status;
}
$where_sql = implode(' AND ', $where);

$events = $pdo->prepare("
    SELECT me.id, me.event_date, me.status, me.taken_at, me.notes,
           m.name AS med_name, m.dosage, m.time_slot,
           u.name AS resident_name, ep.id AS profile_id
    FROM medication_events me
    JOIN medications m ON m.id = me.medication_id
    JOIN elderly_profiles ep ON ep.id = m.elderly_profile_id
    JOIN users u ON u.id = ep.user_id
    WHERE {$where_sql}
    ORDER BY me.event_date DESC, ep.id ASC
    LIMIT 100
");
$events->execute($params);
$event_rows = $events->fetchAll();

// Residents for filter dropdown
$residents_list = $pdo->query("
    SELECT ep.id, u.name FROM elderly_profiles ep
    JOIN medications m ON m.elderly_profile_id = ep.id
    JOIN users u ON u.id = ep.user_id
    GROUP BY ep.id ORDER BY u.name
")->fetchAll();

// Global adherence totals
$totals = ['taken'=>0,'missed'=>0,'pending'=>0,'skipped'=>0,'total'=>0];
foreach ($adherence as $a) {
    $totals['taken']   += (int)$a['taken_count'];
    $totals['missed']  += (int)$a['missed_count'];
    $totals['pending'] += (int)$a['pending_count'];
    $totals['skipped'] += (int)$a['skipped_count'];
    $totals['total']   += (int)$a['total_events'];
}

$pageTitle = 'Medication Monitoring';
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width:1100px;">
    <div class="elderly-page-header">
        <h1>Medication Monitoring</h1>
        <p class="mb-0 text-white-50">Read-only adherence oversight across all residents</p>
    </div>

    <!-- Overall adherence summary -->
    <?php if ($totals['total'] > 0): ?>
    <div class="row g-3 mb-4">
        <?php
        $summary_cards = [
            ['label'=>'Taken',   'val'=>$totals['taken'],   'cls'=>'text-success'],
            ['label'=>'Missed',  'val'=>$totals['missed'],  'cls'=>'text-danger'],
            ['label'=>'Pending', 'val'=>$totals['pending'], 'cls'=>'text-warning'],
            ['label'=>'Skipped', 'val'=>$totals['skipped'], 'cls'=>'text-secondary'],
        ];
        foreach ($summary_cards as $sc):
        ?>
        <div class="col-6 col-md-3">
            <div class="dashboard-card h-100 text-center <?php echo $sc['val']===0 ? 'dashboard-card--muted' : ''; ?>">
                <div class="dashboard-card-label"><?php echo $sc['label']; ?> (all time)</div>
                <div class="dashboard-card-value <?php echo $sc['cls']; ?>"><?php echo $sc['val']; ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Per-resident adherence table -->
    <h2 class="elderly-section-title">Adherence by Resident</h2>
    <?php if (empty($adherence)): ?>
        <div class="elderly-empty mb-4"><p>No medication data recorded yet.</p></div>
    <?php else: ?>
    <div class="table-responsive mb-5">
        <table class="table table-hover align-middle bg-white shadow-sm rounded">
            <thead class="table-light">
                <tr>
                    <th>Resident</th>
                    <th>Room</th>
                    <th class="text-center">Active Meds</th>
                    <th class="text-center">Taken</th>
                    <th class="text-center text-danger">Missed</th>
                    <th class="text-center text-warning">Pending</th>
                    <th>Adherence</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($adherence as $a):
                    $resolved   = (int)$a['taken_count'] + (int)$a['missed_count'] + (int)$a['skipped_count'];
                    $adherence_pct = $resolved > 0 ? round((int)$a['taken_count'] / $resolved * 100) : null;
                    $bar_class  = $adherence_pct === null ? 'bg-secondary' : ($adherence_pct >= 80 ? 'bg-success' : ($adherence_pct >= 50 ? 'bg-warning' : 'bg-danger'));
                ?>
                <tr>
                    <td>
                        <strong><?php echo sanitize($a['resident_name']); ?></strong>
                        <?php if ((int)$a['missed_count'] > 0): ?>
                            <span class="badge bg-danger ms-1" style="font-size:0.65rem;">⚠ <?php echo $a['missed_count']; ?> missed</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $a['room_number'] ? 'Room '.sanitize($a['room_number']) : '<span class="text-muted">—</span>'; ?></td>
                    <td class="text-center"><?php echo (int)$a['total_meds']; ?></td>
                    <td class="text-center text-success fw-semibold"><?php echo (int)$a['taken_count']; ?></td>
                    <td class="text-center text-danger fw-semibold"><?php echo (int)$a['missed_count']; ?></td>
                    <td class="text-center text-warning fw-semibold"><?php echo (int)$a['pending_count']; ?></td>
                    <td style="min-width:120px;">
                        <?php if ($adherence_pct !== null): ?>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:8px;">
                                    <div class="progress-bar <?php echo $bar_class; ?>" style="width:<?php echo $adherence_pct; ?>%"></div>
                                </div>
                                <small class="fw-semibold"><?php echo $adherence_pct; ?>%</small>
                            </div>
                        <?php else: ?>
                            <span class="text-muted small">No events yet</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Detailed event log with filter -->
    <h2 class="elderly-section-title">Medication Event Log</h2>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label mb-1">Resident</label>
                    <select name="resident" class="form-select">
                        <option value="">All residents</option>
                        <?php foreach ($residents_list as $res): ?>
                        <option value="<?php echo $res['id']; ?>" <?php echo $filter_resident==$res['id']?'selected':'';?>>
                            <?php echo sanitize($res['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">Event Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <?php foreach (['taken','missed','pending','skipped'] as $st): ?>
                        <option value="<?php echo $st; ?>" <?php echo $filter_status===$st?'selected':'';?>>
                            <?php echo ucfirst($st); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-brand w-100">Filter</button>
                    <?php if ($filter_resident || $filter_status): ?>
                    <a href="medications.php" class="btn btn-outline-secondary w-100">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($event_rows)): ?>
        <div class="elderly-empty"><p>No medication events found.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle bg-white shadow-sm rounded">
            <thead class="table-light">
                <tr>
                    <th>Resident</th>
                    <th>Medication</th>
                    <th>Date</th>
                    <th>Scheduled</th>
                    <th class="text-center">Status</th>
                    <th>Taken At</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($event_rows as $ev): ?>
                <tr>
                    <td><?php echo sanitize($ev['resident_name']); ?></td>
                    <td>
                        <strong><?php echo sanitize($ev['med_name']); ?></strong>
                        <?php if ($ev['dosage']): ?><br><small class="text-muted"><?php echo sanitize($ev['dosage']); ?></small><?php endif; ?>
                    </td>
                    <td><?php echo date('M j, Y', strtotime($ev['event_date'])); ?></td>
                    <td><?php echo date('g:i A', strtotime($ev['time_slot'])); ?></td>
                    <td class="text-center">
                        <span class="status-badge status-<?php echo sanitize($ev['status']); ?>">
                            <?php echo sanitize(ucfirst($ev['status'])); ?>
                        </span>
                    </td>
                    <td>
                        <?php echo $ev['taken_at']
                            ? date('g:i A', strtotime($ev['taken_at']))
                            : '<span class="text-muted">—</span>'; ?>
                    </td>
                    <td><small class="text-muted"><?php echo sanitize($ev['notes'] ?? '—'); ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
