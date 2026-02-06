<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Role, App\Models\User;

echo "=== COMPLETE END-TO-END FLOW TEST ===\n\n";

// 1. Check database state
echo "1. DATABASE STATE:\n";
$roles = Role::all();
foreach ($roles as $role) {
    $permCount = $role->permissions()->count();
    echo "   {$role->name}: {$permCount} permissions\n";
}

// 2. Check super_admin user
echo "\n2. SUPER ADMIN USER:\n";
$superAdmin = User::where('email', 'superadmin@example.com')->first() ?? User::whereHas('role', function($q) {
    $q->where('name', 'super_admin');
})->first();

if ($superAdmin) {
    echo "   Found: {$superAdmin->name} ({$superAdmin->email})\n";
    echo "   Role: {$superAdmin->role->name}\n";
} else {
    echo "   ❌ No super admin found!\n";
}

// 3. Simulate API /roles response
echo "\n3. API /ROLES RESPONSE:\n";
$rolesData = Role::query()->with('permissions')->get();
$rolesForApi = $rolesData->map(function($role) {
    return [
        'id' => $role->id,
        'name' => $role->name,
        'permissions_count' => $role->permissions->count(),
        'permissions' => $role->permissions->map(function($p) {
            return ['id' => $p->id, 'name' => $p->name];
        })->toArray()
    ];
});

foreach ($rolesForApi as $role) {
    echo "   {$role['name']}: {$role['permissions_count']} permissions\n";
    if ($role['permissions_count'] > 0) {
        echo "      " . implode(', ', array_map(fn($p) => $p['name'], $role['permissions'])) . "\n";
    }
}

// 4. Check if middleware is registered
echo "\n4. MIDDLEWARE CHECK:\n";
$middlewareFile = __DIR__ . '/app/Http/Middleware/DisableCache.php';
if (file_exists($middlewareFile)) {
    echo "   ✓ DisableCache middleware file exists\n";
} else {
    echo "   ❌ DisableCache middleware file NOT found\n";
}

// 5. Check bootstrap app.php
$bootstrapFile = __DIR__ . '/bootstrap/app.php';
$bootstrapContent = file_get_contents($bootstrapFile);
if (strpos($bootstrapContent, 'DisableCache') !== false) {
    echo "   ✓ DisableCache registered in bootstrap/app.php\n";
} else {
    echo "   ❌ DisableCache NOT registered in bootstrap/app.php\n";
}

// 6. Check routes
$routesFile = __DIR__ . '/routes/api.php';
$routesContent = file_get_contents($routesFile);
if (strpos($routesContent, "'no-cache'") !== false) {
    echo "   ✓ 'no-cache' middleware applied to routes\n";
} else {
    echo "   ❌ 'no-cache' middleware NOT applied to routes\n";
}

echo "\n=== END OF DIAGNOSTIC ===\n";
