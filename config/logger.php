<?php
// config/logger.php — Activity log helper

function logActivity(
    PDO $pdo,
    string $action,
    string $entity = '',
    ?int $entityId = null,
    mixed $oldData = null,
    mixed $newData = null
): void {
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $ip = getClientIp();

    $stmt = $pdo->prepare(
        "INSERT INTO activity_logs
            (user_id, action, entity, entity_id, old_data, new_data, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $userId,
        $action,
        $entity,
        $entityId,
        $oldData !== null ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
        $newData !== null ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
        $ip,
    ]);
}
