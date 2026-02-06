<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

// Get FOJI BHAI (User B - ID: 9)
$userB = User::find(9);

if (!$userB) {
    echo "User B (ID: 9) not found!\n";
    exit;
}

echo "=== TESTING FOJI BHAI API RESPONSE ===\n\n";
echo "Logged in as: {$userB->name} (ID: {$userB->id})\n\n";

// Simulate the /api/companies request exactly as backend does
$companies = \App\Models\Company::where('created_by_user_id', $userB->id)
    ->with(['users' => function($query) {
        $query->with('role')->select('id', 'company_id', 'name', 'email', 'role_id', 'status', 'created_at');
    }])
    ->orderBy('created_at', 'desc')
    ->get();

echo "API Response (what backend returns):\n";
$response = ['companies' => $companies->toArray()];
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

echo "\n\n=== CHECKING COMPANY USERS ===\n";
foreach ($companies as $company) {
    echo "\nCompany: {$company->name} (ID: {$company->id})\n";
    echo "Users count: " . count($company->users) . "\n";
    if ($company->users) {
        foreach ($company->users as $user) {
            echo "  - {$user->name} (ID: {$user->id})\n";
        }
    }
}
