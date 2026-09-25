<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
checkRole(5);

$user_id = (int)$_SESSION['user_id'];
$success = '';
$error   = '';

// ── POST: Create campaign ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_campaign'])) {
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '') ?: null;
    $goal        = trim($_POST['goal_amount'] ?? '') ?: null;
    $start_date  = trim($_POST['start_date']  ?? '');
    $end_date    = trim($_POST['end_date']    ?? '') ?: null;

    if (empty($title) || empty($start_date)) {
        $error = 'Title and start date are required.';
    } else {
        $pdo->prepare("INSERT INTO donation_campaigns (title,description,goal_amount,start_date,end_date,status,created_by)
                       VALUES (?,?,?,?,?,'active',?)")
            ->execute([$title, $description, $goal, $start_date, $end_date, $user_id]);
        $cid = (int)$pdo->lastInsertId();
        audit_log($pdo, $user_id, 'create_campaign', 'donation_campaigns', $cid, "Created campaign: {$title}");
        $success = "Campaign '{$title}' created.";
    }
}

// ── POST: Update campaign status ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_campaign_status'])) {
    $cid    = (int)($_POST['campaign_id'] ?? 0);
    $status = trim($_POST['new_status']   ?? '');
    $valid  = ['active','completed','cancelled'];
    if ($cid > 0 && in_array($status, $valid, true)) {
        $pdo->prepare("UPDATE donation_campaigns SET status=? WHERE id=?")->execute([$status, $cid]);
        audit_log($pdo, $user_id, 'update_campaign_status', 'donation_campaigns', $cid, "Set campaign status to {$status}");
        $success = "Campaign status updated to '{$status}'.";
    }
}

// ── Data ──────────────────────────────────────────────────────────────────────
$campaigns = $pdo->query("
    SELECT dc.*,
           COALESCE(SUM(d.amount),0) AS total_raised,
           COUNT(d.id)               AS donor_count,
           u.name                    AS created_by_name
    FROM donation_campaigns dc
    LEFT JOIN donations d ON d.campaign_id = dc.id
    LEFT JOIN users u     ON u.id = dc.created_by
    GROUP BY dc.id
    ORDER BY dc.start_date DESC
")->fetchAll();

// Overall donation totals
$totals = $pdo->query("
    SELECT COUNT(*) AS total_donations,
           COALESCE(SUM(amount),0) AS grand_total,
           COUNT(DISTINCT donor_name) AS unique_donors
    FROM donations
")->fetch();

// Recent donations (all campaigns)
$filter_campaign = (int)($_GET['campaign'] ?? 0);
$d_where  = $filter_campaign > 0 ? 'WHERE d.campaign_id = ?' : 'WHERE 1=1';
$d_params = $filter_campaign > 0 ? [$filter_campaign] : [];
$recent_donations = $pdo->prepare("
    SELECT d.*, dc.title AS campaign_title
    FROM donations d
    LEFT JOIN donation_campaigns dc ON dc.id = d.campaign_id
    {$d_where}
    ORDER BY d.donated_at DESC LIMIT 50
");
$recent_donations->execute($d_params);
$donation_rows = $recent_donations->fetchAll();

$status_badge = ['active'=>'status-approved','completed'=>'status-pending','cancelled'=>'status-cancelled'];
$pageTitle = 'Donations';
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width:1050px;">
    <div class="elderly-page-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1>Donations</h1>
                <p class="mb-0 text-white-50">Campaign management and donation oversight</p>
            </div>
            <button class="btn btn-light btn-sm flex-shrink-0 ms-3"
                    data-bs-toggle="collapse" data-bs-target="#newCampaignForm">
                + New Campaign
            </button>
        </div>
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

    <!-- New Campaign Form -->
    <div class="collapse mb-4" id="newCampaignForm">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3">New Donation Campaign</h2>
                <form method="POST" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" required placeholder="Campaign title">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Goal Amount</label>
                        <input type="number" name="goal_amount" class="form-control" min="0" step="0.01" placeholder="Optional">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Start Date *</label>
                        <input type="date" name="start_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control">
                    </div>
                    <div class="col-md-9">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control" placeholder="Brief description">
                    </div>
                    <div class="col-12">
                        <button type="submit" name="create_campaign" class="btn btn-brand">Create Campaign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Overall totals -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="dashboard-card h-100">
                <div class="dashboard-card-label">Total Raised (All Time)</div>
                <div class="dashboard-card-value">&#2547;<?php echo number_format((float)$totals['grand_total']); ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dashboard-card h-100">
                <div class="dashboard-card-label">Total Donations</div>
                <div class="dashboard-card-value"><?php echo (int)$totals['total_donations']; ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dashboard-card h-100">
                <div class="dashboard-card-label">Unique Donors</div>
                <div class="dashboard-card-value"><?php echo (int)$totals['unique_donors']; ?></div>
            </div>
        </div>
    </div>

    <!-- Campaigns -->
    <h2 class="elderly-section-title">Campaigns</h2>
    <?php if (empty($campaigns)): ?>
        <div class="elderly-empty mb-4"><p>No campaigns yet. Create one above.</p></div>
    <?php else: ?>
    <div class="row g-3 mb-5">
        <?php foreach ($campaigns as $c):
            $goal  = $c['goal_amount'] ? (float)$c['goal_amount'] : null;
            $raised = (float)$c['total_raised'];
            $pct   = $goal && $goal > 0 ? min(100, round($raised / $goal * 100)) : null;
        ?>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h3 class="h6 mb-0"><?php echo sanitize($c['title']); ?></h3>
                            <?php if ($c['description']): ?>
                                <small class="text-muted"><?php echo sanitize($c['description']); ?></small>
                            <?php endif; ?>
                        </div>
                        <span class="status-badge <?php echo $status_badge[$c['status']] ?? 'status-pending'; ?> flex-shrink-0 ms-2">
                            <?php echo ucfirst($c['status']); ?>
                        </span>
                    </div>
                    <div class="mb-2">
                        <strong class="text-success">&#2547;<?php echo number_format($raised); ?></strong>
                        <?php if ($goal): ?>
                            <small class="text-muted"> / &#2547;<?php echo number_format($goal); ?> goal</small>
                        <?php endif; ?>
                        <small class="text-muted ms-2">(<?php echo (int)$c['donor_count']; ?> donation<?php echo $c['donor_count']!=1?'s':'';?>)</small>
                    </div>
                    <?php if ($pct !== null): ?>
                    <div class="progress mb-2" style="height:6px;">
                        <div class="progress-bar bg-success" style="width:<?php echo $pct; ?>%"></div>
                    </div>
                    <small class="text-muted"><?php echo $pct; ?>% of goal</small>
                    <?php endif; ?>
                    <div class="mt-2 d-flex gap-1">
                        <a href="?campaign=<?php echo (int)$c['id']; ?>" class="btn btn-sm btn-outline-primary">View Donations</a>
                        <?php if ($c['status'] === 'active'): ?>
                        <form method="POST">
                            <input type="hidden" name="campaign_id" value="<?php echo (int)$c['id']; ?>">
                            <input type="hidden" name="new_status" value="completed">
                            <button type="submit" name="set_campaign_status" class="btn btn-sm btn-outline-secondary">Mark Completed</button>
                        </form>
                        <form method="POST" onsubmit="return confirm('Cancel this campaign?')">
                            <input type="hidden" name="campaign_id" value="<?php echo (int)$c['id']; ?>">
                            <input type="hidden" name="new_status" value="cancelled">
                            <button type="submit" name="set_campaign_status" class="btn btn-sm btn-outline-danger">Cancel</button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <?php echo date('M j, Y', strtotime($c['start_date'])); ?>
                            <?php echo $c['end_date'] ? ' – '.date('M j, Y', strtotime($c['end_date'])) : ''; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Donation Log -->
    <h2 class="elderly-section-title">
        Donation Log
        <?php if ($filter_campaign): ?><small class="text-muted fs-6">(filtered by campaign)</small><?php endif; ?>
        <?php if ($filter_campaign): ?><a href="donations.php" class="btn btn-sm btn-outline-secondary ms-2">Show All</a><?php endif; ?>
    </h2>
    <?php if (empty($donation_rows)): ?>
        <div class="elderly-empty"><p>No donations recorded yet.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle bg-white shadow-sm rounded">
            <thead class="table-light">
                <tr>
                    <th>Donor</th>
                    <th>Campaign</th>
                    <th class="text-end">Amount</th>
                    <th>Method</th>
                    <th>Message</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($donation_rows as $d): ?>
                <tr>
                    <td>
                        <strong><?php echo sanitize($d['donor_name']); ?></strong>
                        <?php if ($d['donor_email']): ?><br><small class="text-muted"><?php echo sanitize($d['donor_email']); ?></small><?php endif; ?>
                    </td>
                    <td><small><?php echo $d['campaign_title'] ? sanitize($d['campaign_title']) : '<span class="text-muted">General</span>'; ?></small></td>
                    <td class="text-end fw-semibold text-success">&#2547;<?php echo number_format((float)$d['amount']); ?></td>
                    <td><small><?php echo sanitize($d['payment_method'] ?? '—'); ?></small></td>
                    <td><small class="text-muted"><?php echo sanitize(mb_substr($d['message'] ?? '', 0, 50)); ?></small></td>
                    <td><small><?php echo date('M j, Y', strtotime($d['donated_at'])); ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
