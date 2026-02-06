<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Company, App\Models\User;

echo "=== CHECKING COMPANIES ===\n\n";

$companies = Company::all();
echo "Total companies: " . $companies->count() . "\n\n";

foreach ($companies as $company) {
    echo "Company: {$company->name}\n";
    echo "  ID: {$company->id}\n";
    echo "  created_by_user_id: " . ($company->created_by_user_id ?? 'NULL') . "\n";
    echo "  Users: " . $company->users()->count() . "\n";
    if ($company->users()->count() > 0) {
        foreach ($company->users as $user) {
            echo "    - {$user->name} (ID: {$user->id})\n";
        }
    }
    echo "\n";
}

echo "=== USERS ===\n\n";
$users = User::all();
foreach ($users as $user) {
    echo "User: {$user->name}\n";
    echo "  ID: {$user->id}\n";
    echo "  Email: {$user->email}\n";
    echo "  Company ID: " . ($user->company_id ?? 'NULL') . "\n";
    echo "  Role: " . ($user->role ? $user->role->name : 'NULL') . "\n\n";
}
