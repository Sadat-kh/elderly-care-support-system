<?php if (isLoggedIn() && (int)($_SESSION['role_id'] ?? 0) === 1 && !($isLandingPage ?? false)): ?>
<nav class="navbar navbar-expand-lg navbar-dark app-navbar" style="background-color: #1e3054 !important; box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15) !important;">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="<?php echo sanitize(elderly_url('index.php')); ?>">
            Elderly Care
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo sanitize(elderly_url('index.php')); ?>">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo sanitize(elderly_url('meals.php')); ?>">Meals</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo sanitize(elderly_url('medications.php')); ?>">Medications</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo sanitize(elderly_url('activities.php')); ?>">Activities</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">More</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?php echo sanitize(elderly_url('requests.php')); ?>">My Requests</a></li>
                        <li><a class="dropdown-item" href="<?php echo sanitize(elderly_url('care.php')); ?>">Care Plans</a></li>
                        <li><a class="dropdown-item" href="<?php echo sanitize(elderly_url('messages.php')); ?>">Messages</a></li>
                        <li><a class="dropdown-item" href="<?php echo sanitize(elderly_url('notifications.php')); ?>">Notifications</a></li>
                        <li><a class="dropdown-item" href="<?php echo sanitize(elderly_url('transportation.php')); ?>">Transportation</a></li>
                        <li><a class="dropdown-item" href="<?php echo sanitize(elderly_url('payments.php')); ?>">Payments</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo sanitize(elderly_url('profile.php')); ?>">My Profile</a></li>
                        <li><a class="dropdown-item" href="<?php echo sanitize(elderly_url('room_change.php')); ?>">Room Change</a></li>
                        <li><a class="dropdown-item text-danger fw-bold" href="<?php echo sanitize(elderly_url('emergency.php')); ?>">Emergency Help</a></li>
                    </ul>
                </li>
            </ul>

            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                <li class="nav-item">
                    <span class="navbar-text fw-semibold" style="color: #e0e0e0; font-size: 0.9rem;">
                        Hello, <?php echo sanitize($_SESSION['name'] ?? ''); ?>
                    </span>
                </li>
                <li class="nav-item" style="margin: 0 0.5rem;">
                    <span class="text-white-50" style="opacity: 0.35;">|</span>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold" href="<?php echo sanitize(public_url('logout.php')); ?>" style="color: #e0e0e0;">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<?php elseif (isLoggedIn() && (int)($_SESSION['role_id'] ?? 0) === 4 && !($isLandingPage ?? false)): ?>
<nav class="navbar navbar-expand-lg navbar-dark app-navbar" style="background-color: #1e3054 !important; box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15) !important;">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="<?php echo sanitize(kitchen_url('index.php')); ?>">
            Kitchen Portal
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo sanitize(kitchen_url('index.php')); ?>">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo sanitize(kitchen_url('daily_menu.php')); ?>">Daily Menu</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo sanitize(kitchen_url('weekly_menu.php')); ?>">Weekly Planner</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo sanitize(kitchen_url('dietary.php')); ?>">Dietary Requirements</a>
                </li>
                <!-- Phase 2 Links -->
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo sanitize(kitchen_url('reports.php')); ?>">Reports</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">More</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?php echo sanitize(kitchen_url('preparation.php')); ?>">Preparation</a></li>
                        <li><a class="dropdown-item" href="<?php echo sanitize(kitchen_url('distribution.php')); ?>">Distribution</a></li>
                        <li><a class="dropdown-item" href="<?php echo sanitize(kitchen_url('inventory.php')); ?>">Inventory</a></li>
                        <li><a class="dropdown-item" href="<?php echo sanitize(kitchen_url('wastage.php')); ?>">Wastage</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo sanitize(kitchen_url('profile.php')); ?>">My Profile</a></li>
                    </ul>
                </li>
            </ul>

            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                <li class="nav-item">
                    <span class="navbar-text fw-semibold" style="color: #e0e0e0; font-size: 0.9rem;">
                        Hello, <?php echo sanitize($_SESSION['name'] ?? ''); ?>
                    </span>
                </li>
                <li class="nav-item" style="margin: 0 0.5rem;">
                    <span class="text-white-50" style="opacity: 0.35;">|</span>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold" href="<?php echo sanitize(public_url('logout.php')); ?>" style="color: #e0e0e0;">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<?php else: ?>
<nav class="site-navbar" role="navigation" aria-label="Main navigation">
    <div class="nav-container">
        <!-- Logo Top Left -->
        <a href="<?php echo sanitize(public_url('index.php')); ?>" class="nav-logo">
            🏡 Elderly Care
        </a>

        <button class="nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false" aria-controls="navMenu">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <div class="nav-menu" id="navMenu">
            <ul class="nav-links">
                <li><a href="<?php echo sanitize(public_url('index.php')); ?>">Home</a></li>
                <li class="has-dropdown">
                    <a href="<?php echo sanitize(public_url('services.php')); ?>">Services <span class="dd-arrow">▼</span></a>
                    <ul class="dropdown-menu">
                        <li><a href="<?php echo sanitize(public_url('care.php')); ?>">Care & Wellness</a></li>
                        <li><a href="<?php echo sanitize(public_url('food.php')); ?>">Food & Nutrition</a></li>
                        <li><a href="<?php echo sanitize(public_url('activities.php')); ?>">Activities</a></li>
                        <li><a href="<?php echo sanitize(public_url('facilities.php')); ?>">Facilities & Rooms</a></li>
                    </ul>
                </li>
                <li class="has-dropdown">
                    <a href="#">Get Involved <span class="dd-arrow">▼</span></a>
                    <ul class="dropdown-menu">
                        <li><a href="<?php echo sanitize(public_url('donation.php')); ?>">Donation</a></li>
                        <li><a href="<?php echo sanitize(public_url('volunteer.php')); ?>">Volunteer</a></li>
                    </ul>
                </li>
                <li><a href="<?php echo sanitize(public_url('contact.php')); ?>">Contact</a></li>
                <li><a href="<?php echo sanitize(public_url('donation.php')); ?>" style="color: var(--amber);">Donate</a></li>
            </ul>

            <!-- Auth Top Right -->
            <div class="nav-auth">
                <?php if (isLoggedIn()): ?>
                    <span class="nav-user-greeting">Hello, <?php echo sanitize($_SESSION['name'] ?? ''); ?></span>
                    <a href="<?php echo sanitize(public_url('logout.php')); ?>" class="nav-login-btn">Logout</a>
                <?php else: ?>
                    <a href="<?php echo sanitize(public_url('login.php')); ?>" class="nav-login-btn">Login</a>
                    <a href="<?php echo sanitize(public_url('register.php')); ?>" class="nav-register-btn">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>