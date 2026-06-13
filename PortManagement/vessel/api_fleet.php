<?php
require_once __DIR__ . '/../helpers/functions.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache');

$cycleLen = 600;
$t = time() % $cycleLen;
$progress = $t < ($cycleLen / 2) ? $t / ($cycleLen / 2) : 2 - ($t / ($cycleLen / 2));

try {
    require_once __DIR__ . '/../services/VesselSimulationService.php';
    VesselSimulationService::tickSimulation();
    $vessels = VesselSimulationService::getVesselMapData();
} catch (Exception $e) { $vessels = []; }

function lerp($a, $b, $p) { return $a + ($b - $a) * $p; }

$drivers = [];
try {
    $rows = dbGetAll("SELECT d.id, d.name, d.vehicle_no, p.name as port_name 
                      FROM drivers d LEFT JOIN ports p ON p.id = d.port_id WHERE d.is_active = TRUE");
    $portCoords = [
        'penang' => [5.4164, 100.3327],
        'langkawi' => [6.3500, 99.8000],
        'johor' => [1.4658, 103.7578],
        'kuala terengganu' => [5.3333, 103.1500],
        'kuantan' => [3.8167, 103.3333],
        'kota kinabalu' => [5.9804, 116.0735],
        'malacca' => [2.1896, 102.2501],
        'port klang' => [3.0000, 101.4000],
    ];
    foreach ($rows as $r) {
        $pn = strtolower($r['port_name'] ?? '');
        $coords = [3.0, 101.5]; // default KL
        foreach ($portCoords as $key => $c) {
            if (strpos($pn, $key) !== false) { $coords = $c; break; }
        }
        $drivers[] = [
            'id' => $r['id'], 'name' => $r['name'], 'vehicle' => $r['vehicle_no'] ?? '',
            'lat' => $coords[0] + (rand(-50, 50) / 1000),
            'lng' => $coords[1] + (rand(-50, 50) / 1000),
            'port_name' => $r['port_name'] ?? '',
        ];
    }
} catch (Exception $e) {}

$shipments = [];
try {
    $rows = dbGetAll("SELECT s.id, s.order_number, s.destination_country, s.destination_port, s.status
                      FROM export_shipment s
                      WHERE s.status NOT IN ('delivered','cancelled')
                      LIMIT 10");
    $departureCoords = [3.0, 101.5];
    $seaDests = [
        'japan' => [26.5, 123.0],
        'china' => [12.0, 112.0],
        'hong kong' => [10.5, 115.0],
        'taiwan' => [18.0, 118.0],
        'korea' => [28.0, 125.0],
        'singapore' => [1.5, 105.0],
        'indonesia' => [-3.0, 108.0],
        'vietnam' => [8.0, 110.0],
        'thailand' => [6.0, 104.0],
        'usa' => [8.0, 140.0],
        'canada' => [12.0, 150.0],
        'australia' => [-12.0, 118.0],
        'new zealand' => [-25.0, 155.0],
        'india' => [6.0, 90.0],
        'france' => [5.0, -15.0],
        'germany' => [8.0, -10.0],
        'uk' => [10.0, -20.0],
    ];
    foreach ($rows as $r) {
        $country = strtolower($r['destination_country'] ?? '');
        $dest = $seaDests[$country] ?? [5.0, 110.0];
        $shipments[] = [
            'id' => $r['id'], 'order' => $r['order_number'], 'country' => $r['destination_country'],
            'port' => $r['destination_port'], 'status' => $r['status'],
            'lat' => lerp($departureCoords[0], $dest[0], $progress),
            'lng' => lerp($departureCoords[1], $dest[1], $progress),
            'warehouse' => '',
        ];
    }
} catch (Exception $e) {}

$zones = [];
try {
    $zones = VesselSimulationService::getFishingZones();
} catch (Exception $e) {}

$catches = [];
try {
    $catches = dbGetAll("SELECT tc.species, tc.estimated_weight_kg as weight_kg, tc.quality_grade, ft.vessel_id, tc.catch_location, tc.created_at FROM trip_catches tc JOIN fishing_trips ft ON ft.id = tc.trip_id ORDER BY tc.created_at DESC LIMIT 100");
} catch (Exception $e) {}

echo json_encode([
    'vessels' => $vessels,
    'drivers' => $drivers,
    'shipments' => $shipments,
    'zones' => $zones,
    'catches' => $catches,
    'timestamp' => date('Y-m-d H:i:s'),
]);
