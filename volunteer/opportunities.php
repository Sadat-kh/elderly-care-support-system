<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(8);
$pageTitle = 'Volunteer Opportunities';
require_once '../includes/header.php';
?>
<div class="elderly-page">
    <div class="elderly-page-header">
        <h1>Opportunities</h1>
        <p class="mb-0 text-white-50">Ways you can help our community</p>
    </div>
    
    <div class="row g-4 mt-2">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h5 class="fw-semibold text-primary">Companionship</h5>
                    <p class="text-muted">Spend time reading, talking, and playing games with residents to brighten their day.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h5 class="fw-semibold text-primary">Transportation</h5>
                    <p class="text-muted">Help drive residents to medical appointments or group outings securely.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h5 class="fw-semibold text-primary">Event Support</h5>
                    <p class="text-muted">Assist in organizing and running community events and holiday celebrations.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
