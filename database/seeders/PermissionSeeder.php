<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'manage_company',
            'manage_permissions',
            'manage_roles',
            'create_project',
            'edit_project',
            'delete_project',
            'view_project',
            'create_task',
            'edit_task',
            'delete_task',
            'assign_task',
            'view_task',
            'invite_users',
            'manage_profile',
            'view_dashboard',
            'manage_settings',
            'collaboration',
        ];

        foreach ($permissions as $permission) {
            \App\Models\Permission::create(['name' => $permission]);
        }
    }
}
