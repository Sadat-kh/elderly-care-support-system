<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(2); // Family

$user_id = (int)$_SESSION['user_id'];
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name  = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name) {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
        if ($stmt->execute([$name, $phone, $user_id])) {
            $_SESSION['name'] = $name;
            $success = "Profile updated successfully.";
        } else {
            $error = "Failed to update profile.";
        }
    } else {
        $error = "Name is required.";
    }
}

// Fetch user data
$stmt = $pdo->prepare("SELECT email, name, phone, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$pageTitle = 'My Profile';
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width: 600px;">
    <div class="elderly-page-header">
        <h1>My Profile</h1>
        <p class="mb-0 text-white-50">Manage your family account details</p>
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

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="mb-3">
                    <label class="form-label text-muted small text-uppercase">Account Email</label>
                    <input type="email" class="form-control bg-light" value="<?php echo sanitize($user['email']); ?>" readonly>
                    <div class="form-text">Email address cannot be changed.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small text-uppercase">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?php echo sanitize($user['name']); ?>" required>
                </div>

                <div class="mb-4">
                    <label class="form-label text-muted small text-uppercase">Contact Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo sanitize($user['phone'] ?? ''); ?>">
                </div>

                <hr class="mb-4">
                
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted small">
                        Member since <?php echo date('M Y', strtotime($user['created_at'])); ?>
                    </div>
                    <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
