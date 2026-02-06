<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Role;

echo "=== CHECKING ACTUAL DATABASE STATE ===\n\n";

$manager = Role::where('name', 'manager')->with('permissions')->first();

if ($manager) {
    echo "Manager role found:\n";
    echo "  ID: {$manager->id}\n";
    echo "  Name: {$manager->name}\n";
    echo "  Permissions count: {$manager->permissions->count()}\n";
    
    if ($manager->permissions->count() > 0) {
        echo "  Permissions:\n";
        foreach ($manager->permissions as $perm) {
            echo "    - {$perm->name} (id: {$perm->id})\n";
        }
    } else {
        echo "  ❌ NO PERMISSIONS!\n";
    }
} else {
    echo "❌ Manager role not found!\n";
}

echo "\n=== CHECKING WHAT API WOULD RETURN ===\n\n";

$rolesData = Role::query()->with('permissions')->get();

$roles = $rolesData->map(function($role) {
    return [
        'id' => $role->id,
        'name' => $role->name,
        'permissions' => $role->permissions->map(function($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'pivot' => [
                    'scope' => $permission->pivot->scope ?? 'full'
                ]
            ];
        })->toArray(),
    ];
})->toArray();

foreach ($roles as $role) {
    $permCount = count($role['permissions']);
    echo "✓ {$role['name']}: {$permCount} permissions\n";
    if ($permCount > 0) {
        foreach ($role['permissions'] as $perm) {
            echo "    - {$perm['name']}\n";
        }
    }
}

echo "\n=== RAW JSON RESPONSE ===\n\n";
echo json_encode($roles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "\n\nDone!\n";
