<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(2); // Family

$user_id = (int)$_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_connection'])) {
    $elderly_profile_id = (int)$_POST['elderly_profile_id'];
    $relationship = trim($_POST['relationship'] ?? '');
    
    $chk = $pdo->prepare("SELECT id FROM family_connections WHERE family_user_id = ? AND elderly_profile_id = ?");
    $chk->execute([$user_id, $elderly_profile_id]);
    if ($chk->fetch()) {
        $error = "You already have a connection or pending request for this resident.";
    } elseif (empty($relationship)) {
        $error = "Relationship is required.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO family_connections (family_user_id, elderly_profile_id, relationship, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$user_id, $elderly_profile_id, $relationship]);
        $success = "Connection request submitted. Please wait for administration to approve it.";
    }
}

// Get linked elderly profiles
$stmt = $pdo->prepare("
    SELECT ep.id as profile_id, u.name, r.room_number 
    FROM family_connections fc
    JOIN elderly_profiles ep ON fc.elderly_profile_id = ep.id
    JOIN users u ON ep.user_id = u.id
    LEFT JOIN room_assignments ra ON ra.elderly_profile_id = ep.id AND ra.end_date IS NULL
    LEFT JOIN rooms r ON r.id = ra.room_id
    WHERE fc.family_user_id = ? AND fc.status = 'approved'
");
$stmt->execute([$user_id]);
$linked_profiles = $stmt->fetchAll();

// Get pending connections
$stmt = $pdo->prepare("
    SELECT ep.id, u.name, fc.relationship 
    FROM family_connections fc
    JOIN elderly_profiles ep ON fc.elderly_profile_id = ep.id
    JOIN users u ON ep.user_id = u.id
    WHERE fc.family_user_id = ? AND fc.status = 'pending'
");
$stmt->execute([$user_id]);
$pending_profiles = $stmt->fetchAll();

// Get available elderly profiles for the form
$stmt = $pdo->prepare("
    SELECT ep.id, u.name 
    FROM elderly_profiles ep
    JOIN users u ON ep.user_id = u.id
    ORDER BY u.name ASC
");
$stmt->execute();
$all_elderly = $stmt->fetchAll();

$pageTitle = 'Family Dashboard';
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width: 1200px;">
    <div class="elderly-page-header">
        <h1>Family Dashboard</h1>
        <p class="mb-0 text-white-50">Overview of your linked residents</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success mt-4"><?php echo sanitize($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger mt-4"><?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <?php if (empty($linked_profiles)): ?>
        <div class="elderly-empty mt-4 mb-4">
            <h4>No Linked Residents</h4>
            <p class="text-muted">You do not have any approved connections to an elderly resident yet.</p>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title text-primary"><i class="bi bi-person-plus"></i> Request Connection</h5>
                        <form method="POST" class="mt-3">
                            <div class="mb-3">
                                <label for="elderly_profile_id" class="form-label">Select Resident *</label>
                                <select class="form-select" id="elderly_profile_id" name="elderly_profile_id" required>
                                    <option value="">-- Choose a Resident --</option>
                                    <?php foreach ($all_elderly as $e): ?>
                                        <option value="<?php echo $e['id']; ?>"><?php echo sanitize($e['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="relationship" class="form-label">Your Relationship to Resident *</label>
                                <input type="text" class="form-control" id="relationship" name="relationship" placeholder="e.g. Son, Daughter, Spouse" required>
                            </div>
                            <button type="submit" name="request_connection" class="btn btn-primary w-100">Submit Request</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($pending_profiles)): ?>
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title text-warning"><i class="bi bi-hourglass-split"></i> Pending Requests</h5>
                        <ul class="list-group list-group-flush mt-3">
                            <?php foreach ($pending_profiles as $pending): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo sanitize($pending['name']); ?></strong><br>
                                        <small class="text-muted">Relationship: <?php echo sanitize($pending['relationship']); ?></small>
                                    </div>
                                    <span class="badge bg-warning text-dark rounded-pill">Pending</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="row g-4 mt-2">
            <?php foreach ($linked_profiles as $profile): 
                $profile_id = (int)$profile['profile_id'];
                
                // Get today's meals
                $m_stmt = $pdo->prepare("
                    SELECT m.meal_type, m.preparation_status 
                    FROM meal_assignments ma 
                    JOIN meals m ON ma.meal_id = m.id 
                    WHERE ma.elderly_profile_id = ? AND DATE(m.created_at) = CURDATE() 
                    AND ma.assignment_status = 'active'
                ");
                $m_stmt->execute([$profile_id]);
                $meals = $m_stmt->fetchAll();
                
                // Get next activity
                $a_stmt = $pdo->prepare("
                    SELECT a.title, a.activity_date, a.start_time 
                    FROM activity_registrations ar 
                    JOIN activities a ON ar.activity_id = a.id 
                    WHERE ar.elderly_profile_id = ? AND ar.status = 'registered' AND CONCAT(a.activity_date, ' ', a.start_time) > NOW() 
                    ORDER BY a.activity_date ASC, a.start_time ASC LIMIT 1
                ");
                $a_stmt->execute([$profile_id]);
                $next_activity = $a_stmt->fetch();
                
                // Get pending requests
                $r_stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM service_requests 
                    WHERE elderly_profile_id = ? AND status IN ('open', 'in_progress')
                ");
                $r_stmt->execute([$profile_id]);
                $pending_requests = $r_stmt->fetchColumn();
            ?>
            <div class="col-md-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <h4 class="card-title text-primary"><?php echo sanitize($profile['name']); ?></h4>
                        <h6 class="card-subtitle mb-3 text-muted">
                            <?php echo $profile['room_number'] ? 'Room ' . sanitize($profile['room_number']) : 'No room assigned'; ?>
                        </h6>
                        
                        <div class="mb-3">
                            <strong>Today's Meals:</strong>
                            <?php if (empty($meals)): ?>
                                <span class="text-muted">None scheduled</span>
                            <?php else: ?>
                                <ul class="mb-0 mt-1 pl-3">
                                    <?php foreach ($meals as $m): ?>
                                        <li><?php echo ucfirst($m['meal_type']) . ' - <span class="badge bg-secondary">' . str_replace('_', ' ', $m['preparation_status']) . '</span>'; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Next Activity:</strong><br>
                            <?php if ($next_activity): ?>
                                <?php echo sanitize($next_activity['title']) . ' (' . date('M j, g:i A', strtotime($next_activity['activity_date'] . ' ' . $next_activity['start_time'])) . ')'; ?>
                            <?php else: ?>
                                <span class="text-muted">None scheduled</span>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <strong>Pending Requests:</strong> 
                            <span class="badge <?php echo $pending_requests > 0 ? 'bg-warning text-dark' : 'bg-success'; ?>">
                                <?php echo $pending_requests; ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top-0 pt-0 pb-3">
                        <a href="<?php echo sanitize(family_url('elderly_profile.php?id=' . $profile_id)); ?>" class="btn btn-sm btn-outline-primary">View Profile</a>
                        <a href="<?php echo sanitize(family_url('updates.php?id=' . $profile_id)); ?>" class="btn btn-sm btn-outline-secondary">Daily Updates</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
