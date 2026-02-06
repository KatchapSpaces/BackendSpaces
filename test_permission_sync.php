<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Role;

echo "=== TESTING PERMISSION PERSISTENCE ===\n\n";

// Get manager role
$manager = Role::where('name', 'manager')->first();

if (!$manager) {
    echo "❌ Manager role not found!\n";
    exit;
}

echo "Manager role ID: {$manager->id}\n";
echo "Current permissions: {$manager->permissions()->count()}\n\n";

// Test 1: Sync a permission
$permission_id = 7; // view_project
echo "Test 1: Syncing permission ID {$permission_id}...\n";
$manager->permissions()->sync([$permission_id]);

// Verify immediately
$manager->refresh();
$count = $manager->permissions()->count();
echo "✓ After sync: {$count} permissions\n";
echo "  Permissions: " . $manager->permissions()->pluck('name')->implode(', ') . "\n\n";

// Test 2: Check database directly
echo "Test 2: Querying database directly...\n";
$dbCount = \DB::table('role_permissions')
    ->where('role_id', $manager->id)
    ->count();
echo "✓ Database has {$dbCount} permissions for manager\n";
$dbPerms = \DB::table('role_permissions')
    ->where('role_id', $manager->id)
    ->get();
foreach ($dbPerms as $perm) {
    echo "  - permission_id: {$perm->permission_id}\n";
}

echo "\n";

// Test 3: Fresh query
echo "Test 3: Fresh query from API format...\n";
$freshManager = Role::with('permissions')->find($manager->id);
echo "✓ Fresh manager permissions: {$freshManager->permissions->count()}\n";
foreach ($freshManager->permissions as $p) {
    echo "  - {$p->name} (id: {$p->id})\n";
}

echo "\nDone!\n";
