<?php
$pdo = new PDO('mysql:host=127.0.0.1', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$dbs = $pdo->query("SHOW DATABASES LIKE '%fish%'")->fetchAll(PDO::FETCH_COLUMN);
echo "Databases: " . implode(', ', $dbs) . "\n\n";
foreach ($dbs as $db) {
    $pdo->exec("USE `$db`");
    $tables = $pdo->query("SHOW TABLES LIKE '%order%'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        echo "=== $db.$t ===\n";
        $cols = $pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) echo "  {$c['Field']} ({$c['Type']})\n";
        echo "\n";
    }
}
