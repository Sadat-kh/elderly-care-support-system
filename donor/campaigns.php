<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

checkRole(7);

// Get all active and completed campaigns with raised amounts
$stmt = $pdo->prepare("
    SELECT c.*, COALESCE(SUM(d.amount), 0) as raised
    FROM donation_campaigns c
    LEFT JOIN donations d ON c.id = d.campaign_id
    GROUP BY c.id
    ORDER BY c.status ASC, c.created_at DESC
");
$stmt->execute();
$campaigns = $stmt->fetchAll();

$pageTitle = 'Campaigns';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header">
        <h1>Donation Campaigns</h1>
        <p class="mb-0 text-white-50">Support our community initiatives and ongoing care.</p>
    </div>

    <div class="row g-4">
        <?php if (count($campaigns) > 0): ?>
            <?php foreach ($campaigns as $campaign): 
                $percent = $campaign['goal_amount'] > 0 ? min(100, ($campaign['raised'] / $campaign['goal_amount']) * 100) : 0;
                $isActive = $campaign['status'] === 'active';
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100 <?php echo $isActive ? '' : 'opacity-75'; ?>">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title mb-0 fw-semibold text-primary"><?php echo sanitize($campaign['title']); ?></h5>
                                <?php if ($isActive): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?php echo ucfirst(sanitize($campaign['status'])); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <p class="text-muted small flex-grow-1">
                                <?php echo sanitize($campaign['description']); ?>
                            </p>
                            
                            <div class="mt-auto">
                                <div class="d-flex justify-content-between align-items-center mb-1 small">
                                    <span class="fw-semibold text-success">৳<?php echo number_format($campaign['raised'], 2); ?> raised</span>
                                    <span class="text-muted">Goal: ৳<?php echo number_format($campaign['goal_amount'], 2); ?></span>
                                </div>
                                <div class="progress mb-3" style="height: 8px;">
                                    <div class="progress-bar <?php echo $isActive ? 'bg-success' : 'bg-secondary'; ?>" role="progressbar" style="width: <?php echo $percent; ?>%;"></div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <?php if ($campaign['end_date']): ?>
                                            Ends: <?php echo date('M j, Y', strtotime($campaign['end_date'])); ?>
                                        <?php endif; ?>
                                    </small>
                                    
                                    <a href="<?php echo sanitize(donor_url('donate.php?campaign_id=' . $campaign['id'])); ?>" class="btn btn-primary">Donate</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <h4 class="text-muted">No campaigns available at the moment.</h4>
                <p class="text-muted">Please check back later.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
