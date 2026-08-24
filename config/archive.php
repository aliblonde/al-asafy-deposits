<?php
// config/archive.php — Soft Delete & Archive System

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/logger.php';

/**
 * Soft delete / archive a record.
 */
function archiveRecord(PDO $pdo, string $recordType, int $originalId, string $reason): int
{
    requirePermission('archive.manage');

    $tableMap = [
        'investor' => 'investors',
        'deposit' => 'deposits',
        'transaction' => 'transactions',
        'user' => 'users'
    ];

    if (!isset($tableMap[$recordType])) {
        throw new Exception('نوع السجل غير معرف للأرشفة.');
    }

    $tableName = $tableMap[$recordType];
    $stmt = $pdo->prepare("SELECT * FROM `$tableName` WHERE id = ?");
    $stmt->execute([$originalId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        throw new Exception('السجل غير موجود أو تم تحويله للأرشيف سابقاً.');
    }

    $deletedBy = currentUserId();
    $ip = getClientIp();

    // Section 13: Archive + delete + audit in a single transaction
    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare("
            INSERT INTO archived_records (record_type, original_id, data_json, deletion_reason, deleted_by, deleted_at, ip_address)
            VALUES (?, ?, ?, ?, ?, NOW(), ?)
        ");
        $ins->execute([$recordType, $originalId, json_encode($data, JSON_UNESCAPED_UNICODE), trim($reason), $deletedBy, $ip]);

        $archiveId = (int)$pdo->lastInsertId();

        $del = $pdo->prepare("DELETE FROM `$tableName` WHERE id = ?");
        $del->execute([$originalId]);

        logActivity($pdo, 'ARCHIVE_RECORD', 'archived_records', $archiveId, null, [
            'type' => $recordType,
            'original_id' => $originalId,
            'reason' => $reason
        ]);

        $pdo->commit();
        return $archiveId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Restore an archived record.
 */
function restoreArchivedRecord(PDO $pdo, int $archiveId): bool
{
    requirePermission('archive.restore');

    $stmt = $pdo->prepare("SELECT * FROM archived_records WHERE id = ?");
    $stmt->execute([$archiveId]);
    $archived = $stmt->fetch();

    if (!$archived) {
        throw new Exception('السجل المؤرشف غير موجود.');
    }

    $tableMap = [
        'investor' => 'investors',
        'deposit' => 'deposits',
        'transaction' => 'transactions',
        'user' => 'users'
    ];

    $recordType = $archived['record_type'];
    if (!isset($tableMap[$recordType])) {
        throw new Exception('نوع السجل غير معرف للاستعادة.');
    }

    $tableName = $tableMap[$recordType];
    $data = json_decode($archived['data_json'], true);

    if (!is_array($data) || empty($data)) {
        throw new Exception('بيانات الأرشيف غير صالحة للاستعادة.');
    }

    // Insert back into active table
    $columns = array_keys($data);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $colList = implode('`, `', $columns);

    $sql = "INSERT INTO `$tableName` (`$colList`) VALUES ($placeholders)";
    $ins = $pdo->prepare($sql);
    $ins->execute(array_values($data));

    // Delete from archived_records
    $pdo->prepare("DELETE FROM archived_records WHERE id = ?")->execute([$archiveId]);

    logActivity($pdo, 'RESTORE_ARCHIVED_RECORD', 'archived_records', $archiveId, null, [
        'type' => $recordType,
        'original_id' => $archived['original_id']
    ]);

    return true;
}
