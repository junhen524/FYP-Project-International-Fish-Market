<?php
require_once __DIR__ . '/../helpers/functions.php';
$vessels = dbGetAll("SELECT v.name, v.status, vt.latitude as lat, vt.longitude as lng, vt.speed_kn as speed, vt.catch_kg_estimate as catch_weight_today, v.id FROM vessels v LEFT JOIN (SELECT a.* FROM vessel_track a INNER JOIN (SELECT vessel_id, MAX(id) AS max_id FROM vessel_track GROUP BY vessel_id) b ON a.id = b.max_id) vt ON vt.vessel_id = v.id WHERE vt.id IS NOT NULL");
$result = [];
foreach ($vessels as $v) {
    $result[] = ['name' => $v['name'], 'status' => $v['status'], 'lat' => (float)$v['lat'], 'lng' => (float)$v['lng'], 'speed' => (float)$v['speed'], 'catch_today' => (float)$v['catch_weight_today'], 'id' => $v['id']];
}
header('Content-Type: application/json');
echo json_encode(['vessels' => $result]);
