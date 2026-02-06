<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Role;

echo "=== API RESPONSE SIMULATION ===\n\n";

// This is what the /roles endpoint returns
$rolesData = Role::query()
    ->with('permissions')
    ->get();

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
})->toArray();

// Find manager role in response
$manager = null;
foreach ($roles as $r) {
    if ($r['name'] === 'manager') {
        $manager = $r;
        break;
    }
}

if ($manager) {
    echo "Manager role from API:\n";
    echo json_encode($manager, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    echo "\n\nManager permissions array:\n";
    echo json_encode($manager['permissions'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    echo "\n\nPermissions count: " . count($manager['permissions']) . "\n";
    
    // Simulate what frontend does
    $rolePermissionIds = array_map(function($p) {
        return (int)$p['id'];
    }, $manager['permissions']);
    
    echo "\nFormData that would be set:\n";
    echo json_encode([
        'name' => $manager['name'],
        'permissions' => $rolePermissionIds
    ], JSON_PRETTY_PRINT);
} else {
    echo "Manager not found!\n";
}

echo "\n\nDone!\n";
