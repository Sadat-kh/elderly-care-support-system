<?php
require_once '../config/database.php';
require_once '../config/auth.php';


$role_folders = [
    1 => 'elderly',
    2 => 'family',
    3 => 'caregiver',
    4 => 'kitchen',
    5 => 'manager',
    6 => 'admin',
    7 => 'donor',
    8 => 'volunteer',
];


function get_redirect_url($role_id, $role_folders) {
    $redirect = "index.php"; // safe fallback
    if (array_key_exists($role_id, $role_folders)) {
        $folder = $role_folders[$role_id];
        $path   = __DIR__ . "/../{$folder}/index.php";
        if (file_exists($path)) {
            $redirect = "../{$folder}/index.php";
        }
    }
    return $redirect;
}


if (isLoggedIn()) {
    $role_id = isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : 0;
    header("Location: " . get_redirect_url($role_id, $role_folders));
    exit();
}

$error = '';
$formEmail = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['email']) && isset($_POST['password'])) {
        $formEmail = trim($_POST['email']);
        $password  = $_POST['password'];

        if (empty($formEmail) || empty($password)) {
            $error = "Please fill in both fields.";
        } else {
            $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role_id, active_status FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$formEmail]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if ($user['active_status'] == 1) {
                    if (password_verify($password, $user['password_hash'])) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['name']    = $user['name'];
                        $_SESSION['role_id'] = $user['role_id'];

                        $role_id = (int)$user['role_id'];
                        header("Location: " . get_redirect_url($role_id, $role_folders));
                        exit();
                    } else {
                        $error = "Wrong email or password.";
                    }
                } else {
                    $error = "Wrong email or password.";
                }
            } else {
                $error = "Wrong email or password.";
            }
        }
    }
}

$pageTitle = 'Login — Elderly Care';
$isLandingPage = true;
require_once '../includes/header.php';
?>

<div class="page-hero" style="background: linear-gradient(135deg, var(--navy-dark), #2a4470); padding-bottom: 6rem;">
    <div class="page-inner">
        <h1>Welcome Back</h1>
        <p class="page-hero-sub">Sign in to the Elderly Care platform.</p>
    </div>
</div>

<section class="page-section section-cream" style="margin-top: -4rem; padding-top: 0; position: relative; z-index: 10;">
    <div class="page-inner" style="max-width: 480px;">
        <div class="overview-card auth-card" style="padding: 2.5rem 2rem; text-align: left;">
            <h2 style="font-family: 'Merriweather', serif; font-size: 1.6rem; margin-bottom: 1.5rem; text-align: center;">Log in</h2>

            <?php if ($error != '') { ?>
                <div class="form-error show" style="margin-bottom: 1rem; color: #dc3545; background: #f8d7da; padding: 0.8rem; border-radius: 8px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php } ?>

            <form method="post" action="login.php" style="display: flex; flex-direction: column; gap: 1.2rem;">
                <div>
                    <label for="email" style="display: block; margin-bottom: 0.4rem; font-weight: 600;">Email Address</label>
                    <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($formEmail); ?>"
                           style="width: 100%; padding: 0.8rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem;">
                </div>

                <div>
                    <label for="password" style="display: block; margin-bottom: 0.4rem; font-weight: 600;">Password</label>
                    <input type="password" id="password" name="password" required
                           style="width: 100%; padding: 0.8rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem;">
                </div>

                <button type="submit" class="btn-navy" style="width: 100%; margin-top: 0.5rem;">Log in</button>
            </form>

            <p style="text-align: center; margin-top: 1.5rem; color: var(--text-muted);">
                Need an account? <a href="register.php" style="color: var(--coral); font-weight: 600; text-decoration: none;">Register</a>
            </p>
        </div>
    </div>
</section>

<?php require_once '../includes/footer.php'; ?>
