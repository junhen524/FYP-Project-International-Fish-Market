<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=fishery_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $tables = ['ports', 'warehouses', 'inventory', 'product'];
    foreach ($tables as $t) {
        echo "=== $t ===\n";
        $st = $pdo->query("SHOW TABLES LIKE '$t'");
        if ($st->fetch()) {
            $st2 = $pdo->query("DESCRIBE $t");
            while ($r = $st2->fetch(PDO::FETCH_ASSOC)) {
                echo "  {$r['Field']} | {$r['Type']}\n";
            }
        } else {
            echo "  TABLE NOT EXISTS\n";
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
