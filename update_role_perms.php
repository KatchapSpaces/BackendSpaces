<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Updating Role Permissions ===\n\n";

// Define the new permissions for each role
$rolePermissions = [
    'manager' => [
        'create_project' => 'full',
        'edit_project' => 'full',
        'delete_project' => 'full',
        'view_project' => 'full',
        'create_task' => 'full',
        'edit_task' => 'full',
        'delete_task' => 'full',
        'assign_task' => 'full',
        'view_task' => 'full',
        'manage_profile' => 'full',
        'view_dashboard' => 'full',
        'collaboration' => 'full',
    ],
    'subcontractor' => [
        'view_project' => 'full',
        'view_task' => 'full',
        'edit_task' => 'full',
        'manage_profile' => 'full',
        'view_dashboard' => 'full',
        'collaboration' => 'full',
    ],
    'user' => [
        'view_project' => 'full',
        'view_task' => 'full',
        'manage_profile' => 'full',
        'view_dashboard' => 'full',
        'collaboration' => 'full',
    ],
];

$allPermissions = \App\Models\Permission::all();

foreach ($rolePermissions as $roleName => $perms) {
    $role = \App\Models\Role::where('name', $roleName)->first();
    
    if (!$role) {
        echo "Role not found: {$roleName}\n";
        continue;
    }
    
    echo "Updating role: {$roleName}\n";
    
    // Clear existing permissions
    $role->permissions()->detach();
    echo "  ✓ Cleared existing permissions\n";
    
    // Add new permissions
    $count = 0;
    foreach ($perms as $permName => $scope) {
        $permission = $allPermissions->where('name', $permName)->first();
        
        if ($permission) {
            \App\Models\RolePermission::create([
                'role_id' => $role->id,
                'permission_id' => $permission->id,
                'scope' => $scope,
            ]);
            $count++;
        } else {
            echo "  ✗ Permission not found: {$permName}\n";
        }
    }
    
    echo "  ✓ Added {$count} permissions\n\n";
}

echo "=== Update Complete ===\n";
