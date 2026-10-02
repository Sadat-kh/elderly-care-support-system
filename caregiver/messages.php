<?php
require_once '../config/database.php';
require_once '../config/auth.php';
checkRole(3);

$user_id = (int)$_SESSION['user_id'];
$success = '';
$error = '';

// Check assigned residents
$stmt = $pdo->prepare("SELECT elderly_profile_id FROM caregiver_assignments WHERE caregiver_user_id = ? AND active = 1");
$stmt->execute([$user_id]);
$assigned_profiles = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($assigned_profiles)) {
    $in_clause = '0';
} else {
    $in_clause = implode(',', $assigned_profiles);
}

// Allowed recipients (Residents + Family members of assigned residents)
$stmt = $pdo->prepare("
    SELECT u.id as user_id, u.name, 'Resident' as relation 
    FROM elderly_profiles ep 
    JOIN users u ON ep.user_id = u.id 
    WHERE ep.id IN ($in_clause)
    UNION
    SELECT u.id as user_id, u.name, CONCAT('Family (', fc.relationship, ')') as relation
    FROM family_connections fc
    JOIN users u ON fc.family_user_id = u.id
    WHERE fc.elderly_profile_id IN ($in_clause) AND fc.status = 'approved'
");
$stmt->execute();
$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$allowed_recipient_ids = array_column($contacts, 'user_id');

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $receiver_id = (int)$_POST['receiver_id'];
    $subject = trim($_POST['subject']);
    $body = trim($_POST['body']);
    
    if (empty($receiver_id) || empty($subject) || empty($body)) {
        $error = "All fields are required.";
    } elseif (!in_array($receiver_id, $allowed_recipient_ids)) {
        $error = "You can only message assigned residents and their family members.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $receiver_id, $subject, $body])) {
            $success = "Message sent successfully.";
        } else {
            $error = "Failed to send message.";
        }
    }
}

// Fetch conversation thread (if recipient selected)
$selected_user = isset($_GET['user']) ? (int)$_GET['user'] : null;
$messages = [];

if ($selected_user && in_array($selected_user, $allowed_recipient_ids)) {
    // Mark as read
    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    $stmt->execute([$selected_user, $user_id]);

    $stmt = $pdo->prepare("
        SELECT m.*, s.name as sender_name, r.name as receiver_name 
        FROM messages m
        JOIN users s ON m.sender_id = s.id
        JOIN users r ON m.receiver_id = r.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?) 
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$user_id, $selected_user, $selected_user, $user_id]);
    $messages = $stmt->fetchAll();
}

$pageTitle = 'Messages';
require_once '../includes/header.php';
?>

<div class="elderly-page">
    <div class="elderly-page-header d-flex justify-content-between align-items-center">
        <div>
            <h1>Messages</h1>
            <p class="mb-0 text-white-50">Communicate with your residents and their families</p>
        </div>
        <button class="btn btn-light fw-bold" data-bs-toggle="modal" data-bs-target="#newMessageModal">
            <i class="bi bi-pencil-square"></i> New Message
        </button>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Contacts Sidebar -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="bi bi-people text-primary"></i> Contacts</h5>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (empty($contacts)): ?>
                        <div class="list-group-item text-muted p-4 text-center">No contacts available.</div>
                    <?php else: ?>
                        <?php foreach ($contacts as $contact): ?>
                            <a href="?user=<?php echo $contact['user_id']; ?>" 
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-start <?php echo $selected_user === $contact['user_id'] ? 'active' : ''; ?>">
                                <div class="ms-2 me-auto">
                                    <div class="fw-bold"><?php echo sanitize($contact['name']); ?></div>
                                    <small <?php echo $selected_user === $contact['user_id'] ? 'class="text-white-50"' : 'class="text-muted"'; ?>>
                                        <?php echo sanitize($contact['relation']); ?>
                                    </small>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm h-100 d-flex flex-column">
                <?php if (!$selected_user): ?>
                    <div class="card-body d-flex flex-column justify-content-center align-items-center text-muted" style="min-height: 400px;">
                        <i class="bi bi-chat-dots fs-1 mb-3 opacity-50"></i>
                        <h5>Select a contact</h5>
                        <p>Choose a resident or family member to view your conversation.</p>
                    </div>
                <?php else: ?>
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <?php 
                            $c_name = 'Unknown';
                            foreach ($contacts as $c) {
                                if ($c['user_id'] === $selected_user) {
                                    $c_name = sanitize($c['name']) . " (" . sanitize($c['relation']) . ")";
                                    break;
                                }
                            }
                            echo $c_name;
                            ?>
                        </h5>
                    </div>
                    
                    <div class="card-body overflow-auto" style="max-height: 500px; background-color: #f8f9fa;">
                        <?php if (empty($messages)): ?>
                            <div class="text-center text-muted my-5">
                                <p>No messages yet. Send a message to start the conversation.</p>
                            </div>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($messages as $msg): 
                                    $is_me = ($msg['sender_id'] == $user_id);
                                ?>
                                    <div class="d-flex <?php echo $is_me ? 'justify-content-end' : 'justify-content-start'; ?>">
                                        <div class="card border-0 shadow-sm <?php echo $is_me ? 'bg-primary text-white' : 'bg-white'; ?>" style="max-width: 75%;">
                                            <div class="card-body p-3">
                                                <h6 class="card-subtitle mb-2 fw-bold <?php echo $is_me ? 'text-white-50' : 'text-primary'; ?>">
                                                    <?php echo sanitize($msg['subject']); ?>
                                                </h6>
                                                <p class="card-text mb-1" style="white-space: pre-wrap; font-size: 0.95rem;"><?php echo sanitize($msg['body']); ?></p>
                                                <div class="text-end">
                                                    <small class="<?php echo $is_me ? 'text-white-50' : 'text-muted'; ?>" style="font-size: 0.75rem;">
                                                        <?php echo date('M j, g:i A', strtotime($msg['created_at'])); ?>
                                                        <?php if ($is_me && $msg['is_read']): ?>
                                                            <i class="bi bi-check-all text-light ms-1"></i>
                                                        <?php elseif ($is_me): ?>
                                                            <i class="bi bi-check text-light ms-1 opacity-50"></i>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-footer bg-white border-top-0 p-3">
                        <form method="POST">
                            <input type="hidden" name="action" value="send_message">
                            <input type="hidden" name="receiver_id" value="<?php echo $selected_user; ?>">
                            
                            <div class="mb-2">
                                <input type="text" name="subject" class="form-control bg-light border-0" placeholder="Subject" required>
                            </div>
                            <div class="input-group">
                                <textarea name="body" class="form-control bg-light border-0" placeholder="Type your message..." rows="2" required></textarea>
                                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-send"></i></button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- New Message Modal -->
<div class="modal fade" id="newMessageModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="send_message">
            <div class="modal-header">
                <h5 class="modal-title">New Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">To <span class="text-danger">*</span></label>
                    <select name="receiver_id" class="form-select" required>
                        <option value="">Select contact...</option>
                        <?php foreach ($contacts as $c): ?>
                            <option value="<?php echo $c['user_id']; ?>" <?php echo $selected_user === $c['user_id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($c['name']) . " (" . sanitize($c['relation']) . ")"; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Subject <span class="text-danger">*</span></label>
                    <input type="text" name="subject" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Message <span class="text-danger">*</span></label>
                    <textarea name="body" class="form-control" rows="4" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Send Message</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
