<?php
// Test script to verify roles endpoint returns permissions

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Role;
use App\Models\Permission;

echo "=== Testing Roles Endpoint Response ===\n\n";

$rolesData = Role::with('permissions')->get();

echo "Total Roles: " . $rolesData->count() . "\n\n";

foreach ($rolesData as $role) {
    echo "Role: " . $role->name . "\n";
    echo "  - ID: " . $role->id . "\n";
    echo "  - Permissions Loaded: " . ($role->permissions ? "YES (" . $role->permissions->count() . ")" : "NO") . "\n";
    
    if ($role->permissions && $role->permissions->count() > 0) {
        echo "  - Permission Names:\n";
        foreach ($role->permissions as $perm) {
            echo "    • " . $perm->name . " (ID: " . $perm->id . ")\n";
        }
    }
    echo "\n";
}

echo "\n=== Formatted API Response ===\n\n";

$roles = $rolesData->map(function($role) {
    return [
        'id' => $role->id,
        'name' => $role->name,
        'created_at' => $role->created_at,
        'updated_at' => $role->updated_at,
        'user_count' => $role->users()->count(),
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
});

foreach ($roles as $role) {
    echo "✅ " . $role['name'] . " - Permissions: " . count($role['permissions']) . "\n";
    if (count($role['permissions']) > 0) {
        foreach ($role['permissions'] as $perm) {
            echo "   ✓ " . $perm['name'] . "\n";
        }
    }
    echo "\n";
}
