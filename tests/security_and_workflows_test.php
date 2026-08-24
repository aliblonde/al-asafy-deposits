<?php
// tests/security_and_workflows_test.php — Comprehensive Database Integration & Security Test Suite
// Covers all 16 Sections of the Security Integrity Audit

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
require_once __DIR__ . '/../config/logger.php';

echo "=== AL-ASAFY GROUP — Strict Database Integration Test Suite ===\n";
echo "APP_ENV: {$appEnv}\n";

// Database Connection & Safety Guard
$pdo = null;
try {
    $pdo = getPDO();
} catch (Throwable $e) {
    fwrite(STDERR, "❌ FATAL: Cannot connect to test database: " . $e->getMessage() . "\n");
    exit(1);
}

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
// 1. CANONICAL & PERMISSION MAPPING TESTS
// ═══════════════════════════════════════════

assertTest(getRequiredApprovalPermission('profits.payout') === 'profits.approve_payout', "1. Permission map: profits.payout");
assertTest(getRequiredApprovalPermission('deposits.financial_change') === 'deposits.approve_financial_change', "2. Permission map: deposits.financial_change");
assertTest(getRequiredApprovalPermission('withdrawals.approve') === 'withdrawals.approve', "3. Permission map: withdrawals.approve");
assertTest(getRequiredApprovalPermission('rates.declaration') === 'rates.approve_declaration', "4. Permission map: rates.declaration");
assertTest(getRequiredApprovalPermission('deposits.close') === 'deposits.approve_close', "5. Permission map: deposits.close");
assertTest(getRequiredApprovalPermission('profits.manual') === 'profits.approve_manual', "6. Permission map: profits.manual");

// 7. Canonical payload sorting idempotency
$p1 = canonicalizePayload(['deposit_id' => 10, 'amount' => 500.00, 'note' => 'test']);
$p2 = canonicalizePayload(['amount' => 500.00, 'note' => 'test', 'deposit_id' => 10]);
assertTest($p1 === $p2, "7. Canonical payload sorting produces identical keys/json");

// ═══════════════════════════════════════════
// 2. ARABIC TRANSLATION & DIRECTION TESTS
// ═══════════════════════════════════════════

assertTest(arabicTxType('profit_accrual') === 'استحقاق أرباح', "8. arabicTxType: profit_accrual");
assertTest(arabicTxType('profit_payout') === 'صرف أرباح', "9. arabicTxType: profit_payout");
assertTest(arabicTxType('withdrawal_payout') === 'صرف طلب سحب', "10. arabicTxType: withdrawal_payout");
assertTest(arabicTxType('principal_refund') === 'إرجاع رأس المال', "11. arabicTxType: principal_refund");
assertTest(arabicTxType('deposit_adjustment') === 'تسوية رأس المال', "12. arabicTxType: deposit_adjustment");
assertTest(arabicTxType('deposit_currency_reversal') === 'عكس قيد لتصحيح العملة', "13. arabicTxType: deposit_currency_reversal");
assertTest(arabicTxType('deposit_currency_restatement') === 'إثبات قيد بالعملة الصحيحة', "14. arabicTxType: deposit_currency_restatement");

assertTest(arabicDirection('credit') === 'إضافة', "15. arabicDirection: credit");
assertTest(arabicDirection('debit') === 'خصم', "16. arabicDirection: debit");

// ═══════════════════════════════════════════
// 3. SECURITY & POLICY UNIT TESTS
// ═══════════════════════════════════════════

$shortPass = validatePasswordPolicy('Short1!');
assertTest($shortPass['valid'] === false, "17. Password policy rejects passwords under 12 characters");

$longPass = validatePasswordPolicy('StrongPass2026!Sec');
assertTest($longPass['valid'] === true, "18. Password policy accepts compliant passwords");

$safeMsg = getSafeErrorMessage(new Exception("SQLSTATE[42S02]: Table not found"));
assertTest(!str_contains($safeMsg, 'SQLSTATE') && str_contains($safeMsg, 'ERR-'), "19. Error sanitizer hides raw database exceptions");

// Exception Hierarchy
$authEx = new AuthorizationException('Auth test');
$bizEx = new BusinessRuleException('Biz test');
$techEx = new TechnicalExecutionException('Tech test');
assertTest($authEx instanceof RuntimeException && $bizEx instanceof RuntimeException && $techEx instanceof RuntimeException, "20. Typed exceptions hierarchy verified");

// ═══════════════════════════════════════════
// 4. DATABASE INTEGRATION & WORKFLOW TESTS
// ═══════════════════════════════════════════

$cleanupIds = [
    'users' => [],
    'investors' => [],
    'deposits' => [],
    'approval_requests' => [],
    'transactions' => []
];

try {
    // 21. Schema version check
    $schemaVer = getSchemaVersion($pdo);
    assertTest($schemaVer >= 0, "21. Schema version function executes successfully (v{$schemaVer})");

    // Setup Test Fixtures
    $testTimestamp = time();
    
    // Create Test Admin User
    $pdo->prepare("INSERT INTO users (username, password_hash, role, session_version, created_at) VALUES (?, 'hash', 'admin', 1, NOW())")
        ->execute(["test_adm_{$testTimestamp}"]);
    $testAdminId = (int)$pdo->lastInsertId();
    $cleanupIds['users'][] = $testAdminId;

    // Create Test Staff User (without approval permissions)
    $pdo->prepare("INSERT INTO users (username, password_hash, role, session_version, created_at) VALUES (?, 'hash', 'staff', 1, NOW())")
        ->execute(["test_stf_{$testTimestamp}"]);
    $testStaffId = (int)$pdo->lastInsertId();
    $cleanupIds['users'][] = $testStaffId;

    // Create Test Investor
    $pdo->prepare("INSERT INTO investors (full_name, phone, national_id, created_at) VALUES (?, '07700000000', ?, NOW())")
        ->execute(["مستثمر اختبار {$testTimestamp}", "NAT-{$testTimestamp}"]);
    $testInvestorId = (int)$pdo->lastInsertId();
    $cleanupIds['investors'][] = $testInvestorId;

    // Get a deposit type
    $dType = $pdo->query("SELECT * FROM deposit_types LIMIT 1")->fetch();
    $dTypeId = $dType ? (int)$dType['id'] : 1;

    // Create Test Deposit
    $pdo->prepare("INSERT INTO deposits (investor_id, deposit_type_id, amount, currency, accumulated_profit, paid_profit, start_date, end_date, profit_payout_frequency, status, created_at) VALUES (?, ?, 1000000.00, 'IQD', 50000.00, 0.00, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 180 DAY), 1, 'active', NOW())")
        ->execute([$testInvestorId, $dTypeId]);
    $testDepositId = (int)$pdo->lastInsertId();
    $cleanupIds['deposits'][] = $testDepositId;

    // 22. RBAC: Universal admin access
    assertTest(userCan('profits.approve_payout', $testAdminId) === true, "22. RBAC: Admin possesses universal permission");

    // 23. RBAC: Staff without permission fails
    assertTest(userCan('profits.approve_payout', $testStaffId) === false, "23. RBAC: Staff denied unmapped permission");

    // 24. Create Approval Request
    $_SESSION['user_id'] = $testStaffId;
    $_SESSION['role'] = 'staff';
    $reqPayload = ['deposit_id' => $testDepositId, 'amount' => 20000.00, 'note' => 'صرف تجريبي'];
    $reqId = createApprovalRequest($pdo, 'profits.payout', 'deposits', $testDepositId, $reqPayload);
    $cleanupIds['approval_requests'][] = $reqId;
    assertTest($reqId > 0, "24. createApprovalRequest generates pending request #{$reqId}");

    // 25. Idempotency: Duplicate creation returns same ID while pending
    $dupReqId = createApprovalRequest($pdo, 'profits.payout', 'deposits', $testDepositId, $reqPayload);
    assertTest($dupReqId === $reqId, "25. Idempotency: Duplicate pending payload returns existing request ID");

    // 26. Pre-check: Staff fails permission pre-check without altering request status
    $caughtAuthEx = false;
    try {
        preCheckApprovalPermission($pdo, $reqId, $testStaffId);
    } catch (AuthorizationException $e) {
        $caughtAuthEx = true;
    }
    $reqRow = $pdo->query("SELECT status FROM approval_requests WHERE id = {$reqId}")->fetch();
    assertTest($caughtAuthEx && $reqRow['status'] === 'pending', "26. preCheckApprovalPermission blocks unauthorized user and keeps status 'pending'");

    // 27. Atomicity: Unauthorized executeApprovalRequest keeps request 'pending'
    $unauthExec = executeApprovalRequest($pdo, $reqId, $testStaffId);
    $reqRow = $pdo->query("SELECT status FROM approval_requests WHERE id = {$reqId}")->fetch();
    assertTest($unauthExec['success'] === false && !empty($unauthExec['is_auth_error']) && $reqRow['status'] === 'pending', "27. executeApprovalRequest with unauthorized user does not change request to 'failed'");

    // 28. Authorized Approval Execution
    $_SESSION['user_id'] = $testAdminId;
    $_SESSION['role'] = 'admin';
    $authExec = executeApprovalRequest($pdo, $reqId, $testAdminId);
    $reqRow = $pdo->query("SELECT status, execution_reference FROM approval_requests WHERE id = {$reqId}")->fetch();
    assertTest($authExec['success'] === true && $reqRow['status'] === 'executed', "28. executeApprovalRequest with admin succeeds and updates status to 'executed'");

    // 29. Ledger Mutation Verification
    $depRow = $pdo->query("SELECT accumulated_profit, paid_profit FROM deposits WHERE id = {$testDepositId}")->fetch();
    $txRow = $pdo->query("SELECT * FROM transactions WHERE approval_request_id = {$reqId}")->fetch();
    assertTest((float)$depRow['accumulated_profit'] === 30000.00 && (float)$depRow['paid_profit'] === 20000.00 && $txRow && $txRow['direction'] === 'debit' && $txRow['type'] === 'profit_payout', "29. Financial ledger debited correctly and balance updated atomically");

    // 30. Double-Execution Prevention
    $doubleExec = executeApprovalRequest($pdo, $reqId, $testAdminId);
    assertTest($doubleExec['success'] === false, "30. Re-execution of executed request is safely blocked");

    // 31. Rejection Workflow
    $reqPayload2 = ['deposit_id' => $testDepositId, 'amount' => 10000.00, 'note' => 'طلب للرفض'];
    $reqId2 = createApprovalRequest($pdo, 'profits.payout', 'deposits', $testDepositId, $reqPayload2);
    $cleanupIds['approval_requests'][] = $reqId2;
    $rejResult = rejectApprovalRequest($pdo, $reqId2, $testAdminId, 'رفض اختباري مبرر');
    $reqRow2 = $pdo->query("SELECT status, rejection_reason FROM approval_requests WHERE id = {$reqId2}")->fetch();
    assertTest($rejResult === true && $reqRow2['status'] === 'rejected' && str_contains($reqRow2['rejection_reason'], 'رفض اختباري'), "31. rejectApprovalRequest sets status 'rejected' with mandatory reason");

} catch (Throwable $e) {
    assertTest(false, "DB Integration Suite Encountered Exception: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
} finally {
    // Clean up test fixtures
    try {
        if (!empty($cleanupIds['transactions'])) {
            $pdo->exec("DELETE FROM transactions WHERE id IN (" . implode(',', $cleanupIds['transactions']) . ")");
        }
        if (!empty($cleanupIds['approval_requests'])) {
            $pdo->exec("DELETE FROM transactions WHERE approval_request_id IN (" . implode(',', $cleanupIds['approval_requests']) . ")");
            $pdo->exec("DELETE FROM approval_requests WHERE id IN (" . implode(',', $cleanupIds['approval_requests']) . ")");
        }
        if (!empty($cleanupIds['deposits'])) {
            $pdo->exec("DELETE FROM deposits WHERE id IN (" . implode(',', $cleanupIds['deposits']) . ")");
        }
        if (!empty($cleanupIds['investors'])) {
            $pdo->exec("DELETE FROM investors WHERE id IN (" . implode(',', $cleanupIds['investors']) . ")");
        }
        if (!empty($cleanupIds['users'])) {
            $pdo->exec("DELETE FROM users WHERE id IN (" . implode(',', $cleanupIds['users']) . ")");
        }
    } catch (Throwable $ignore) {}
}

echo "\n=======================================================\n";
echo "Test Results: $passed Passed, $failed Failed out of " . ($passed + $failed) . " Tests.\n";
echo "=======================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
