<?php
// tests/static_financial_bypass_scan.php — Static Financial Bypass Scanner for Public Endpoints

echo "=== AL-ASAFY GROUP — Static Financial Bypass Scanner ===\n\n";

$publicDir = __DIR__ . '/../public';
$forbiddenPatterns = [
    'UPDATE deposits accumulated_profit' => '/UPDATE\s+deposits\s+SET\s+.*accumulated_profit/i',
    'UPDATE deposits amount'             => '/UPDATE\s+deposits\s+SET\s+.*amount/i',
    'UPDATE deposits currency'           => '/UPDATE\s+deposits\s+SET\s+.*currency/i',
    'UPDATE deposits status=completed'   => '/UPDATE\s+deposits\s+SET\s+.*status\s*=\s*[\'"]completed[\'"]/i',
    'UPDATE deposits principal_refunded' => '/UPDATE\s+deposits\s+SET\s+.*principal_refunded/i',
    'INSERT INTO transactions'          => '/INSERT\s+INTO\s+transactions/i',
    'UPDATE withdraw_requests status'    => '/UPDATE\s+withdraw_requests\s+SET\s+.*status\s*=\s*[\'"](approved|paid|executed)[\'"]/i',
    'INSERT INTO monthly_rates'          => '/INSERT\s+INTO\s+monthly_rates/i',
    'UPDATE monthly_rates'              => '/UPDATE\s+monthly_rates/i',
    'INSERT INTO profit_cycles'          => '/INSERT\s+INTO\s+profit_cycles/i'
];

// Whitelisted initial deposit creation in deposit_add.php (sole allowed exception for new deposits)
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
    $lines = file($file->getPathname());

    foreach ($lines as $lineNum => $lineContent) {
        foreach ($forbiddenPatterns as $label => $pattern) {
            if (preg_match($pattern, $lineContent)) {
                // Check if whitelisted
                if (isset($allowedExceptions[$fileName])) {
                    $isAllowed = false;
                    foreach ($allowedExceptions[$fileName] as $excPattern) {
                        if (preg_match($excPattern, $lineContent)) {
                            $isAllowed = true;
                            break;
                        }
                    }
                    if ($isAllowed && !str_contains($pattern, 'accumulated_profit') && !str_contains($pattern, 'monthly_rates')) {
                        continue;
                    }
                }

                echo "❌ BYPASS VIOLATION: Forbidden direct financial query in [public/{$fileName}: line " . ($lineNum + 1) . "] ($label)\n";
                echo "   Code: " . trim($lineContent) . "\n\n";
                $violations++;
            }
        }
    }
}

echo "Files Scanned: $filesScanned\n";

if ($violations > 0) {
    echo "❌ AUDIT FAILED: $violations direct financial mutation queries detected in public interface files!\n";
    exit(1);
} else {
    echo "✅ AUDIT PASSED: 0 direct financial mutation queries exist in public interface files. All financial executions are centralized in config/approval.php!\n";
    exit(0);
}
