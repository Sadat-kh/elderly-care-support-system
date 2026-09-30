<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

checkRole(7);
$user_id = (int)$_SESSION['user_id'];

// Get user's donation history
$stmt = $pdo->prepare("
    SELECT d.*, c.title as campaign_title
    FROM donations d
    LEFT JOIN donation_campaigns c ON d.campaign_id = c.id
    WHERE d.donor_user_id = ?
    ORDER BY d.donated_at DESC
");
$stmt->execute([$user_id]);
$donations = $stmt->fetchAll();

$pageTitle = 'Donation History';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header">
        <h1>Donation History</h1>
        <p class="mb-0 text-white-50">Review your past contributions.</p>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <?php if (count($donations) > 0): ?>
                <div class="table-responsive">
                    <table class="table align-middle table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Campaign</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($donations as $donation): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark">
                                            <?php echo date('M j, Y', strtotime($donation['donated_at'])); ?>
                                        </div>
                                        <small class="text-muted"><?php echo date('g:i A', strtotime($donation['donated_at'])); ?></small>
                                    </td>
                                    <td>
                                        <?php echo sanitize($donation['campaign_title'] ?: 'General Fund'); ?>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-success">৳<?php echo number_format($donation['amount'], 2); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo sanitize($donation['payment_method']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($donation['message']): ?>
                                            <span class="text-muted small fst-italic">"<?php echo sanitize($donation['message']); ?>"</span>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <h5 class="text-muted">You haven't made any donations yet.</h5>
                    <a href="<?php echo sanitize(donor_url('donate.php')); ?>" class="btn btn-primary mt-3">Donate Now</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
