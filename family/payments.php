<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(2); // Family

$user_id = (int)$_SESSION['user_id'];
$success = '';
$error = '';

// Get linked elderly profile
$stmt = $pdo->prepare("
    SELECT ep.id as profile_id, u.name as elderly_name 
    FROM family_connections fc
    JOIN elderly_profiles ep ON fc.elderly_profile_id = ep.id
    JOIN users u ON ep.user_id = u.id
    WHERE fc.family_user_id = ? AND fc.status = 'approved'
");
$stmt->execute([$user_id]);
$connection = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_invoice']) && $connection) {
    $invoice_id = (int)$_POST['invoice_id'];
    $payment_amount = (float)$_POST['amount'];
    $payment_method = $_POST['payment_method'] ?? 'Credit Card';
    
    // Verify invoice belongs to linked profile
    $inv_stmt = $pdo->prepare("SELECT id, amount, status FROM invoices WHERE id = ? AND elderly_profile_id = ?");
    $inv_stmt->execute([$invoice_id, $connection['profile_id']]);
    $invoice = $inv_stmt->fetch();
    
    if ($invoice && $invoice['status'] !== 'paid' && $invoice['status'] !== 'cancelled') {
        $paid_stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ?");
        $paid_stmt->execute([$invoice_id]);
        $already_paid = (float)$paid_stmt->fetchColumn();
        
        $total_amount = (float)$invoice['amount'];
        $remaining = $total_amount - $already_paid;
        
        if ($payment_amount > $remaining) {
            $payment_amount = $remaining; // Don't overpay
        }
        
        if ($payment_amount > 0) {
            $pdo->prepare("INSERT INTO payments (invoice_id, amount, payment_method, notes) VALUES (?, ?, ?, ?)")
                ->execute([$invoice_id, $payment_amount, $payment_method, 'Paid via Family Portal by ' . $_SESSION['name']]);
                
            if (($already_paid + $payment_amount) >= $total_amount) {
                $pdo->prepare("UPDATE invoices SET status = 'paid' WHERE id = ?")->execute([$invoice_id]);
            } else {
                $pdo->prepare("UPDATE invoices SET status = 'partial' WHERE id = ?")->execute([$invoice_id]);
            }
            $success = "Payment of $" . number_format($payment_amount, 2) . " processed successfully!";
        } else {
            $error = "Invalid payment amount.";
        }
    } else {
        $error = "Invoice not found or already paid.";
    }
}

$invoices = [];
if ($connection) {
    // Get all invoices for the linked resident
    $stmt = $pdo->prepare("
        SELECT i.*, 
               (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = i.id) as paid_amount
        FROM invoices i
        WHERE i.elderly_profile_id = ?
        ORDER BY i.created_at DESC
    ");
    $stmt->execute([$connection['profile_id']]);
    $invoices = $stmt->fetchAll();
}

$pageTitle = 'Payments';
require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h1 class="mb-0">Payments & Invoices</h1>
            <p class="text-muted">Manage payments for <?php echo $connection ? sanitize($connection['elderly_name']) : 'your linked resident'; ?></p>
        </div>
    </div>

    <?php if (!$connection): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> No Linked Residents. You must have an approved connection to view invoices.
        </div>
    <?php else: ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo sanitize($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <?php if (empty($invoices)): ?>
                    <p class="text-muted mb-0">No invoices found for this resident.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Date Issued</th>
                                    <th>Due Date</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $inv): 
                                    $remaining = $inv['amount'] - $inv['paid_amount'];
                                    $status_class = match($inv['status']) {
                                        'paid' => 'success',
                                        'partial' => 'warning',
                                        'cancelled' => 'danger',
                                        default => 'secondary'
                                    };
                                ?>
                                    <tr>
                                        <td><strong><?php echo sanitize($inv['invoice_number']); ?></strong></td>
                                        <td><?php echo date('M j, Y', strtotime($inv['created_at'])); ?></td>
                                        <td><?php echo $inv['due_date'] ? date('M j, Y', strtotime($inv['due_date'])) : '-'; ?></td>
                                        <td><?php echo sanitize($inv['description']); ?></td>
                                        <td>
                                            $<?php echo number_format($inv['amount'], 2); ?>
                                            <?php if ($inv['paid_amount'] > 0 && $inv['status'] !== 'paid'): ?>
                                                <br><small class="text-success">(Paid: $<?php echo number_format($inv['paid_amount'], 2); ?>)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $status_class; ?>">
                                                <?php echo ucfirst($inv['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (in_array($inv['status'], ['unpaid', 'partial'])): ?>
                                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#payModal<?php echo $inv['id']; ?>">
                                                    Pay Now
                                                </button>

                                                <!-- Payment Modal -->
                                                <div class="modal fade" id="payModal<?php echo $inv['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <form method="POST">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Make Payment: <?php echo sanitize($inv['invoice_number']); ?></h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="invoice_id" value="<?php echo $inv['id']; ?>">
                                                                    <p><strong>Remaining Balance:</strong> $<?php echo number_format($remaining, 2); ?></p>
                                                                    
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Payment Amount ($)</label>
                                                                        <input type="number" class="form-control" name="amount" step="0.01" min="1" max="<?php echo $remaining; ?>" value="<?php echo $remaining; ?>" required>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Payment Method</label>
                                                                        <select class="form-select" name="payment_method" required>
                                                                            <option value="Credit Card">Credit Card</option>
                                                                            <option value="Bank Transfer">Bank Transfer</option>
                                                                            <option value="Debit Card">Debit Card</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" name="pay_invoice" class="btn btn-primary">Confirm Payment</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-secondary" disabled>Paid</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
