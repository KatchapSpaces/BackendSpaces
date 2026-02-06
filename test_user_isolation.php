<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Company;

echo "=== DATABASE STATE ===\n\n";

// Get all companies
$companies = Company::with('users')->get();
echo "Total Companies: " . count($companies) . "\n";
foreach ($companies as $company) {
    echo "- Company: {$company->name} (ID: {$company->id}, created_by_user_id: {$company->created_by_user_id})\n";
    echo "  Members: " . $company->users->count() . "\n";
    foreach ($company->users as $user) {
        echo "    - {$user->name} (ID: {$user->id})\n";
    }
}

echo "\n=== USERS ===\n\n";
$users = User::all();
foreach ($users as $user) {
    echo "User: {$user->name} (ID: {$user->id})\n";
    echo "  Company ID: " . ($user->company_id ?? 'NULL') . "\n";
    echo "  Created Companies: " . Company::where('created_by_user_id', $user->id)->count() . "\n";
    $createdCompanies = Company::where('created_by_user_id', $user->id)->get();
    foreach ($createdCompanies as $comp) {
        echo "    - {$comp->name} (ID: {$comp->id})\n";
    }
}

echo "\n=== API RESPONSE TEST ===\n\n";

// Simulate API request for each user
foreach ($users as $user) {
    echo "--- When User {$user->name} (ID: {$user->id}) calls GET /api/companies ---\n";
    
    $companies = Company::where('created_by_user_id', $user->id)
        ->with(['users' => function($query) {
            $query->with('role')->select('id', 'company_id', 'name', 'email', 'role_id', 'status', 'created_at');
        }])
        ->orderBy('created_at', 'desc')
        ->get();

    echo "API returns: " . count($companies) . " companies\n";
    foreach ($companies as $comp) {
        echo "  - {$comp->name} (ID: {$comp->id})\n";
        echo "    Members: " . $comp->users->count() . "\n";
        foreach ($comp->users as $member) {
            echo "      - {$member->name}\n";
        }
    }
    echo "\n";
}
