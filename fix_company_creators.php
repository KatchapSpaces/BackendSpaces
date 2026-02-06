<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Company;

echo "=== FIXING COMPANIES ===\n\n";

$companies = Company::where('created_by_user_id', NULL)->get();

echo "Companies without creator: " . $companies->count() . "\n\n";

foreach ($companies as $company) {
    echo "Processing: {$company->name}\n";
    
    // Get the first user in this company
    $user = $company->users()->first();
    
    if ($user) {
        $company->update(['created_by_user_id' => $user->id]);
        echo "  ✓ Set creator to: {$user->name} (ID: {$user->id})\n";
    } else {
        echo "  ❌ No users in this company\n";
    }
}

echo "\n=== VERIFICATION ===\n\n";

$allCompanies = Company::all();
foreach ($allCompanies as $company) {
    echo "Company: {$company->name}\n";
    echo "  created_by_user_id: {$company->created_by_user_id}\n";
    echo "  Users: " . $company->users()->count() . "\n\n";
}

echo "Done!\n";
