<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=fishery_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Sample products data ===\n";
    $st = $pdo->query("SELECT id, name, slug, image_url, quantity, domestic_price, is_active FROM product WHERE id IN (1, 36, 44, 50)");
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode($row, JSON_PRETTY_PRINT) . "\n\n";
    }
    
    echo "\n=== Products with stock ===\n";
    $st = $pdo->query("SELECT COUNT(*) FROM product");
    echo "Total products: " . $st->fetchColumn() . "\n";
    
    echo "\n=== Products with stock > 0 ===\n";
    $st = $pdo->query("SELECT COUNT(*) FROM product WHERE COALESCE(tier_3kg_stock,0)+COALESCE(tier_6kg_stock,0)+COALESCE(tier_10kg_stock,0) > 0");
    echo "Count: " . $st->fetchColumn() . "\n";

    $st = $pdo->query("SELECT COUNT(*) FROM product WHERE COALESCE(tier_3kg_stock,0)+COALESCE(tier_6kg_stock,0)+COALESCE(tier_10kg_stock,0) = 0");
    echo "Count (zero stock): " . $st->fetchColumn() . "\n";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
