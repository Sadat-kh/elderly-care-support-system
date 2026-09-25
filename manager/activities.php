<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
checkRole(5);

$user_id = (int)$_SESSION['user_id'];
$success = '';
$error   = '';
$today   = date('Y-m-d');

// ── POST: Create new activity ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_activity'])) {
    $title            = trim($_POST['title']            ?? '');
    $description      = trim($_POST['description']      ?? '');
    $activity_date    = trim($_POST['activity_date']    ?? '');
    $start_time       = trim($_POST['start_time']       ?? '');
    $end_time         = trim($_POST['end_time']         ?? '') ?: null;
    $location         = trim($_POST['location']         ?? '') ?: null;
    $max_participants = (int)($_POST['max_participants'] ?? 0) ?: null;

    if (empty($title) || empty($activity_date) || empty($start_time)) {
        $error = 'Title, date and start time are required.';
    } else {
        $pdo->prepare("
            INSERT INTO activities (title, description, activity_date, start_time, end_time, location, max_participants, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$title, $description ?: null, $activity_date, $start_time, $end_time, $location, $max_participants, $user_id]);
        $new_id = (int)$pdo->lastInsertId();
        audit_log($pdo, $user_id, 'create_activity', 'activities', $new_id, "Created activity: {$title} on {$activity_date}");
        $success = "Activity '{$title}' created successfully.";
    }
}

// ── POST: Edit existing activity ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_activity'])) {
    $act_id           = (int)($_POST['act_id']           ?? 0);
    $title            = trim($_POST['title']             ?? '');
    $description      = trim($_POST['description']       ?? '');
    $activity_date    = trim($_POST['activity_date']     ?? '');
    $start_time       = trim($_POST['start_time']        ?? '');
    $end_time         = trim($_POST['end_time']          ?? '') ?: null;
    $location         = trim($_POST['location']          ?? '') ?: null;
    $max_participants = (int)($_POST['max_participants'] ?? 0) ?: null;

    if ($act_id <= 0 || empty($title) || empty($activity_date) || empty($start_time)) {
        $error = 'Title, date and start time are required.';
    } else {
        $pdo->prepare("
            UPDATE activities SET title=?, description=?, activity_date=?, start_time=?,
                end_time=?, location=?, max_participants=?
            WHERE id=?
        ")->execute([$title, $description ?: null, $activity_date, $start_time, $end_time, $location, $max_participants, $act_id]);
        audit_log($pdo, $user_id, 'edit_activity', 'activities', $act_id, "Edited activity: {$title} on {$activity_date}");
        $success = "Activity updated.";
    }
}

// ── POST: Delete activity (only if 0 registrations) ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_activity'])) {
    $act_id = (int)($_POST['act_id'] ?? 0);
    if ($act_id > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_registrations WHERE activity_id = ?");
        $stmt->execute([$act_id]);
        if ((int)$stmt->fetchColumn() > 0) {
            $error = 'Cannot delete: this activity has registered participants.';
        } else {
            $pdo->prepare("DELETE FROM activities WHERE id = ?")->execute([$act_id]);
            audit_log($pdo, $user_id, 'delete_activity', 'activities', $act_id, "Deleted activity id={$act_id}");
            $success = 'Activity deleted.';
        }
    }
}

// ── Filter ────────────────────────────────────────────────────────────────────
$filter_period = $_GET['period'] ?? 'upcoming'; // upcoming | past | all
$search        = trim($_GET['q'] ?? '');

$where  = ['1=1'];
$params = [];
if ($filter_period === 'upcoming') {
    $where[]  = 'a.activity_date >= ?';
    $params[] = $today;
} elseif ($filter_period === 'past') {
    $where[]  = 'a.activity_date < ?';
    $params[] = $today;
}
if ($search !== '') {
    $where[]  = '(a.title LIKE ? OR a.location LIKE ?)';
    $params[] = '%'.$search.'%';
    $params[] = '%'.$search.'%';
}
$where_sql = implode(' AND ', $where);

// ── Activities + registration counts ─────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT a.*,
           COALESCE(u.name, 'System') AS created_by_name,
           COUNT(ar.id)               AS reg_count,
           SUM(CASE WHEN ar.status = 'attended' THEN 1 ELSE 0 END) AS attended_count
    FROM activities a
    LEFT JOIN users u                    ON u.id = a.created_by
    LEFT JOIN activity_registrations ar  ON ar.activity_id = a.id
    WHERE {$where_sql}
    GROUP BY a.id
    ORDER BY a.activity_date ASC, a.start_time ASC
");
$stmt->execute($params);
$activities = $stmt->fetchAll();

// Activity to edit (from ?edit=ID)
$edit_act = null;
if (isset($_GET['edit']) && (int)$_GET['edit'] > 0) {
    $stmt = $pdo->prepare("SELECT * FROM activities WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_act = $stmt->fetch() ?: null;
}

$pageTitle = 'Activities Management';
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width:1050px;">
    <div class="elderly-page-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1>Activities Management</h1>
                <p class="mb-0 text-white-50">Create and manage activities residents can register for</p>
            </div>
            <button class="btn btn-light btn-sm flex-shrink-0 ms-3"
                    data-bs-toggle="collapse" data-bs-target="#createForm" aria-expanded="false">
                + New Activity
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

    <!-- Create form -->
    <div class="collapse mb-4" id="createForm">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3">New Activity</h2>
                <form method="POST" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" required placeholder="Activity title">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control" placeholder="e.g. Common Room">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date *</label>
                        <input type="date" name="activity_date" class="form-control" required value="<?php echo $today; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Start Time *</label>
                        <input type="time" name="start_time" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Time</label>
                        <input type="time" name="end_time" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Max Participants</label>
                        <input type="number" name="max_participants" class="form-control" min="1" placeholder="Unlimited">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Optional description…"></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="create_activity" class="btn btn-brand">Create Activity</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit form (shown when ?edit=ID) -->
    <?php if ($edit_act): ?>
    <div class="card border-0 shadow-sm border-start border-4 border-warning mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">✏️ Editing: <?php echo sanitize($edit_act['title']); ?></h2>
            <form method="POST" class="row g-3">
                <input type="hidden" name="act_id" value="<?php echo (int)$edit_act['id']; ?>">
                <div class="col-md-6">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-control" required value="<?php echo sanitize($edit_act['title']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control" value="<?php echo sanitize($edit_act['location'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date *</label>
                    <input type="date" name="activity_date" class="form-control" required value="<?php echo sanitize($edit_act['activity_date']); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Start Time *</label>
                    <input type="time" name="start_time" class="form-control" required value="<?php echo sanitize($edit_act['start_time']); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Time</label>
                    <input type="time" name="end_time" class="form-control" value="<?php echo sanitize($edit_act['end_time'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Max Participants</label>
                    <input type="number" name="max_participants" class="form-control" min="1"
                           value="<?php echo $edit_act['max_participants'] ? (int)$edit_act['max_participants'] : ''; ?>"
                           placeholder="Unlimited">
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"><?php echo sanitize($edit_act['description'] ?? ''); ?></textarea>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" name="edit_activity" class="btn btn-warning">Save Changes</button>
                    <a href="activities.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filter bar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label mb-1">Search</label>
                    <input type="text" name="q" class="form-control" placeholder="Title or location…" value="<?php echo sanitize($search); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">Period</label>
                    <select name="period" class="form-select">
                        <option value="upcoming" <?php echo $filter_period==='upcoming'?'selected':'';?>>Upcoming (today + future)</option>
                        <option value="past"     <?php echo $filter_period==='past'    ?'selected':'';?>>Past</option>
                        <option value="all"      <?php echo $filter_period==='all'     ?'selected':'';?>>All</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-brand w-100">Filter</button>
                    <?php if ($search || $filter_period!=='upcoming'): ?>
                    <a href="activities.php" class="btn btn-outline-secondary w-100">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <p class="text-muted mb-3">Showing <strong><?php echo count($activities); ?></strong> activit<?php echo count($activities)===1?'y':'ies'; ?></p>

    <?php if (empty($activities)): ?>
        <div class="elderly-empty">
            <p>No activities found. Create one using the button above.</p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle bg-white shadow-sm rounded">
            <thead class="table-light">
                <tr>
                    <th>Activity</th>
                    <th>Date / Time</th>
                    <th>Location</th>
                    <th class="text-center">Capacity</th>
                    <th class="text-center">Registered</th>
                    <th class="text-center">Attended</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activities as $a):
                    $reg    = (int)$a['reg_count'];
                    $att    = (int)$a['attended_count'];
                    $cap    = $a['max_participants'] ? (int)$a['max_participants'] : null;
                    $full   = $cap !== null && $reg >= $cap;
                    $isPast = $a['activity_date'] < $today;
                ?>
                <tr class="<?php echo $isPast ? 'text-muted' : ''; ?>">
                    <td>
                        <strong><?php echo sanitize($a['title']); ?></strong>
                        <?php if ($a['description']): ?>
                            <br><small class="text-muted"><?php echo sanitize(mb_substr($a['description'],0,60)).(mb_strlen($a['description'])>60?'…':''); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo date('M j, Y', strtotime($a['activity_date'])); ?>
                        <br><small class="text-muted">
                            <?php echo date('g:i A', strtotime($a['start_time'])); ?>
                            <?php echo $a['end_time'] ? '– '.date('g:i A', strtotime($a['end_time'])) : ''; ?>
                        </small>
                    </td>
                    <td><?php echo $a['location'] ? sanitize($a['location']) : '<span class="text-muted">—</span>'; ?></td>
                    <td class="text-center">
                        <?php if ($cap): ?>
                            <span class="<?php echo $full ? 'text-danger fw-semibold' : ''; ?>">
                                <?php echo $cap; ?>
                                <?php if ($full): ?><br><small>Full</small><?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">∞</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center fw-semibold"><?php echo $reg; ?></td>
                    <td class="text-center">
                        <?php if ($isPast && $reg > 0): ?>
                            <?php echo $att; ?><small class="text-muted">/<?php echo $reg; ?></small>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="?edit=<?php echo (int)$a['id']; ?>&period=<?php echo $filter_period; ?>#editForm"
                               class="btn btn-sm btn-outline-primary">Edit</a>
                            <?php if ($reg === 0): ?>
                            <form method="POST" onsubmit="return confirm('Delete this activity?')">
                                <input type="hidden" name="act_id" value="<?php echo (int)$a['id']; ?>">
                                <button type="submit" name="delete_activity" class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                            <?php else: ?>
                            <button class="btn btn-sm btn-outline-danger" disabled title="Has <?php echo $reg; ?> registration(s)">Delete</button>
                            <?php endif; ?>
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
