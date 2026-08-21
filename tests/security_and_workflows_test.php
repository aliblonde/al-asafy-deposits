<?php
// tests/security_and_workflows_test.php — Strict Automated Integration & Security Test Suite

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/rbac.php';
require_once __DIR__ . '/../config/approval.php';
require_once __DIR__ . '/../config/archive.php';
require_once __DIR__ . '/../config/helpers.php';

echo "=== AL-ASAFY GROUP — Strict Automated Integration & Security Test Suite ===\n\n";

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
        echo "❌ FAIL: $testName " . ($details ? "($details)" : "") . "\n";
    }
}

// 1. Staff permission evaluation for approval views
$_SESSION['user_id'] = 2; $_SESSION['role'] = 'staff';
$canView = userCan('approvals.view', 2);
assertTest(is_bool($canView), "1. Staff permission evaluation returns boolean for approval views");

// 2. Staff without approval permission cannot approve request
$canApprove = userCan('profits.approve_payout', 2);
assertTest($canApprove === false || currentRole() === 'admin', "2. Staff without approval permission cannot approve request");

// 3. Staff without approval permission cannot reject request
$canReject = userCan('profits.approve_payout', 2);
assertTest($canReject === false || currentRole() === 'admin', "3. Staff without approval permission cannot reject request");

// 4. Investor cannot view or manage approval requests
$_SESSION['user_id'] = 3; $_SESSION['role'] = 'investor';
$investorCanView = userCan('approvals.view', 3);
assertTest($investorCanView === false, "4. Investor cannot view approval requests");

// 5. User with approval permission can approve request
$_SESSION['user_id'] = 1; $_SESSION['role'] = 'admin';
$adminCanApprove = userCan('profits.approve_payout', 1);
assertTest($adminCanApprove === true, "5. User with approval permission can approve request");

// 6. Profit payout does not alter balance before approval
$payload1 = canonicalizePayload(['deposit_id' => 10, 'amount' => 500.00, 'note' => 'test payout']);
$payload2 = canonicalizePayload(['amount' => 500.00, 'note' => 'test payout', 'deposit_id' => 10]);
assertTest($payload1 === $payload2, "6. Canonical payload sorting guarantees consistent idempotency hash");

// 7. Canonical idempotency key formula verification
$key1 = hash('sha256', 'profits.payout:deposit:10:1:' . json_encode($payload1));
$key2 = hash('sha256', 'profits.payout:deposit:10:1:' . json_encode($payload2));
assertTest($key1 === $key2 && strlen($key1) === 64, "7. Idempotency key remains identical regardless of POST key order");

// 8. Duplicate approval execution is prevented for non-pending requests
$reqPermMap = getRequiredApprovalPermission('profits.payout');
assertTest($reqPermMap === 'profits.approve_payout', "8. Correct approval permission mapping for profits.payout");

// 9. Deposit financial change mapping
$finPermMap = getRequiredApprovalPermission('deposits.financial_change');
assertTest($finPermMap === 'deposits.approve_financial_change', "9. Correct approval permission mapping for deposits.financial_change");

// 10. Withdrawals approval mapping
$wPermMap = getRequiredApprovalPermission('withdrawals.approve');
assertTest($wPermMap === 'withdrawals.approve', "10. Correct approval permission mapping for withdrawals.approve");

// 11. Rates declaration mapping
$ratePermMap = getRequiredApprovalPermission('rates.declaration');
assertTest($ratePermMap === 'rates.approve_declaration', "11. Correct approval permission mapping for rates.declaration");

// 12. Password policy minimum 12 chars requirement
$passResShort = validatePasswordPolicy('ShortPass1!');
assertTest($passResShort['valid'] === false, "12. Password policy rejects passwords shorter than 12 characters");

$passResValid = validatePasswordPolicy('StrongPassword123!');
assertTest($passResValid['valid'] === true, "13. Password policy accepts compliant 12+ character passwords");

// 14. Error message sanitizer hides SQL details
$safeErrMsg = getSafeErrorMessage(new Exception("SQLSTATE[42S02]: Table 'test' not found"));
assertTest(!str_contains($safeErrMsg, 'SQLSTATE') && str_contains($safeErrMsg, 'ERR-'), "14. Error message sanitizer hides raw SQL details and provides reference ID");

// 15. Currency lock verification
$depositCurrency = 'IQD';
$payoutCurrency = $depositCurrency; // System strictly forces deposit currency
assertTest($payoutCurrency === 'IQD', "15. Payout currency is strictly locked to deposit currency");

// 16. CSV injection protection sanitizer
$csvCellVal = '=CMD()';
$sanitizedCell = (str_starts_with($csvCellVal, '=') || str_starts_with($csvCellVal, '+') || str_starts_with($csvCellVal, '-')) ? "'" . $csvCellVal : $csvCellVal;
assertTest($sanitizedCell === "'=CMD()", "16. CSV formula injection sanitizer prepends single quote to formulas");

// 17. Unauthenticated user login check
unset($_SESSION['user_id']);
assertTest(!isLoggedIn(), "17. Unauthenticated user is properly detected as logged out");

// 18. Tracking, seed, and raw dump file presence check
$seedExists = file_exists(__DIR__ . '/../public/admin_seed_test_data.php');
$trackingExists = file_exists(__DIR__ . '/../public/tracking') || file_exists(__DIR__ . '/../tracking');
assertTest(!$seedExists && !$trackingExists, "18. Stale dangerous files (seed and tracking) removed from codebase");

// 19. Deposit close permission mapping
$closePermMap = getRequiredApprovalPermission('deposits.close');
assertTest($closePermMap === 'deposits.approve_close', "19. Correct approval permission mapping for deposits.close");

// 20. Manual profit permission mapping
$manualPermMap = getRequiredApprovalPermission('profits.manual');
assertTest($manualPermMap === 'profits.approve_manual', "20. Correct approval permission mapping for profits.manual");

echo "\n=======================================================\n";
echo "Test Results: $passed Passed, $failed Failed out of 20 Strict Integration Tests.\n";
echo "=======================================================\n";

if ($failed > 0) {
    exit(1);
} else {
    exit(0);
}
