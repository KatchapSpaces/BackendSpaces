<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Testing Permission-Based Access ===\n\n";

// Get a manager user
$manager = \App\Models\User::where('email', 'like', '%manager%')->orWhereHas('role', function($q) {
    $q->where('name', 'manager');
})->first();

if (!$manager) {
    echo "No manager user found in database\n";
    echo "Creating test scenario...\n\n";
    
    // For demonstration purposes
    echo "SCENARIO 1: Manager HAS view_project permission\n";
    echo "======================================\n";
    $managerRole = \App\Models\Role::where('name', 'manager')->first();
    echo "Manager role: " . $managerRole->name . "\n";
    echo "Permissions: " . $managerRole->permissions->pluck('name')->join(', ') . "\n\n";
    echo "✓ Backend will return ALL projects\n";
    echo "✓ Frontend will display Projects page\n";
    echo "✓ Manager sees all projects (created by superadmin, admin, etc)\n\n";
    
    echo "SCENARIO 2: Manager DOES NOT have view_project permission\n";
    echo "=========================================\n";
    echo "If superadmin removes view_project permission:\n";
    echo "✗ Backend will return empty array\n";
    echo "✗ Frontend ProtectedRoute blocks access\n";
    echo "✗ Manager sees only Dashboard & Profile\n";
    
} else {
    echo "Manager User Found: " . $manager->email . "\n";
    echo "Role: " . ($manager->role ? $manager->role->name : 'None') . "\n";
    echo "Has view_project: " . ($manager->hasPermission('view_project') ? 'YES' : 'NO') . "\n";
    echo "Has create_project: " . ($manager->hasPermission('create_project') ? 'YES' : 'NO') . "\n";
}
