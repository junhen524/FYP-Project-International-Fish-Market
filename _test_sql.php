<?php
require_once __DIR__ . '/PortManagement/helpers/functions.php';
$p = db();

try {
    // Test the SQL query directly
    $port = dbGetRow("SELECT id, name, code FROM ports WHERE id = ? AND is_active = 1", [1]);
    echo "Port query: " . json_encode($port) . "\n";
    
    $sql = "SELECT p.id, p.name, p.category, p.tier_3kg_price, p.tier_6kg_price, p.tier_10kg_price,
            p.tier_3kg_stock, p.tier_6kg_stock, p.tier_10kg_stock, p.image_url
            FROM product p
            WHERE p.is_active = TRUE
            ORDER BY p.category, p.name LIMIT 3";
    $products = dbGetAll($sql);
    echo "Products count: " . count($products) . "\n";
    if (count($products) > 0) {
        echo "First product: " . json_encode($products[0]) . "\n";
    }
    
    $categories = dbGetAll("SELECT DISTINCT category as cat FROM product WHERE is_active = TRUE ORDER BY cat");
    echo "Categories: " . json_encode(array_column($categories, 'cat')) . "\n";
    
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
