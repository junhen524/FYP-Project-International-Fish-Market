<?php
/**
 * API: return catch data for a trip as JSON (for live-update AJAX)
 * Populated from trip_catches (merged from catch_record)
 */
require_once __DIR__ . '/../../helpers/functions.php';

header('Content-Type: application/json');

$tripId = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : 0;
if ($tripId <= 0) {
    echo json_encode([]);
    exit;
}

// Single source: trip_catches (merged with quality_grade from same table)
$tc = dbGetAll(
    "SELECT tc.id, tc.trip_id, tc.estimated_weight_kg, tc.status, tc.reject_reason, tc.quality_grade,
            p.name as product_name, p.category as product_category
     FROM trip_catches tc
     JOIN product p ON p.id = tc.product_id
     WHERE tc.trip_id = ?
     ORDER BY tc.id",
    [$tripId]
);

if (!empty($tc)) {
    echo json_encode($tc);
    exit;
}

// No data
echo json_encode([]);
