<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Checking Super Admin Permissions ===\n\n";

$superAdminRole = \App\Models\Role::where('name', 'super_admin')->first();
if ($superAdminRole) {
    echo "✓ Super Admin Role found (ID: {$superAdminRole->id})\n";
    echo "  Permissions assigned: " . $superAdminRole->permissions()->count() . "\n";
    
    $invitePerms = $superAdminRole->permissions()->where('name', 'invite_users')->first();
    echo "  Has invite_users permission: " . ($invitePerms ? 'YES' : 'NO') . "\n";
    
    echo "\n  All Permissions:\n";
    foreach ($superAdminRole->permissions as $perm) {
        echo "    - " . $perm->name . "\n";
    }
} else {
    echo "✗ Super Admin role not found!\n";
}

echo "\n=== Checking Self-Registered Users ===\n\n";

$users = \App\Models\User::where('email', '!=', 'superadmin123@gmail.com')->get();
foreach ($users as $user) {
    echo "User: {$user->email}\n";
    echo "  Role ID: " . ($user->role_id ? $user->role_id : 'NULL') . "\n";
    echo "  Role: " . ($user->role ? $user->role->name : 'NULL') . "\n";
    if ($user->role) {
        echo "  Has invite_users: " . ($user->hasPermission('invite_users') ? 'YES' : 'NO') . "\n";
    }
    echo "\n";
}
