<?php
// tests/static_financial_bypass_scan.php — Comprehensive Static Financial Bypass Scanner
// Section 15: Scans ALL PHP files, not just public/

echo "=== AL-ASAFY GROUP — Static Financial Bypass Scanner ===\n\n";

$rootDir = realpath(__DIR__ . '/..');

// Files allowed to make financial mutations (centralized engine + initial deposit)
$allowedFiles = [
    'config/approval.php',      // Central approval engine
    'config/archive.php',       // Archiving system (DELETE only)
    'public/deposit_add.php',   // Initial deposit creation only
    'public/activity_logs.php', // Audit management — export + manifest-based delete
];

$forbiddenPatterns = [
    'INSERT INTO transactions'              => '/INSERT\s+INTO\s+transactions/is',
    'UPDATE deposits balance fields'        => '/UPDATE\s+deposits\s+SET\s+[\s\S]{0,300}?(accumulated_profit|paid_profit|principal_refunded|amount\s*=)/is',
    'UPDATE deposits status=completed'      => '/UPDATE\s+deposits\s+SET\s+[\s\S]{0,200}?status\s*=\s*[\'"]completed[\'"]/is',
    'UPDATE withdraw_requests status'       => '/UPDATE\s+withdraw_requests\s+SET\s+[\s\S]{0,200}?status\s*=\s*[\'"](approved|paid|executed)[\'"]/is',
    'INSERT INTO monthly_rates'             => '/INSERT\s+INTO\s+monthly_rates/is',
    'UPDATE monthly_rates'                  => '/UPDATE\s+monthly_rates/is',
    'INSERT INTO profit_cycles'             => '/INSERT\s+INTO\s+profit_cycles/is',
    'INSERT INTO manual_profit_adjustments' => '/INSERT\s+INTO\s+manual_profit_adjustments/is',
    'INSERT INTO deposit_adjustments'       => '/INSERT\s+INTO\s+deposit_adjustments/is',
    'INSERT INTO rate_declarations'         => '/INSERT\s+INTO\s+rate_declarations/is',
    'DELETE FROM activity_logs'             => '/DELETE\s+FROM\s+activity_logs/is',
];

// Context-based exceptions for deposit_add.php
$allowedContexts = [
    'public/deposit_add.php' => [
        '/INSERT\s+INTO\s+deposits\s*\(/is',
        "/INSERT\s+INTO\s+transactions\s+[\s\S]*?'deposit'/is",
    ]
];

$violations = 0;
$filesScanned = 0;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootDir));
foreach ($iterator as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') continue;

    $fullPath = $file->getRealPath();
    $relativePath = str_replace('\\', '/', substr($fullPath, strlen($rootDir) + 1));

    // Skip test files, vendor, and the scanner itself
    if (str_starts_with($relativePath, 'tests/')
        || str_starts_with($relativePath, 'vendor/')
        || str_starts_with($relativePath, 'scripts/')
        || str_starts_with($relativePath, '.github/')
        || str_starts_with($relativePath, 'sql/')) {
        continue;
    }

    $content = file_get_contents($fullPath);
    if ($content === false) continue;

    $filesScanned++;
    $isAllowed = in_array($relativePath, $allowedFiles, true);

    foreach ($forbiddenPatterns as $label => $regex) {
        if (!preg_match($regex, $content)) continue;

        // If file is in the allowed list, skip
        if ($isAllowed) continue;

        // Check context-based exceptions
        if (isset($allowedContexts[$relativePath])) {
            $isContextAllowed = false;
            foreach ($allowedContexts[$relativePath] as $ctxPattern) {
                if (preg_match($ctxPattern, $content)) {
                    $isContextAllowed = true;
                    break;
                }
            }
            if ($isContextAllowed) continue;
        }

        // Find line number
        $lines = explode("\n", $content);
        $lineNo = '?';
        foreach ($lines as $idx => $line) {
            if (preg_match($regex, $line)) {
                $lineNo = $idx + 1;
                break;
            }
        }

        echo "❌ VIOLATION: $relativePath:$lineNo — $label\n";
        $violations++;
    }
}

echo "\nFiles Scanned: $filesScanned\n";
if ($violations > 0) {
    echo "🚨 AUDIT FAILED: $violations violation(s) found.\n";
    exit(1);
} else {
    echo "✅ AUDIT PASSED: 0 violations.\n";
    exit(0);
}
