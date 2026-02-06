<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');
$response = $kernel->handle(
    $request = \Illuminate\Http\Request::capture()
);

use App\Models\Role;

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║    Testing GET /roles endpoint response                       ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Simulate what the endpoint does
$rolesData = Role::query()
    ->with(['permissions' => function ($query) {
        $query->select('rbac_permissions.id', 'rbac_permissions.name')
              ->wherePivot('role_id', '!=', null);
    }])
    ->get();

echo "Total roles: " . $rolesData->count() . "\n\n";

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

// Print manager role
$manager = $roles->firstWhere('name', 'manager');
echo "Manager role permissions:\n";
echo json_encode($manager['permissions'], JSON_PRETTY_PRINT) . "\n";
echo "Count: " . count($manager['permissions']) . "\n";

echo "\n\nAll roles with permission counts:\n";
foreach ($roles as $role) {
    echo "  {$role['name']}: " . count($role['permissions']) . " permissions\n";
}
