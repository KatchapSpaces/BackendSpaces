<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RevokeManagerProjectPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $manager = \App\Models\Role::where('name', 'manager')->first();
        if (!$manager) return;

        $toRemove = ['create_project', 'edit_project', 'delete_project'];
        $permissions = \App\Models\Permission::whereIn('name', $toRemove)->get();

        foreach ($permissions as $perm) {
            // delete any RolePermission entries
            \App\Models\RolePermission::where('role_id', $manager->id)
                ->where('permission_id', $perm->id)
                ->delete();
        }

        // ensure manager role doesn't have these attached via relationship
        $manager->permissions()->whereIn('name', $toRemove)->detach();
    }
}
