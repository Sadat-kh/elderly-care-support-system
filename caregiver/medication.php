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

// Handle updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_id'])) {
    $event_id = (int)$_POST['event_id'];
    $status = $_POST['status']; // 'taken' or 'missed'
    
    if (in_array($status, ['taken', 'missed'])) {
        // Verify this event belongs to an assigned resident
        $stmt = $pdo->prepare("
            SELECT me.id 
            FROM medication_events me
            JOIN medications m ON me.medication_id = m.id
            WHERE me.id = ? AND m.elderly_profile_id IN ($in_clause)
        ");
        $stmt->execute([$event_id]);
        if ($stmt->fetch()) {
            $taken_at = ($status === 'taken') ? date('Y-m-d H:i:s') : null;
            $update_stmt = $pdo->prepare("UPDATE medication_events SET status = ?, taken_at = ? WHERE id = ?");
            if ($update_stmt->execute([$status, $taken_at, $event_id])) {
                $success = "Medication marked as $status.";
            } else {
                $error = "Database error.";
            }
        } else {
            $error = "Unauthorized to update this medication.";
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

$where_clause = "m.elderly_profile_id IN ($in_clause) AND me.event_date = ?";
$params = [$filter_date];
if ($filter_profile_id && in_array($filter_profile_id, $assigned_profiles)) {
    $where_clause .= " AND m.elderly_profile_id = ?";
    $params[] = $filter_profile_id;
}

// Fetch medications
$stmt = $pdo->prepare("
    SELECT me.id, me.status, me.taken_at, m.name as med_name, m.dosage, m.instructions, m.time_slot, u.name as resident_name
    FROM medication_events me
    JOIN medications m ON me.medication_id = m.id
    JOIN elderly_profiles ep ON m.elderly_profile_id = ep.id
    JOIN users u ON ep.user_id = u.id
    WHERE $where_clause
    ORDER BY m.time_slot ASC, u.name ASC
");
$stmt->execute($params);
$meds = $stmt->fetchAll();

$pageTitle = 'Medication Support';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header">
        <h1>Medication Support</h1>
        <p class="mb-0 text-white-50">Log medication adherence for your assigned residents</p>
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
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Resident</th>
                            <th>Time</th>
                            <th>Medication</th>
                            <th>Dosage & Instructions</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($meds)): ?>
                            <tr><td colspan="6" class="text-center py-4">No medications found for this date.</td></tr>
                        <?php else: ?>
                            <?php foreach ($meds as $med): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><i class="bi bi-person text-primary"></i> <?php echo sanitize($med['resident_name']); ?></div>
                                    </td>
                                    <td><?php echo date('h:i A', strtotime($med['time_slot'])); ?></td>
                                    <td class="fw-bold"><?php echo sanitize($med['med_name']); ?></td>
                                    <td>
                                        <small class="d-block text-dark fw-semibold"><?php echo sanitize($med['dosage']); ?></small>
                                        <?php if ($med['instructions']): ?>
                                            <small class="text-muted fst-italic"><?php echo sanitize($med['instructions']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $badge = match($med['status']) {
                                            'taken' => 'bg-success',
                                            'pending' => 'bg-warning text-dark',
                                            'missed' => 'bg-danger',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?php echo $badge; ?>"><?php echo ucfirst($med['status']); ?></span>
                                        <?php if ($med['taken_at']): ?>
                                            <div class="small text-muted mt-1" style="font-size: 0.7rem;"><?php echo date('h:i A', strtotime($med['taken_at'])); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($med['status'] === 'pending' || $med['status'] === 'missed'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="event_id" value="<?php echo $med['id']; ?>">
                                                <input type="hidden" name="status" value="taken">
                                                <button type="submit" class="btn btn-sm btn-outline-success"><i class="bi bi-check-circle"></i> Mark Taken</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($med['status'] === 'pending'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="event_id" value="<?php echo $med['id']; ?>">
                                                <input type="hidden" name="status" value="missed">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i> Mark Missed</button>
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
