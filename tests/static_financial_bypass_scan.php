<?php
// tests/static_financial_bypass_scan.php — Multiline Static Financial Bypass Scanner

echo "=== AL-ASAFY GROUP — Multiline Static Financial Bypass Scanner ===\n\n";

$publicDir = __DIR__ . '/../public';

$forbiddenPatterns = [
    'UPDATE deposits accumulated_profit' => '/UPDATE\s+deposits\s+SET\s+[\s\S]*?accumulated_profit/is',
    'UPDATE deposits amount'             => '/UPDATE\s+deposits\s+SET\s+[\s\S]*?\bamount\b/is',
    'UPDATE deposits currency'           => '/UPDATE\s+deposits\s+SET\s+[\s\S]*?\bcurrency\b/is',
    'UPDATE deposits status=completed'   => '/UPDATE\s+deposits\s+SET\s+[\s\S]*?status\s*=\s*[\'"]completed[\'"]/is',
    'UPDATE deposits principal_refunded' => '/UPDATE\s+deposits\s+SET\s+[\s\S]*?principal_refunded/is',
    'INSERT INTO transactions'          => '/INSERT\s+INTO\s+transactions/is',
    'UPDATE withdraw_requests status'    => '/UPDATE\s+withdraw_requests\s+SET\s+[\s\S]*?status\s*=\s*[\'"](approved|paid|executed)[\'"]/is',
    'INSERT INTO monthly_rates'          => '/INSERT\s+INTO\s+monthly_rates/is',
    'UPDATE monthly_rates'              => '/UPDATE\s+monthly_rates/is',
    'INSERT INTO profit_cycles'          => '/INSERT\s+INTO\s+profit_cycles/is'
];

// Whitelisted initial deposit creation in deposit_add.php (sole allowed exception)
$allowedExceptions = [
    'deposit_add.php' => [
        '/INSERT\s+INTO\s+deposits/is',
        '/INSERT\s+INTO\s+transactions/is'
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

    foreach ($forbiddenPatterns as $label => $pattern) {
        if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $matchedText = $match[0];
                $offset = $match[1];
                $lineNum = substr_count(substr($content, 0, $offset), "\n") + 1;

                // Check if whitelisted exception for this file
                if (isset($allowedExceptions[$fileName])) {
                    $isAllowed = false;
                    foreach ($allowedExceptions[$fileName] as $excPattern) {
                        if (preg_match($excPattern, $matchedText)) {
                            $isAllowed = true;
                            break;
                        }
                    }
                    if ($isAllowed && !str_contains($matchedText, 'accumulated_profit') && !str_contains($matchedText, 'monthly_rates')) {
                        continue;
                    }
                }

                echo "❌ BYPASS VIOLATION: Forbidden direct financial query in [public/{$fileName}: line {$lineNum}] ($label)\n";
                echo "   Snippet: " . trim(substr($matchedText, 0, 100)) . "...\n\n";
                $violations++;
            }
        }
    }
}

echo "Files Scanned: $filesScanned\n";

if ($violations > 0) {
    echo "❌ AUDIT FAILED: $violations multiline direct financial mutation queries detected in public interface files!\n";
    exit(1);
} else {
    echo "✅ AUDIT PASSED: 0 direct financial mutation queries exist in public interface files. All financial executions are centralized in config/approval.php!\n";
    exit(0);
}
