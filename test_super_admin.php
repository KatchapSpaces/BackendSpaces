<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing super_admin system...\n";
echo "===============================\n";

// Check roles
$roles = \App\Models\Role::all();
echo "Available roles:\n";
foreach($roles as $role) {
    echo "- {$role->name}\n";
}
echo "\n";

// Check super_admin permissions
$superAdminRole = \App\Models\Role::where('name', 'super_admin')->first();
if ($superAdminRole) {
    $permissions = $superAdminRole->permissions;
    echo "Super admin permissions: " . $permissions->count() . "\n";
    echo "Super admin has all permissions: " . ($permissions->count() > 10 ? 'YES' : 'NO') . "\n";
} else {
    echo "ERROR: super_admin role not found!\n";
}

echo "\n";

// Check seeded super admin user
$superAdminUser = \App\Models\User::where('email', 'superadmin123@gmail.com')->first();
if ($superAdminUser) {
    echo "Seeded super admin user: {$superAdminUser->name} ({$superAdminUser->email})\n";
    echo "Role: " . ($superAdminUser->role ? $superAdminUser->role->name : 'None') . "\n";
    echo "Has permission to invite_users: " . ($superAdminUser->hasPermission('invite_users') ? 'YES' : 'NO') . "\n";
} else {
    echo "ERROR: Seeded super admin user not found!\n";
}

echo "\nTest completed!\n";