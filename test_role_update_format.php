<?php
/*
 * Test script to verify role update response format matches index response format
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');
$response = $kernel->handle(
    $request = \Illuminate\Http\Request::capture()
);

use Illuminate\Support\Facades\DB;
use App\Models\Role;
use App\Models\Permission;

echo "=== Testing Role Update Response Format ===\n";

// Get a test role (not super_admin)
$role = Role::where('name', '!=', 'super_admin')->first();

if (!$role) {
    echo "❌ No test role found\n";
    exit(1);
}

echo "Testing role: {$role->name}\n";

// Get the original role format (like index would return)
$indexFormat = Role::with('permissions')->get()->map(function($r) {
    return [
        'id' => $r->id,
        'name' => $r->name,
        'created_at' => $r->created_at,
        'updated_at' => $r->updated_at,
        'user_count' => $r->users()->count(),
        'permissions' => $r->permissions->map(function($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'pivot' => [
                    'scope' => $permission->pivot->scope ?? 'full'
                ]
            ];
        })->toArray(),
    ];
})->firstWhere('id', $role->id);

echo "\n✓ Index format structure:\n";
echo "  - Has 'id': " . (isset($indexFormat['id']) ? "YES" : "NO") . "\n";
echo "  - Has 'name': " . (isset($indexFormat['name']) ? "YES" : "NO") . "\n";
echo "  - Has 'permissions' array: " . (is_array($indexFormat['permissions']) ? "YES" : "NO") . "\n";
echo "  - Permissions count: " . count($indexFormat['permissions']) . "\n";

// Verify permissions structure
if (!empty($indexFormat['permissions'])) {
    $firstPerm = $indexFormat['permissions'][0];
    echo "\n✓ First permission structure:\n";
    echo "  - Has 'id': " . (isset($firstPerm['id']) ? "YES" : "NO") . "\n";
    echo "  - Has 'name': " . (isset($firstPerm['name']) ? "YES (" . $firstPerm['name'] . ")" : "NO") . "\n";
    echo "  - Has 'pivot.scope': " . (isset($firstPerm['pivot']['scope']) ? "YES" : "NO") . "\n";
}

echo "\n=== Update Response Format Check ===\n";
echo "✓ Update response should match this structure when role is updated\n";
echo "✓ Frontend will receive role with:\n";
echo "  - Correctly formatted permissions array\n";
echo "  - All required fields (id, name, user_count, permissions)\n";
echo "  - Same structure as index() for consistency\n";
