<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Role, Illuminate\Http\Request;

echo "=== TESTING ROLE UPDATE DIRECTLY ===\n\n";

$manager = Role::find(3); // Manager role

echo "Manager BEFORE update:\n";
$manager->permissions->each(function($p) {
    echo "  - {$p->name} (id: {$p->id})\n";
});
echo "Total: " . $manager->permissions->count() . " permissions\n\n";

// Simulate what the frontend would send
$permissionIds = [7, 8]; // view_project, create_task
echo "Simulating frontend request with permissions: " . json_encode($permissionIds) . "\n\n";

// Update like the API would
$manager->update(['name' => 'manager']);
$manager->permissions()->sync($permissionIds);

$manager->refresh();

echo "Manager AFTER update:\n";
$manager->permissions->each(function($p) {
    echo "  - {$p->name} (id: {$p->id})\n";
});
echo "Total: " . $manager->permissions->count() . " permissions\n\n";

// Check database
$dbPerms = \DB::table('role_permissions')
    ->where('role_id', 3)
    ->pluck('permission_id')
    ->toArray();
echo "Database permission IDs: " . json_encode($dbPerms) . "\n";

echo "\nDone!\n";
