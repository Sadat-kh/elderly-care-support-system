<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
checkRole(5);

$user_id = (int)$_SESSION['user_id'];
$success = '';
$error   = '';

// ── POST: Approve or Reject a room-change request ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rcr_action'])) {
    $rcr_id = (int)($_POST['rcr_id'] ?? 0);
    $action = $_POST['rcr_action'] ?? '';

    if ($rcr_id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
        $error = 'Invalid action.';
    } else {
        // Load the request (must still be pending)
        $stmt = $pdo->prepare("
            SELECT rcr.*,
                   ep.id AS profile_id,
                   u.name AS resident_name
            FROM room_change_requests rcr
            JOIN elderly_profiles ep ON ep.id = rcr.elderly_profile_id
            JOIN users u             ON u.id  = ep.user_id
            WHERE rcr.id = ? AND rcr.status = 'pending'
        ");
        $stmt->execute([$rcr_id]);
        $rcr = $stmt->fetch();

        if (!$rcr) {
            $error = 'Request not found or already actioned.';
        } elseif ($action === 'reject') {
            // ── REJECT ──────────────────────────────────────────────────────
            $pdo->prepare("
                UPDATE room_change_requests
                SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW()
                WHERE id = ?
            ")->execute([$user_id, $rcr_id]);
            audit_log($pdo, $user_id, 'reject_room_change', 'room_change_requests', $rcr_id,
                      "Rejected room-change request for {$rcr['resident_name']}");
            $success = 'Room-change request for ' . htmlspecialchars($rcr['resident_name'], ENT_QUOTES, 'UTF-8') . ' has been rejected.';

        } else {
            // ── APPROVE ─────────────────────────────────────────────────────
            $preferred_room_id = $rcr['preferred_room_id'] ? (int)$rcr['preferred_room_id'] : null;

            if (!$preferred_room_id) {
                $error = 'Cannot approve: this request has no specific preferred room. Please contact the resident to resubmit with a room selection.';
            } else {
                // Server-side capacity check
                $stmt = $pdo->prepare("
                    SELECT r.id, r.room_number, r.capacity, r.status,
                           COUNT(ra.id) AS current_occupancy
                    FROM rooms r
                    LEFT JOIN room_assignments ra ON ra.room_id = r.id AND ra.end_date IS NULL
                    WHERE r.id = ?
                    GROUP BY r.id
                ");
                $stmt->execute([$preferred_room_id]);
                $target = $stmt->fetch();

                if (!$target) {
                    $error = 'Preferred room no longer exists.';
                } elseif ((int)$target['current_occupancy'] >= (int)$target['capacity']) {
                    $error = 'Cannot approve: Room ' . htmlspecialchars($target['room_number'], ENT_QUOTES, 'UTF-8')
                           . ' is already at full capacity ('
                           . $target['current_occupancy'] . '/' . $target['capacity'] . ').';
                } else {
                    // All clear — execute in a transaction
                    $pdo->beginTransaction();
                    try {
                        $profile_id    = (int)$rcr['profile_id'];
                        $old_room_id   = $rcr['current_room_id'] ? (int)$rcr['current_room_id'] : null;

                        // 1. End the current active assignment
                        $pdo->prepare("
                            UPDATE room_assignments
                            SET end_date = CURDATE()
                            WHERE elderly_profile_id = ? AND end_date IS NULL
                        ")->execute([$profile_id]);

                        // 2. Check if the old room is now empty → mark available
                        if ($old_room_id) {
                            $stmt = $pdo->prepare("
                                SELECT COUNT(*) AS cnt FROM room_assignments
                                WHERE room_id = ? AND end_date IS NULL
                            ");
                            $stmt->execute([$old_room_id]);
                            if ((int)$stmt->fetch()['cnt'] === 0) {
                                $pdo->prepare("UPDATE rooms SET status = 'available' WHERE id = ?")
                                    ->execute([$old_room_id]);
                            }
                        }

                        // 3. Insert new room assignment
                        $pdo->prepare("
                            INSERT INTO room_assignments (room_id, elderly_profile_id, assigned_by, start_date)
                            VALUES (?, ?, ?, CURDATE())
                        ")->execute([$preferred_room_id, $profile_id, $user_id]);

                        // 4. Mark the new room as occupied (if not already)
                        $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?")
                            ->execute([$preferred_room_id]);

                        // 5. Mark the request as approved
                        $pdo->prepare("
                            UPDATE room_change_requests
                            SET status = 'approved', reviewed_by = ?, reviewed_at = NOW()
                            WHERE id = ?
                        ")->execute([$user_id, $rcr_id]);

                        audit_log($pdo, $user_id, 'approve_room_change', 'room_change_requests', $rcr_id,
                                  "Approved room-change for {$rcr['resident_name']}: moved to Room {$target['room_number']}");

                        $pdo->commit();
                        $success = 'Room-change approved! '
                                 . htmlspecialchars($rcr['resident_name'], ENT_QUOTES, 'UTF-8')
                                 . ' has been moved to Room '
                                 . htmlspecialchars($target['room_number'], ENT_QUOTES, 'UTF-8') . '.';

                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = 'Approval failed due to a database error. Please try again.';
                    }
                }
            }
        }
    }
}

// ── Fetch all rooms with real occupancy count ─────────────────────────────────
$rooms = $pdo->query("
    SELECT r.id, r.room_number, r.floor, r.room_type, r.capacity, r.status,
           COUNT(ra.id) AS current_occupancy
    FROM rooms r
    LEFT JOIN room_assignments ra ON ra.room_id = r.id AND ra.end_date IS NULL
    GROUP BY r.id
    ORDER BY r.floor ASC, r.room_number ASC
")->fetchAll();

$rooms_by_status = [
    'available'   => array_filter($rooms, fn($r) => $r['status'] === 'available'),
    'occupied'    => array_filter($rooms, fn($r) => $r['status'] === 'occupied'),
    'maintenance' => array_filter($rooms, fn($r) => $r['status'] === 'maintenance'),
];

// ── Fetch room-change requests (all, ordered pending first) ───────────────────
$all_requests = $pdo->query("
    SELECT
        rcr.id, rcr.reason, rcr.status, rcr.created_at, rcr.reviewed_at,
        -- Resident
        ep.id        AS profile_id,
        u_res.name   AS resident_name,
        u_res.email  AS resident_email,
        -- Current room
        cr.room_number AS current_room, cr.floor AS current_floor, cr.room_type AS current_type,
        -- Preferred room
        pr.room_number AS preferred_room, pr.floor AS preferred_floor, pr.room_type AS preferred_type,
        pr.capacity AS preferred_capacity,
        -- Preferred room current occupancy
        (SELECT COUNT(*) FROM room_assignments ra2
         WHERE ra2.room_id = rcr.preferred_room_id AND ra2.end_date IS NULL) AS preferred_occupancy,
        -- Reviewer name
        u_rev.name AS reviewer_name
    FROM room_change_requests rcr
    JOIN elderly_profiles ep ON ep.id = rcr.elderly_profile_id
    JOIN users u_res         ON u_res.id = ep.user_id
    LEFT JOIN rooms cr       ON cr.id = rcr.current_room_id
    LEFT JOIN rooms pr       ON pr.id = rcr.preferred_room_id
    LEFT JOIN users u_rev    ON u_rev.id = rcr.reviewed_by
    ORDER BY rcr.status = 'pending' DESC, rcr.created_at DESC
")->fetchAll();

$pending_requests  = array_filter($all_requests, fn($r) => $r['status'] === 'pending');
$resolved_requests = array_filter($all_requests, fn($r) => $r['status'] !== 'pending');

$pageTitle = 'Room Management';
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width: 1100px;">
    <div class="elderly-page-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1>Room Management</h1>
                <p class="mb-0 text-white-50">Room inventory &amp; room-change request approvals</p>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ── Room Inventory ── -->
    <h2 class="elderly-section-title">🏠 Room Inventory (<?php echo count($rooms); ?> total)</h2>
    <div class="row g-2 mb-4">
        <?php
        $status_colors = [
            'available'   => 'border-success',
            'occupied'    => 'border-primary',
            'maintenance' => 'border-warning',
        ];
        $status_badge = [
            'available'   => 'bg-success',
            'occupied'    => 'bg-primary',
            'maintenance' => 'bg-warning text-dark',
        ];
        foreach ($rooms as $room):
            $occ   = (int)$room['current_occupancy'];
            $cap   = (int)$room['capacity'];
            $pct   = $cap > 0 ? min(100, round($occ / $cap * 100)) : 0;
            $color = $status_colors[$room['status']] ?? 'border-secondary';
        ?>
        <div class="col-6 col-md-3 col-lg-2">
            <div class="card h-100 border-start border-4 <?php echo $color; ?> shadow-sm">
                <div class="card-body p-2 text-center">
                    <div class="fw-bold" style="font-size:1.1rem;">Room <?php echo sanitize($room['room_number']); ?></div>
                    <small class="text-muted d-block">Floor <?php echo sanitize($room['floor']); ?> · <?php echo sanitize(ucfirst($room['room_type'])); ?></small>
                    <span class="badge <?php echo $status_badge[$room['status']] ?? 'bg-secondary'; ?> mt-1" style="font-size:0.7rem;">
                        <?php echo ucfirst($room['status']); ?>
                    </span>
                    <div class="mt-2" style="font-size:0.8rem; color:#555;">
                        <?php echo $occ; ?>/<?php echo $cap; ?> occupied
                    </div>
                    <div class="progress mt-1" style="height:4px;">
                        <div class="progress-bar <?php echo $occ >= $cap ? 'bg-danger' : 'bg-success'; ?>"
                             style="width:<?php echo $pct; ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Pending Room-Change Requests ── -->
    <h2 class="elderly-section-title">
        ⏳ Pending Room-Change Requests
        <?php if (count($pending_requests) > 0): ?>
            <span class="badge bg-warning text-dark ms-2"><?php echo count($pending_requests); ?></span>
        <?php endif; ?>
    </h2>

    <?php if (count($pending_requests) === 0): ?>
        <div class="elderly-empty mb-4">
            <p>No pending room-change requests. All caught up! ✓</p>
        </div>
    <?php else: ?>
        <div class="table-responsive mb-4">
            <table class="table table-hover align-middle bg-white shadow-sm rounded">
                <thead class="table-light">
                    <tr>
                        <th>Resident</th>
                        <th>Current Room</th>
                        <th>Preferred Room</th>
                        <th>Capacity</th>
                        <th>Reason</th>
                        <th>Requested</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_requests as $req): ?>
                    <?php
                        $pref_occ = (int)($req['preferred_occupancy'] ?? 0);
                        $pref_cap = (int)($req['preferred_capacity']  ?? 0);
                        $room_full = $req['preferred_room'] && $pref_occ >= $pref_cap && $pref_cap > 0;
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo sanitize($req['resident_name']); ?></strong><br>
                            <small class="text-muted"><?php echo sanitize($req['resident_email']); ?></small>
                        </td>
                        <td>
                            <?php if ($req['current_room']): ?>
                                Room <?php echo sanitize($req['current_room']); ?>
                                <br><small class="text-muted">Floor <?php echo sanitize($req['current_floor']); ?> · <?php echo sanitize(ucfirst($req['current_type'])); ?></small>
                            <?php else: ?>
                                <span class="text-muted">Not assigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($req['preferred_room']): ?>
                                <strong>Room <?php echo sanitize($req['preferred_room']); ?></strong>
                                <br><small class="text-muted">Floor <?php echo sanitize($req['preferred_floor']); ?> · <?php echo sanitize(ucfirst($req['preferred_type'])); ?></small>
                            <?php else: ?>
                                <span class="text-muted">Any available</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($req['preferred_room']): ?>
                                <span class="<?php echo $room_full ? 'text-danger fw-semibold' : 'text-success'; ?>">
                                    <?php echo $pref_occ; ?>/<?php echo $pref_cap; ?>
                                    <?php if ($room_full): ?><br><small>⚠ Full</small><?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="max-width:220px;">
                            <span class="d-inline-block text-truncate" style="max-width:200px;"
                                  title="<?php echo sanitize($req['reason']); ?>">
                                <?php echo sanitize($req['reason']); ?>
                            </span>
                        </td>
                        <td>
                            <small><?php echo date('M j, Y', strtotime($req['created_at'])); ?></small>
                        </td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end">
                                <?php if ($req['preferred_room'] && !$room_full): ?>
                                <form method="POST" onsubmit="return confirm('Approve and move <?php echo sanitize($req['resident_name']); ?> to Room <?php echo sanitize($req['preferred_room']); ?>?')">
                                    <input type="hidden" name="rcr_id"    value="<?php echo (int)$req['id']; ?>">
                                    <input type="hidden" name="rcr_action" value="approve">
                                    <button type="submit" class="btn btn-sm btn-success">✓ Approve</button>
                                </form>
                                <?php elseif ($room_full): ?>
                                <button class="btn btn-sm btn-success" disabled title="Room is full">✓ Approve</button>
                                <?php else: ?>
                                <button class="btn btn-sm btn-success" disabled title="No specific room requested">✓ Approve</button>
                                <?php endif; ?>
                                <form method="POST" onsubmit="return confirm('Reject this room-change request?')">
                                    <input type="hidden" name="rcr_id"    value="<?php echo (int)$req['id']; ?>">
                                    <input type="hidden" name="rcr_action" value="reject">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">✗ Reject</button>
                                </form>
                            </div>
                            <?php if ($room_full): ?>
                                <small class="text-danger d-block text-end mt-1">Room at capacity</small>
                            <?php elseif (!$req['preferred_room']): ?>
                                <small class="text-muted d-block text-end mt-1">No room specified</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- ── Resolved Requests History ── -->
    <?php if (count($resolved_requests) > 0): ?>
    <h2 class="elderly-section-title">📋 Request History (Resolved)</h2>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle bg-white shadow-sm rounded">
            <thead class="table-light">
                <tr>
                    <th>Resident</th>
                    <th>From → To</th>
                    <th>Reason</th>
                    <th class="text-center">Status</th>
                    <th>Reviewed By</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resolved_requests as $req): ?>
                <tr>
                    <td><?php echo sanitize($req['resident_name']); ?></td>
                    <td>
                        <?php echo $req['current_room']   ? 'Room ' . sanitize($req['current_room'])   : 'None'; ?>
                        →
                        <?php echo $req['preferred_room'] ? 'Room ' . sanitize($req['preferred_room']) : 'Any'; ?>
                    </td>
                    <td><small class="text-muted"><?php echo sanitize(mb_substr($req['reason'], 0, 60)) . (mb_strlen($req['reason']) > 60 ? '…' : ''); ?></small></td>
                    <td class="text-center">
                        <span class="status-badge status-<?php echo sanitize($req['status']); ?>">
                            <?php echo sanitize(ucfirst($req['status'])); ?>
                        </span>
                    </td>
                    <td><small><?php echo sanitize($req['reviewer_name'] ?? '—'); ?></small></td>
                    <td><small><?php echo $req['reviewed_at'] ? date('M j, Y', strtotime($req['reviewed_at'])) : date('M j, Y', strtotime($req['created_at'])); ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
