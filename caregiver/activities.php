<?php
require_once '../config/database.php';
require_once '../config/auth.php';
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

// Handle attendance update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registration_id']) && isset($_POST['status'])) {
    $registration_id = (int)$_POST['registration_id'];
    $status = $_POST['status'];
    
    if (in_array($status, ['attended', 'missed'])) {
        $stmt = $pdo->prepare("
            SELECT ar.id 
            FROM activity_registrations ar
            WHERE ar.id = ? AND ar.elderly_profile_id IN ($in_clause)
        ");
        $stmt->execute([$registration_id]);
        if ($stmt->fetch()) {
            $update = $pdo->prepare("UPDATE activity_registrations SET status = ? WHERE id = ?");
            if ($update->execute([$status, $registration_id])) {
                $success = "Attendance updated to $status.";
            } else {
                $error = "Failed to update attendance.";
            }
        } else {
            $error = "Unauthorized to update this registration.";
        }
    }
}

// Fetch residents for dropdown
$stmt = $pdo->prepare("
    SELECT ep.id, u.name 
    FROM elderly_profiles ep 
    JOIN users u ON ep.user_id = u.id 
    WHERE ep.id IN ($in_clause)
    ORDER BY u.name
");
$stmt->execute();
$residents = $stmt->fetchAll();

// Filters
$filter_profile_id = isset($_GET['profile_id']) ? (int)$_GET['profile_id'] : null;
$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

$where_clause = "ar.elderly_profile_id IN ($in_clause) AND DATE(a.activity_date) = ?";
$params = [$filter_date];

if ($filter_profile_id && in_array($filter_profile_id, $assigned_profiles)) {
    $where_clause .= " AND ar.elderly_profile_id = ?";
    $params[] = $filter_profile_id;
}

// Fetch activity registrations for assigned residents
$stmt = $pdo->prepare("
    SELECT ar.id as registration_id, ar.status, a.title, a.activity_date, a.start_time, a.end_time, u.name as resident_name, ep.id as profile_id
    FROM activity_registrations ar
    JOIN activities a ON ar.activity_id = a.id
    JOIN elderly_profiles ep ON ar.elderly_profile_id = ep.id
    JOIN users u ON ep.user_id = u.id
    WHERE $where_clause
    ORDER BY a.activity_date ASC, u.name ASC
");
$stmt->execute($params);
$activities = $stmt->fetchAll();

$pageTitle = 'Activity Attendance';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header">
        <h1>Activity Attendance</h1>
        <p class="mb-0 text-white-50">Manage attendance for your assigned residents</p>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body bg-light">
            <form method="GET" class="d-flex gap-2 flex-wrap">
                <input type="date" name="date" class="form-control w-auto" value="<?php echo sanitize($filter_date); ?>">
                <select name="profile_id" class="form-select w-auto">
                    <option value="">All Assigned Residents</option>
                    <?php foreach ($residents as $r): ?>
                        <option value="<?php echo $r['id']; ?>" <?php echo $filter_profile_id === $r['id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($r['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Resident</th>
                            <th>Time</th>
                            <th>Activity</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activities)): ?>
                            <tr><td colspan="6" class="text-center py-4">No activities scheduled for this date.</td></tr>
                        <?php else: ?>
                            <?php foreach ($activities as $act): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><i class="bi bi-person text-primary"></i> <?php echo sanitize($act['resident_name']); ?></div>
                                    </td>
                                    <td><?php echo date('h:i A', strtotime($act['start_time'])); ?></td>
                                    <td class="fw-bold"><?php echo sanitize($act['title']); ?></td>
                                    <td><?php echo $act['end_time'] ? date('h:i A', strtotime($act['end_time'])) : '—'; ?></td>
                                    <td>
                                        <?php 
                                        $badge = match($act['status']) {
                                            'attended' => 'bg-success',
                                            'registered' => 'bg-primary',
                                            'missed' => 'bg-danger',
                                            'cancelled' => 'bg-secondary',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?php echo $badge; ?>"><?php echo ucfirst($act['status']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($act['status'] === 'registered' || $act['status'] === 'missed'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="registration_id" value="<?php echo $act['registration_id']; ?>">
                                                <input type="hidden" name="status" value="attended">
                                                <button type="submit" class="btn btn-sm btn-outline-success"><i class="bi bi-check-lg"></i> Attended</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($act['status'] === 'registered' || $act['status'] === 'attended'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="registration_id" value="<?php echo $act['registration_id']; ?>">
                                                <input type="hidden" name="status" value="missed">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i> Missed</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
