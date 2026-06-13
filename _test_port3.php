<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simulate what index.php does for the port route
$_SERVER['REQUEST_URI'] = '/dashboard/stock/port/1/';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_GET['port_id'] = 1;

try {
    require __DIR__ . '/PortManagement/stock/port.php';
    echo "OK\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\nFile: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
