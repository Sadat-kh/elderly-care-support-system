<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(5);

$profile_id = (int)($_GET['id'] ?? 0);
$success = '';
$error   = '';

if (!$profile_id) {
    header('Location: ' . manager_url('residents.php'));
    exit;
}

// Fetch profile + user data
$stmt = $pdo->prepare("
    SELECT u.id AS user_id, u.name, u.email, u.active_status,
           ep.*
    FROM elderly_profiles ep
    JOIN users u ON u.id = ep.user_id
    WHERE ep.id = ? AND u.role_id = 1
");
$stmt->execute([$profile_id]);
$resident = $stmt->fetch();

if (!$resident) {
    header('Location: ' . manager_url('residents.php'));
    exit;
}

// ── Handle form submission ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $name                   = trim($_POST['name']                   ?? '');
    $email                  = trim($_POST['email']                  ?? '');
    $date_of_birth          = trim($_POST['date_of_birth']          ?? '') ?: null;
    $gender                 = trim($_POST['gender']                 ?? '') ?: null;
    $blood_group            = trim($_POST['blood_group']            ?? '') ?: null;
    $medical_conditions     = trim($_POST['medical_conditions']     ?? '') ?: null;
    $allergies              = trim($_POST['allergies']              ?? '') ?: null;
    $dietary_requirements   = trim($_POST['dietary_requirements']   ?? '') ?: null;
    $mobility_notes         = trim($_POST['mobility_notes']         ?? '') ?: null;
    $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '') ?: null;
    $emergency_contact_phone= trim($_POST['emergency_contact_phone']?? '') ?: null;

    if (empty($name) || empty($email)) {
        $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check email uniqueness (excluding this user)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $resident['user_id']]);
        if ($stmt->fetch()) {
            $error = 'That email is already in use by another account.';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?")
                    ->execute([$name, $email, $resident['user_id']]);

                $pdo->prepare("
                    UPDATE elderly_profiles SET
                        date_of_birth          = ?,
                        gender                 = ?,
                        blood_group            = ?,
                        medical_conditions     = ?,
                        allergies              = ?,
                        dietary_requirements   = ?,
                        mobility_notes         = ?,
                        emergency_contact_name = ?,
                        emergency_contact_phone= ?
                    WHERE id = ?
                ")->execute([
                    $date_of_birth, $gender, $blood_group,
                    $medical_conditions, $allergies, $dietary_requirements,
                    $mobility_notes, $emergency_contact_name, $emergency_contact_phone,
                    $profile_id,
                ]);
                $pdo->commit();
                $success = 'Resident profile updated successfully.';
                // Refresh resident data
                $stmt = $pdo->prepare("
                    SELECT u.id AS user_id, u.name, u.email, u.active_status, ep.*
                    FROM elderly_profiles ep JOIN users u ON u.id = ep.user_id
                    WHERE ep.id = ?
                ");
                $stmt->execute([$profile_id]);
                $resident = $stmt->fetch();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Save failed. Please try again.';
            }
        }
    }
}

// ── Fetch current room assignment ─────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT r.room_number, r.floor, r.room_type, ra.start_date
    FROM room_assignments ra
    JOIN rooms r ON r.id = ra.room_id
    WHERE ra.elderly_profile_id = ? AND ra.end_date IS NULL
    ORDER BY ra.start_date DESC LIMIT 1
");
$stmt->execute([$profile_id]);
$current_room = $stmt->fetch();

$pageTitle = 'Edit Resident — ' . ($resident['name'] ?? '');
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width: 860px;">
    <div class="elderly-page-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1>Edit Resident Profile</h1>
                <p class="mb-0 text-white-50"><?php echo sanitize($resident['name']); ?></p>
            </div>
            <a href="<?php echo sanitize(manager_url('residents.php')); ?>" class="btn btn-outline-light btn-sm flex-shrink-0 ms-3">
                ← Back
            </a>
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

    <!-- Current Room Info (read-only) -->
    <div class="elderly-card elderly-card--highlight mb-4">
        <h2 class="h6 mb-2 text-muted text-uppercase" style="font-size:0.8rem; letter-spacing:0.05em;">Current Room Assignment</h2>
        <?php if ($current_room): ?>
            <p class="mb-0">
                <strong>Room <?php echo sanitize($current_room['room_number']); ?></strong>
                — Floor <?php echo sanitize($current_room['floor']); ?>
                (<?php echo sanitize(ucfirst($current_room['room_type'])); ?>)
                <span class="text-muted ms-2">· Since <?php echo date('M j, Y', strtotime($current_room['start_date'])); ?></span>
            </p>
        <?php else: ?>
            <p class="mb-0 text-muted">No room currently assigned.</p>
        <?php endif; ?>
        <small class="text-muted">Room changes are handled via the Room-Change Requests workflow.</small>
    </div>

    <!-- Edit Form -->
    <form method="POST">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h2 class="elderly-section-title mt-0">Account Details</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="name">Full Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required
                               value="<?php echo sanitize($resident['name']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required
                               value="<?php echo sanitize($resident['email']); ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h2 class="elderly-section-title mt-0">Personal Information</h2>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="date_of_birth">Date of Birth</label>
                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                               value="<?php echo sanitize($resident['date_of_birth'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="gender">Gender</label>
                        <select class="form-select" id="gender" name="gender">
                            <option value="">— Select —</option>
                            <?php foreach (['male','female','other'] as $g): ?>
                            <option value="<?php echo $g; ?>" <?php echo ($resident['gender'] ?? '') === $g ? 'selected' : ''; ?>>
                                <?php echo ucfirst($g); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="blood_group">Blood Group</label>
                        <input type="text" class="form-control" id="blood_group" name="blood_group"
                               value="<?php echo sanitize($resident['blood_group'] ?? ''); ?>"
                               placeholder="e.g. B+">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="medical_conditions">Medical Conditions</label>
                        <textarea class="form-control" id="medical_conditions" name="medical_conditions" rows="3"><?php echo sanitize($resident['medical_conditions'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="allergies">Allergies</label>
                        <textarea class="form-control" id="allergies" name="allergies" rows="3"><?php echo sanitize($resident['allergies'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="dietary_requirements">Dietary Requirements</label>
                        <textarea class="form-control" id="dietary_requirements" name="dietary_requirements" rows="2"><?php echo sanitize($resident['dietary_requirements'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="mobility_notes">Mobility Notes</label>
                        <textarea class="form-control" id="mobility_notes" name="mobility_notes" rows="2"><?php echo sanitize($resident['mobility_notes'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h2 class="elderly-section-title mt-0">Emergency Contact</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="emergency_contact_name">Contact Name</label>
                        <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name"
                               value="<?php echo sanitize($resident['emergency_contact_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="emergency_contact_phone">Contact Phone</label>
                        <input type="text" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone"
                               value="<?php echo sanitize($resident['emergency_contact_phone'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" name="save" class="btn btn-brand">Save Changes</button>
            <a href="<?php echo sanitize(manager_url('residents.php')); ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
