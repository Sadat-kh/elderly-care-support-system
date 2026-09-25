<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
checkRole(5);

$user_id = (int)$_SESSION['user_id'];
$name    = $_SESSION['name'] ?? 'Manager';

$hour = (int)date('G');
if ($hour < 12)       $greeting = 'Good morning';
elseif ($hour < 17)   $greeting = 'Good afternoon';
else                  $greeting = 'Good evening';

$today       = date('Y-m-d');
$displayDate = date('l, F j, Y');

// ── 1. Total residents (all elderly_profiles rows) ──────────────────────────
$stmt = $pdo->query("SELECT COUNT(*) AS total FROM elderly_profiles");
$total_residents = (int)$stmt->fetch()['total'];

// ── 2. Total staff (Caregiver=3, Kitchen=4, Manager=5) ──────────────────────
$stmt = $pdo->query("SELECT COUNT(*) AS total FROM users WHERE role_id IN (3,4,5) AND active_status = 1");
$total_staff = (int)$stmt->fetch()['total'];

// ── 3. Room occupancy ────────────────────────────────────────────────────────
$stmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'occupied'    THEN 1 ELSE 0 END) AS occupied,
        SUM(CASE WHEN status = 'available'   THEN 1 ELSE 0 END) AS available,
        SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) AS maintenance
    FROM rooms
");
$room_stats = $stmt->fetch();

// ── 4. Pending requests (service_requests open + room_change_requests pending) ─
$stmt = $pdo->query("SELECT COUNT(*) AS total FROM service_requests WHERE status = 'open'");
$open_service = (int)$stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) AS total FROM room_change_requests WHERE status = 'pending'");
$pending_room_changes = (int)$stmt->fetch()['total'];

$total_pending = $open_service + $pending_room_changes;

// ── 5. Active emergency alerts ───────────────────────────────────────────────
$stmt = $pdo->query("SELECT COUNT(*) AS total FROM emergency_alerts WHERE status = 'active'");
$active_alerts = (int)$stmt->fetch()['total'];

// ── 6. Today's meal summary — exact same query Kitchen uses ─────────────────
$meal_counts = ['breakfast' => 0, 'lunch' => 0, 'dinner' => 0, 'snack' => 0];
$stmt = $pdo->prepare("
    SELECT m.meal_type, COUNT(ma.id) AS count
    FROM meal_assignments ma
    JOIN meals m  ON m.id  = ma.meal_id
    JOIN menus mn ON mn.id = m.menu_id
    WHERE mn.meal_date = ? AND ma.assignment_status = 'active'
    GROUP BY m.meal_type
");
$stmt->execute([$today]);
while ($row = $stmt->fetch()) {
    $meal_counts[$row['meal_type']] = (int)$row['count'];
}
$total_meals_today = array_sum($meal_counts);

// ── 7. Low-stock kitchen alerts ──────────────────────────────────────────────
$stmt = $pdo->query("SELECT COUNT(*) AS total FROM kitchen_inventory WHERE quantity <= low_stock_threshold");
$low_stock = (int)$stmt->fetch()['total'];

// ── 8. Recent pending room-change requests (for quick-view) ─────────────────
$stmt = $pdo->query("
    SELECT rcr.id, u.name AS resident_name,
           cr.room_number AS current_room,
           pr.room_number AS preferred_room,
           rcr.reason, rcr.created_at
    FROM room_change_requests rcr
    JOIN elderly_profiles ep ON ep.id = rcr.elderly_profile_id
    JOIN users u             ON u.id  = ep.user_id
    LEFT JOIN rooms cr ON cr.id = rcr.current_room_id
    LEFT JOIN rooms pr ON pr.id = rcr.preferred_room_id
    WHERE rcr.status = 'pending'
    ORDER BY rcr.created_at DESC
    LIMIT 5
");
$recent_room_requests = $stmt->fetchAll();

// ── 9. Recent service requests (open/in_progress) ───────────────────────────
$stmt = $pdo->query("
    SELECT sr.id, sr.title, sr.priority, sr.status, sr.created_at, u.name AS resident_name
    FROM service_requests sr
    JOIN elderly_profiles ep ON ep.id = sr.elderly_profile_id
    JOIN users u             ON u.id  = ep.user_id
    WHERE sr.status IN ('open', 'in_progress')
    ORDER BY sr.created_at DESC
    LIMIT 5
");
$recent_service = $stmt->fetchAll();

$pageTitle = 'Manager Dashboard';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <!-- Page Header -->
    <div class="elderly-page-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1><?php echo sanitize($greeting); ?>, <?php echo sanitize($name); ?></h1>
                <p class="mb-0 text-white-50"><?php echo $displayDate; ?> &mdash; Operations Overview</p>
            </div>
            <?php if ($active_alerts > 0): ?>
                <a href="#" class="btn btn-danger position-relative flex-shrink-0 ms-3">
                    🚨 Alerts
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark">
                        <?php echo $active_alerts; ?>
                    </span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Stat Cards ── -->
    <div class="row g-3 mb-4">
        <!-- Residents -->
        <div class="col-sm-6 col-lg-3">
            <a href="<?php echo sanitize(manager_url('residents.php')); ?>" class="text-decoration-none">
                <div class="dashboard-card h-100">
                    <div class="dashboard-card-label">Total Residents</div>
                    <div class="dashboard-card-value"><?php echo $total_residents; ?></div>
                    <small class="text-muted">Registered profiles</small>
                </div>
            </a>
        </div>

        <!-- Staff -->
        <div class="col-sm-6 col-lg-3">
            <div class="dashboard-card h-100">
                <div class="dashboard-card-label">Active Staff</div>
                <div class="dashboard-card-value"><?php echo $total_staff; ?></div>
                <small class="text-muted">Caregiver · Kitchen · Manager</small>
            </div>
        </div>

        <!-- Room Occupancy -->
        <div class="col-sm-6 col-lg-3">
            <a href="<?php echo sanitize(manager_url('rooms.php')); ?>" class="text-decoration-none">
                <div class="dashboard-card h-100 <?php echo (int)$room_stats['available'] === 0 ? 'dashboard-card--muted' : ''; ?>">
                    <div class="dashboard-card-label">Room Occupancy</div>
                    <div class="dashboard-card-value text-dark">
                        <?php echo (int)$room_stats['occupied']; ?><span style="font-size:1rem; font-weight:400; color:#6c757d;"> / <?php echo (int)$room_stats['total']; ?></span>
                    </div>
                    <small class="text-muted"><?php echo (int)$room_stats['available']; ?> available</small>
                </div>
            </a>
        </div>

        <!-- Pending Requests -->
        <div class="col-sm-6 col-lg-3">
            <a href="<?php echo sanitize(manager_url('rooms.php')); ?>" class="text-decoration-none">
                <div class="dashboard-card h-100 <?php echo $total_pending > 0 ? '' : 'dashboard-card--muted'; ?>">
                    <div class="dashboard-card-label">Pending Requests</div>
                    <div class="dashboard-card-value <?php echo $total_pending > 0 ? 'text-warning' : 'text-dark'; ?>">
                        <?php echo $total_pending; ?>
                    </div>
                    <small class="text-muted"><?php echo $open_service; ?> service · <?php echo $pending_room_changes; ?> room change</small>
                </div>
            </a>
        </div>
    </div>

    <!-- ── Today's Kitchen Summary ── -->
    <h3 class="elderly-section-title">🍽️ Today's Kitchen Summary</h3>
    <?php if ($total_meals_today > 0): ?>
    <div class="row g-3 mb-4">
        <?php foreach (['breakfast' => '🌅', 'lunch' => '☀️', 'dinner' => '🌙', 'snack' => '🍎'] as $type => $icon): ?>
        <div class="col-6 col-md-3">
            <div class="card dashboard-card h-100">
                <div class="card-body text-center">
                    <div style="font-size:1.5rem; margin-bottom:0.25rem;"><?php echo $icon; ?></div>
                    <h5 class="card-title text-muted mb-1" style="font-size:0.85rem; text-transform:uppercase; letter-spacing:0.05em;">
                        <?php echo ucfirst($type); ?>
                    </h5>
                    <h2 class="display-5 fw-bold mb-0" style="color: var(--brand-green);">
                        <?php echo $meal_counts[$type]; ?>
                    </h2>
                    <small class="text-muted">assignments</small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="elderly-empty mb-4">
        <p>No meal assignments recorded for today.</p>
    </div>
    <?php endif; ?>

    <!-- ── Two-column lower section ── -->
    <div class="row g-3">

        <!-- Pending Room-Change Requests -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">⏳ Room-Change Requests</h2>
                        <a href="<?php echo sanitize(manager_url('rooms.php')); ?>" class="btn btn-sm btn-outline-secondary">View All</a>
                    </div>
                    <?php if (count($recent_room_requests) > 0): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($recent_room_requests as $rcr): ?>
                            <li class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?php echo sanitize($rcr['resident_name']); ?></strong><br>
                                        <small class="text-muted">
                                            <?php echo $rcr['current_room'] ? 'From Room ' . sanitize($rcr['current_room']) : 'No current room'; ?>
                                            → <?php echo $rcr['preferred_room'] ? 'Room ' . sanitize($rcr['preferred_room']) : 'Any available'; ?>
                                        </small>
                                    </div>
                                    <span class="status-badge status-pending ms-2">Pending</span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-muted text-center py-4">No pending room-change requests.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Open Service Requests + Kitchen Alert -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">🔧 Open Service Requests</h2>
                    </div>
                    <?php if (count($recent_service) > 0): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($recent_service as $sr): ?>
                            <li class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?php echo sanitize($sr['title']); ?></strong>
                                        <br><small class="text-muted"><?php echo sanitize($sr['resident_name']); ?> &mdash; <?php echo date('M j', strtotime($sr['created_at'])); ?></small>
                                    </div>
                                    <span class="status-badge status-<?php echo sanitize($sr['priority']); ?> ms-2" style="font-size:0.75rem;">
                                        <?php echo sanitize(ucfirst($sr['priority'])); ?>
                                    </span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-muted text-center py-3">No open service requests.</div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($low_stock > 0): ?>
            <div class="card border-0 shadow-sm border-start border-4 border-warning">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">⚠️ Kitchen: Low Stock Alert</h6>
                            <small class="text-muted"><?php echo $low_stock; ?> item(s) below threshold</small>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /row -->
</div><!-- /elderly-page -->

<?php require_once '../includes/footer.php'; ?>
