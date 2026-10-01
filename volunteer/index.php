<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(8); // Volunteer

$user_id = (int)$_SESSION['user_id'];

// Check application status
$stmt = $pdo->prepare("SELECT * FROM volunteer_applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$user_id]);
$application = $stmt->fetch();

// Check if they are an active volunteer
$stmt = $pdo->prepare("SELECT * FROM volunteers WHERE user_id = ? LIMIT 1");
$stmt->execute([$user_id]);
$volunteer = $stmt->fetch();

$pageTitle = 'Volunteer Dashboard';
require_once '../includes/header.php';
?>
<div class="elderly-page">
    <div class="elderly-page-header">
        <h1>Welcome, <?php echo sanitize($_SESSION['name']); ?></h1>
        <p class="mb-0 text-white-50">Volunteer Portal Dashboard</p>
    </div>
    
    <div class="row g-4 mt-2">
        <?php if (!$application || ($application['status'] === 'approved' && !$volunteer)): ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-5 text-center">
                        <h3 class="mb-3">Ready to Make a Difference?</h3>
                        <p class="text-muted mb-4">You haven't submitted a volunteer application yet. Join our team of dedicated volunteers to support the elderly community.</p>
                        <a href="<?php echo sanitize(volunteer_url('apply.php')); ?>" class="btn btn-primary btn-lg px-5">Apply Now</a>
                    </div>
                </div>
            </div>
        <?php elseif ($application['status'] === 'pending' && !$volunteer): ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-5 text-center">
                        <h3 class="mb-3 text-warning">Application Under Review</h3>
                        <p class="text-muted mb-0">Thank you for applying! Our team is currently reviewing your application. We will notify you once a decision has been made.</p>
                    </div>
                </div>
            </div>
        <?php elseif ($application['status'] === 'rejected' && !$volunteer): ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-5 text-center">
                        <h3 class="mb-3 text-danger">Application Unsuccessful</h3>
                        <p class="text-muted mb-0">Unfortunately, your application was not approved at this time. Please contact support if you have questions.</p>
                    </div>
                </div>
            </div>
        <?php elseif ($volunteer && $volunteer['status'] === 'active'): ?>
            <?php
            // Get upcoming assignments count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM volunteer_assignments WHERE volunteer_id = ? AND status IN ('assigned', 'in_progress')");
            $stmt->execute([$volunteer['id']]);
            $upcoming_count = $stmt->fetchColumn();
            
            // Get completed assignments count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM volunteer_assignments WHERE volunteer_id = ? AND status = 'completed'");
            $stmt->execute([$volunteer['id']]);
            $completed_count = $stmt->fetchColumn();
            ?>
            <div class="col-md-6">
                <div class="card text-white bg-primary h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <h1 class="display-4 fw-bold mb-0"><?php echo (int)$upcoming_count; ?></h1>
                        <p class="card-text fs-5">Active Assignments</p>
                        <a href="<?php echo sanitize(volunteer_url('schedule.php')); ?>" class="btn btn-light mt-3">View Schedule</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card text-white bg-success h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <h1 class="display-4 fw-bold mb-0"><?php echo (int)$completed_count; ?></h1>
                        <p class="card-text fs-5">Completed Tasks</p>
                        <a href="<?php echo sanitize(volunteer_url('history.php')); ?>" class="btn btn-light mt-3">View History</a>
                    </div>
                </div>
            </div>
        <?php elseif ($volunteer && $volunteer['status'] === 'inactive'): ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-5 text-center">
                        <h3 class="mb-3 text-secondary">Account Inactive</h3>
                        <p class="text-muted mb-0">Your volunteer status is currently inactive. Please contact the manager to resume your duties.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
