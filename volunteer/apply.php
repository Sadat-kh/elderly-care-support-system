<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(8);

$user_id = (int)$_SESSION['user_id'];
$success = '';
$error = '';

// Check if they already applied
$stmt = $pdo->prepare("SELECT * FROM volunteer_applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$user_id]);
$application = $stmt->fetch();

// We no longer redirect if they are already a volunteer, as requested by the user.
// The form will always be shown.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motivation = trim($_POST['motivation'] ?? '');
    $experience = trim($_POST['experience'] ?? '');
    
    if (empty($motivation)) {
        $error = "Please provide your motivation.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO volunteer_applications (user_id, motivation, experience) VALUES (?, ?, ?)");
        if ($stmt->execute([$user_id, $motivation, $experience])) {
            $success = "Application submitted successfully! We will review it shortly.";
            $application = true; // Block form re-rendering
        } else {
            $error = "Failed to submit application. Please try again.";
        }
    }
}

$pageTitle = 'Apply to Volunteer';
require_once '../includes/header.php';
?>
<div class="elderly-page">
    <div class="elderly-page-header">
        <h1>Volunteer Application</h1>
        <p class="mb-0 text-white-50">Join our team of dedicated volunteers</p>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success mt-4"><?php echo sanitize($success); ?></div>
        <a href="<?php echo sanitize(volunteer_url('index.php')); ?>" class="btn btn-primary">Return to Dashboard</a>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert alert-danger mt-4"><?php echo sanitize($error); ?></div>
        <?php endif; ?>
        
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body p-4">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Why do you want to volunteer? <span class="text-danger">*</span></label>
                        <textarea name="motivation" class="form-control" rows="4" required placeholder="Tell us about your motivation..."></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Relevant Experience (Optional)</label>
                        <textarea name="experience" class="form-control" rows="3" placeholder="Any previous volunteering, medical, or care experience?"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit Application</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php require_once '../includes/footer.php'; ?>
