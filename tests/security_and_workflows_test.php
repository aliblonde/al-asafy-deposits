<?php
// tests/security_and_workflows_test.php — Strict Database Integration Test Suite

// ═══════════════════════════════════════════
// STRICT ENVIRONMENT SAFETY GUARD
// ═══════════════════════════════════════════

$appEnv = getenv('APP_ENV');
$allowDbTests = getenv('ASAFY_ALLOW_DB_TESTS');

if ($appEnv === false || $appEnv === '') {
    fwrite(STDERR, "❌ FATAL: APP_ENV is not set. You must set APP_ENV=testing explicitly.\n");
    exit(1);
}

if ($appEnv !== 'testing') {
    fwrite(STDERR, "❌ FATAL: APP_ENV must be 'testing', got '$appEnv'.\n");
    exit(1);
}

if ($allowDbTests !== '1') {
    fwrite(STDERR, "❌ FATAL: ASAFY_ALLOW_DB_TESTS must be '1'. Set it explicitly.\n");
    exit(1);
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/rbac.php';
require_once __DIR__ . '/../config/approval.php';
require_once __DIR__ . '/../config/helpers.php';

echo "=== AL-ASAFY GROUP — Strict Database Integration Test Suite ===\n";
echo "APP_ENV: {$appEnv}\n";

// Database Safety Guard
$pdo = null;
try {
    $pdo = getPDO();
} catch (Throwable $e) {
    fwrite(STDERR, "❌ FATAL: Cannot connect to test database: " . $e->getMessage() . "\n");
    exit(1);
}

// Reject production database names
$dbName = defined('DB_NAME') ? DB_NAME : '';
$blockedNames = ['alasisfh_al_asafy_deposits', 'al_asafy_deposits', 'production_al_asafy'];

if (in_array($dbName, $blockedNames, true)) {
    fwrite(STDERR, "❌ FATAL: Refusing to run tests against production database '$dbName'!\n");
    exit(1);
}

if ($dbName !== '' && !str_ends_with($dbName, '_test')) {
    fwrite(STDERR, "❌ FATAL: Test database name must end with '_test', got '$dbName'.\n");
    exit(1);
}

// Reject production host
$dbHost = getenv('ASAFY_DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : '');
if ($dbHost && str_contains($dbHost, 'alasafygroup.xyz')) {
    fwrite(STDERR, "❌ FATAL: Refusing to run tests against production host!\n");
    exit(1);
}

$maskedDb = substr($dbName, 0, 6) . '***' . substr($dbName, -5);
echo "Database: {$maskedDb}\n\n";

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $testName, string $details = ''): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "✅ PASS: $testName\n";
    } else {
        $failed++;
        echo "❌ FAIL: $testName" . ($details ? " ($details)" : "") . "\n";
    }
}

// ═══════════════════════════════════════════
// SCHEMA & CANONICAL FUNCTION TESTS
// ═══════════════════════════════════════════

// 1. Permission mapping tests
$_SESSION['user_id'] = 1; $_SESSION['role'] = 'admin';

assertTest(getRequiredApprovalPermission('profits.payout') === 'profits.approve_payout', "1. Permission map: profits.payout");
assertTest(getRequiredApprovalPermission('deposits.financial_change') === 'deposits.approve_financial_change', "2. Permission map: deposits.financial_change");
assertTest(getRequiredApprovalPermission('withdrawals.approve') === 'withdrawals.approve', "3. Permission map: withdrawals.approve");
assertTest(getRequiredApprovalPermission('rates.declaration') === 'rates.approve_declaration', "4. Permission map: rates.declaration");
assertTest(getRequiredApprovalPermission('deposits.close') === 'deposits.approve_close', "5. Permission map: deposits.close");
assertTest(getRequiredApprovalPermission('profits.manual') === 'profits.approve_manual', "6. Permission map: profits.manual");

// 7. Canonical payload idempotency
$p1 = canonicalizePayload(['deposit_id' => 10, 'amount' => 500.00, 'note' => 'test']);
$p2 = canonicalizePayload(['amount' => 500.00, 'note' => 'test', 'deposit_id' => 10]);
assertTest($p1 === $p2, "7. Canonical payload sorting produces identical results");

// 8. Transaction type translations
assertTest(arabicTxType('profit_accrual') === 'استحقاق أرباح', "8. arabicTxType: profit_accrual");
assertTest(arabicTxType('profit_payout') === 'صرف أرباح', "9. arabicTxType: profit_payout");
assertTest(arabicTxType('withdrawal_payout') === 'صرف طلب سحب', "10. arabicTxType: withdrawal_payout");
assertTest(arabicTxType('principal_refund') === 'إرجاع رأس المال', "11. arabicTxType: principal_refund");
assertTest(arabicTxType('deposit_adjustment') === 'تسوية رأس المال', "12. arabicTxType: deposit_adjustment");

// 13. Direction translations
assertTest(arabicDirection('credit') === 'إضافة', "13. arabicDirection: credit");
assertTest(arabicDirection('debit') === 'خصم', "14. arabicDirection: debit");
assertTest(arabicDirection('increase') === 'زيادة', "15. arabicDirection: increase");
assertTest(arabicDirection('decrease') === 'تخفيض', "16. arabicDirection: decrease");

// 17. Error sanitizer
$safeMsg = getSafeErrorMessage(new Exception("SQLSTATE[42S02]: Table not found"));
assertTest(!str_contains($safeMsg, 'SQLSTATE') && str_contains($safeMsg, 'ERR-'), "17. Error sanitizer hides SQL details");

// 18. Password policy
$shortPass = validatePasswordPolicy('Short1!');
assertTest($shortPass['valid'] === false, "18. Password policy rejects short passwords");

$longPass = validatePasswordPolicy('StrongPassword123!');
assertTest($longPass['valid'] === true, "19. Password policy accepts compliant passwords");

// 20. Stale files removed
assertTest(!file_exists(__DIR__ . '/../public/admin_seed_test_data.php'), "20. Seed file removed from codebase");

echo "\n=======================================================\n";
echo "Test Results: $passed Passed, $failed Failed out of 20 Tests.\n";
echo "=======================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
