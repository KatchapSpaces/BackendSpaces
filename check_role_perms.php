<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Checking Role Permissions ===\n\n";

$roles = ['manager', 'subcontractor', 'user', 'admin', 'super_admin'];

foreach ($roles as $roleName) {
    $role = \App\Models\Role::where('name', $roleName)->first();
    
    if ($role) {
        echo "Role: {$role->name}\n";
        echo "  Permissions (" . $role->permissions()->count() . "):\n";
        
        foreach ($role->permissions as $perm) {
            echo "    ✓ " . $perm->name . "\n";
        }
        echo "\n";
    }
}
