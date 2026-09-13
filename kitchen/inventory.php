<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../config/auth.php';

checkRole(4);
$user_id = (int)$_SESSION['user_id'];
$success = '';
$error = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $item_id = (int)($_POST['item_id'] ?? 0);
    
    if ($action === 'save_item') {
        $item_name = trim($_POST['item_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $quantity = (float)($_POST['quantity'] ?? 0);
        $unit = trim($_POST['unit'] ?? '');
        $threshold = (float)($_POST['low_stock_threshold'] ?? 0);
        $expiry_date = trim($_POST['expiry_date'] ?? '');
        $supplier = trim($_POST['supplier'] ?? '');
        
        if ($expiry_date === '') $expiry_date = null;
        if ($supplier === '') $supplier = null;
        
        if (empty($item_name) || empty($unit)) {
            $error = "Item Name and Unit are required.";
        } elseif ($quantity < 0 || $threshold < 0) {
            $error = "Quantity and threshold cannot be negative.";
        } else {
            try {
                $pdo->beginTransaction();
                
                if ($item_id > 0) {
                    // Update
                    $stmt = $pdo->prepare("UPDATE kitchen_inventory SET item_name = ?, category = ?, quantity = ?, unit = ?, low_stock_threshold = ?, expiry_date = ?, supplier = ? WHERE id = ?");
                    $stmt->execute([$item_name, $category, $quantity, $unit, $threshold, $expiry_date, $supplier, $item_id]);
                    $details = "Updated inventory item: $item_name (Qty: $quantity $unit)";
                } else {
                    // Insert
                    $stmt = $pdo->prepare("INSERT INTO kitchen_inventory (item_name, category, quantity, unit, low_stock_threshold, expiry_date, supplier) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$item_name, $category, $quantity, $unit, $threshold, $expiry_date, $supplier]);
                    $item_id = $pdo->lastInsertId();
                    $details = "Added new inventory item: $item_name (Qty: $quantity $unit)";
                }
                
                // Audit log
                $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, 'save_inventory', 'kitchen_inventory', ?, ?, ?)");
                $stmt->execute([$user_id, $item_id, $details, $_SERVER['REMOTE_ADDR']]);
                
                // Notifications check
                if ($quantity <= $threshold) {
                    $msg = "Low Stock Alert: $item_name is at or below threshold ($quantity $unit).";
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE role_id = 4");
                    $stmt->execute();
                    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $staff_id) {
                        $notify = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, 'Low Stock', ?, 'warning', 'inventory.php')");
                        $notify->execute([$staff_id, $msg]);
                    }
                }
                
                $pdo->commit();
                $success = "Inventory saved successfully.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Database error: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_item') {
        if ($item_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM kitchen_inventory WHERE id = ?");
                $stmt->execute([$item_id]);
                
                $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, 'delete_inventory', 'kitchen_inventory', ?, 'Deleted inventory item', ?)");
                $stmt->execute([$user_id, $item_id, $_SERVER['REMOTE_ADDR']]);
                
                $success = "Item deleted successfully.";
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Fetch Inventory
$stmt = $pdo->prepare("SELECT * FROM kitchen_inventory ORDER BY category ASC, item_name ASC");
$stmt->execute();
$inventory = $stmt->fetchAll();

$pageTitle = 'Food Inventory';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Food Inventory</h1>
                <p class="mb-0 text-white-50">Manage ingredients and stock levels.</p>
            </div>
            <button class="btn btn-outline-light btn-elderly" data-bs-toggle="modal" data-bs-target="#itemModal" onclick="resetForm()">
                <i class="fas fa-plus"></i> Add Item
            </button>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo sanitize($success); ?> <button class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?php echo sanitize($error); ?> <button class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php if (empty($inventory)): ?>
        <div class="elderly-empty">
            <p>No inventory items found.</p>
            <button class="btn btn-brand mt-3" data-bs-toggle="modal" data-bs-target="#itemModal" onclick="resetForm()">Add First Item</button>
        </div>
    <?php else: ?>
        <div class="card dashboard-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4 py-3">Item Name</th>
                                <th class="py-3">Category</th>
                                <th class="py-3">Quantity</th>
                                <th class="py-3">Status</th>
                                <th class="py-3">Expiry Date</th>
                                <th class="px-4 py-3 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory as $item): 
                                $status = 'In Stock';
                                $badge = 'bg-success';
                                
                                if ($item['quantity'] <= 0) {
                                    $status = 'Out of Stock';
                                    $badge = 'bg-danger';
                                } elseif ($item['quantity'] <= $item['low_stock_threshold']) {
                                    $status = 'Low Stock';
                                    $badge = 'bg-warning text-dark';
                                }
                                
                                $today = new DateTime();
                                if ($item['expiry_date']) {
                                    $expiry = new DateTime($item['expiry_date']);
                                    $diff = $today->diff($expiry)->days;
                                    $invert = $today->diff($expiry)->invert; // 1 if past
                                    
                                    if ($invert) {
                                        $status = 'Expired';
                                        $badge = 'bg-dark';
                                    } elseif ($diff <= 7) {
                                        $status = 'Near Expiry';
                                        $badge = 'bg-warning text-dark';
                                    }
                                }
                            ?>
                                <tr>
                                    <td class="px-4 py-3 fw-bold text-brand">
                                        <?php echo sanitize($item['item_name']); ?>
                                        <?php if ($item['supplier']): ?>
                                            <div class="small text-muted fw-normal"><i class="fas fa-truck"></i> <?php echo sanitize($item['supplier']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 text-muted"><?php echo sanitize($item['category'] ?? '-'); ?></td>
                                    <td class="py-3 fw-semibold">
                                        <?php echo floatval($item['quantity']) . ' ' . sanitize($item['unit']); ?>
                                        <div class="small text-muted fw-normal">Min: <?php echo floatval($item['low_stock_threshold']); ?></div>
                                    </td>
                                    <td class="py-3">
                                        <span class="badge <?php echo $badge; ?>"><?php echo $status; ?></span>
                                    </td>
                                    <td class="py-3">
                                        <?php echo $item['expiry_date'] ? date('M j, Y', strtotime($item['expiry_date'])) : '<span class="text-muted">-</span>'; ?>
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        <button class="btn btn-sm btn-outline-primary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#itemModal"
                                            data-id="<?php echo $item['id']; ?>"
                                            data-name="<?php echo sanitize($item['item_name']); ?>"
                                            data-cat="<?php echo sanitize($item['category']); ?>"
                                            data-qty="<?php echo $item['quantity']; ?>"
                                            data-unit="<?php echo sanitize($item['unit']); ?>"
                                            data-thresh="<?php echo $item['low_stock_threshold']; ?>"
                                            data-exp="<?php echo $item['expiry_date']; ?>"
                                            data-sup="<?php echo sanitize($item['supplier']); ?>"
                                            onclick="fillForm(this)">
                                            Edit
                                        </button>
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

<!-- Add/Edit Modal -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="" method="POST" class="modal-content">
            <input type="hidden" name="action" value="save_item">
            <input type="hidden" name="item_id" id="modal_item_id" value="0">
            
            <div class="modal-header bg-light">
                <h5 class="modal-title text-brand fw-bold" id="modalTitle">Add Inventory Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Item Name *</label>
                    <input type="text" class="form-control" name="item_name" id="modal_item_name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Category</label>
                    <input type="text" class="form-control" name="category" id="modal_category" placeholder="e.g. Produce, Dairy, Dry Goods">
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label fw-semibold">Quantity *</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="quantity" id="modal_quantity" required>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label fw-semibold">Unit *</label>
                        <input type="text" class="form-control" name="unit" id="modal_unit" required placeholder="e.g. kg, lbs, count">
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label fw-semibold">Low Stock Threshold *</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="low_stock_threshold" id="modal_threshold" required>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label fw-semibold">Expiry Date</label>
                        <input type="date" class="form-control" name="expiry_date" id="modal_expiry">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Supplier</label>
                    <input type="text" class="form-control" name="supplier" id="modal_supplier">
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-danger d-none" id="modal_delete_btn" onclick="deleteItem()">Delete</button>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand">Save Item</button>
                </div>
            </div>
        </form>
        <form id="deleteForm" method="POST" class="d-none">
            <input type="hidden" name="action" value="delete_item">
            <input type="hidden" name="item_id" id="delete_item_id">
        </form>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('modalTitle').textContent = 'Add Inventory Item';
    document.getElementById('modal_item_id').value = '0';
    document.getElementById('modal_item_name').value = '';
    document.getElementById('modal_category').value = '';
    document.getElementById('modal_quantity').value = '';
    document.getElementById('modal_unit').value = '';
    document.getElementById('modal_threshold').value = '0';
    document.getElementById('modal_expiry').value = '';
    document.getElementById('modal_supplier').value = '';
    document.getElementById('modal_delete_btn').classList.add('d-none');
}

function fillForm(btn) {
    document.getElementById('modalTitle').textContent = 'Edit Inventory Item';
    document.getElementById('modal_item_id').value = btn.getAttribute('data-id');
    document.getElementById('modal_item_name').value = btn.getAttribute('data-name');
    document.getElementById('modal_category').value = btn.getAttribute('data-cat');
    document.getElementById('modal_quantity').value = btn.getAttribute('data-qty');
    document.getElementById('modal_unit').value = btn.getAttribute('data-unit');
    document.getElementById('modal_threshold').value = btn.getAttribute('data-thresh');
    document.getElementById('modal_expiry').value = btn.getAttribute('data-exp');
    document.getElementById('modal_supplier').value = btn.getAttribute('data-sup');
    document.getElementById('modal_delete_btn').classList.remove('d-none');
}

function deleteItem() {
    if (confirm('Are you sure you want to delete this inventory item?')) {
        document.getElementById('delete_item_id').value = document.getElementById('modal_item_id').value;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
