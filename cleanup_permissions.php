<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');
$response = $kernel->handle(
    $request = \Illuminate\Http\Request::capture()
);

use App\Models\Role;

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  CLEANUP: Remove all permissions from manager, subcontractor,  ║\n";
echo "║           user, design_team, and basic roles                   ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$rolesToClean = ['manager', 'subcontractor', 'user', 'design_team', 'basic'];

foreach ($rolesToClean as $roleName) {
    $role = Role::where('name', $roleName)->first();
    if ($role) {
        $beforeCount = $role->permissions()->count();
        // Sync with empty array removes all permissions
        $role->permissions()->sync([]);
        $afterCount = $role->permissions()->count();
        echo "✓ {$roleName}: removed {$beforeCount} permissions → {$afterCount} remaining\n";
    } else {
        echo "✗ {$roleName}: role not found\n";
    }
}

echo "\n✅ Cleanup complete! All test permissions removed from non-admin roles.\n";
echo "\nCurrent state:\n";
$allRoles = Role::with('permissions')->get();
foreach ($allRoles as $role) {
    echo "  {$role->name}: " . $role->permissions->count() . " permissions\n";
}
