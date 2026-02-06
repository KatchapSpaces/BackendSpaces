<?php
// Show last 100 lines of Laravel log
$logFile = __DIR__ . '/storage/logs/laravel.log';

if (!file_exists($logFile)) {
    echo "Log file not found: $logFile\n";
    exit;
}

$lines = file($logFile);
$lastLines = array_slice($lines, -100);

echo "=== LAST 100 LINES OF LARAVEL LOG ===\n\n";
foreach ($lastLines as $line) {
    echo $line;
}
