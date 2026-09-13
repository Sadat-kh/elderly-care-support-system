<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../config/auth.php';

// Strict Role Check for Kitchen
checkRole(4);
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
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $user_id]);
                    $_SESSION['user_name'] = $name;
                    $success = "Profile updated successfully.";
                    
                    // Log audit
                    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, 'update_profile', 'users', ?, 'Updated name/email', ?)");
                    $stmt->execute([$user_id, $user_id, $_SERVER['REMOTE_ADDR']]);
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
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($current_password, $user['password'])) {
                $error = "Incorrect current password.";
            } else {
                try {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $user_id]);
                    $success = "Password changed successfully.";
                    
                    // Log audit
                    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, 'change_password', 'users', ?, 'Changed password', ?)");
                    $stmt->execute([$user_id, $user_id, $_SERVER['REMOTE_ADDR']]);
                } catch (Exception $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch user data
$stmt = $pdo->prepare("SELECT name, email, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

$pageTitle = 'My Profile';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header">
        <h1>My Profile</h1>
        <p class="mb-0 text-white-50">Manage your account settings and credentials.</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo sanitize($success); ?> <button class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?php echo sanitize($error); ?> <button class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card dashboard-card h-100">
                <div class="card-header bg-light border-bottom">
                    <h5 class="mb-0 fw-bold">Personal Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Name</label>
                            <input type="text" name="name" class="form-control" value="<?php echo sanitize($user_data['name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?php echo sanitize($user_data['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Account Created</label>
                            <input type="text" class="form-control text-muted" value="<?php echo date('M j, Y', strtotime($user_data['created_at'])); ?>" disabled>
                        </div>
                        <button type="submit" class="btn btn-brand">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card dashboard-card h-100 border-start border-4 border-primary">
                <div class="card-header bg-light border-bottom">
                    <h5 class="mb-0 fw-bold">Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">New Password</label>
                            <input type="password" name="new_password" class="form-control" required minlength="8">
                            <div class="form-text">Must be at least 8 characters.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="8">
                        </div>
                        <button type="submit" class="btn btn-brand">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
