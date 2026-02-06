<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Role;

// Cleanup strategy: Delete ALL permissions for non-admin roles (except super_admin and admin)
$rolesToClean = ['manager', 'subcontractor', 'user', 'design_team', 'basic'];

echo "=== PERMISSION CLEANUP ===\n";
echo "Target roles: " . implode(', ', $rolesToClean) . "\n\n";

foreach ($rolesToClean as $roleName) {
    $role = Role::where('name', $roleName)->first();
    
    if (!$role) {
        echo "❌ Role '{$roleName}' not found\n";
        continue;
    }
    
    $permissionCountBefore = $role->permissions()->count();
    
    // Completely detach all permissions
    $role->permissions()->detach();
    
    // Verify cleanup
    $permissionCountAfter = $role->permissions()->count();
    
    echo "✓ {$roleName}: {$permissionCountBefore} → {$permissionCountAfter} permissions\n";
}

echo "\n✅ ALL TEST PERMISSIONS REMOVED\n";
echo "\nCurrent state:\n";

$roles = Role::all();
foreach ($roles as $role) {
    $permCount = $role->permissions()->count();
    echo "  {$role->name}: {$permCount} permissions\n";
}

echo "\nDone!\n";
