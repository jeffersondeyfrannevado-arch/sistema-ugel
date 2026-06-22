<?php
$logFile = 'storage/logs/laravel.log';
if (!file_exists($logFile)) {
    echo "Log file does not exist.\n";
    exit;
}

$lines = file($logFile);
$lastLines = array_slice($lines, -150);

echo "=== LAST 150 LINES OF LARAVEL.LOG ===\n";
foreach ($lastLines as $line) {
    echo $line;
}
