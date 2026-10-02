<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

// Strict Role Check for Caregiver
checkRole(3);
$user_id = (int)$_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($name) || empty($email)) {
            $error = "Name and email are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            // Check if email exists for another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $error = "Email is already in use by another account.";
            } else {
                try {
                    // Update profile (does NOT allow changing role_id)
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ? AND role_id = 3");
                    $stmt->execute([$name, $email, $user_id]);
                    $_SESSION['user_name'] = $name;
                    $success = "Profile updated successfully.";
                    
                    audit_log($pdo, $user_id, 'update_profile', 'users', $user_id, 'Updated Caregiver profile name/email');
                } catch (Exception $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $error = "New password must be at least 8 characters long.";
        } else {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $hash = $stmt->fetchColumn();
            
            // Allow checking 'password' column logic fallback if needed, but the schema uses password_hash
            if (password_verify($current_password, $hash)) {
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND role_id = 3");
                $stmt->execute([$new_hash, $user_id]);
                $success = "Password changed successfully.";
                audit_log($pdo, $user_id, 'change_password', 'users', $user_id, 'Caregiver changed their password');
            } else {
                $error = "Current password is incorrect.";
            }
        }
    }
}

// Fetch current user data
$stmt = $pdo->prepare("SELECT name, email, role_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$pageTitle = 'Caregiver Profile';
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width:800px;">
    <div class="elderly-page-header">
        <h1>My Profile</h1>
        <p class="mb-0 text-white-50">Manage your account settings and password</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo sanitize($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo sanitize($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Update Profile Details -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h5 class="card-title mb-4">Personal Information</h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="mb-3">
                            <label class="form-label text-muted small">Full Name</label>
                            <input type="text" name="name" class="form-control" required value="<?php echo sanitize($user['name']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted small">Email Address</label>
                            <input type="email" name="email" class="form-control" required value="<?php echo sanitize($user['email']); ?>">
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-muted small">Role</label>
                            <input type="text" class="form-control bg-light" disabled value="Caregiver">
                            <small class="text-muted d-block mt-1">Role assignment is managed by Administrators.</small>
                        </div>
                        
                        <button type="submit" class="btn btn-brand w-100">Update Profile</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Change Password -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h5 class="card-title mb-4">Change Password</h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="mb-3">
                            <label class="form-label text-muted small">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted small">New Password</label>
                            <input type="password" name="new_password" class="form-control" required minlength="8">
                            <small class="text-muted">Must be at least 8 characters.</small>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-muted small">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="8">
                        </div>
                        
                        <button type="submit" class="btn btn-outline-primary w-100">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
