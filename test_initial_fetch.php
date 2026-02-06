<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Role;

echo "=== DIRECT API ENDPOINT TEST ===\n\n";

// Simulate the exact API call
$rolesData = Role::query()
    ->with('permissions')
    ->get();

echo "Total roles fetched: " . $rolesData->count() . "\n\n";

foreach ($rolesData as $role) {
    echo "Role: {$role->name}\n";
    echo "  DB permissions: " . $role->permissions()->count() . "\n";
    echo "  Loaded permissions: " . $role->permissions->count() . "\n";
    echo "  Permission IDs: " . $role->permissions->pluck('id')->implode(', ') . "\n\n";
}

// Now test the mapped response
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

echo "\n=== MAPPED API RESPONSE ===\n";
echo json_encode($roles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
