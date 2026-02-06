<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = \App\Models\Role::all();
        $permissions = \App\Models\Permission::all();

        // Only set default permissions for roles that don't have any permissions assigned yet
        // This prevents overwriting manually assigned permissions
        $rolePermissions = [
            'super_admin' => [
                'manage_company' => 'full',
                'manage_permissions' => 'full',
                'manage_roles' => 'full',
                'create_project' => 'full',
                'edit_project' => 'full',
                'delete_project' => 'full',
                'view_project' => 'full',
                'create_task' => 'full',
                'edit_task' => 'full',
                'delete_task' => 'full',
                'assign_task' => 'full',
                'view_task' => 'full',
                'invite_users' => 'full',
                'manage_profile' => 'full',
                'view_dashboard' => 'full',
                'manage_settings' => 'full',
                'collaboration' => 'full',
            ],
            'admin' => [
                // Admin has most permissions but not all
                'manage_company' => 'full',
                'create_project' => 'full',
                'edit_project' => 'full',
                'delete_project' => 'full',
                'view_project' => 'full',
                'create_task' => 'full',
                'edit_task' => 'full',
                'delete_task' => 'full',
                'assign_task' => 'full',
                'view_task' => 'full',
                'invite_users' => 'full',
                'manage_profile' => 'full',
                'view_dashboard' => 'full',
                'manage_settings' => 'full',
                'collaboration' => 'full',
            ],
            'manager' => [
                // Manager can manage tasks and view projects but cannot create/edit/delete projects
                'view_project' => 'full',
                'create_task' => 'full',
                'edit_task' => 'full',
                'delete_task' => 'full',
                'assign_task' => 'full',
                'view_task' => 'full',
                'manage_profile' => 'full',
                'view_dashboard' => 'full',
            ],
            'subcontractor' => [
                // Subcontractors can view and update tasks and (by default) participate in projects
                'view_project' => 'full',
                'create_project' => 'full',
                'edit_project' => 'full',
                'delete_project' => 'full',
                'view_task' => 'full',
                'edit_task' => 'full',
                'manage_profile' => 'full',
                'view_dashboard' => 'full',
                'collaboration' => 'full',
            ],
            'user' => [
                // Basic users can view projects and tasks and (by default) create/edit/delete projects
                'view_project' => 'full',
                'create_project' => 'full',
                'edit_project' => 'full',
                'delete_project' => 'full',
                'view_task' => 'full',
                'manage_profile' => 'full',
                'view_dashboard' => 'full',
                'collaboration' => 'full',
            ],
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $role = $roles->where('name', $roleName)->first();
            if (!$role) continue;

            // Only assign default permissions if the role has no permissions assigned yet
            if ($role->permissions()->count() === 0) {
                foreach ($perms as $permName => $scope) {
                    $permission = $permissions->where('name', $permName)->first();
                    if ($permission) {
                        \App\Models\RolePermission::create([
                            'role_id' => $role->id,
                            'permission_id' => $permission->id,
                            'scope' => $scope,
                        ]);
                    }
                }
            }
        }
    }
}
