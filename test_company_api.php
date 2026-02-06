<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User, App\Models\Company;

echo "=== TESTING API RESPONSE ===\n\n";

// Get the super admin user
$user = User::where('role_id', 1)->first();

if (!$user) {
    echo "❌ No super admin user found!\n";
    exit;
}

echo "Testing user: {$user->name} (ID: {$user->id})\n";
echo "User company_id: " . ($user->company_id ?? 'NULL') . "\n\n";

// Simulate what the API returns
$companies = Company::where('created_by_user_id', $user->id)
    ->with(['users' => function($query) {
        $query->with('role')->select('id', 'company_id', 'name', 'email', 'role_id', 'status', 'created_at');
    }])
    ->orderBy('created_at', 'desc')
    ->get();

echo "Companies found: " . $companies->count() . "\n";

if ($companies->count() == 0) {
    echo "\n❌ NO COMPANIES FOUND FOR THIS USER!\n";
    echo "This means created_by_user_id is not set correctly.\n\n";
    
    echo "Let's check all companies in database:\n";
    $allCompanies = Company::all();
    foreach ($allCompanies as $c) {
        echo "  Company: {$c->name}\n";
        echo "    created_by_user_id: " . ($c->created_by_user_id ?? 'NULL') . "\n";
        echo "    Users: " . $c->users()->count() . "\n";
    }
} else {
    echo "\n✅ API RESPONSE:\n";
    echo json_encode([
        'companies' => $companies->toArray()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
