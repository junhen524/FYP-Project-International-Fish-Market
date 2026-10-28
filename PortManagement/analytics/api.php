<?php
/**
 * Fishery System - Data API for Power BI & Analytics
 * 
 * Endpoints:
 *   /dashboard/analytics/api/?format=json   (default, for Power BI)
 *   /dashboard/analytics/api/?format=csv    (for Excel download)
 *   /dashboard/analytics/api/?dataset=...   (specific dataset)
 * 
 * Datasets: stock, sales, fishing, drivers, vessels, all
 */

require_once __DIR__ . '/../helpers/functions.php';

$format = $_GET['format'] ?? 'json';
$dataset = $_GET['dataset'] ?? 'all';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$data = [];

try {
    // ── 1. STOCK / PRODUCT ──
    if ($dataset === 'all' || $dataset === 'stock') {
        $products = dbGetAll("
            SELECT p.id, p.name, p.category, p.domestic_price, p.export_price, 
                   p.origin, p.freshness, p.is_active,
                   p.created_at
            FROM product p
            ORDER BY p.id
        ");
        
        // Get tier stock for each product
        $tierData = dbGetAll("
            SELECT p.id as product_id, '3kg' as label, 3.0 as weight_kg, p.tier_3kg_price as price, p.tier_3kg_stock as stock_quantity, 1 as sort_order FROM product p WHERE p.tier_3kg_price IS NOT NULL AND p.is_active = 1
            UNION ALL
            SELECT p.id, '6kg', 6.0, p.tier_6kg_price, p.tier_6kg_stock, 2 FROM product p WHERE p.tier_6kg_price IS NOT NULL AND p.is_active = 1
            UNION ALL
            SELECT p.id, '10kg', 10.0, p.tier_10kg_price, p.tier_10kg_stock, 3 FROM product p WHERE p.tier_10kg_price IS NOT NULL AND p.is_active = 1
            ORDER BY product_id, sort_order
        ");
        
        $tiersByProduct = [];
        foreach ($tierData as $t) {
            $pid = $t['product_id'];
            if (!isset($tiersByProduct[$pid])) $tiersByProduct[$pid] = [];
            $tiersByProduct[$pid][] = $t;
        }
        
        $stockData = [];
        foreach ($products as $p) {
            $pid = $p['id'];
            $tiers = $tiersByProduct[$pid] ?? [];
            $totalUnits = 0;
            foreach ($tiers as $t) $totalUnits += (int)$t['stock_quantity'];
            
            $stockData[] = [
                'product_id' => $pid,
                'product_name' => $p['name'],
                'category' => $p['category'],
                'domestic_price_rm' => (float)$p['domestic_price'],
                'export_price_usd' => (float)$p['export_price'],
                'origin' => $p['origin'],
                'freshness' => $p['freshness'],
                'total_unit_stock' => $totalUnits,
                'is_active' => $p['is_active'] ? 'Yes' : 'No',
                'created_at' => $p['created_at'],
            ];
        }
        $data['stock'] = $stockData;
        
        // Tier breakdown
        $tierFlat = [];
        foreach ($tierData as $t) {
            $tierFlat[] = [
                'product_id' => $t['product_id'],
                'tier_label' => $t['label'],
                'weight_kg' => (float)$t['weight_kg'],
                'price_rm' => (float)$t['price'],
                'stock_units' => (int)$t['stock_quantity'],
            ];
        }
        $data['stock_tiers'] = $tierFlat;
    }

    // ── 2. SALES / MARKET ORDERS ──
    if ($dataset === 'all' || $dataset === 'sales') {
        $orders = dbGetAll("
            SELECT mo.id, mo.order_number, mo.user_id, mu.name as customer_name,
                   mu.email as customer_email, mu.phone as customer_phone,
                   mo.total_amount, mo.status, mo.payment_method,
                   mo.shipping_address, mo.port_id,
                   mo.created_at, mo.paid_at, mo.delivered_at
            FROM market_order mo
            LEFT JOIN market_user mu ON mu.id = mo.user_id
            ORDER BY mo.created_at DESC
            LIMIT 500
        ");
        
        $salesData = [];
        foreach ($orders as $o) {
            $salesData[] = [
                'order_id' => $o['id'],
                'order_number' => $o['order_number'],
                'customer_name' => $o['customer_name'],
                'customer_email' => $o['customer_email'],
                'total_amount_rm' => (float)$o['total_amount'],
                'status' => $o['status'],
                'payment_method' => $o['payment_method'],
                'shipping_address' => $o['shipping_address'],
                'created_at' => $o['created_at'],
                'paid_at' => $o['paid_at'],
                'delivered_at' => $o['delivered_at'],
            ];
        }
        $data['sales'] = $salesData;
        
        // Wallet transactions
        $walletTxns = dbGetAll("
            SELECT wtx.*, mu.name as user_name
            FROM market_wallet_txn wtx
            LEFT JOIN market_user mu ON mu.id = wtx.user_id
            ORDER BY wtx.created_at DESC
            LIMIT 200
        ");
        $data['wallet_transactions'] = $walletTxns;
    }

    // ── 3. FISHING / CATCHES ──
    if ($dataset === 'all' || $dataset === 'fishing') {
        $catches = dbGetAll("
            SELECT ft.vessel_id, v.name as vessel_name,
                   tc.estimated_weight_kg as weight_kg, tc.quality_grade, tc.species,
                   tc.catch_location, tc.status, tc.created_at
            FROM trip_catches tc
            JOIN fishing_trips ft ON ft.id = tc.trip_id
            LEFT JOIN vessels v ON v.id = ft.vessel_id
            ORDER BY tc.created_at DESC
            LIMIT 500
        ");
        
        $catchData = [];
        foreach ($catches as $c) {
            $catchData[] = [
                'catch_id' => $c['id'],
                'vessel_id' => $c['vessel_id'],
                'vessel_name' => $c['vessel_name'],
                'catch_date' => $c['catch_date'],
                'catch_location' => $c['catch_location'],
                'species' => $c['species'],
                'weight_kg' => (float)$c['weight_kg'],
                'quality_grade' => $c['quality_grade'],
                'temperature_c' => (float)$c['temperature_c'],
                'status' => $c['status'],
            ];
        }
        $data['catches'] = $catchData;
        
        // Fishing trips
        $trips = dbGetAll("
            SELECT ft.id, ft.vessel_id, v.name as vessel_name,
                   ft.zone_id, fz.name as zone_name,
                   ft.status, ft.started_at, ft.fishing_start,
                   ft.fishing_end, ft.completed_at,
                   ft.created_at
            FROM fishing_trips ft
            LEFT JOIN vessels v ON v.id = ft.vessel_id
            LEFT JOIN fishery_zone fz ON fz.id = ft.zone_id
            ORDER BY ft.created_at DESC
            LIMIT 200
        ");
        $data['fishing_trips'] = $trips;
    }

    // ── 4. DRIVERS ──
    if ($dataset === 'all' || $dataset === 'drivers') {
        $drivers = dbGetAll("
            SELECT d.id, d.name, d.phone, d.identification_no,
                   p.name as port_name, d.license_no, d.vehicle_no,
                   d.is_active, d.created_at
            FROM drivers d
            LEFT JOIN ports p ON p.id = d.port_id
            ORDER BY d.name
        ");
        $data['drivers'] = $drivers;
        
        // Export drivers
        $expDrivers = dbGetAll("
            SELECT ed.id, ed.name, ed.phone, ed.identification_no,
                   p.name as port_name, ed.license_no, ed.vehicle_no,
                   ed.is_active, ed.created_at
            FROM export_driver ed
            LEFT JOIN ports p ON p.id = ed.port_id
            ORDER BY ed.name
        ");
        $data['export_drivers'] = $expDrivers;
    }

    // ── 5. VESSELS ──
    if ($dataset === 'all' || $dataset === 'vessels') {
        $vessels = dbGetAll("
            SELECT v.id, v.name, v.status, v.capacity_tonnes,
                   v.port_id, p.name as port_name,
                   v.is_active, v.last_docked_at, v.created_at
            FROM vessels v
            LEFT JOIN ports p ON p.id = v.port_id
            ORDER BY v.id
        ");
        $data['vessels'] = $vessels;
        
        // Vessel tracking
        $tracks = dbGetAll("
            SELECT vt.id, vt.vessel_id, v.name as vessel_name,
                   vt.latitude, vt.longitude, vt.speed_kn,
                   vt.heading_deg, vt.depth_m, vt.fish_density,
                   vt.is_fishing, vt.catch_kg_estimate,
                   vt.zone_id, vt.recorded_at
            FROM vessel_track vt
            JOIN vessels v ON v.id = vt.vessel_id
            ORDER BY vt.recorded_at DESC
            LIMIT 500
        ");
        $data['vessel_tracks'] = $tracks;
    }

} catch (Exception $e) {
    $data['error'] = $e->getMessage();
}

// ── Output ──
if ($format === 'csv') {
    // CSV output for Excel/Power BI
    $dataset = $_GET['dataset'] ?? 'stock';
    $rows = $data[$dataset] ?? [];
    if (empty($rows)) { $rows = [['error' => 'No data for dataset: ' . $dataset]]; }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="fishery_' . $dataset . '_' . date('Ymd') . '.csv"');
    
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // BOM for Excel UTF-8
    fputcsv($out, array_keys((array)$rows[0]));
    foreach ($rows as $row) {
        fputcsv($out, array_values((array)$row));
    }
    fclose($out);
    exit;
}

// JSON output (default, for Power BI Web connector)
header('Content-Type: application/json; charset=utf-8');
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
