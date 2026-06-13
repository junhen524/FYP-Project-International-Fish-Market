<?php
require 'C:\xampp\htdocs\FisherySystem\International\includes\bootstrap.php';
$cols = dbGetAll("SHOW COLUMNS FROM export_user");
foreach ($cols as $c) {
    echo $c['Field'] . ' : ' . $c['Type'] . ' | Null: ' . $c['Null'] . ' | Default: ' . ($c['Default'] ?? 'NULL') . "\n";
}
echo "\n--- Functions referencing export_user ---\n";
