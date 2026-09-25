<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
checkRole(5);

// ── Filters ───────────────────────────────────────────────────────────────────
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date   = $_GET['end_date']   ?? date('Y-m-d');
$params = [':start' => $start_date, ':end' => $end_date];

// 1. Residents & Rooms (Current Snapshot)
$residents_total = $pdo->query("SELECT COUNT(*) FROM elderly_profiles")->fetchColumn();
$rooms_occupied  = $pdo->query("SELECT COUNT(DISTINCT room_id) FROM room_assignments WHERE end_date IS NULL")->fetchColumn();
$rooms_total     = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();

// 2. Service Requests (Filtered)
$req_stmt = $pdo->prepare("
    SELECT request_type, status, COUNT(*) as count 
    FROM service_requests 
    WHERE created_at BETWEEN :start AND :end 
    GROUP BY request_type, status
");
$req_stmt->execute([':start' => $start_date . ' 00:00:00', ':end' => $end_date . ' 23:59:59']);
$requests_data = $req_stmt->fetchAll();

// 3. Kitchen Operations (Reusing existing queries from kitchen/reports.php)
$kitchen_prepared = $pdo->prepare("
    SELECT COALESCE(SUM(m.prepared_qty), 0) FROM meals m
    JOIN menus mn ON mn.id = m.menu_id
    WHERE mn.meal_date BETWEEN :start AND :end
");
$kitchen_prepared->execute($params);
$total_meals_prepared = $kitchen_prepared->fetchColumn();

$kitchen_waste = $pdo->prepare("
    SELECT COALESCE(SUM(quantity), 0) FROM kitchen_wastage 
    WHERE meal_date BETWEEN :start AND :end
");
$kitchen_waste->execute($params);
$total_waste = $kitchen_waste->fetchColumn();

// 4. Financial: Payments & Invoices
$finance = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN i.created_at BETWEEN :start_full AND :end_full THEN i.amount ELSE 0 END) as billed_period,
        SUM(CASE WHEN p.paid_at BETWEEN :start_full AND :end_full THEN p.amount ELSE 0 END) as collected_period
    FROM invoices i
    LEFT JOIN payments p ON 1=0 -- Just a dummy join since we need independent sums, we'll run two queries instead for accuracy
");

$billed = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE created_at BETWEEN :start AND :end");
$billed->execute([':start' => $start_date . ' 00:00:00', ':end' => $end_date . ' 23:59:59']);
$total_billed = $billed->fetchColumn();

$collected = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE paid_at BETWEEN :start AND :end");
$collected->execute([':start' => $start_date . ' 00:00:00', ':end' => $end_date . ' 23:59:59']);
$total_collected = $collected->fetchColumn();

// 5. Donations
$donations = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM donations WHERE donated_at BETWEEN :start AND :end");
$donations->execute([':start' => $start_date . ' 00:00:00', ':end' => $end_date . ' 23:59:59']);
$total_donations = $donations->fetchColumn();

// 6. Volunteers (Current snapshot)
$active_volunteers = $pdo->query("SELECT COUNT(*) FROM volunteers WHERE status = 'active'")->fetchColumn();
$completed_assignments = $pdo->prepare("SELECT COUNT(*) FROM volunteer_assignments WHERE status = 'completed' AND assigned_date BETWEEN :start AND :end");
$completed_assignments->execute($params);
$tasks_done = $completed_assignments->fetchColumn();

$pageTitle = 'Cross-Module Reports';
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width:1100px;">
    <div class="elderly-page-header d-flex justify-content-between align-items-center">
        <div>
            <h1>Cross-Module Reports</h1>
            <p class="mb-0 text-white-50">Consolidated operational metrics across all departments</p>
        </div>
        <button onclick="window.print()" class="btn btn-light btn-sm"><i class="bi bi-printer"></i> Print Report</button>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4 d-print-none">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo sanitize($start_date); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo sanitize($end_date); ?>">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button class="btn btn-brand w-100">Generate Report</button>
                </div>
            </form>
        </div>
    </div>

    <div id="print-area">
        <h3 class="h5 mb-4 border-bottom pb-2">Report Period: <?php echo date('M j, Y', strtotime($start_date)); ?> to <?php echo date('M j, Y', strtotime($end_date)); ?></h3>
        
        <div class="row g-4 mb-4">
            <!-- Facility Snapshot -->
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-bold">Facility & Residents (Snapshot)</div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span>Total Registered Residents</span>
                                <strong><?php echo $residents_total; ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span>Total Rooms</span>
                                <strong><?php echo $rooms_total; ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0 border-bottom-0">
                                <span>Occupied Rooms</span>
                                <strong><?php echo $rooms_occupied; ?> (<?php echo $rooms_total > 0 ? round(($rooms_occupied/$rooms_total)*100) : 0; ?>%)</strong>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Financials & Donations -->
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-bold">Financials & Donations (Period)</div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span>Invoiced (Billed)</span>
                                <strong>&#2547;<?php echo number_format((float)$total_billed); ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0 text-success">
                                <span>Payments Collected</span>
                                <strong>&#2547;<?php echo number_format((float)$total_collected); ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0 text-primary border-bottom-0">
                                <span>Donations Received</span>
                                <strong>&#2547;<?php echo number_format((float)$total_donations); ?></strong>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Kitchen Operations -->
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-bold">Kitchen Operations (Period)</div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span>Total Meals Prepared</span>
                                <strong><?php echo (int)$total_meals_prepared; ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0 text-danger border-bottom-0">
                                <span>Total Wastage</span>
                                <strong><?php echo (float)$total_waste; ?> units</strong>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Volunteers -->
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-bold">Volunteers (Period & Snapshot)</div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span>Active Volunteers (Now)</span>
                                <strong><?php echo $active_volunteers; ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0 border-bottom-0">
                                <span>Completed Assignments (Period)</span>
                                <strong><?php echo $tasks_done; ?></strong>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Service Requests Breakdown -->
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white fw-bold">Service Requests Breakdown (Period)</div>
                    <div class="card-body">
                        <?php if (empty($requests_data)): ?>
                            <p class="text-muted mb-0">No service requests created in this period.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th class="text-end">Count</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $grand_total_req = 0;
                                        foreach ($requests_data as $row): 
                                            $grand_total_req += $row['count'];
                                        ?>
                                        <tr>
                                            <td><?php echo ucfirst($row['request_type']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $row['status'] === 'completed' ? 'success' : ($row['status']==='in_progress' ? 'primary' : 'secondary'); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                                                </span>
                                            </td>
                                            <td class="text-end"><?php echo $row['count']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="fw-bold bg-light">
                                            <td colspan="2" class="text-end">Total Period Requests</td>
                                            <td class="text-end"><?php echo $grand_total_req; ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
@media print {
    .d-print-none, .navbar, .elderly-sidebar { display: none !important; }
    .elderly-page { margin-left: 0 !important; max-width: 100% !important; padding: 0 !important; }
    body { background: #fff; }
    .card { border: 1px solid #ddd !important; box-shadow: none !important; margin-bottom: 20px; }
}
</style>

<?php require_once '../includes/footer.php'; ?>
