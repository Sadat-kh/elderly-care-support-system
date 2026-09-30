<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';

checkRole(7);
$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Anonymous';
$user_email = $_SESSION['email'] ?? '';

$success = '';
$error = '';

$campaign_id = isset($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campaign_id = (int)($_POST['campaign_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_method = trim($_POST['payment_method'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($campaign_id <= 0) {
        $error = "Please select a valid campaign.";
    } elseif ($amount <= 0) {
        $error = "Please enter a valid donation amount.";
    } elseif (empty($payment_method)) {
        $error = "Please select a payment method.";
    } else {
        // Verify campaign is active
        $stmt = $pdo->prepare("SELECT status FROM donation_campaigns WHERE id = ?");
        $stmt->execute([$campaign_id]);
        $campaign = $stmt->fetch();

        if (!$campaign) {
            $error = "Campaign not found.";
        } else {
            try {
                // Insert donation, linking donor_user_id
                $stmt = $pdo->prepare("
                    INSERT INTO donations (campaign_id, donor_user_id, donor_name, donor_email, amount, payment_method, message)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $campaign_id,
                    $user_id,
                    $user_name,
                    $user_email,
                    $amount,
                    $payment_method,
                    $message
                ]);
                
                $success = "Thank you! Your donation of ৳" . number_format($amount, 2) . " has been received successfully.";
                audit_log($pdo, $user_id, 'make_donation', 'donations', $pdo->lastInsertId(), "Donated to campaign $campaign_id");
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Get active campaigns for the dropdown
$stmt = $pdo->prepare("SELECT id, title FROM donation_campaigns ORDER BY title ASC");
$stmt->execute();
$active_campaigns = $stmt->fetchAll();

$pageTitle = 'Donate';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header">
        <h1>Make a Donation</h1>
        <p class="mb-0 text-white-50">Your support makes a real difference.</p>
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

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-5">
                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Select Campaign <span class="text-danger">*</span></label>
                            <select name="campaign_id" class="form-select" required>
                                <option value="">-- Choose a campaign --</option>
                                <?php foreach ($active_campaigns as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo ($campaign_id === $c['id']) ? 'selected' : ''; ?>>
                                        <?php echo sanitize($c['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Donation Amount (৳) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control form-control-lg" step="0.01" min="1" required placeholder="e.g., 1000">
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
                            <select name="payment_method" class="form-select" required>
                                <option value="">-- Select payment method --</option>
                                <option value="bKash">bKash</option>
                                <option value="Nagad">Nagad</option>
                                <option value="Rocket">Rocket</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Credit Card">Credit Card</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Message of Support (Optional)</label>
                            <textarea name="message" class="form-control" rows="3" placeholder="Leave a message or dedication..."></textarea>
                        </div>
                        
                        <div class="d-grid mt-5">
                            <button type="submit" class="btn btn-primary btn-lg fw-semibold">Confirm Donation</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
