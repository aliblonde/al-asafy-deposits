<?php
require_once 'C:\xampp\htdocs\al-asafy-deposits\config\db.php';
$pdo = getPDO();
$deposits = $pdo->query('SELECT * FROM deposits')->fetchAll(PDO::FETCH_ASSOC);
$cycles = $pdo->query('SELECT * FROM profit_cycles')->fetchAll(PDO::FETCH_ASSOC);
$rates = $pdo->query('SELECT * FROM monthly_rates')->fetchAll(PDO::FETCH_ASSOC);
file_put_contents('C:\xampp\htdocs\al-asafy-deposits\debug_output.json', json_encode(array('deposits' => $deposits, 'cycles' => $cycles, 'rates' => $rates), JSON_PRETTY_PRINT));
echo "Done.";
