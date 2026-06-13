<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=fishery_db;charset=utf8mb4', 'root', '');
$cols = $pdo->query("SHOW COLUMNS FROM product")->fetchAll(PDO::FETCH_ASSOC);
echo "=== product columns ===\n";
foreach ($cols as $c) echo $c['Field'] . " : " . $c['Type'] . (str_starts_with($c['Field'], 'tier_')||$c['Field']==='stock' ? "  <---" : "") . "\n";
