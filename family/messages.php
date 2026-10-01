<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(2);

$user_id = (int)$_SESSION['user_id'];
$success = '';
$error = '';

// Check if family has a linked elderly profile
$stmt = $pdo->prepare("
    SELECT ep.id as profile_id, ep.user_id as elderly_user_id, u.name as elderly_name 
    FROM family_connections fc
    JOIN elderly_profiles ep ON fc.elderly_profile_id = ep.id
    JOIN users u ON ep.user_id = u.id
    WHERE fc.family_user_id = ? AND fc.status = 'approved'
");
$stmt->execute([$user_id]);
$connection = $stmt->fetch();

// mark message as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $msg_id = (int)$_POST['msg_id'];
    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?");
    $stmt->execute([$msg_id, $user_id]);
    header('Location: messages.php');
    exit;
}

// send a new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message']) && $connection) {
    $receiver_id = (int)$_POST['receiver_id'];
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if (empty($body)) {
        $error = 'Please enter a message.';
    } elseif ($receiver_id !== (int)$connection['elderly_user_id']) {
        $error = 'You can only message your linked elderly resident.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $receiver_id, $subject ?: null, $body]);
        $success = 'Message sent to ' . sanitize($connection['elderly_name']) . '!';
    }
}

// get received messages
$inbox = [];
$sent = [];
if ($connection) {
    $stmt = $pdo->prepare("
        SELECT m.*, u.name as sender_name
        FROM messages m
        JOIN users u ON u.id = m.sender_id
        WHERE m.receiver_id = ?
        ORDER BY m.is_read ASC, m.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $inbox = $stmt->fetchAll();

    // get sent messages
    $stmt = $pdo->prepare("
        SELECT m.*, u.name as receiver_name
        FROM messages m
        JOIN users u ON u.id = m.receiver_id
        WHERE m.sender_id = ?
        ORDER BY m.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $sent = $stmt->fetchAll();
}

$pageTitle = 'Messages';
require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h1 class="mb-0">Messages</h1>
            <p class="text-muted">Communicate with your loved ones</p>
        </div>
    </div>

    <?php if (!$connection): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> No Linked Residents. You must have an approved connection to message a resident.
        </div>
    <?php else: ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo sanitize($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title text-primary"><i class="bi bi-pencil-square"></i> Send Message</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="receiver_id" class="form-label">To *</label>
                                <select class="form-select" id="receiver_id" name="receiver_id" required>
                                    <option value="<?php echo $connection['elderly_user_id']; ?>">
                                        <?php echo sanitize($connection['elderly_name']); ?> (Resident)
                                    </option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" maxlength="160">
                            </div>
                            <div class="mb-3">
                                <label for="body" class="form-label">Message *</label>
                                <textarea class="form-control" id="body" name="body" rows="4" required></textarea>
                            </div>
                            <button type="submit" name="send_message" class="btn btn-primary w-100">Send Message</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <!-- Inbox -->
                <h5 class="text-primary mb-3"><i class="bi bi-inbox"></i> Inbox</h5>
                <?php if (empty($inbox)): ?>
                    <p class="text-muted">No messages received.</p>
                <?php else: ?>
                    <div class="list-group mb-4">
                        <?php foreach ($inbox as $msg): ?>
                            <div class="list-group-item list-group-item-action <?php echo $msg['is_read'] ? 'bg-light text-muted' : ''; ?>">
                                <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                    <h6 class="mb-0">
                                        <?php if (!$msg['is_read']): ?>
                                            <span class="badge bg-danger me-1">New</span>
                                        <?php endif; ?>
                                        From: <?php echo sanitize($msg['sender_name']); ?>
                                    </h6>
                                    <small><?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?></small>
                                </div>
                                <?php if ($msg['subject']): ?>
                                    <strong><?php echo sanitize($msg['subject']); ?></strong><br>
                                <?php endif; ?>
                                <p class="mb-1 mt-1"><?php echo nl2br(sanitize($msg['body'])); ?></p>
                                
                                <?php if (!$msg['is_read']): ?>
                                    <form method="POST" class="mt-2 text-end">
                                        <input type="hidden" name="msg_id" value="<?php echo $msg['id']; ?>">
                                        <button type="submit" name="mark_read" class="btn btn-sm btn-outline-secondary">Mark as Read</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Sent -->
                <h5 class="text-primary mb-3"><i class="bi bi-send"></i> Sent Messages</h5>
                <?php if (empty($sent)): ?>
                    <p class="text-muted">No messages sent.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($sent as $msg): ?>
                            <div class="list-group-item bg-light text-muted">
                                <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                    <h6 class="mb-0">To: <?php echo sanitize($msg['receiver_name']); ?></h6>
                                    <small><?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?></small>
                                </div>
                                <?php if ($msg['subject']): ?>
                                    <strong><?php echo sanitize($msg['subject']); ?></strong><br>
                                <?php endif; ?>
                                <p class="mb-0 mt-1"><?php echo nl2br(sanitize($msg['body'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
