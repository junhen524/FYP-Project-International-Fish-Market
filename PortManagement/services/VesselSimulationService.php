<?php

class VesselSimulationService {

    private static $ports = [];
    private static $portsLoaded = false;

    private static function loadPorts() {
        if (self::$portsLoaded) return;
        self::$portsLoaded = true;
        try {
            require_once __DIR__ . '/../helpers/functions.php';
            $rows = dbGetAll("SELECT DISTINCT pu.port_id AS id, pu.port_name AS name FROM port_user pu WHERE pu.port_id IS NOT NULL ORDER BY pu.port_id");
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
                $pn = strtolower($r['name'] ?? '');
                $coords = [3.0, 101.5]; // default
                foreach ($portCoords as $key => $c) {
                    if (strpos($pn, $key) !== false) { $coords = $c; break; }
                }
                self::$ports[(int)$r['id']] = ['name' => $r['name'], 'lat' => $coords[0], 'lng' => $coords[1]];
            }
        } catch (Exception $e) {
            self::$ports = [];
        }
    }

    private static function getFishProducts() {
        try {
            require_once __DIR__ . '/../helpers/functions.php';
            $rows = dbGetAll("SELECT id, name, category FROM product WHERE is_active = TRUE AND category IN ('fish', 'crustacean', 'shellfish', 'cephalopod', 'mollusc') ORDER BY RAND()");
            if (empty($rows)) { $rows = dbGetAll("SELECT id, name, category FROM product WHERE is_active = TRUE ORDER BY RAND()"); }
            $result = [];
            foreach ($rows as $r) {
                $cat = $r['category'] ?? 'fish';
                $tiers = [];
                if (strpos($r['name'], 'Prawn') !== false || strpos($r['name'], 'Shrimp') !== false || strpos($r['name'], 'Lobster') !== false || strpos($r['name'], 'Crab') !== false) {
                    $tiers = [['Small', 0.3, 1.5, 0.8], ['Medium', 1.5, 3.5, 1.0], ['Large', 3.5, 6.0, 1.3]];
                } elseif (strpos($r['name'], 'Mussel') !== false || strpos($r['name'], 'Clam') !== false || strpos($r['name'], 'Oyster') !== false || strpos($r['name'], 'Scallop') !== false) {
                    $tiers = [['Small', 0.2, 0.8, 0.85], ['Medium', 0.8, 2.0, 1.0], ['Large', 2.0, 4.0, 1.3]];
                } elseif (strpos($r['name'], 'Tuna') !== false || strpos($r['name'], 'Salmon') !== false || strpos($r['name'], 'Cod') !== false) {
                    $tiers = [['Small', 3.0, 10.0, 0.85], ['Medium', 10.0, 20.0, 1.0], ['Large', 20.0, 40.0, 1.2]];
                } else {
                    $tiers = [['Small', 1.0, 4.0, 0.8], ['Medium', 4.0, 8.0, 1.0], ['Large', 8.0, 15.0, 1.25]];
                }
                $result[] = ['pid' => (int)$r['id'], 'name' => $r['name'], 'tiers' => $tiers, 'cat' => $cat];
            }
            return $result;
        } catch (Exception $e) {
            return [['pid' => 1, 'name' => 'Fish', 'tiers' => [['Small', 1, 3, 1.0], ['Large', 3, 8, 1.2]], 'cat' => 'fish']];
        }
    }

    private static $qualityGrades = ['A', 'B', 'C'];

    private static $standardCatchTiers = [
        ['min' => 2.8, 'max' => 3.3, 'tier_id' => '3kg', 'tier_label' => 'TIER_3KG', 'stock_field' => 'tier_3kg_stock'],
        ['min' => 5.8, 'max' => 6.3, 'tier_id' => '6kg', 'tier_label' => 'TIER_6KG', 'stock_field' => 'tier_6kg_stock'],
        ['min' => 9.8, 'max' => 10.3, 'tier_id' => '10kg', 'tier_label' => 'TIER_10KG', 'stock_field' => 'tier_10kg_stock'],
    ];

    private const OUTGOING_SECONDS = 120;
    private const FISHING_SECONDS = 300;
    private const RETURNING_SECONDS = 300;
    private const TARGET_CATCHES_PER_TRIP = 5;

    private static function classifyCatchTier($weightKg) {
        $kg = (float)$weightKg;
        foreach (self::$standardCatchTiers as $tier) {
            if ($kg >= $tier['min'] && $kg <= $tier['max']) {
                return $tier;
            }
        }
        return null;
    }

    private static function generateStandardCatchWeight($tripId) {
        $catchCount = (int)dbGetValue("SELECT COUNT(*) FROM trip_catches WHERE trip_id = ?", [$tripId]);
        $acceptedCount = (int)dbGetValue("SELECT COUNT(*) FROM trip_catches WHERE trip_id = ? AND status = 'accepted'", [$tripId]);

        // ~33% chance to generate a reject-weight (non-tier) for any catch
        if (mt_rand(1, 100) <= 33) {
            $rejectWeights = [[1.8, 2.7], [3.4, 5.7], [6.4, 9.7]];
            $range = $rejectWeights[array_rand($rejectWeights)];
            return round(mt_rand((int)($range[0] * 10), (int)($range[1] * 10)) / 10, 1);
        }

        $tier = self::$standardCatchTiers[array_rand(self::$standardCatchTiers)];
        return round(mt_rand((int)($tier['min'] * 10), (int)($tier['max'] * 10)) / 10, 1);
    }

    private static function insertTripCatch($tripId, $productId, $weightKg, $qualityGrade = null, $species = null, $catchLocation = null, $catchRecordId = null) {
        // All catches start as 'landed' (pending) — sorting happens at port after unload
        dbExecute(
            "INSERT INTO trip_catches (trip_id, product_id, pieces_caught, estimated_weight_kg, quality_grade, species, catch_location, status, unloaded)
             VALUES (?, ?, 1, ?, ?, ?, ?, 'landed', 0)",
            [$tripId, $productId, $weightKg, $qualityGrade, $species, $catchLocation]
        );

        return ['landed', null, null];
    }

    private static function catchCountForTrip($tripId) {
        return (int)dbGetValue("SELECT COUNT(*) FROM trip_catches WHERE trip_id = ?", [$tripId]);
    }

    private static function tripElapsedSeconds($tripId) {
        return (int)dbGetValue("SELECT TIMESTAMPDIFF(SECOND, started_at, NOW()) FROM fishing_trips WHERE id = ?", [$tripId]);
    }

    private static function pruneTripToTarget($tripId) {
        $extraTripCatchIds = dbGetAll(
            "SELECT id FROM trip_catches WHERE trip_id = ? ORDER BY id LIMIT 999 OFFSET " . self::TARGET_CATCHES_PER_TRIP,
            [$tripId]
        );
        foreach ($extraTripCatchIds as $row) {
            dbExecute("DELETE FROM trip_catches WHERE id = ? AND unloaded = 0", [(int)$row['id']]);
        }
    }

    private static function syncAcceptedTripCatchesToInventory($vid, $tripId, $now) {
        $acceptedCatches = dbGetAll(
            "SELECT tc.id, tc.product_id, tc.estimated_weight_kg
             FROM trip_catches tc
             WHERE tc.trip_id = ? AND tc.status = 'accepted' AND tc.unloaded = 0",
            [$tripId]
        );
        foreach ($acceptedCatches as $ac) {
            $tier = self::classifyCatchTier((float)$ac['estimated_weight_kg']);
            if (!$tier) continue;
            dbExecute("UPDATE trip_catches SET unloaded = 1, processed_at = NOW() WHERE id = ?", [$ac['id']]);
            self::syncCatchToInventory(
                $vid,
                (int)$ac['product_id'],
                (float)$ac['estimated_weight_kg'],
                null,
                $now,
                $tier['tier_label']
            );
        }
    }

    private static function completeTrip($vid, $tripId, $portId, $now) {
        $port = self::$ports[$portId] ?? ['lat' => 3.0, 'lng' => 101.4];
        dbExecute("UPDATE vessels SET status = 'docked', current_trip_id = NULL, updated_at = NOW() WHERE id = ?", [$vid]);
        dbExecute(
            "UPDATE fishing_trips
             SET status = 'completed',
                 fishing_start = DATE_ADD(started_at, INTERVAL 2 MINUTE),
                 fishing_end = DATE_ADD(started_at, INTERVAL 7 MINUTE),
                 completed_at = DATE_ADD(started_at, INTERVAL 12 MINUTE)
             WHERE id = ?",
            [$tripId]
        );
        dbExecute(
            "INSERT INTO vessel_track (vessel_id, latitude, longitude, speed_kn, heading_deg, depth_m, is_fishing, recorded_at)
             VALUES (?, ?, ?, 0, 0, 0, false, NOW())",
            [$vid, $port['lat'], $port['lng']]
        );
        self::sortLandedCatches($tripId);
        self::syncAcceptedTripCatchesToInventory($vid, $tripId, $now);
    }

    private static function sortLandedCatches($tripId) {
        // At unload time, sort all 'landed' catches -> accepted or rejected
        $landed = dbGetAll(
            "SELECT id, product_id, estimated_weight_kg FROM trip_catches WHERE trip_id = ? AND status = 'landed'",
            [$tripId]
        );
        foreach ($landed as $c) {
            $weightKg = (float)$c['estimated_weight_kg'];
            $tier = self::classifyCatchTier($weightKg);
            if ($tier) {
                // Accepted — weight fits a standard tier
                dbExecute(
                    "UPDATE trip_catches SET status = 'accepted', tier_id = ?, reject_reason = NULL, processed_at = NOW() WHERE id = ?",
                    [$tier['tier_id'], $c['id']]
                );
            } else {
                // Rejected — weight doesn't fit any tier
                dbExecute(
                    "UPDATE trip_catches SET status = 'rejected', reject_reason = ?, processed_at = NOW() WHERE id = ?",
                    ["Below accepted tier range ({$weightKg}kg)", $c['id']]
                );
            }
        }
    }

    private static function enforceTripTimeline($vid, $portId, $now) {
        $trip = dbGetRow(
            "SELECT id, zone_id, status, started_at
             FROM fishing_trips
             WHERE vessel_id = ? AND status IN ('outgoing','fishing','returning')
             ORDER BY started_at DESC LIMIT 1",
            [$vid]
        );
        if (!$trip || empty($trip['started_at'])) return null;

        $tripId = (int)$trip['id'];
        self::pruneTripToTarget($tripId);
        $elapsed = self::tripElapsedSeconds($tripId);
        $fishStartAt = self::OUTGOING_SECONDS;
        $fishEndAt = self::OUTGOING_SECONDS + self::FISHING_SECONDS;
        $completeAt = $fishEndAt + self::RETURNING_SECONDS;

        if ($elapsed >= $completeAt) {
            self::completeTrip($vid, $tripId, $portId, $now);
            return 'completed';
        }

        if ($elapsed >= $fishEndAt) {
            if ($trip['status'] !== 'returning') {
                dbExecute(
                    "UPDATE fishing_trips
                     SET status = 'returning',
                         fishing_start = DATE_ADD(started_at, INTERVAL 2 MINUTE),
                         fishing_end = DATE_ADD(started_at, INTERVAL 7 MINUTE)
                     WHERE id = ?",
                    [$tripId]
                );
                dbExecute("UPDATE vessels SET status = 'returning', updated_at = NOW() WHERE id = ?", [$vid]);
            }
            return 'returning';
        }

        if ($elapsed >= $fishStartAt) {
            if ($trip['status'] !== 'fishing') {
                dbExecute(
                    "UPDATE fishing_trips
                     SET status = 'fishing',
                         fishing_start = DATE_ADD(started_at, INTERVAL 2 MINUTE)
                     WHERE id = ?",
                    [$tripId]
                );
                dbExecute("UPDATE vessels SET status = 'at_sea', updated_at = NOW() WHERE id = ?", [$vid]);
            }
            return 'fishing';
        }

        if ($trip['status'] !== 'outgoing') {
            dbExecute("UPDATE fishing_trips SET status = 'outgoing', fishing_start = NULL WHERE id = ?", [$tripId]);
            dbExecute("UPDATE vessels SET status = 'at_sea', updated_at = NOW() WHERE id = ?", [$vid]);
            dbExecute("DELETE FROM trip_catches WHERE trip_id = ? AND unloaded = 0", [$tripId]);
        }

        return 'outgoing';
    }

    private static function haversine($lat1, $lng1, $lat2, $lng2) {
        $R = 6371.0;
        $dlat = deg2rad($lat2 - $lat1);
        $dlng = deg2rad($lng2 - $lng1);
        $a = sin($dlat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dlng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private static function bearing($lat1, $lng1, $lat2, $lng2) {
        $dlat = deg2rad($lat2 - $lat1);
        $dlng = deg2rad($lng2 - $lng1);
        $x = sin($dlng) * cos(deg2rad($lat2));
        $y = cos(deg2rad($lat1)) * sin(deg2rad($lat2)) - sin(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos($dlng);
        return (int)(rad2deg(atan2($x, $y)) + 360) % 360;
    }

    private static function moveTowards($lat, $lng, $targetLat, $targetLng, $speedKn) {
        $distKm = self::haversine($lat, $lng, $targetLat, $targetLng);
        if ($distKm < 0.5) {
            return [$targetLat, $targetLng, 0];
        }
        $bearing = self::bearing($lat, $lng, $targetLat, $targetLng);
        $moveKm = $speedKn * 0.33 * 1.852; // 20 min per tick (was 10 min)
        if ($moveKm > $distKm) {
            $moveKm = $distKm * 0.9;
        }
        $newLat = $lat + ($moveKm / 6371.0) * cos(deg2rad($bearing));
        $newLng = $lng + ($moveKm / 6371.0) * sin(deg2rad($bearing)) / cos(deg2rad(($lat + $newLat) / 2));
        return [$newLat, $newLng, $speedKn];
    }

    public static function tickSimulation() {
        $now = date('c');

        $vessels = dbGetAll("SELECT id, port_id, name, status, capacity_tonnes, current_trip_id FROM vessels ORDER BY id");
        $zones = dbGetAll("SELECT id, name, center_lat, center_lng, radius_km, fish_density_base, primary_species FROM fishery_zone WHERE is_active = true");

        foreach ($vessels as $v) {
            try {
                self::processVessel($v, $zones, $now);
            } catch (\Exception $e) {
                error_log("Vessel {$v['id']} tick failed: " . $e->getMessage());
            }
        }
    }

    // ── Port ID → sensible fishing zone mapping ──
    private static function pickZoneForPort($portId) {
        $port = self::$ports[$portId] ?? ['lat' => 3.0, 'lng' => 101.4];
        $portLat = $port['lat'];
        $portLng = $port['lng'];

        // Load all zones and pick nearest one that's on the correct side of the peninsula
        $zones = dbGetAll("SELECT id, name, center_lat, center_lng, radius_km, fish_density_base FROM fishery_zone WHERE is_active = true");
        if (empty($zones)) return 1;

        // West coast ports (lng < 101.5) → west sea zones only
        // East coast ports (lng > 103) → east sea zones only
        $isWestCoast = $portLng < 101.5;

        $best = null;
        $bestDist = INF;
        foreach ($zones as $z) {
            $zLng = (float)$z['center_lng'];
            // Skip zones on wrong side of peninsula
            if ($isWestCoast && $zLng > 102) continue;  // west coast → skip east sea zones
            if (!$isWestCoast && $zLng < 102) continue;  // east coast → skip west sea zones

            $dist = self::haversine($portLat, $portLng, (float)$z['center_lat'], $zLng);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $z;
            }
        }
        // Fallback: if no zone on correct side, pick nearest any side
        if (!$best) {
            $best = $zones[0];
            $bestDist = INF;
            foreach ($zones as $z) {
                $d = self::haversine($portLat, $portLng, (float)$z['center_lat'], (float)$z['center_lng']);
                if ($d < $bestDist) { $bestDist = $d; $best = $z; }
            }
        }
        return $best ? (int)$best['id'] : 1;
    }

    // ── Check if vessel is stuck on land (NOT just far from port) ──
    private static function isOnLand($lat, $lng, $portId) {
        // Only flag vessels that are literally on the Malay Peninsula landmass
        // Coordinates that fall on the peninsula: lat 1.5-6.5, lng 101.5-103.5
        // (rough boundary — west coast ports face the Strait of Malacca)
        if ($lat < 1.5 || $lat > 6.5) return false;
        if ($lng < 101.5 || $lng > 103.5) return false;
        // If lng is between 101.5 and 103.5 and lat between 1.5 and 6.5,
        // the vessel is ON the Malay Peninsula landmass — teleport to sea
        return true;
    }

    // ── Safety net: force-create catch if handleAtSea fishing block didn't fire ──
    private static function forceCreateCatch($vid, $tripId, $portId, $zoneId, $allZones, $capacity) {
        if (self::catchCountForTrip($tripId) >= self::TARGET_CATCHES_PER_TRIP) return;

        $zone = dbGetRow("SELECT name, fish_density_base FROM fishery_zone WHERE id = ?", [$zoneId]);
        if (!$zone) return;
        $zname = $zone['name'];
        $zdensity = (float)($zone['fish_density_base'] ?? 0.5);

        $fishProducts = self::getFishProducts();
        $product = $fishProducts[array_rand($fishProducts)];
        $pid = $product['pid'];
        $pname = $product['name'];
        $catchKg = self::generateStandardCatchWeight($tripId);
        $quality = self::$qualityGrades[array_rand(self::$qualityGrades)];

        self::insertTripCatch($tripId, $pid, $catchKg, $quality, $pname, $zname);

    }

    // ── Teleport a stuck vessel directly to a fishing zone ──
    private static function teleportToZone($vid, $portId, $zoneId) {
        $zone = dbGetRow("SELECT id, center_lat, center_lng, radius_km, fish_density_base FROM fishery_zone WHERE id = ?", [$zoneId]);
        if (!$zone) return false;

        $zLat = (float)$zone['center_lat'];
        $zLng = (float)$zone['center_lng'];
        $port = self::$ports[$portId] ?? ['lat' => 3.0, 'lng' => 101.4];

        // Place vessel just inside the zone at a random offset
        $offsetDeg = ((float)$zone['radius_km']) * 0.7 / 111.0;
        $seaLat = $zLat + (mt_rand(-100, 100) / 100) * $offsetDeg;
        $seaLng = $zLng + (mt_rand(-100, 100) / 100) * $offsetDeg;

        dbExecute(
            "INSERT INTO vessel_track (vessel_id, latitude, longitude, speed_kn, heading_deg, depth_m, fish_density, zone_id, is_fishing, recorded_at)
             VALUES (?, ?, ?, 0.5, ?, ?, ?, ?, true, NOW())",
            [$vid, $seaLat, $seaLng, mt_rand(0, 360), mt_rand(15, 60), (float)$zone['fish_density_base'], $zoneId]
        );

        // Mark trip as fishing
        $tripId = (int)dbGetValue("SELECT current_trip_id FROM vessels WHERE id = ?", [$vid]);
        if ($tripId > 0) {
            dbExecute("UPDATE fishing_trips SET status = 'fishing', fishing_start = DATE_ADD(started_at, INTERVAL 2 MINUTE) WHERE id = ?", [$tripId]);
        }

        return true;
    }

    private static function processVessel($v, $zones, $now) {
        self::loadPorts();
        $vid = $v['id'];
        $portId = $v['port_id'];
        $status = $v['status'];
        $capacity = $v['capacity_tonnes'];

        $lastPos = dbGetRow(
            "SELECT latitude, longitude, is_fishing, zone_id FROM vessel_track WHERE vessel_id = ? ORDER BY recorded_at DESC LIMIT 1",
            [$vid]
        );

        if ($lastPos) {
            $lat = (float)$lastPos['latitude'];
            $lng = (float)$lastPos['longitude'];
            $isFishing = $lastPos['is_fishing'];
            $zoneId = $lastPos['zone_id'];
        } else {
            $port = self::$ports[$portId] ?? ['lat' => 3.0, 'lng' => 101.4];
            $lat = $port['lat'];
            $lng = $port['lng'];
            $isFishing = false;
            $zoneId = null;
            dbExecute("UPDATE vessels SET status = 'docked' WHERE id = ?", [$vid]);
            $status = 'docked';
        }

        $timelineStatus = self::enforceTripTimeline($vid, $portId, $now);
        if ($timelineStatus === 'completed') return;
        if ($timelineStatus === 'returning') $status = 'returning';
        if ($timelineStatus === 'fishing') $status = 'at_sea';

        // ── Check if vessel is stuck on land or way off course ──
        if ($status === 'at_sea' && $timelineStatus !== 'outgoing' && self::isOnLand($lat, $lng, $portId)) {
            $tripId = (int)dbGetValue("SELECT current_trip_id FROM vessels WHERE id = ?", [$vid]);
            $zoneIdForTrip = (int)dbGetValue("SELECT zone_id FROM fishing_trips WHERE id = ?", [$tripId]);
            if ($zoneIdForTrip <= 0) $zoneIdForTrip = self::pickZoneForPort($portId);
            self::teleportToZone($vid, $portId, $zoneIdForTrip);
            return;
        }

        // ── Check if trip has been outgoing too long (stuck) ──
        if ($status === 'at_sea' && !$isFishing) {
            $elapsedMins = (int)dbGetValue(
                "SELECT TIMESTAMPDIFF(MINUTE, started_at, NOW()) FROM fishing_trips WHERE vessel_id = ? AND status = 'outgoing' ORDER BY started_at DESC LIMIT 1",
                [$vid]
            );
            if ($elapsedMins > 120) {
                // Been outgoing > 2 hours — teleport to zone
                $tripZoneId = (int)dbGetValue("SELECT zone_id FROM fishing_trips WHERE vessel_id = ? AND status = 'outgoing' ORDER BY started_at DESC LIMIT 1", [$vid]);
                // Always pick a geographically correct zone instead of the possibly wrong trip zone
                $tripZoneId = self::pickZoneForPort($portId);
                self::teleportToZone($vid, $portId, $tripZoneId);
                // Update trip with correct zone
                dbExecute("UPDATE fishing_trips SET zone_id = ? WHERE vessel_id = ? AND status = 'outgoing'", [$tripZoneId, $vid]);
                return;
            }
        }

        if ($status === 'docked') {
            $existingTripId = (int)dbGetValue("SELECT current_trip_id FROM vessels WHERE id = ?", [$vid]);
            if ($existingTripId > 0) {
                dbExecute("UPDATE vessels SET status = 'at_sea', updated_at = NOW() WHERE id = ?", [$vid]);
            } else {
                // No waiting — depart immediately for next batch
                self::departToSea($vid, $portId);
            }
            return;
        }
        if ($status === 'at_sea') {
            // ── FISHING? Generate ~6 fish over 10 ticks (5 min) ──
            $fishingTrip = dbGetRow("SELECT id, zone_id, started_at, fishing_start FROM fishing_trips WHERE vessel_id = ? AND status = 'fishing' ORDER BY started_at DESC LIMIT 1", [$vid]);
            if ($fishingTrip) {
                $fishingElapsed = (int)dbGetValue("SELECT TIMESTAMPDIFF(SECOND, COALESCE(fishing_start, started_at), NOW()) FROM fishing_trips WHERE id = ?", [(int)$fishingTrip['id']]);

                // Generate fish with ~60% chance per tick → ~6 fish over 10 ticks
                $genRoll = mt_rand(1, 100);
                if ($genRoll <= 60 && self::catchCountForTrip((int)$fishingTrip['id']) < self::TARGET_CATCHES_PER_TRIP) {
                    $catchKg = self::generateStandardCatchWeight((int)$fishingTrip['id']);

                    // Find a matching product
                    $randomFish = dbGetRow("SELECT id, name FROM product WHERE is_active = TRUE ORDER BY RAND() LIMIT 1");
                    if ($randomFish) {
                        $zone = dbGetRow("SELECT name FROM fishery_zone WHERE id = ?", [(int)$fishingTrip['zone_id']]);
                        $zname = $zone ? $zone['name'] : 'Unknown';
                        // Record catch in trip_catches (merged — was previously catch_record)
                        self::insertTripCatch((int)$fishingTrip['id'], (int)$randomFish['id'], $catchKg, 'A', $randomFish['name'], $zname);
                    }
                }

                // Return after 10 ticks fishing (300s)
                if ($fishingElapsed >= 300) {
                    $homePort = self::$ports[$portId] ?? ['lat' => 3.0, 'lng' => 101.4];
                    dbExecute("UPDATE vessels SET status = 'returning', updated_at = NOW() WHERE id = ?", [$vid]);
                    dbExecute("UPDATE fishing_trips SET status = 'returning', fishing_end = NOW() WHERE id = ?", [(int)$fishingTrip['id']]);
                    // Teleport to ~8 km from port (4 ticks sailing at ~12 kn = 2 min returning)
                    $port = self::$ports[$portId] ?? ['lat' => 3.0, 'lng' => 101.4];
                    $ratio = max(0.8, 1 - (8.0 / self::haversine($lat, $lng, $port['lat'], $port['lng'])));
                    if ($ratio > 0.99) $ratio = 0.8;
                    $newLat = $lat + ($port['lat'] - $lat) * $ratio;
                    $newLng = $lng + ($port['lng'] - $lng) * $ratio;
                    $heading = self::bearing($lat, $lng, $newLat, $newLng);
                    dbExecute("INSERT INTO vessel_track (vessel_id, latitude, longitude, speed_kn, heading_deg, depth_m, zone_id, is_fishing, recorded_at) VALUES (?, ?, ?, 12.0, ?, ?, ?, false, NOW())", [$vid, $newLat, $newLng, $heading, mt_rand(5, 15), (int)$fishingTrip['zone_id']]);

                }
                return; // handled fishing
            }

            // ── OUTGOING? Teleport to zone after ~2 min, but keep moving GPS ──
            $outgoingTrip = dbGetRow("SELECT id, zone_id, started_at FROM fishing_trips WHERE vessel_id = ? AND status = 'outgoing' ORDER BY started_at DESC LIMIT 1", [$vid]);
            if ($outgoingTrip) {
                $outElapsed = self::tripElapsedSeconds((int)$outgoingTrip['id']);
                if ($outElapsed >= 120) {
                    $zoneIdForTrip = self::pickZoneForPort($portId);
                    self::teleportToZone($vid, $portId, $zoneIdForTrip);
                    dbExecute("UPDATE fishing_trips SET zone_id = ? WHERE id = ?", [$zoneIdForTrip, $outgoingTrip['id']]);
                } else {
                    // Moving toward sea — update GPS every tick so map doesn't freeze
                    $speed = 12.0;
                    $bearing = $outgoingTrip['zone_id'] ? self::bearing($lat, $lng, 5.5, 110.0) : 45.0;
                    $distPerTick = $speed * (15 / 3600); // ~0.05 deg per tick at 12 kn
                    $newLat = $lat + cos(deg2rad($bearing)) * $distPerTick;
                    $newLng = $lng + sin(deg2rad($bearing)) * $distPerTick;
                    dbExecute("INSERT INTO vessel_track (vessel_id, latitude, longitude, speed_kn, heading_deg, depth_m, is_fishing, recorded_at) VALUES (?, ?, ?, ?, ?, ?, false, NOW())", [$vid, $newLat, $newLng, $speed, $bearing, mt_rand(5, 15)]);
                }
                return;
            }
            self::handleAtSea($vid, $v['name'], $portId, $lat, $lng, $isFishing, $zoneId, $zones, $capacity, $now);
            return;
        }
        if ($status === 'returning') {
            $tripId = isset($v['current_trip_id']) ? (int)$v['current_trip_id'] : 0;
            if ($tripId <= 0) $tripId = (int)dbGetValue("SELECT id FROM fishing_trips WHERE vessel_id = ? AND status = 'returning' ORDER BY started_at DESC LIMIT 1", [$vid]);

            if ($tripId > 0) {
                // All catches already in trip_catches (merged from catch_record)
            }

            // ── Returning vessels: go straight to handleReturning (skip isOnLand) ──
            self::handleReturning($vid, $portId, $lat, $lng, $now);
            return;
        }
    }

    private static function departToSea($vid, $portId) {
        self::loadPorts();
        dbExecute("UPDATE vessels SET status = 'at_sea', updated_at = NOW() WHERE id = ?", [$vid]);
        $port = self::$ports[$portId] ?? ['lat' => 3.0, 'lng' => 101.4];

        // Pick a zone that makes geographic sense for this port
        $zoneId = self::pickZoneForPort($portId);
        $zone = dbGetRow("SELECT id, name, center_lat, center_lng, radius_km FROM fishery_zone WHERE id = ?", [$zoneId]);
        $zoneName = $zone['name'] ?? 'Unknown';

        // Create a fishing trip
        dbExecute(
            "INSERT INTO fishing_trips (vessel_id, zone_id, status, started_at, created_at)
             VALUES (?, ?, 'outgoing', NOW(), NOW())",
            [$vid, $zoneId]
        );
        $tripId = dbLastInsertId();
        dbExecute("UPDATE vessels SET current_trip_id = ? WHERE id = ?", [$tripId, $vid]);

        // Head towards zone center (seaward direction)
        $zLat = (float)($zone['center_lat'] ?? $port['lat']);
        $zLng = (float)($zone['center_lng'] ?? ($port['lng'] - 0.5));
        $heading = self::bearing($port['lat'], $port['lng'], $zLat, $zLng);

        // Depart 10 km towards the zone
        $moveKm = 10.0;
        $dlat = ($moveKm / 6371.0) * cos(deg2rad($heading));
        $dlng = ($moveKm / 6371.0) * sin(deg2rad($heading)) / cos(deg2rad($port['lat']));
        $seaLat = $port['lat'] + $dlat;
        $seaLng = $port['lng'] + $dlng;

        dbExecute(
            "INSERT INTO vessel_track (vessel_id, latitude, longitude, speed_kn, heading_deg, depth_m, is_fishing, recorded_at)
             VALUES (?, ?, ?, 12.0, ?, 5.0, false, NOW())",
            [$vid, $seaLat, $seaLng, $heading]
        );

    }

    private static function handleAtSea($vid, $vname, $portId, $lat, $lng, $isFishing, $currentZoneId, $zones, $capacity, $now) {
        $nearestZone = null;
        $nearestDist = INF;
        foreach ($zones as $z) {
            $dist = self::haversine($lat, $lng, (float)$z['center_lat'], (float)$z['center_lng']);
            if ($dist < $nearestDist) {
                $nearestDist = $dist;
                $nearestZone = $z;
            }
        }

        if (!$nearestZone) return;

        $zid = $nearestZone['id'];
        $zname = $nearestZone['name'];
        $zlat = (float)$nearestZone['center_lat'];
        $zlng = (float)$nearestZone['center_lng'];
        $zradius = (float)$nearestZone['radius_km'];
        $zdensity = (float)$nearestZone['fish_density_base'];
        $inZone = $nearestDist <= $zradius;

        if (!$inZone) {
            $speed = mt_rand(100, 180) / 10;
            list($newLat, $newLng) = self::moveTowards($lat, $lng, $zlat, $zlng, $speed);
            $fishDensity = $zdensity * (1 - min($nearestDist / 200, 0.8));
            $heading = self::bearing($lat, $lng, $newLat, $newLng);
            dbExecute(
                "INSERT INTO vessel_track (vessel_id, latitude, longitude, speed_kn, heading_deg, depth_m, fish_density, is_fishing, recorded_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, false, NOW())",
                [$vid, $newLat, $newLng, $speed, $heading, mt_rand(10, 50), round($fishDensity, 2)]
            );
        } else {
            if (!$isFishing) {
                $tripId = (int)dbGetValue("SELECT current_trip_id FROM vessels WHERE id = ?", [$vid]);
                if ($tripId <= 0) {
                    // No valid trip — head back instead of starting blind fishing
                    dbExecute("UPDATE vessels SET status = 'returning', updated_at = NOW() WHERE id = ?", [$vid]);
                    $homePort = self::$ports[$portId] ?? ['lat' => 3.0, 'lng' => 101.4];
                    $heading = self::bearing($lat, $lng, $homePort['lat'], $homePort['lng']);
                    dbExecute("INSERT INTO vessel_track (vessel_id, latitude, longitude, speed_kn, heading_deg, depth_m, fish_density, zone_id, is_fishing, recorded_at) VALUES (?, ?, ?, 14.0, ?, ?, ?, ?, false, NOW())", [$vid, $lat, $lng, $heading, mt_rand(5, 15), round($fishDensity, 2), $zid]);
                    return;
                }

                $fishDensity = $zdensity * (mt_rand(70, 130) / 100);
                $fishDensity = min(max($fishDensity, 0.1), 0.99);
                dbExecute("UPDATE vessels SET status = 'at_sea', updated_at = NOW() WHERE id = ?", [$vid]);
                dbExecute(
                    "INSERT INTO vessel_track (vessel_id, latitude, longitude, speed_kn, heading_deg, depth_m, fish_density, zone_id, is_fishing, catch_kg_estimate, recorded_at)
                     VALUES (?, ?, ?, 0.5, ?, ?, ?, ?, true, ?, NOW())",
                    [$vid, $lat, $lng, mt_rand(0, 360), mt_rand(15, 60), round($fishDensity, 2), $zid, round($fishDensity * 500, 1)]
                );
                // Record fishing_start
                if ($tripId > 0) {
                    dbExecute("UPDATE fishing_trips SET status = 'fishing', fishing_start = DATE_ADD(started_at, INTERVAL 2 MINUTE) WHERE id = ?", [$tripId]);
                }
            } else {
                // 确保 vessel 有有效的 current_trip_id，否则不捕鱼
                $tripId = (int)dbGetValue("SELECT current_trip_id FROM vessels WHERE id = ?", [$vid]);
                if ($tripId <= 0) {
                    // No valid trip — head back to port instead of fishing blind
                    dbExecute("UPDATE vessels SET status = 'returning', updated_at = NOW() WHERE id = ?", [$vid]);
                    $homePort = self::$ports[$portId] ?? ['lat' => 3.0, 'lng' => 101.4];
                    $heading = self::bearing($lat, $lng, $homePort['lat'], $homePort['lng']);
                    dbExecute(
                        "INSERT INTO vessel_track (vessel_id, latitude, longitude, speed_kn, heading_deg, depth_m, fish_density, zone_id, is_fishing, recorded_at)
                         VALUES (?, ?, ?, 14.0, ?, ?, ?, ?, false, NOW())",
                        [$vid, $lat, $lng, $heading, mt_rand(5, 15), round($fishDensity, 2), $zid]
                    );
                    return;
                }

                $fishDensity = $zdensity * (mt_rand(60, 120) / 100);
                $fishDensity = min(max($fishDensity, 0.1), 0.99);

                $species = 'catch limit reached';
                $catchKg = 0;
                if (self::catchCountForTrip($tripId) < self::TARGET_CATCHES_PER_TRIP) {
                $fishProducts = self::getFishProducts();
                $product = $fishProducts[array_rand($fishProducts)];
                $pid = $product['pid'];
                $pname = $product['name'];
                $catchKg = self::generateStandardCatchWeight($tripId);
                $quality = self::$qualityGrades[array_rand(self::$qualityGrades)];

                // Check if already exists
                $existingTc = dbGetValue("SELECT COUNT(*) FROM trip_catches WHERE trip_id = ? AND product_id = ? AND estimated_weight_kg = ?", [$tripId, $pid, $catchKg]);
                if (!$existingTc) {
                    self::insertTripCatch($tripId, $pid, $catchKg, $quality, $pname, $zname);
                }
                }

                // Check how many times this vessel has fished on this trip
                $catchCount = self::catchCountForTrip($tripId);
                // Auto-return after about 6 generated catch items.
                if ($catchCount >= self::TARGET_CATCHES_PER_TRIP) {
                    dbExecute("UPDATE vessels SET status = 'returning', updated_at = NOW() WHERE id = ?", [$vid]);
                    // Record fishing_end
                    $tripId = (int)dbGetValue("SELECT current_trip_id FROM vessels WHERE id = ?", [$vid]);
                    if ($tripId > 0) {
                        dbExecute("UPDATE fishing_trips SET status = 'returning', fishing_end = NOW() WHERE id = ?", [$tripId]);
                    }
                    $homePort = self::$ports[$portId] ?? ['lat' => 3.0, 'lng' => 101.4];
                    $heading = self::bearing($lat, $lng, $homePort['lat'], $homePort['lng']);
                    dbExecute(
                        "INSERT INTO vessel_track (vessel_id, latitude, longitude, speed_kn, heading_deg, depth_m, fish_density, zone_id, is_fishing, recorded_at)
                         VALUES (?, ?, ?, 14.0, ?, ?, ?, ?, false, NOW())",
                        [$vid, $lat, $lng, $heading, mt_rand(5, 15), round($fishDensity, 2), $zid]
                    );
                    return;
                }

                dbExecute(
                    "INSERT INTO vessel_track (vessel_id, latitude, longitude, speed_kn, heading_deg, depth_m, fish_density, zone_id, is_fishing, catch_kg_estimate, recorded_at)
                     VALUES (?, ?, ?, 0.8, ?, ?, ?, ?, true, ?, NOW())",
                    [$vid, $lat, $lng, mt_rand(0, 360), mt_rand(15, 60), round($fishDensity, 2), $zid, round($catchKg, 1)]
                );
            }
        }
    }

    private static function handleReturning($vid, $portId, $lat, $lng, $now) {
        $port = self::$ports[$portId] ?? ['lat' => 3.0, 'lng' => 101.4];
        $dist = self::haversine($lat, $lng, $port['lat'], $port['lng']);

        $activeTrip = dbGetRow(
            "SELECT id, started_at FROM fishing_trips WHERE vessel_id = ? AND status = 'returning' ORDER BY started_at DESC LIMIT 1",
            [$vid]
        );
        if ($activeTrip && self::tripElapsedSeconds((int)$activeTrip['id']) < (self::OUTGOING_SECONDS + self::FISHING_SECONDS + self::RETURNING_SECONDS)) {
            $heading = self::bearing($lat, $lng, $port['lat'], $port['lng']);
            dbExecute(
                "INSERT INTO vessel_track (vessel_id, latitude, longitude, speed_kn, heading_deg, depth_m, is_fishing, recorded_at)
                 VALUES (?, ?, ?, 12.0, ?, ?, false, NOW())",
                [$vid, $lat, $lng, $heading, mt_rand(5, 20)]
            );
            return;
        }

        // ── DOCK if within 5 km ──
        if ($dist < 5.0) {
            dbExecute("UPDATE vessels SET status = 'docked', updated_at = NOW() WHERE id = ?", [$vid]);
            dbExecute(
                "INSERT INTO vessel_track (vessel_id, latitude, longitude, speed_kn, heading_deg, depth_m, is_fishing, recorded_at)
                 VALUES (?, ?, ?, 0, 0, 0, false, NOW())",
                [$vid, $port['lat'], $port['lng']]
            );

            // Complete the trip
            $tripId = (int)dbGetValue("SELECT current_trip_id FROM vessels WHERE id = ?", [$vid]);
            if ($tripId > 0) {
                dbExecute(
                    "UPDATE fishing_trips
                     SET status = 'completed',
                         fishing_start = DATE_ADD(started_at, INTERVAL 2 MINUTE),
                         fishing_end = DATE_ADD(started_at, INTERVAL 7 MINUTE),
                         completed_at = DATE_ADD(started_at, INTERVAL 12 MINUTE)
                     WHERE id = ?",
                    [$tripId]
                );

                // ── Sort all landed catches at unload ──
                self::sortLandedCatches($tripId);

                // ── Sync accepted trip_catches to inventory (warehouse) ──
                $acceptedCatches = dbGetAll(
                    "SELECT tc.id, tc.product_id, tc.estimated_weight_kg, p.name as pname
                     FROM trip_catches tc
                     JOIN product p ON p.id = tc.product_id
                     WHERE tc.trip_id = ? AND tc.status = 'accepted' AND tc.unloaded = 0",
                    [$tripId]
                );
                foreach ($acceptedCatches as $ac) {
                    $estKg = (float)$ac['estimated_weight_kg'];
                    $tier = self::classifyCatchTier($estKg);
                    if (!$tier) continue;
                    // Mark as unloaded
                    dbExecute("UPDATE trip_catches SET unloaded = 1, processed_at = NOW() WHERE id = ?", [$ac['id']]);
                    // Sync to warehouse inventory with correct tier
                    self::syncCatchToInventory(
                        $vid, (int)$ac['product_id'], (float)$ac['estimated_weight_kg'],
                        null, $now, $tier['tier_label']
                    );
                }

                // Clear current_trip_id so vessel can start a new trip
                dbExecute("UPDATE vessels SET current_trip_id = NULL WHERE id = ?", [$vid]);
            }
            return;
        }

        // ── Not yet at port: teleport close in one jump ──
        if ($dist > 15) {
            // Jump to ~1.5 km from port (next tick will dock)
            $ratio = ($dist - 1.5) / $dist;
            $newLat = $lat + ($port['lat'] - $lat) * $ratio;
            $newLng = $lng + ($port['lng'] - $lng) * $ratio;
            $speed = 25;
        } else {
            $speed = mt_rand(160, 220) / 10;
            list($newLat, $newLng) = self::moveTowards($lat, $lng, $port['lat'], $port['lng'], $speed);
        }
        $heading = self::bearing($lat, $lng, $newLat, $newLng);
        dbExecute(
            "INSERT INTO vessel_track (vessel_id, latitude, longitude, speed_kn, heading_deg, depth_m, is_fishing, recorded_at)
             VALUES (?, ?, ?, ?, ?, ?, false, NOW())",
            [$vid, $newLat, $newLng, $speed, $heading, mt_rand(5, 20)]
        );
    }

    private static function syncCatchToInventory($vid, $pid, $weightKg, $zoneId, $now, $tierLabel = 'MEDIUM') {
        $portIdRow = dbGetRow("SELECT port_id FROM vessels WHERE id = ?", [$vid]);
        if (!$portIdRow) return;
        $pId = $portIdRow['port_id'];

        $wid = $pId; // port_id == warehouse_id (1:1 merged)

        $normalizedTier = strtoupper((string)$tierLabel);
        $batchNo = str_starts_with($normalizedTier, 'TIER_') ? $normalizedTier : 'TIER_' . $normalizedTier;

        $existing = dbGetRow(
            "SELECT id, quantity FROM inventory WHERE product_id = ? AND port_id = ? AND batch_no = ? AND status = 'available'",
            [$pid, $wid, $batchNo]
        );

        if ($existing) {
            $invId = $existing['id'];
            $oldQty = (float)$existing['quantity'];
            $newQty = $oldQty + $weightKg;
            dbExecute("UPDATE inventory SET quantity = ?, updated_at = NOW() WHERE id = ?", [$newQty, $invId]);
            dbExecute(
                "INSERT INTO stock_move (inventory_id, product_id, port_id, movement_type, quantity, balance_before, balance_after, reference_type, notes, created_by_type)
                 VALUES (?, ?, ?, 'receive', ?, ?, ?, 'ai_vessel', 'AI vessel auto-stock', 'system')",
                [$invId, $pid, $wid, $weightKg, $oldQty, $newQty]
            );
        } else {
            dbExecute(
                "INSERT INTO inventory (product_id, port_id, quantity, reserved_qty, batch_no, status, received_date)
                 VALUES (?, ?, ?, 0, ?, 'available', ?)",
                [$pid, $wid, $weightKg, $batchNo, date('Y-m-d')]
            );
            $newInvId = dbLastInsertId();
            if ($newInvId) {
                dbExecute(
                    "INSERT INTO stock_move (inventory_id, product_id, port_id, movement_type, quantity, balance_before, balance_after, reference_type, notes, created_by_type)
                     VALUES (?, ?, ?, 'receive', ?, 0, ?, 'ai_vessel', 'AI vessel initial stock', 'system')",
                    [$newInvId, $pid, $wid, $weightKg, $weightKg]
                );
            }
        }

        // ── Sync to product.tier_X_stock for FishMarket / International ──
        $stockCol = match ($normalizedTier) { 'SMALL', 'TIER_3KG' => 'tier_3kg_stock', 'MEDIUM', 'TIER_6KG' => 'tier_6kg_stock', 'LARGE', 'TIER_10KG' => 'tier_10kg_stock', default => null };
        $tierKg = match ($normalizedTier) { 'SMALL', 'TIER_3KG' => 3, 'MEDIUM', 'TIER_6KG' => 6, 'LARGE', 'TIER_10KG' => 10, default => 0 };
        if ($stockCol && $tierKg > 0) {
            $units = max(1, round($weightKg / $tierKg));
            dbExecute("UPDATE product SET $stockCol = $stockCol + ? WHERE id = ?", [$units, $pid]);
        }

        try {
            dbExecute(
                "INSERT INTO user_activity (user_type, event_type, metadata, created_at)
                 VALUES ('system', 'ai_catch_synced', ?, NOW())",
                [json_encode(['vessel_id' => $vid, 'product_id' => $pid, 'weight_kg' => $weightKg])]
            );
        } catch (Exception $e) {}
    }

    public static function getVesselMapData() {
        $rows = dbGetAll(
            "SELECT v.id, v.name, v.status, vt.latitude, vt.longitude,
                    vt.speed_kn, vt.heading_deg, vt.fish_density, vt.is_fishing,
                    vt.catch_kg_estimate, vt.recorded_at,
                    p.name as port_name
             FROM vessels v
             LEFT JOIN (
                 SELECT a.vessel_id, a.latitude, a.longitude,
                     a.speed_kn, a.heading_deg, a.fish_density, a.is_fishing,
                     a.catch_kg_estimate, a.recorded_at
                 FROM vessel_track a
                 INNER JOIN (
                     SELECT vessel_id, MAX(id) AS max_id
                     FROM vessel_track
                     GROUP BY vessel_id
                 ) b ON a.id = b.max_id
             ) vt ON vt.vessel_id = v.id
             JOIN ports p ON p.id = v.port_id
             ORDER BY v.id"
        );
        $result = [];
        // Add slight jitter per vessel id so vessels at same port don't overlap
        $jitterSeeds = [1=>0.02, 2=>-0.015, 3=>0.01, 4=>-0.02, 5=>0.015, 6=>-0.01];
        foreach ($rows as $r) {
            $vid = (int)$r['id'];
            $jitter = $jitterSeeds[$vid] ?? (($vid % 5) * 0.008);
            $result[] = [
                'id' => $vid,
                'name' => $r['name'],
                'status' => $r['status'],
                'lat' => $r['latitude'] ? (float)$r['latitude'] + $jitter : null,
                'lng' => $r['longitude'] ? (float)$r['longitude'] + $jitter : null,
                'speed' => $r['speed_kn'] ? (float)$r['speed_kn'] : 0,
                'heading' => $r['heading_deg'] ? (float)$r['heading_deg'] : 0,
                'fish_density' => $r['fish_density'] ? (float)$r['fish_density'] : 0,
                'is_fishing' => !empty($r['is_fishing']),
                'catch_kg' => $r['catch_kg_estimate'] ? (float)$r['catch_kg_estimate'] : 0,
                'last_update' => $r['recorded_at'] ?? null,
                'port_name' => $r['port_name'] ?? '',
            ];
        }
        return $result;
    }

    public static function getFishingZones() {
        $rows = dbGetAll(
            "SELECT id, name, center_lat, center_lng, radius_km, fish_density_base, primary_species, description
             FROM fishery_zone WHERE is_active = true ORDER BY id"
        );
        $result = [];
        foreach ($rows as $r) {
            $result[] = [
                'id' => $r['id'],
                'name' => $r['name'],
                'lat' => (float)$r['center_lat'],
                'lng' => (float)$r['center_lng'],
                'radius' => (float)$r['radius_km'],
                'density' => (float)$r['fish_density_base'],
                'species' => $r['primary_species'] ?? '',
                'desc' => $r['description'] ?? '',
            ];
        }
        return $result;
    }

    public static function getSimulationStats() {
        $totalVessels = dbGetValue("SELECT count(*) FROM vessels") ?: 0;
        $atSea = dbGetValue("SELECT count(*) FROM vessels WHERE status = 'at_sea'") ?: 0;
        $fishing = dbGetValue("SELECT count(DISTINCT vessel_id) FROM vessel_track WHERE is_fishing = true AND recorded_at > NOW() - INTERVAL 1 HOUR") ?: 0;
        $docked = dbGetValue("SELECT count(*) FROM vessels WHERE status = 'docked'") ?: 0;
        $totalCatchesToday = dbGetValue("SELECT COALESCE(SUM(estimated_weight_kg), 0) FROM trip_catches WHERE DATE(created_at) = CURRENT_DATE") ?: 0;
        $totalCatchesAll = dbGetValue("SELECT COALESCE(SUM(estimated_weight_kg), 0) FROM trip_catches") ?: 0;
        $catchCount = dbGetValue("SELECT count(*) FROM trip_catches") ?: 0;
        $actions24h = 0;

        return [
            'total_vessels' => (int)$totalVessels,
            'at_sea' => (int)$atSea,
            'fishing' => (int)$fishing,
            'docked' => (int)$docked,
            'returning' => (int)(($totalVessels - $atSea - $docked)),
            'total_catches_today' => (float)$totalCatchesToday,
            'total_catches_all' => (float)$totalCatchesAll,
            'catch_count' => (int)$catchCount,
            'ai_actions_24h' => (int)$actions24h,
        ];
    }
}
