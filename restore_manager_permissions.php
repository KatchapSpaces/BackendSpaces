<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Role;

echo "=== RESTORING MANAGER PERMISSIONS ===\n\n";

$manager = Role::where('name', 'manager')->first();

if (!$manager) {
    echo "❌ Manager role not found!\n";
    exit;
}

echo "Manager role found (ID: {$manager->id})\n";
echo "Current permissions: {$manager->permissions()->count()}\n\n";

// Assign useful permissions to manager
$permissionsToAssign = [
    4,  // create_project
    5,  // edit_project
    6,  // delete_project
    7,  // view_project
    8,  // create_task
    9,  // edit_task
    10, // delete_task
    11, // assign_task
    12, // view_task
];

echo "Assigning permissions: " . implode(', ', $permissionsToAssign) . "\n\n";
$manager->permissions()->sync($permissionsToAssign);

// Verify
$manager->refresh();
echo "✓ Manager now has {$manager->permissions()->count()} permissions\n";
echo "   Permissions: " . $manager->permissions()->pluck('name')->implode(', ') . "\n";

// Check all roles
echo "\n=== ALL ROLES STATE ===\n";
$roles = Role::all();
foreach ($roles as $role) {
    $permCount = $role->permissions()->count();
    echo "✓ {$role->name}: {$permCount} permissions\n";
}

echo "\nDone!\n";
