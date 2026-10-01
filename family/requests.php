<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(2); // Family

$user_id = (int)$_SESSION['user_id'];
$requested_profile_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify link and fetch profile
$query = "
    SELECT ep.id as profile_id, u.name 
    FROM family_connections fc
    JOIN elderly_profiles ep ON fc.elderly_profile_id = ep.id
    JOIN users u ON ep.user_id = u.id
    WHERE fc.family_user_id = ? AND fc.status = 'approved'
";
$params = [$user_id];
if ($requested_profile_id > 0) {
    $query .= " AND ep.id = ?";
    $params[] = $requested_profile_id;
} else {
    $query .= " LIMIT 1";
}
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$profile = $stmt->fetch();

if (!$profile) {
    $pageTitle = 'Requests Not Found';
    require_once '../includes/header.php';
    echo '<div class="container mt-5"><div class="alert alert-danger">Elderly profile not found or access denied.</div></div>';
    require_once '../includes/footer.php';
    exit;
}

$profile_id = (int)$profile['profile_id'];
$success = '';
$error = '';

// Handle new request form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_request') {
        $request_type = trim($_POST['request_type'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = trim($_POST['priority'] ?? 'medium');
        
        if ($request_type && $title && $priority) {
            $stmt = $pdo->prepare("
                INSERT INTO service_requests (elderly_profile_id, requested_by, request_type, title, description, priority, status)
                VALUES (?, ?, ?, ?, ?, ?, 'open')
            ");
            if ($stmt->execute([$profile_id, $user_id, $request_type, $title, $description, $priority])) {
                $success = 'Service request created successfully on behalf of ' . sanitize($profile['name']) . '.';
            } else {
                $error = 'Failed to create request.';
            }
        } else {
            $error = 'Please fill in all required fields.';
        }
    } elseif ($_POST['action'] === 'room_change') {
        $preferred_room_id = (int)$_POST['preferred_room_id'];
        $reason = trim($_POST['reason'] ?? '');
        
        // get current room
        $cr_stmt = $pdo->prepare("SELECT room_id FROM room_assignments WHERE elderly_profile_id = ? AND end_date IS NULL LIMIT 1");
        $cr_stmt->execute([$profile_id]);
        $current_room_id = $cr_stmt->fetchColumn() ?: null;
        
        $stmt = $pdo->prepare("
            INSERT INTO room_change_requests (elderly_profile_id, current_room_id, preferred_room_id, reason, status, requested_by_family_id)
            VALUES (?, ?, ?, ?, 'pending', ?)
        ");
        if ($stmt->execute([$profile_id, $current_room_id, $preferred_room_id ?: null, $reason, $user_id])) {
            $success = 'Room change requested successfully.';
        } else {
            $error = 'Failed to submit room change request.';
        }
    }
}

// Fetch service requests
$stmt = $pdo->prepare("
    SELECT sr.*, u.name as requested_by_name, a_u.name as assigned_to_name
    FROM service_requests sr
    JOIN users u ON sr.requested_by = u.id
    LEFT JOIN users a_u ON sr.assigned_to = a_u.id
    WHERE sr.elderly_profile_id = ?
    ORDER BY sr.created_at DESC
");
$stmt->execute([$profile_id]);
$requests = $stmt->fetchAll();

// Fetch room change requests
$stmt = $pdo->prepare("
    SELECT rcr.*, 
           cr.room_number as current_room_number,
           pr.room_number as preferred_room_number
    FROM room_change_requests rcr
    LEFT JOIN rooms cr ON rcr.current_room_id = cr.id
    LEFT JOIN rooms pr ON rcr.preferred_room_id = pr.id
    WHERE rcr.elderly_profile_id = ?
    ORDER BY rcr.created_at DESC
");
$stmt->execute([$profile_id]);
$room_requests = $stmt->fetchAll();

// Fetch all rooms for the modal
$rooms_stmt = $pdo->query("SELECT id, room_number, room_type, capacity FROM rooms ORDER BY room_number");
$all_rooms = $rooms_stmt->fetchAll();

$pageTitle = 'Requests - ' . sanitize($profile['name']);
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width: 1000px;">
    <div class="elderly-page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Requests</h1>
                <p class="mb-0 text-white-50">Manage service & room requests for <?php echo sanitize($profile['name']); ?></p>
            </div>
            <div>
                <button type="button" class="btn btn-light fw-bold me-2" data-bs-toggle="modal" data-bs-target="#newRequestModal">
                    <i class="bi bi-plus-lg"></i> Service Request
                </button>
                <button type="button" class="btn btn-outline-light fw-bold" data-bs-toggle="modal" data-bs-target="#roomChangeModal">
                    <i class="bi bi-door-open"></i> Request Room Change
                </button>
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

    <?php if (empty($requests)): ?>
        <div class="elderly-empty mt-4">
            <p>No service requests found.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive mt-4">
            <table class="table table-hover align-middle bg-white shadow-sm rounded">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Request Details</th>
                        <th>Requested By</th>
                        <th>Priority</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $req): ?>
                    <tr>
                        <td><?php echo date('M j, Y', strtotime($req['created_at'])); ?></td>
                        <td><?php echo sanitize(ucfirst(str_replace('_', ' ', $req['request_type']))); ?></td>
                        <td>
                            <strong><?php echo sanitize($req['title']); ?></strong>
                            <?php if (!empty($req['description'])): ?>
                                <br><small class="text-muted d-inline-block text-truncate" style="max-width: 250px;">
                                    <?php echo sanitize($req['description']); ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            if ($req['requested_by'] === $user_id) {
                                echo '<span class="badge bg-primary">You</span>';
                            } else {
                                echo sanitize($req['requested_by_name']); 
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            $pri_class = match($req['priority']) {
                                'low' => 'text-secondary',
                                'medium' => 'text-primary',
                                'high' => 'text-warning fw-bold',
                                'urgent' => 'text-danger fw-bold',
                                default => 'text-secondary'
                            };
                            ?>
                            <span class="<?php echo $pri_class; ?>"><?php echo sanitize(ucfirst($req['priority'])); ?></span>
                        </td>
                        <td class="text-center">
                            <?php 
                            $status_class = match($req['status']) {
                                'open' => 'bg-info text-dark',
                                'in_progress' => 'bg-primary',
                                'completed' => 'bg-success',
                                'cancelled' => 'bg-secondary',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?php echo $status_class; ?>">
                                <?php echo sanitize(ucfirst(str_replace('_', ' ', $req['status']))); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <h4 class="mt-5 mb-3 text-primary">Room Change Requests</h4>
    <?php if (empty($room_requests)): ?>
        <div class="elderly-empty mt-2">
            <p>No room change requests found.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive mt-2">
            <table class="table table-hover align-middle bg-white shadow-sm rounded">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Current Room</th>
                        <th>Preferred Room</th>
                        <th>Reason</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($room_requests as $rr): ?>
                    <tr>
                        <td><?php echo date('M j, Y', strtotime($rr['created_at'])); ?></td>
                        <td><?php echo $rr['current_room_number'] ? 'Room ' . sanitize($rr['current_room_number']) : '-'; ?></td>
                        <td><?php echo $rr['preferred_room_number'] ? 'Room ' . sanitize($rr['preferred_room_number']) : '-'; ?></td>
                        <td>
                            <small class="text-muted d-inline-block text-truncate" style="max-width: 250px;">
                                <?php echo sanitize($rr['reason']); ?>
                            </small>
                        </td>
                        <td class="text-center">
                            <?php 
                            $status_class = match($rr['status']) {
                                'pending' => 'bg-warning text-dark',
                                'approved' => 'bg-success',
                                'rejected' => 'bg-danger',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?php echo $status_class; ?>">
                                <?php echo sanitize(ucfirst($rr['status'])); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- New Request Modal -->
<div class="modal fade" id="newRequestModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="create_request">
            <div class="modal-header">
                <h5 class="modal-title">New Request for <?php echo sanitize($profile['name']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Request Type <span class="text-danger">*</span></label>
                    <select name="request_type" class="form-select" required>
                        <option value="">Select type...</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="housekeeping">Housekeeping</option>
                        <option value="medical">Medical Assistance</option>
                        <option value="dietary">Dietary Need</option>
                        <option value="transport">Transportation</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" required placeholder="Brief description of the issue">
                </div>
                <div class="mb-3">
                    <label class="form-label">Details</label>
                    <textarea name="description" class="form-control" rows="4" placeholder="Provide more context..."></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Priority <span class="text-danger">*</span></label>
                    <select name="priority" class="form-select" required>
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<!-- Room Change Request Modal -->
<div class="modal fade" id="roomChangeModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="room_change">
            <div class="modal-header">
                <h5 class="modal-title">Request Room Change</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Preferred Room <span class="text-danger">*</span></label>
                    <select name="preferred_room_id" class="form-select" required>
                        <option value="">Select a room...</option>
                        <?php foreach ($all_rooms as $room): ?>
                            <option value="<?php echo $room['id']; ?>">
                                Room <?php echo sanitize($room['room_number']); ?> 
                                (<?php echo ucfirst($room['room_type']); ?> - Capacity: <?php echo $room['capacity']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason for change <span class="text-danger">*</span></label>
                    <textarea name="reason" class="form-control" rows="4" required placeholder="Explain why a room change is needed..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
