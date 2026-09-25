<?php

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Write one row to audit_logs — same INSERT pattern used by Kitchen portal.
 * @param PDO    $pdo
 * @param int    $user_id     Acting user (manager)
 * @param string $action      e.g. 'approve_room_change'
 * @param string $entity_type e.g. 'room_change_requests'
 * @param int    $entity_id   PK of the affected row
 * @param string $details     Human-readable summary
 */
function audit_log(PDO $pdo, int $user_id, string $action, string $entity_type, int $entity_id, string $details): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)"
    )->execute([$user_id, $action, $entity_type, $entity_id, $details, $ip]);
}

/**
 * Insert a notification for a specific user into the notifications table.
 * @param PDO    $pdo
 * @param int    $target_user_id  Recipient
 * @param string $title
 * @param string $message
 * @param string $type            info | warning | danger | success
 * @param string $link            Optional relative link (e.g. 'manager/rooms.php')
 */
function notify_user(PDO $pdo, int $target_user_id, string $title, string $message, string $type = 'info', string $link = ''): void {
    $pdo->prepare(
        "INSERT INTO notifications (user_id, title, message, type, is_read, link)
         VALUES (?, ?, ?, ?, 0, ?)"
    )->execute([$target_user_id, $title, $message, $type, $link ?: null]);
}
