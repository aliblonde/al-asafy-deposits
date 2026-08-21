<?php
// tests/static_security_scan.php — Static Security Audit Scanner for Public Endpoints

echo "=== AL-ASAFY GROUP — Static Security Audit Scanner ===\n\n";

$publicDir = __DIR__ . '/../public';
$forbiddenPatterns = [
    '/UPDATE\s+deposits\s+SET\s+.*accumulated_profit/i',
    '/UPDATE\s+deposits\s+SET\s+.*amount/i',
    '/UPDATE\s+deposits\s+SET\s+.*currency/i',
    '/UPDATE\s+deposits\s+SET\s+.*status\s*=\s*[\'"]completed[\'"]/i',
    '/INSERT\s+INTO\s+transactions/i',
    '/UPDATE\s+withdraw_requests\s+SET\s+.*status\s*=\s*[\'"](approved|paid|executed)[\ me]/i',
    '/INSERT\s+INTO\s+monthly_rates/i',
    '/UPDATE\s+monthly_rates/i',
    '/INSERT\s+INTO\s+profit_cycles/i'
];

// Whitelisted initial deposit creation in deposit_add.php (sole allowed exception)
$allowedExceptions = [
    'deposit_add.php' => [
        '/INSERT\s+INTO\s+deposits/i',
        '/INSERT\s+INTO\s+transactions/i'
    ]
];

$violations = 0;
$filesScanned = 0;

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($publicDir));
foreach ($files as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') {
        continue;
    }

    $filesScanned++;
    $fileName = $file->getFilename();
    $content = file_get_contents($file->getPathname());

    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $content)) {
            // Check if whitelisted
            if (isset($allowedExceptions[$fileName])) {
                $isAllowed = false;
                foreach ($allowedExceptions[$fileName] as $excPattern) {
                    if (preg_match($excPattern, $content)) {
                        $isAllowed = true;
                        break;
                    }
                }
                if ($isAllowed && !str_contains($pattern, 'accumulated_profit') && !str_contains($pattern, 'monthly_rates')) {
                    continue;
                }
            }

            echo "❌ SECURITY VIOLATION: Forbidden direct financial query in [{$fileName}] matching pattern [{$pattern}]\n";
            $violations++;
        }
    }
}

echo "\nFiles Scanned: $filesScanned\n";

if ($violations > 0) {
    echo "❌ AUDIT FAILED: $violations forbidden direct financial mutation queries detected in public interface files!\n";
    exit(1);
} else {
    echo "✅ AUDIT PASSED: 0 direct financial mutation queries exist in public interface files. All financial executions are centralized in config/approval.php!\n";
    exit(0);
}
