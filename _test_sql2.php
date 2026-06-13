<?php
try {
    $p = new PDO('mysql:host=127.0.0.1;dbname=fishery_db;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    
    $port = $p->query("SELECT id, name, code FROM ports WHERE id = 1 AND is_active = 1")->fetch();
    echo "Port #1: " . json_encode($port) . "\n\n";
    
    $products = $p->query("SELECT id, name, category, tier_3kg_stock, tier_6kg_stock, tier_10kg_stock FROM product WHERE is_active = TRUE LIMIT 3")->fetchAll();
    echo "Products:\n";
    foreach ($products as $r) echo "  #{$r['id']} {$r['name']} 3kg={$r['tier_3kg_stock']} 6kg={$r['tier_6kg_stock']} 10kg={$r['tier_10kg_stock']}\n\n";
    
    $cats = $p->query("SELECT DISTINCT category FROM product WHERE is_active = TRUE ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
    echo "Categories: " . implode(', ', $cats) . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
