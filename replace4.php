<?php
$file = __DIR__ . '/config/approval.php';
$lines = file($file);

foreach ($lines as $k => $line) {
    if (strpos($line, 'generateReceiptNo($pdo)') !== false && $k > 280 && $k < 320) {
        $insert = <<<EOD
                if (!empty(\$forfeitProfit) && (float)\$deposit['accumulated_profit'] > 0) {
                    \$adjReceipt = generateReceiptNo(\$pdo);
                    \$forfeitedAmount = (float)\$deposit['accumulated_profit'];
                    
                    \$adjTx = \$pdo->prepare("
                        INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, direction, amount, currency, approval_request_id, date, note)
                        VALUES (?, ?, ?, 'deposit_adjustment', 'debit', ?, ?, ?, NOW(), ?)
                    ");
                    \$adjTx->execute([\$adjReceipt, \$deposit['investor_id'], \$depositId, \$forfeitedAmount, \$deposit['currency'], \$requestId, 'مصادرة الأرباح التراكمية (كسر وديعة)']);
                    
                    \$pdo->prepare("UPDATE deposits SET accumulated_profit = 0 WHERE id = ?")->execute([\$depositId]);
                }


EOD;
        if (strpos(implode("", $lines), 'deposit_adjustment') === false) {
            $lines[$k] = $insert . $lines[$k];
        }
    }
}
file_put_contents($file, implode("", $lines));
echo "Replacement by line done.";
