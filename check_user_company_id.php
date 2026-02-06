<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

echo "=== CHECKING USER COMPANY_ID ===\n\n";

$users = User::with('company')->get();

foreach ($users as $user) {
    echo "User: {$user->name} (ID: {$user->id})\n";
    echo "  company_id: " . ($user->company_id ?? 'NULL') . "\n";
    if ($user->company) {
        echo "  Company: {$user->company->name} (ID: {$user->company->id})\n";
    }
    echo "\n";
}
