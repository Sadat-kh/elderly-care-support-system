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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photo']) && $connection) {
    $caption = trim($_POST['caption'] ?? '');
    
    // Check if file was uploaded without errors
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['photo']['tmp_name'];
        $file_size = $_FILES['photo']['size'];
        $file_name = $_FILES['photo']['name'];
        
        $max_size = 5 * 1024 * 1024; // 5MB
        
        // Allowed MIME types
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        // Allowed Extensions
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        // 1. Check size
        if ($file_size > $max_size) {
            $error = "File is too large. Maximum size is 5MB.";
        } else {
            // 2. Check actual MIME type to prevent spoofing
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);
            
            // Extract extension
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            if (!in_array($mime_type, $allowed_mimes) || !in_array($ext, $allowed_exts)) {
                $error = "Invalid file type. Only JPG, PNG, GIF, and WEBP images are allowed.";
            } else {
                // 3. Generate safe random filename
                $new_filename = bin2hex(random_bytes(16)) . '.' . $ext;
                $upload_dir = '../public/uploads/photos/';
                $destination = $upload_dir . $new_filename;
                
                // Move file
                if (move_uploaded_file($file_tmp, $destination)) {
                    // Save to DB
                    $db_path = 'uploads/photos/' . $new_filename;
                    $stmt = $pdo->prepare("INSERT INTO photos (elderly_profile_id, uploaded_by, file_path, caption) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$connection['profile_id'], $user_id, $db_path, $caption ?: null]);
                    $success = "Photo uploaded successfully!";
                } else {
                    $error = "Failed to move uploaded file. Check server permissions.";
                }
            }
        }
    } else {
        $error = "Error uploading file (Error Code: " . ($_FILES['photo']['error'] ?? 'No File') . ").";
    }
}

$photos = [];
if ($connection) {
    $stmt = $pdo->prepare("
        SELECT p.*, u.name as uploader_name, u.role_id 
        FROM photos p
        JOIN users u ON p.uploaded_by = u.id
        WHERE p.elderly_profile_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$connection['profile_id']]);
    $photos = $stmt->fetchAll();
}

$pageTitle = 'Photos & Memories';
require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h1 class="mb-0">Photos & Memories</h1>
            <p class="text-muted">Share and view moments with <?php echo $connection ? sanitize($connection['elderly_name']) : 'your linked resident'; ?></p>
        </div>
    </div>

    <?php if (!$connection): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> No Linked Residents. You must have an approved connection to view photos.
        </div>
    <?php else: ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo sanitize($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
        <?php endif; ?>

        <!-- Upload Form -->
        <div class="card shadow-sm mb-5">
            <div class="card-body">
                <h5 class="card-title text-primary"><i class="bi bi-cloud-arrow-up"></i> Upload a Memory</h5>
                <form method="POST" enctype="multipart/form-data" class="mt-3">
                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label for="photo" class="form-label">Select Photo (Max 5MB) *</label>
                            <input type="file" class="form-control" id="photo" name="photo" accept=".jpg,.jpeg,.png,.gif,.webp" required>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label for="caption" class="form-label">Caption (Optional)</label>
                            <input type="text" class="form-control" id="caption" name="caption" maxlength="500" placeholder="e.g. Birthday Party 2026!">
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="submit" name="upload_photo" class="btn btn-primary w-100">Upload</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Gallery -->
        <h5 class="text-primary mb-3"><i class="bi bi-images"></i> Gallery</h5>
        <?php if (empty($photos)): ?>
            <div class="card shadow-sm">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-camera" style="font-size: 3rem;"></i>
                    <p class="mt-2 mb-0">No photos shared yet. Be the first to upload a memory!</p>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($photos as $photo): ?>
                    <div class="col-md-4 col-sm-6">
                        <div class="card h-100 shadow-sm border-0">
                            <!-- Securely link to public path -->
                            <img src="<?php echo sanitize(public_url($photo['file_path'])); ?>" class="card-img-top" alt="Memory" style="height: 200px; object-fit: cover;">
                            <div class="card-body">
                                <?php if ($photo['caption']): ?>
                                    <p class="card-text mb-2"><?php echo sanitize($photo['caption']); ?></p>
                                <?php endif; ?>
                                <div class="small text-muted d-flex justify-content-between">
                                    <span>
                                        <i class="bi bi-person"></i> 
                                        <?php echo sanitize($photo['uploader_name']); ?> 
                                        <?php if ($photo['role_id'] == 3 || $photo['role_id'] == 5) echo '<span class="badge bg-secondary ms-1">Staff</span>'; ?>
                                    </span>
                                    <span><i class="bi bi-clock"></i> <?php echo date('M j, Y', strtotime($photo['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
