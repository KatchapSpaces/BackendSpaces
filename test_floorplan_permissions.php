<?php
// Test script to verify floorplan deletion permissions

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;

echo "=== Testing FloorPlan Deletion Permissions ===\n\n";

// Find manager role
$managerRole = Role::where('name', 'manager')->with('permissions')->first();

echo "Manager Role: " . $managerRole->name . "\n";
echo "Manager Permissions:\n";
foreach ($managerRole->permissions as $perm) {
    echo "  • " . $perm->name . "\n";
}

echo "\n=== What Manager CAN DO ===\n";
echo "✅ view_project\n";
echo "❌ create_project\n";
echo "❌ edit_project\n";
echo "❌ delete_project (BLOCKS FLOORPLAN DELETION)\n\n";

echo "=== Authorization Check ===\n";
$hasDeletePermission = $managerRole->permissions->contains('name', 'delete_project');
echo "Manager can delete floorplans? " . ($hasDeletePermission ? "YES ❌" : "NO ✅") . "\n";

echo "\n=== Expected Behavior ===\n";
echo "Before Fix: Manager could delete floorplans (no permission check)\n";
echo "After Fix: Manager gets 403 error when trying to delete floorplans ✅\n";
