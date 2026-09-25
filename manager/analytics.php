<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/functions.php';
checkRole(5);

// 1. Service Requests Trend (Last 7 Days)
$req_trend = $pdo->query("
    SELECT DATE(created_at) as req_date, COUNT(*) as count 
    FROM service_requests 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY req_date 
    ORDER BY req_date ASC
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Fill missing days
$last_7_days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $last_7_days[$date] = $req_trend[$date] ?? 0;
}
$max_req = !empty($last_7_days) ? max($last_7_days) : 1;
if ($max_req == 0) $max_req = 1;

// 2. Room Occupancy Stats
$total_rooms = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$occupied_rooms = $pdo->query("SELECT COUNT(DISTINCT room_id) FROM room_assignments WHERE end_date IS NULL")->fetchColumn();
$available_rooms = $total_rooms - $occupied_rooms;
$occupancy_rate = $total_rooms > 0 ? round(($occupied_rooms / $total_rooms) * 100) : 0;

// 3. Activity Popularity (Top 5 by registration)
$top_activities = $pdo->query("
    SELECT a.title, COUNT(ar.id) as registrations 
    FROM activities a
    LEFT JOIN activity_registrations ar ON ar.activity_id = a.id
    GROUP BY a.id 
    ORDER BY registrations DESC 
    LIMIT 5
")->fetchAll();
$max_act = !empty($top_activities) ? max(array_column($top_activities, 'registrations')) : 1;
if ($max_act == 0) $max_act = 1;

// 4. Invoicing Status Overall
$inv_stats = $pdo->query("
    SELECT status, COUNT(*) as count, SUM(amount) as total_val
    FROM invoices 
    GROUP BY status
")->fetchAll();
$inv_total = array_sum(array_column($inv_stats, 'count')) ?: 1;

$pageTitle = 'Analytics Dashboard';
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width:1100px;">
    <div class="elderly-page-header">
        <h1>Analytics Dashboard</h1>
        <p class="mb-0 text-white-50">Visual summary and facility trends</p>
    </div>

    <div class="row g-4 mb-4">
        
        <!-- Occupancy -->
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <h5 class="card-title text-muted mb-4">Room Occupancy</h5>
                    
                    <div class="position-relative d-inline-block mx-auto mb-3" style="width:150px; height:150px;">
                        <svg viewBox="0 0 36 36" class="w-100 h-100">
                            <!-- Background Circle -->
                            <path class="text-light" stroke-dasharray="100, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-width="3"></path>
                            <!-- Progress Circle -->
                            <path class="text-brand" stroke-dasharray="<?php echo $occupancy_rate; ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                        </svg>
                        <div class="position-absolute top-50 start-50 translate-middle text-center">
                            <h2 class="mb-0 fw-bold"><?php echo $occupancy_rate; ?>%</h2>
                            <span class="small text-muted">Full</span>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-center gap-4 mt-2">
                        <div>
                            <div class="fw-bold fs-5"><?php echo $occupied_rooms; ?></div>
                            <small class="text-muted">Occupied</small>
                        </div>
                        <div>
                            <div class="fw-bold fs-5 text-success"><?php echo $available_rooms; ?></div>
                            <small class="text-muted">Available</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Service Requests Trend -->
        <div class="col-md-6 col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title text-muted mb-4">Service Requests (Last 7 Days)</h5>
                    <div class="d-flex align-items-end h-100 pb-3" style="min-height: 200px; gap:10px;">
                        <?php foreach($last_7_days as $date => $count): 
                            $height_pct = ($count / $max_req) * 100;
                            // Ensure minimum visual height if > 0
                            if ($count > 0 && $height_pct < 5) $height_pct = 5;
                        ?>
                            <div class="d-flex flex-column align-items-center flex-grow-1" style="height: 100%;">
                                <div class="mt-auto w-100 bg-brand rounded-top" style="height: <?php echo $height_pct; ?>%; opacity: 0.8;" title="<?php echo $count; ?> requests">
                                    <div class="text-center text-white small fw-bold pt-1"><?php echo $count > 0 ? $count : ''; ?></div>
                                </div>
                                <div class="small text-muted mt-2" style="font-size: 0.75rem;"><?php echo date('D', strtotime($date)); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoice Breakdown -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title text-muted mb-4">Invoice Status Breakdown</h5>
                    
                    <?php if(empty($inv_stats)): ?>
                        <p class="text-muted text-center py-4">No invoice data available.</p>
                    <?php else: ?>
                        <?php foreach($inv_stats as $stat): 
                            $pct = round(($stat['count'] / $inv_total) * 100);
                            $color = 'bg-secondary';
                            if ($stat['status'] === 'paid') $color = 'bg-success';
                            if ($stat['status'] === 'unpaid') $color = 'bg-danger';
                            if ($stat['status'] === 'partial') $color = 'bg-warning';
                        ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?php echo ucfirst($stat['status']); ?> (<?php echo $stat['count']; ?>)</span>
                                    <span class="fw-bold">&#2547;<?php echo number_format($stat['total_val']); ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar <?php echo $color; ?>" style="width: <?php echo $pct; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top Activities -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title text-muted mb-4">Top Activities (All Time)</h5>
                    
                    <?php if(empty($top_activities)): ?>
                        <p class="text-muted text-center py-4">No activities logged yet.</p>
                    <?php else: ?>
                        <?php foreach($top_activities as $idx => $act): 
                            $pct = round(($act['registrations'] / $max_act) * 100);
                        ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-truncate pe-2" style="max-width: 80%;"><?php echo sanitize($act['title']); ?></span>
                                    <span class="text-muted small"><?php echo $act['registrations']; ?> reg</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-info" style="width: <?php echo $pct; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
