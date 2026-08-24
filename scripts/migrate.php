<?php
// scripts/migrate.php — CLI-only Migration Runner (Section 9)
// Usage: ASAFY_RUN_MIGRATIONS=1 php scripts/migrate.php

// ── Reject HTTP access ──
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Migration runner is CLI-only.');
}

// ── Require explicit opt-in ──
if (getenv('ASAFY_RUN_MIGRATIONS') !== '1') {
    fwrite(STDERR, "ERROR: Set ASAFY_RUN_MIGRATIONS=1 to run migrations.\n");
    exit(1);
}

require_once __DIR__ . '/../config/db.php';

$pdo = getPDO();

// ── Advisory lock to prevent concurrent migration ──
$lockResult = $pdo->query("SELECT GET_LOCK('asafy_migration_lock', 10) AS acquired")->fetch();
if ((int)$lockResult['acquired'] !== 1) {
    fwrite(STDERR, "ERROR: Could not acquire migration lock. Another migration may be running.\n");
    exit(1);
}

try {
    // Create schema_migrations if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        version INT UNSIGNED NOT NULL PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        description VARCHAR(255) NOT NULL DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Get current version
    $currentVersion = (int)$pdo->query("SELECT COALESCE(MAX(version), 0) FROM schema_migrations")->fetchColumn();
    echo "Current schema version: $currentVersion\n";

    // Discover migration files
    $migrationDir = __DIR__ . '/../sql/migrations';
    $files = glob($migrationDir . '/*.sql');
    sort($files);

    $applied = 0;
    foreach ($files as $file) {
        $basename = basename($file);
        // Extract version number from filename like 001_xxx.sql, 006_xxx.sql
        if (!preg_match('/^(\d+)_/', $basename, $m)) {
            echo "SKIP: $basename (no version prefix)\n";
            continue;
        }

        $version = (int)$m[1];
        if ($version <= $currentVersion) {
            echo "SKIP: $basename (already applied, version $version <= $currentVersion)\n";
            continue;
        }

        echo "APPLYING: $basename (version $version)...\n";

        $sql = file_get_contents($file);
        if ($sql === false) {
            fwrite(STDERR, "ERROR: Cannot read $file\n");
            exit(1);
        }

        try {
            $pdo->exec($sql);
            echo "  OK: $basename applied successfully.\n";
            $applied++;
        } catch (Throwable $e) {
            fwrite(STDERR, "FAILED: $basename — " . $e->getMessage() . "\n");
            exit(1);
        }
    }

    if ($applied === 0) {
        echo "No new migrations to apply.\n";
    } else {
        echo "\n$applied migration(s) applied. New version: " . (int)$pdo->query("SELECT COALESCE(MAX(version), 0) FROM schema_migrations")->fetchColumn() . "\n";
    }

} finally {
    $pdo->query("SELECT RELEASE_LOCK('asafy_migration_lock')");
}
