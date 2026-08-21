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
    'UPDATE withdraw_requests to paid'   => '/UPDATE\s+withdraw_requests\s+SET\s+[\s\S]*?status\s*=\s*[\'"](approved|paid|executed)[\'"]/is',
    'INSERT INTO monthly_rates'          => '/INSERT\s+INTO\s+monthly_rates/is',
    'UPDATE monthly_rates'              => '/UPDATE\s+monthly_rates/is',
    'INSERT INTO profit_cycles'          => '/INSERT\s+INTO\s+profit_cycles/is',
    'INSERT INTO manual_profit_adjustments' => '/INSERT\s+INTO\s+manual_profit_adjustments/is',
    'INSERT INTO deposit_adjustments'    => '/INSERT\s+INTO\s+deposit_adjustments/is',
];

// Whitelisted: deposit_add.php initial deposit creation block ONLY
// We match the specific context of the allowed INSERT patterns
$allowedContexts = [
    'deposit_add.php' => [
        // Pattern 1: Initial deposit INSERT INTO deposits
        '/INSERT\s+INTO\s+deposits\s*\(/is',
        // Pattern 2: Initial deposit transaction INSERT INTO transactions (only within 'deposit' type context)
        "/INSERT\s+INTO\s+transactions\s+[\s\S]*?'deposit'/is",
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

                // Check contextual exceptions for deposit_add.php
                if ($fileName === 'deposit_add.php') {
                    // Extract context around the match (200 chars before and after)
                    $contextStart = max(0, $offset - 200);
                    $contextEnd = min(strlen($content), $offset + strlen($matchedText) + 200);
                    $context = substr($content, $contextStart, $contextEnd - $contextStart);

                    // Allow: INSERT INTO deposits (initial creation)
                    if ($label === 'UPDATE deposits amount' || $label === 'UPDATE deposits currency') {
                        // Financial change routes through approval engine
                    } elseif (str_contains($label, 'INSERT INTO transactions') && preg_match("/'deposit'/", $context) && !preg_match("/'(profit|withdraw|profit_accrual|profit_payout|withdrawal_payout|principal_refund|deposit_adjustment)'/", $context)) {
                        continue; // Allowed: initial deposit transaction
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
    echo "✅ AUDIT PASSED: 0 direct financial mutation queries exist in public interface files.\n";
    exit(0);
}
