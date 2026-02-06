<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EnsureAdminPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $required = [
            'view_project', 'create_project', 'edit_project', 'delete_project',
            'manage_settings', 'collaboration'
        ];

        $permissions = \App\Models\Permission::whereIn('name', $required)->pluck('id')->toArray();

        $admin = \App\Models\Role::where('name', 'admin')->first();
        if ($admin) {
            // Sync without detaching other permissions but ensure required exist
            $existing = $admin->permissions()->get()->pluck('id')->toArray();
            $toAttach = array_diff($permissions, $existing);
            foreach ($toAttach as $permId) {
                \App\Models\RolePermission::create([
                    'role_id' => $admin->id,
                    'permission_id' => $permId,
                    'scope' => 'full',
                ]);
            }
        }

        // Also ensure manager/subcontractor/basic have project permissions if desired
        $manager = \App\Models\Role::where('name', 'manager')->first();
        if ($manager) {
            $mgrPerms = \App\Models\Permission::whereIn('name', ['view_project', 'create_project', 'edit_project', 'delete_project'])->pluck('id')->toArray();
            $existing = $manager->permissions()->get()->pluck('id')->toArray();
            $toAttach = array_diff($mgrPerms, $existing);
            foreach ($toAttach as $permId) {
                \App\Models\RolePermission::create([
                    'role_id' => $manager->id,
                    'permission_id' => $permId,
                    'scope' => 'full',
                ]);
            }
        }

        $sub = \App\Models\Role::where('name', 'subcontractor')->first();
        if ($sub) {
            $subPerms = \App\Models\Permission::whereIn('name', ['view_project', 'view_task', 'manage_profile', 'collaboration'])->pluck('id')->toArray();
            $existing = $sub->permissions()->get()->pluck('id')->toArray();
            $toAttach = array_diff($subPerms, $existing);
            foreach ($toAttach as $permId) {
                \App\Models\RolePermission::create([
                    'role_id' => $sub->id,
                    'permission_id' => $permId,
                    'scope' => 'full',
                ]);
            }
        }

        $basic = \App\Models\Role::where('name', 'user')->first();
        if ($basic) {
            $basicPerms = \App\Models\Permission::whereIn('name', ['view_project', 'view_task', 'manage_profile', 'collaboration'])->pluck('id')->toArray();
            $existing = $basic->permissions()->get()->pluck('id')->toArray();
            $toAttach = array_diff($basicPerms, $existing);
            foreach ($toAttach as $permId) {
                \App\Models\RolePermission::create([
                    'role_id' => $basic->id,
                    'permission_id' => $permId,
                    'scope' => 'full',
                ]);
            }
        }
    }
}
