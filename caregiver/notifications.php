<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(3);

$user_id = (int)$_SESSION['user_id'];

// ── POST: Mark notification(s) read ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $notif_id = (int)($_POST['notif_id'] ?? 0);
    if ($notif_id > 0) {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")
            ->execute([$notif_id, $user_id]);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user_id]);
}

// ── Fetch notifications for this caregiver user ─────────────────────────────────
$filter = $_GET['filter'] ?? 'all'; // all | unread
$where  = ['user_id = ?'];
$params = [$user_id];
if ($filter === 'unread') {
    $where[] = 'is_read = 0';
}
$where_sql = implode(' AND ', $where);

$notifs = $pdo->prepare("
    SELECT * FROM notifications
    WHERE {$where_sql}
    ORDER BY created_at DESC
    LIMIT 100
");
$notifs->execute($params);
$rows = $notifs->fetchAll();

$unread_count = (int)$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0")->execute([$user_id]) ? 
    (function() use($pdo, $user_id){ $s=$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0"); $s->execute([$user_id]); return (int)$s->fetchColumn(); })() : 0;

$type_icon = ['info'=>'ℹ️','warning'=>'⚠️','danger'=>'🚨','success'=>'✅','error'=>'❌'];
$type_badge= ['info'=>'bg-info text-dark','warning'=>'bg-warning text-dark','danger'=>'bg-danger','success'=>'bg-success','error'=>'bg-danger'];

$pageTitle = 'Notifications';
require_once '../includes/header.php';
?>

<div class="elderly-page" style="max-width:800px;">
    <div class="elderly-page-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1>Notifications</h1>
                <p class="mb-0 text-white-50">
                    <?php echo $unread_count > 0
                        ? "{$unread_count} unread notification" . ($unread_count>1?'s':'')
                        : 'All caught up'; ?>
                </p>
            </div>
            <?php if ($unread_count > 0): ?>
            <form method="POST" class="flex-shrink-0 ms-3">
                <button type="submit" name="mark_all_read" class="btn btn-outline-light btn-sm">
                    Mark all read
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter tabs -->
    <div class="d-flex gap-2 mb-4">
        <a href="?filter=all"    class="btn btn-sm <?php echo $filter==='all'    ?'btn-brand':'btn-outline-secondary'; ?>">All</a>
        <a href="?filter=unread" class="btn btn-sm <?php echo $filter==='unread' ?'btn-brand':'btn-outline-secondary'; ?>">
            Unread <?php if($unread_count>0): ?><span class="badge bg-danger ms-1"><?php echo $unread_count;?></span><?php endif;?>
        </a>
    </div>

    <?php if (empty($rows)): ?>
        <div class="elderly-empty">
            <p><?php echo $filter==='unread' ? 'No unread notifications. All caught up! ✓' : 'No notifications yet.'; ?></p>
        </div>
    <?php else: ?>
    <div class="d-flex flex-column gap-2">
        <?php foreach ($rows as $n): ?>
        <div class="card border-0 shadow-sm <?php echo $n['is_read'] ? '' : 'border-start border-4 border-primary'; ?>">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="d-flex align-items-start gap-3">
                        <span style="font-size:1.3rem; line-height:1;"><?php echo $type_icon[$n['type']] ?? 'ℹ️'; ?></span>
                        <div>
                            <div class="fw-semibold <?php echo $n['is_read'] ? 'text-muted' : ''; ?>">
                                <?php echo sanitize($n['title']); ?>
                                <?php if (!$n['is_read']): ?>
                                    <span class="badge <?php echo $type_badge[$n['type']] ?? 'bg-info text-dark'; ?> ms-1" style="font-size:0.65rem;">New</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($n['message']): ?>
                                <div class="text-muted small mt-1"><?php echo sanitize($n['message']); ?></div>
                            <?php endif; ?>
                            <div class="text-muted" style="font-size:0.75rem; margin-top:0.25rem;">
                                <?php echo date('M j, Y g:i A', strtotime($n['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2 flex-shrink-0 ms-3">
                        <?php if ($n['link']): ?>
                            <a href="<?php echo sanitize($n['link']); ?>" class="btn btn-sm btn-outline-primary">View</a>
                        <?php endif; ?>
                        <?php if (!$n['is_read']): ?>
                        <form method="POST">
                            <input type="hidden" name="notif_id" value="<?php echo (int)$n['id']; ?>">
                            <button type="submit" name="mark_read" class="btn btn-sm btn-outline-secondary">
                                Mark Read
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
