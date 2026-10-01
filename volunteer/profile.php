<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(8);

$user_id = (int)$_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $skills = trim($_POST['skills'] ?? '');
    $availability = trim($_POST['availability'] ?? '');
    
    if ($name) {
        $pdo->prepare("UPDATE users SET name = ? WHERE id = ?")->execute([$name, $user_id]);
        
        // Also update volunteer profile if exists
        $stmt = $pdo->prepare("SELECT id FROM volunteers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        if ($stmt->fetchColumn()) {
            $pdo->prepare("UPDATE volunteers SET skills = ?, availability = ? WHERE user_id = ?")
                ->execute([$skills, $availability, $user_id]);
        }
        
        $_SESSION['name'] = $name;
        $success = "Profile updated successfully.";
    } else {
        $error = "Name is required.";
    }
}

$stmt = $pdo->prepare("
    SELECT u.name, u.email, v.skills, v.availability 
    FROM users u 
    LEFT JOIN volunteers v ON u.id = v.user_id 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$pageTitle = 'My Profile';
require_once '../includes/header.php';
?>
<div class="elderly-page">
    <div class="elderly-page-header">
        <h1>My Profile</h1>
        <p class="mb-0 text-white-50">Update your personal details</p>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success mt-4"><?php echo sanitize($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger mt-4"><?php echo sanitize($error); ?></div>
    <?php endif; ?>
    
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body p-4">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?php echo sanitize($user['name']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" class="form-control" value="<?php echo sanitize($user['email']); ?>" disabled>
                    </div>
                    
                    <div class="col-12 mt-4">
                        <hr>
                        <h5 class="fw-semibold mb-3">Volunteer Details</h5>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Skills</label>
                        <input type="text" name="skills" class="form-control" value="<?php echo sanitize($user['skills'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Availability</label>
                        <input type="text" name="availability" class="form-control" value="<?php echo sanitize($user['availability'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
