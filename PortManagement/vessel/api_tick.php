<?php
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../services/VesselSimulationService.php';
VesselSimulationService::tickSimulation();
header('Content-Type: application/json');
echo '{"ok":true}';
