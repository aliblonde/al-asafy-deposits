<?php
$file = __DIR__ . '/config/approval.php';
$content = file_get_contents($file);

// Clean up newlines to just \n for reliable matching
$content = str_replace("\r\n", "\n", $content);

$search = <<<EOD
                if (\$deposit['end_date'] > date('Y-m-d')) {
                    throw new BusinessRuleException('لا يمكن إنهاء الوديعة قبل تاريخ استحقاقها.');
                }
                if ((int)\$deposit['principal_refunded'] === 1) {
                    throw new BusinessRuleException('تم إرجاع رأس المال مسبقاً.');
                }
                if ((float)\$deposit['accumulated_profit'] > 0) {
                    throw new BusinessRuleException('لا يمكن إنهاء الوديعة بسبب وجود أرباح متراكمة.');
                }
EOD;

$replace = <<<EOD
                if (\$deposit['end_date'] > date('Y-m-d') && empty(\$isBreak)) {
                    throw new BusinessRuleException('لا يمكن إنهاء الوديعة قبل تاريخ استحقاقها.');
                }
                if ((int)\$deposit['principal_refunded'] === 1) {
                    throw new BusinessRuleException('تم إرجاع رأس المال مسبقاً.');
                }
                if ((float)\$deposit['accumulated_profit'] > 0 && empty(\$forfeitProfit)) {
                    throw new BusinessRuleException('لا يمكن إنهاء الوديعة بسبب وجود أرباح متراكمة.');
                }

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

$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
echo "PHP replacement done.";
