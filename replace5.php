<?php
$file = __DIR__ . '/config/approval.php';
$content = file_get_contents($file);

$search = "                \$receiptNo = generateReceiptNo(\$pdo);\n\n                \$upDep = \$pdo->prepare(\"UPDATE deposits SET status = 'completed'";

$replace = "                if (!empty(\$forfeitProfit) && (float)\$deposit['accumulated_profit'] > 0) {\n                    \$adjReceipt = generateReceiptNo(\$pdo);\n                    \$forfeitedAmount = (float)\$deposit['accumulated_profit'];\n                    \n                    \$adjTx = \$pdo->prepare(\"\n                        INSERT INTO transactions (receipt_no, investor_id, deposit_id, type, direction, amount, currency, approval_request_id, date, note)\n                        VALUES (?, ?, ?, 'deposit_adjustment', 'debit', ?, ?, ?, NOW(), ?)\n                    \");\n                    \$adjTx->execute([\$adjReceipt, \$deposit['investor_id'], \$depositId, \$forfeitedAmount, \$deposit['currency'], \$requestId, 'مصادرة الأرباح التراكمية (كسر وديعة)']);\n                    \n                    \$pdo->prepare(\"UPDATE deposits SET accumulated_profit = 0 WHERE id = ?\")->execute([\$depositId]);\n                }\n\n                \$receiptNo = generateReceiptNo(\$pdo);\n\n                \$upDep = \$pdo->prepare(\"UPDATE deposits SET status = 'completed'";

$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
echo "PHP full string replace done.";
