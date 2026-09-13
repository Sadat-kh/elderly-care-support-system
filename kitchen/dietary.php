<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../config/auth.php';

// Only Kitchen staff (role_id = 4)
checkRole(4);

// Fetch residents with dietary requirements or allergies
$stmt = $pdo->prepare("
    SELECT 
        u.name, 
        ep.dietary_requirements, 
        ep.allergies,
        r.room_number
    FROM elderly_profiles ep
    JOIN users u ON u.id = ep.user_id
    LEFT JOIN room_assignments ra ON ra.elderly_profile_id = ep.id AND ra.end_date IS NULL
    LEFT JOIN rooms r ON r.id = ra.room_id
    WHERE (ep.dietary_requirements IS NOT NULL AND ep.dietary_requirements != '')
       OR (ep.allergies IS NOT NULL AND ep.allergies != '')
    ORDER BY r.room_number ASC, u.name ASC
");
$stmt->execute();
$dietary_records = $stmt->fetchAll();

$pageTitle = 'Dietary Requirements';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header">
        <h1>Dietary Requirements & Allergies</h1>
        <p class="mb-0">Resident dietary restrictions necessary for safe meal preparation.</p>
    </div>

    <?php if (empty($dietary_records)): ?>
        <div class="elderly-empty">
            <p>No dietary requirements or allergies found for current residents.</p>
        </div>
    <?php else: ?>
        <div class="card dashboard-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4 py-3">Resident</th>
                                <th class="py-3">Room</th>
                                <th class="py-3">Allergies (Critical)</th>
                                <th class="px-4 py-3">Dietary Requirements</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dietary_records as $record): ?>
                                <tr>
                                    <td class="px-4 py-3 fw-semibold text-brand">
                                        <?php echo sanitize($record['name']); ?>
                                    </td>
                                    <td class="py-3">
                                        <?php echo $record['room_number'] ? sanitize($record['room_number']) : '<span class="text-muted">Unassigned</span>'; ?>
                                    </td>
                                    <td class="py-3">
                                        <?php if (!empty($record['allergies'])): ?>
                                            <span class="badge bg-danger text-wrap text-start" style="line-height: 1.4;">
                                                <i class="fas fa-exclamation-circle"></i> <?php echo sanitize($record['allergies']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if (!empty($record['dietary_requirements'])): ?>
                                            <?php echo nl2br(sanitize($record['dietary_requirements'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted small">Standard Diet</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php require_once '../includes/footer.php'; ?>
