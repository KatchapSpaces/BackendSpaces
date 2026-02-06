<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;

// Get FOJI BHAI (User B - ID: 9)
$userB = User::find(9);

if (!$userB) {
    echo "User B (ID: 9) not found!\n";
    exit;
}

echo "=== SIMULATING USER B REQUEST ===\n\n";
echo "User: {$userB->name} (ID: {$userB->id})\n";
echo "User's company_id: " . ($userB->company_id ?? 'NULL') . "\n\n";

// Simulate the /api/companies request
echo "--- GET /api/companies ---\n";

$companies = \App\Models\Company::where('created_by_user_id', $userB->id)
    ->with(['users' => function($query) {
        $query->with('role')->select('id', 'company_id', 'name', 'email', 'role_id', 'status', 'created_at');
    }])
    ->orderBy('created_at', 'desc')
    ->get();

echo "Backend query returns:\n";
echo json_encode(['companies' => $companies->toArray()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

echo "\n\n--- GET /api/companies-analytics ---\n";

$analytics = [
    'total_companies' => \App\Models\Company::count(),
    'active_companies' => \App\Models\Company::where('status', 'active')->count(),
    'suspended_companies' => \App\Models\Company::where('status', 'suspended')->count(),
    'total_users' => \App\Models\User::count(),
    'companies_by_status' => [
        'active' => 1,
        'suspended' => 0,
        'inactive' => 0,
    ],
    'recent_companies' => [],
];

echo "Backend returns:\n";
echo json_encode($analytics, JSON_PRETTY_PRINT);

echo "\n\n=== CHECKING IF ISSUE IS IN ALL-USERS ENDPOINT ===\n";
echo "--- GET /api/all-users ---\n";

$users = User::with(['role', 'company'])
    ->select('id', 'name', 'email', 'role_id', 'company_id', 'status', 'created_at')
    ->orderBy('created_at', 'desc')
    ->get()
    ->map(function ($user) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'created_at' => $user->created_at,
            'role' => $user->role ? [
                'id' => $user->role->id,
                'name' => $user->role->name,
            ] : null,
            'company' => $user->company ? [
                'id' => $user->company->id,
                'name' => $user->company->name,
                'status' => $user->company->status,
            ] : null,
        ];
    });

echo "All users returned (should show both users with their companies):\n";
echo json_encode($users, JSON_PRETTY_PRINT);
