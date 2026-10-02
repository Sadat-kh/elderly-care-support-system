<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php'; // For notify_user
checkRole(3);

$user_id = (int)$_SESSION['user_id'];
$success = '';
$error = '';

// Check assigned residents
$stmt = $pdo->prepare("SELECT elderly_profile_id FROM caregiver_assignments WHERE caregiver_user_id = ? AND active = 1");
$stmt->execute([$user_id]);
$assigned_profiles = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($assigned_profiles)) {
    $in_clause = '0';
} else {
    $in_clause = implode(',', $assigned_profiles);
}

// Handle request update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id']) && isset($_POST['status'])) {
    $request_id = (int)$_POST['request_id'];
    $status = $_POST['status'];
    
    $valid_statuses = ['open', 'in_progress', 'completed', 'cancelled'];
    
    if (in_array($status, $valid_statuses)) {
        $stmt = $pdo->prepare("
            SELECT sr.id, sr.title, ep.user_id as resident_user_id 
            FROM service_requests sr
            JOIN elderly_profiles ep ON sr.elderly_profile_id = ep.id
            WHERE sr.id = ? AND sr.elderly_profile_id IN ($in_clause)
        ");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();
        
        if ($request) {
            $update = $pdo->prepare("UPDATE service_requests SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            if ($update->execute([$status, $request_id])) {
                $success = "Request marked as " . str_replace('_', ' ', $status) . ".";
                
                // Notify the resident
                notify_user($pdo, (int)$request['resident_user_id'], 
                    "Request Update: " . sanitize($request['title']), 
                    "Your request has been updated to: " . str_replace('_', ' ', $status), 
                    'requests.php'
                );
            } else {
                $error = "Failed to update request.";
            }
        } else {
            $error = "Unauthorized to update this request.";
        }
    }
}

// Filters
$filter_status = $_GET['status'] ?? 'active';

$where = ["sr.elderly_profile_id IN ($in_clause)"];
if ($filter_status === 'active') {
    $where[] = "sr.status IN ('open', 'in_progress')";
} elseif ($filter_status === 'history') {
    $where[] = "sr.status IN ('completed', 'cancelled')";
}
$where_clause = implode(' AND ', $where);

// Fetch service requests
$stmt = $pdo->prepare("
    SELECT sr.*, u.name as resident_name, r.room_number
    FROM service_requests sr
    JOIN elderly_profiles ep ON sr.elderly_profile_id = ep.id
    JOIN users u ON ep.user_id = u.id
    LEFT JOIN room_assignments ra ON ra.elderly_profile_id = ep.id AND ra.end_date IS NULL
    LEFT JOIN rooms r ON ra.room_id = r.id
    WHERE $where_clause
    ORDER BY sr.created_at DESC
");
$stmt->execute();
$requests = $stmt->fetchAll();

$pageTitle = 'Assistance Requests';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header">
        <h1>Assistance Requests</h1>
        <p class="mb-0 text-white-50">Manage service requests from your assigned residents</p>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <ul class="nav nav-pills mb-4">
        <li class="nav-item">
            <a class="nav-link <?php echo $filter_status === 'active' ? 'active' : ''; ?>" href="?status=active">Active Requests</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $filter_status === 'history' ? 'active' : ''; ?>" href="?status=history">History</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $filter_status === 'all' ? 'active' : ''; ?>" href="?status=all">All</a>
        </li>
    </ul>

    <?php if (empty($requests)): ?>
        <div class="elderly-empty">
            <p>No requests found for this filter.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($requests as $req): ?>
                <div class="col-md-6 col-lg-4">
                    <?php 
                    $border = match($req['status']) {
                        'open' => 'border-warning',
                        'in_progress' => 'border-primary',
                        'completed' => 'border-success',
                        'cancelled' => 'border-danger',
                        default => 'border-secondary'
                    };
                    $badge = str_replace('border-', 'bg-', $border);
                    if ($badge === 'bg-warning') $badge .= ' text-dark';
                    ?>
                    <div class="card h-100 shadow-sm border-0 border-start <?php echo $border; ?> border-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title fw-bold mb-0"><?php echo sanitize($req['title']); ?></h5>
                                <span class="badge <?php echo $badge; ?>"><?php echo ucfirst(str_replace('_', ' ', $req['status'])); ?></span>
                            </div>
                            <h6 class="text-primary mb-1"><i class="bi bi-person"></i> <?php echo sanitize($req['resident_name']); ?></h6>
                            <p class="text-muted small mb-3"><i class="bi bi-door-open"></i> <?php echo $req['room_number'] ? 'Room ' . sanitize($req['room_number']) : 'No room'; ?></p>
                            
                            <p class="card-text text-muted" style="font-size: 0.95rem; white-space: pre-wrap;"><?php echo sanitize($req['description']); ?></p>
                            
                            <hr>
                            <small class="text-muted d-block mb-3">Requested: <?php echo date('M j, g:i A', strtotime($req['created_at'])); ?></small>
                            
                            <?php if ($req['status'] === 'open' || $req['status'] === 'in_progress'): ?>
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php if ($req['status'] === 'open'): ?>
                                        <form method="POST">
                                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                            <input type="hidden" name="status" value="in_progress">
                                            <button type="submit" class="btn btn-sm btn-primary">Accept & Start</button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($req['status'] === 'in_progress'): ?>
                                        <form method="POST">
                                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" class="btn btn-sm btn-success">Mark Completed</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
