<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');
$response = $kernel->handle(
    $request = \Illuminate\Http\Request::capture()
);

use App\Models\Role;
use App\Models\Permission;

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║    DEBUG: Check role_permissions pivot table                  ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Check manager role specifically
$manager = Role::where('name', 'manager')->first();
echo "Manager role found: " . ($manager ? "YES (id={$manager->id})" : "NO") . "\n\n";

if ($manager) {
    echo "TEST 1: Load permissions with relation\n";
    echo "─────────────────────────────────────\n";
    $manager->load('permissions');
    echo "Permissions count: " . $manager->permissions->count() . "\n";
    if ($manager->permissions->count() > 0) {
        echo "First permission: " . $manager->permissions[0]->name . "\n";
    }
    echo "\n";
    
    echo "TEST 2: Raw query - check role_permissions table\n";
    echo "──────────────────────────────────────────────\n";
    $pivotRecords = \Illuminate\Support\Facades\DB::table('role_permissions')
        ->where('role_id', $manager->id)
        ->get();
    echo "Records in role_permissions for manager: " . $pivotRecords->count() . "\n";
    if ($pivotRecords->count() > 0) {
        echo "First record: ";
        print_r($pivotRecords[0]);
    }
    echo "\n";
    
    echo "TEST 3: Check if permissions table exists and has data\n";
    echo "──────────────────────────────────────────────────────\n";
    $allPerms = Permission::all();
    echo "Total permissions in system: " . $allPerms->count() . "\n";
    echo "Sample permission: " . ($allPerms->first() ? $allPerms->first()->name : "NONE") . "\n";
    echo "\n";
    
    echo "TEST 4: What does the query builder return?\n";
    echo "───────────────────────────────────────────\n";
    $roles = Role::with('permissions')->get();
    $managerInRoles = $roles->firstWhere('id', $manager->id);
    if ($managerInRoles) {
        echo "Manager from query: id=" . $managerInRoles->id . ", name=" . $managerInRoles->name . "\n";
        echo "Permissions on manager: " . $managerInRoles->permissions->count() . "\n";
    }
}
