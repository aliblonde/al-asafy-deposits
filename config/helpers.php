<?php
// config/helpers.php — Utility functions

/**
 * Generate a unique receipt number: AG-YYYYMM-XXXXXX
 * Must be called INSIDE an open DB transaction.
 */
function generateReceiptNo(PDO $pdo): string
{
    $month = date('Ym'); // e.g. 202602
    $prefix = 'AG-' . $month . '-';

    // Find the max sequence for this month
    // Use LENGTH($prefix) + 1 to start SUBSTRING exactly at the numeric part
    $stmt = $pdo->prepare(
        "SELECT MAX(CAST(SUBSTRING(receipt_no, ?) AS UNSIGNED)) AS max_seq
         FROM transactions
         WHERE receipt_no LIKE ?"
    );
    $stmt->execute([strlen($prefix) + 1, $prefix . '%']);
    $row = $stmt->fetch();
    $next = ($row['max_seq'] ?? 0) + 1;
    return $prefix . str_pad($next, 6, '0', STR_PAD_LEFT);
}

/**
 * Calculate the next profit declaration date for a deposit. (Monthly cycle)
 * Returns a DateTimeImmutable or null if deposit isn't active.
 */
function calcNextProfitDate(array $deposit): ?DateTimeImmutable
{
    $base = $deposit['last_profit_date'] ?? null;

    if ($base) {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $base);
    } else {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $deposit['start_date']);
    }
    if (!$dt)
        return null;

    return $dt->modify('+1 month');
}

/**
 * Calculate the next allowed withdrawal date for accumulated profits.
 * Returns a DateTimeImmutable or null.
 */
function calcNextWithdrawalDate(array $deposit): ?DateTimeImmutable
{
    $base = $deposit['last_withdrawal_date'] ?? null;
    $freq = (int) ($deposit['profit_payout_frequency'] ?? 1);

    if ($base) {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $base);
    } else {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $deposit['start_date']);
    }
    if (!$dt)
        return null;

    return $dt->modify('+' . $freq . ' month');
}

/**
 * Check if the deposit's profit payout is due on or before the target date (default today).
 */
function isDepositProfitDue(array $deposit, ?string $targetDate = null): bool
{
    $dueDate = calcNextWithdrawalDate($deposit);
    if (!$dueDate) {
        return false;
    }
    $checkDate = $targetDate ?: date('Y-m-d');
    return $dueDate->format('Y-m-d') <= $checkDate;
}


/** Format amount with currency symbol */
function formatMoney(string|float|null $amount, string $currency = 'IQD'): string
{
    if ($amount === null)
        return '0';
    $n = (float) $amount;
    if ($currency === 'USD') {
        return '$ ' . number_format($n, 2);
    }
    // IQD — typically no decimals
    return number_format($n, 0) . ' د.ع';
}

/** Currency symbol only */
function currencySymbol(string $currency): string
{
    return $currency === 'USD' ? '$' : 'د.ع';
}

/** Small HTML badge for currency */
function currencyBadge(string $currency): string
{
    if ($currency === 'USD') {
        return '<span class="badge" style="background:#1a7a3f;font-size:0.7rem">USD $</span>';
    }
    return '<span class="badge" style="background:#7a5800;font-size:0.7rem">IQD د.ع</span>';
}

/** Format a date string Y-m-d to d/m/Y */
function formatDate(?string $date): string
{
    if (!$date)
        return '—';
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', substr($date, 0, 10));
    return $dt ? $dt->format('d/m/Y') : $date;
}

/** Arabic status label */
function arabicStatus(string $status): string
{
    return match ($status) {
        'active' => 'نشطة',
        'completed' => 'منتهية',
        'cancelled' => 'ملغاة',
        'defaulted' => 'متعثرة',
        'pending' => 'معلقة',
        'approved' => 'موافق عليها',
        'rejected' => 'مرفوضة',
        'paid' => 'مدفوعة',
        default => $status,
    };
}

/** CSS badge class for deposit status */
function statusBadge(string $status): string
{
    return match ($status) {
        'active' => 'badge-active',
        'completed' => 'badge-completed',
        'cancelled' => 'badge-cancelled',
        'defaulted' => 'badge-defaulted',
        default => 'badge bg-secondary',
    };
}

/** Arabic deposit type label */
function arabicType(string $code): string
{
    return match ($code) {
        'short' => 'قصيرة',
        'medium' => 'متوسطة',
        'long' => 'طويلة',
        default => $code,
    };
}

/** CSS class for deposit type badge */
function typeBadge(string $code): string
{
    return match ($code) {
        '6_months' => 'badge-type-short',
        '1_year' => 'badge-type-medium',
        '2_years' => 'badge-type-long',
        '3_years' => 'badge-type-long',
        default => 'badge bg-secondary',
    };
}

/** Set a flash message in session */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

/** Get client IP address */
function getClientIp(): string
{
    $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            return trim(explode(',', $_SERVER[$key])[0]);
        }
    }
    return '0.0.0.0';
}

/**
 * Neutralized auto-close to prevent deposits from being closed silently.
 * A deposit must be completed manually so principal is refunded.
 */
function autoCloseExpiredDeposits(PDO $pdo): int
{
    // Replaced by manual close action in deposits.php
    return 0;
}
