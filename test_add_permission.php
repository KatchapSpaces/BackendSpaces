<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Role;

echo "=== TESTING ADDING NEW PERMISSION ===\n\n";

$manager = Role::where('name', 'manager')->first();
echo "Manager current permissions:\n";
$manager->permissions->each(function($p) {
    echo "  - {$p->name} (id: {$p->id})\n";
});

echo "\nAdding permission IDs [7, 8] (view_project, create_task)...\n";
$manager->permissions()->sync([7, 8]);

$manager->refresh();
echo "\nAfter sync:\n";
$manager->permissions->each(function($p) {
    echo "  - {$p->name} (id: {$p->id})\n";
});

echo "\nNow checking database directly:\n";
$dbPerms = \DB::table('role_permissions')
    ->where('role_id', $manager->id)
    ->pluck('permission_id')
    ->toArray();
echo "Database permission IDs: " . implode(', ', $dbPerms) . "\n";

echo "\nDone!\n";
