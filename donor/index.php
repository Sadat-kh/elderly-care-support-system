<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

checkRole(7);
$user_id = (int)$_SESSION['user_id'];

// Get total donated by this user
$stmt = $pdo->prepare("SELECT SUM(amount) FROM donations WHERE donor_user_id = ?");
$stmt->execute([$user_id]);
$total_donated = (float)$stmt->fetchColumn();

// Get recent campaigns
$stmt = $pdo->prepare("
    SELECT c.*, COALESCE(SUM(d.amount), 0) as raised
    FROM donation_campaigns c
    LEFT JOIN donations d ON c.id = d.campaign_id
    WHERE c.status = 'active'
    GROUP BY c.id
    ORDER BY c.created_at DESC 
    LIMIT 3
");
$stmt->execute();
$recent_campaigns = $stmt->fetchAll();

// Get recent donations by this user
$stmt = $pdo->prepare("
    SELECT d.amount, d.donated_at, c.title as campaign_title
    FROM donations d
    LEFT JOIN donation_campaigns c ON d.campaign_id = c.id
    WHERE d.donor_user_id = ?
    ORDER BY d.donated_at DESC
    LIMIT 3
");
$stmt->execute([$user_id]);
$recent_donations = $stmt->fetchAll();

$pageTitle = 'Donor Dashboard';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header">
        <h1>Welcome, <?php echo sanitize($_SESSION['name'] ?? 'Donor'); ?>!</h1>
        <p class="mb-0 text-white-50">Thank you for supporting our community.</p>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 text-center">
                <div class="card-body p-4">
                    <div class="mb-3">
                        <span class="badge bg-success p-3 rounded-circle">
                            <i class="fs-4">💰</i>
                        </span>
                    </div>
                    <h6 class="text-muted mb-2">Total Donated</h6>
                    <h3 class="mb-0">৳<?php echo number_format($total_donated, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 text-center">
                <div class="card-body p-4">
                    <div class="mb-3">
                        <span class="badge bg-primary p-3 rounded-circle">
                            <i class="fs-4">❤️</i>
                        </span>
                    </div>
                    <h6 class="text-muted mb-2">Make a Difference</h6>
                    <a href="<?php echo sanitize(donor_url('donate.php')); ?>" class="btn btn-primary mt-2">Donate Now</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 text-center">
                <div class="card-body p-4">
                    <div class="mb-3">
                        <span class="badge bg-info p-3 rounded-circle">
                            <i class="fs-4">🌟</i>
                        </span>
                    </div>
                    <h6 class="text-muted mb-2">Your Impact</h6>
                    <a href="<?php echo sanitize(donor_url('impact.php')); ?>" class="btn btn-outline-info mt-2">View Impact</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h5 class="mb-0 fw-semibold text-primary">Active Campaigns</h5>
                </div>
                <div class="card-body p-4">
                    <?php if (count($recent_campaigns) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_campaigns as $campaign): 
                                $percent = $campaign['goal_amount'] > 0 ? min(100, ($campaign['raised'] / $campaign['goal_amount']) * 100) : 0;
                            ?>
                                <div class="list-group-item px-0 py-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <h6 class="mb-0"><?php echo sanitize($campaign['title']); ?></h6>
                                        <span class="badge bg-success">Active</span>
                                    </div>
                                    <p class="text-muted small mb-2 text-truncate" style="max-width: 90%;">
                                        <?php echo sanitize($campaign['description']); ?>
                                    </p>
                                    <div class="progress mb-2" style="height: 6px;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percent; ?>%;"></div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <small class="text-muted">Raised: ৳<?php echo number_format($campaign['raised'], 2); ?> of ৳<?php echo number_format($campaign['goal_amount'], 2); ?></small>
                                        <a href="<?php echo sanitize(donor_url('donate.php?campaign_id=' . $campaign['id'])); ?>" class="btn btn-sm btn-outline-primary">Donate</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3 text-end">
                            <a href="<?php echo sanitize(donor_url('campaigns.php')); ?>" class="text-decoration-none small">View all campaigns &rarr;</a>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No active campaigns at the moment.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h5 class="mb-0 fw-semibold text-primary">Your Recent Donations</h5>
                </div>
                <div class="card-body p-4">
                    <?php if (count($recent_donations) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_donations as $donation): ?>
                                <div class="list-group-item px-0 py-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <h6 class="mb-0 text-success">৳<?php echo number_format($donation['amount'], 2); ?></h6>
                                        <small class="text-muted"><?php echo date('M j, Y', strtotime($donation['donated_at'])); ?></small>
                                    </div>
                                    <p class="text-muted small mb-0">
                                        To: <?php echo sanitize($donation['campaign_title'] ?: 'General Fund'); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3 text-end">
                            <a href="<?php echo sanitize(donor_url('history.php')); ?>" class="text-decoration-none small">View all history &rarr;</a>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">You haven't made any donations yet.</p>
                        <a href="<?php echo sanitize(donor_url('donate.php')); ?>" class="btn btn-sm btn-primary mt-3">Make your first donation</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
