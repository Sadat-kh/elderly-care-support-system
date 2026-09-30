<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

checkRole(7);
$user_id = (int)$_SESSION['user_id'];

// Get campaigns this user contributed to
$stmt = $pdo->prepare("
    SELECT c.title, c.description, c.goal_amount, c.status, 
           SUM(d.amount) as user_contribution,
           (SELECT COALESCE(SUM(amount), 0) FROM donations WHERE campaign_id = c.id) as total_raised
    FROM donation_campaigns c
    JOIN donations d ON c.id = d.campaign_id
    WHERE d.donor_user_id = ?
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$stmt->execute([$user_id]);
$impacts = $stmt->fetchAll();

$pageTitle = 'Your Impact';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header">
        <h1>Your Impact</h1>
        <p class="mb-0 text-white-50">See how your contributions are helping the community.</p>
    </div>

    <div class="row g-4">
        <?php if (count($impacts) > 0): ?>
            <?php foreach ($impacts as $impact): 
                $percent = $impact['goal_amount'] > 0 ? min(100, ($impact['total_raised'] / $impact['goal_amount']) * 100) : 0;
            ?>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h5 class="card-title fw-semibold text-primary mb-3"><?php echo sanitize($impact['title']); ?></h5>
                            <p class="text-muted small mb-4">
                                <?php echo sanitize($impact['description']); ?>
                            </p>
                            
                            <div class="row text-center mb-4">
                                <div class="col-6 border-end">
                                    <span class="text-muted small d-block mb-1">You Contributed</span>
                                    <span class="fw-bold text-success fs-5">৳<?php echo number_format($impact['user_contribution'], 2); ?></span>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted small d-block mb-1">Status</span>
                                    <span class="badge <?php echo $impact['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo ucfirst(sanitize($impact['status'])); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mt-auto">
                                <div class="d-flex justify-content-between align-items-center mb-1 small">
                                    <span class="fw-semibold">Overall Progress</span>
                                    <span class="text-muted">৳<?php echo number_format($impact['total_raised'], 2); ?> / ৳<?php echo number_format($impact['goal_amount'], 2); ?></span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $percent; ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <h4 class="text-muted">No impact data available yet.</h4>
                <p class="text-muted">Make a donation to start seeing your impact here.</p>
                <a href="<?php echo sanitize(donor_url('donate.php')); ?>" class="btn btn-primary mt-2">Make a Donation</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
