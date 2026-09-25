<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
checkRole(5);

// ── Filters ───────────────────────────────────────────────────────────────────
$filter_status   = trim($_GET['status']   ?? '');
$filter_resident = (int)($_GET['resident'] ?? 0);

$where  = ['1=1'];
$params = [];
if ($filter_status !== '') {
    $where[]  = 'i.status = ?';
    $params[] = $filter_status;
}
if ($filter_resident > 0) {
    $where[]  = 'ep.id = ?';
    $params[] = $filter_resident;
}
$where_sql = implode(' AND ', $where);

// ── Invoice list with payment totals ─────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT i.id, i.invoice_number, i.amount, i.description, i.status,
           i.due_date, i.created_at,
           u.name AS resident_name, ep.id AS profile_id,
           COALESCE(SUM(p.amount), 0) AS paid_amount,
           COUNT(p.id)                AS payment_count
    FROM invoices i
    JOIN elderly_profiles ep ON ep.id = i.elderly_profile_id
    JOIN users u              ON u.id  = ep.user_id
    LEFT JOIN payments p      ON p.invoice_id = i.id
    WHERE {$where_sql}
    GROUP BY i.id
    ORDER BY
        CASE i.status WHEN 'unpaid' THEN 1 WHEN 'partial' THEN 2 ELSE 3 END,
        i.due_date ASC
");
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// ── Financial summary ─────────────────────────────────────────────────────────
$summary = $pdo->query("
    SELECT
        COUNT(*) AS total_invoices,
        SUM(amount) AS total_billed,
        SUM(CASE WHEN status='paid'    THEN amount ELSE 0 END) AS total_paid,
        SUM(CASE WHEN status='unpaid'  THEN amount ELSE 0 END) AS total_unpaid,
        SUM(CASE WHEN status='partial' THEN amount ELSE 0 END) AS total_partial,
        SUM(CASE WHEN status='unpaid' AND due_date < CURDATE() THEN amount ELSE 0 END) AS overdue
    FROM invoices
")->fetch();

// ── Monthly trend (last 4 months) ─────────────────────────────────────────────
$monthly = $pdo->query("
    SELECT DATE_FORMAT(created_at,'%b %Y') AS month_label,
           DATE_FORMAT(created_at,'%Y-%m') AS month_sort,
           COUNT(*) AS invoices,
           SUM(amount) AS billed,
           SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) AS collected
    FROM invoices
    GROUP BY month_sort, month_label
    ORDER BY month_sort DESC LIMIT 4
")->fetchAll();

$residents_list = $pdo->query("
    SELECT ep.id, u.name FROM elderly_profiles ep
    JOIN users u ON u.id = ep.user_id ORDER BY u.name
")->fetchAll();

$status_badge = ['paid'=>'status-approved','partial'=>'status-pending','unpaid'=>'status-rejected','cancelled'=>'status-cancelled'];

$pageTitle = 'Payments & Invoices';
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width:1100px;">
    <div class="elderly-page-header">
        <h1>Payments &amp; Invoices</h1>
        <p class="mb-0 text-white-50">Financial overview — billing records tied to residents</p>
    </div>

    <!-- Summary cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="dashboard-card h-100">
                <div class="dashboard-card-label">Total Billed</div>
                <div class="dashboard-card-value">&#2547;<?php echo number_format((float)$summary['total_billed']); ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dashboard-card h-100">
                <div class="dashboard-card-label">Collected</div>
                <div class="dashboard-card-value text-success">&#2547;<?php echo number_format((float)$summary['total_paid']); ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dashboard-card h-100 <?php echo (float)$summary['total_unpaid'] > 0 ? '' : 'dashboard-card--muted'; ?>">
                <div class="dashboard-card-label">Outstanding</div>
                <div class="dashboard-card-value text-danger">&#2547;<?php echo number_format((float)$summary['total_unpaid'] + (float)$summary['total_partial']); ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="dashboard-card h-100 <?php echo (float)$summary['overdue'] > 0 ? '' : 'dashboard-card--muted'; ?>">
                <div class="dashboard-card-label">Overdue</div>
                <div class="dashboard-card-value text-warning">&#2547;<?php echo number_format((float)$summary['overdue']); ?></div>
            </div>
        </div>
    </div>

    <!-- Monthly trend -->
    <?php if (!empty($monthly)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h6 text-muted mb-3">Monthly Collection Trend</h2>
            <div class="row g-3">
                <?php foreach (array_reverse($monthly) as $m):
                    $pct = $m['billed'] > 0 ? round($m['collected'] / $m['billed'] * 100) : 0;
                ?>
                <div class="col">
                    <div class="text-center">
                        <div class="fw-semibold small"><?php echo sanitize($m['month_label']); ?></div>
                        <div class="text-muted" style="font-size:0.7rem;">Billed: &#2547;<?php echo number_format((float)$m['billed']); ?></div>
                        <div class="text-success fw-semibold">&#2547;<?php echo number_format((float)$m['collected']); ?></div>
                        <div class="progress mt-1" style="height:6px;">
                            <div class="progress-bar bg-success" style="width:<?php echo $pct; ?>%"></div>
                        </div>
                        <small class="text-muted"><?php echo $pct; ?>% collected</small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label mb-1">Resident</label>
                    <select name="resident" class="form-select">
                        <option value="">All residents</option>
                        <?php foreach ($residents_list as $res): ?>
                        <option value="<?php echo (int)$res['id']; ?>" <?php echo $filter_resident==(int)$res['id']?'selected':'';?>>
                            <?php echo sanitize($res['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All statuses</option>
                        <?php foreach (['unpaid','partial','paid','cancelled'] as $st): ?>
                        <option value="<?php echo $st; ?>" <?php echo $filter_status===$st?'selected':'';?>>
                            <?php echo ucfirst($st); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-brand w-100">Filter</button>
                    <?php if ($filter_status || $filter_resident): ?>
                    <a href="payments.php" class="btn btn-outline-secondary w-100">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <p class="text-muted mb-3">Showing <strong><?php echo count($invoices); ?></strong> invoice(s)</p>

    <?php if (empty($invoices)): ?>
        <div class="elderly-empty"><p>No invoices found matching your filters.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle bg-white shadow-sm rounded">
            <thead class="table-light">
                <tr>
                    <th>Invoice #</th>
                    <th>Resident</th>
                    <th>Description</th>
                    <th class="text-end">Billed</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Balance</th>
                    <th class="text-center">Status</th>
                    <th>Due Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv):
                    $balance = (float)$inv['amount'] - (float)$inv['paid_amount'];
                    $overdue = $inv['status'] === 'unpaid' && $inv['due_date'] && $inv['due_date'] < date('Y-m-d');
                ?>
                <tr class="<?php echo $overdue ? 'table-warning' : ''; ?>">
                    <td><small class="text-muted"><?php echo sanitize($inv['invoice_number']); ?></small></td>
                    <td><strong><?php echo sanitize($inv['resident_name']); ?></strong></td>
                    <td><small class="text-muted"><?php echo sanitize($inv['description'] ?? '—'); ?></small></td>
                    <td class="text-end fw-semibold">&#2547;<?php echo number_format((float)$inv['amount']); ?></td>
                    <td class="text-end text-success">&#2547;<?php echo number_format((float)$inv['paid_amount']); ?></td>
                    <td class="text-end <?php echo $balance > 0 ? 'text-danger fw-semibold' : 'text-muted'; ?>">
                        <?php echo $balance > 0 ? '&#2547;'.number_format($balance) : '—'; ?>
                    </td>
                    <td class="text-center">
                        <span class="status-badge <?php echo $status_badge[$inv['status']] ?? 'status-pending'; ?>">
                            <?php echo ucfirst($inv['status']); ?>
                        </span>
                        <?php if ($overdue): ?>
                            <br><small class="text-danger" style="font-size:0.65rem;">Overdue</small>
                        <?php endif; ?>
                    </td>
                    <td><small><?php echo $inv['due_date'] ? date('M j, Y', strtotime($inv['due_date'])) : '—'; ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
