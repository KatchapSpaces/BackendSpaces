<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'super_admin'],
            ['name' => 'admin'],
            ['name' => 'manager'],
            ['name' => 'subcontractor'],
            ['name' => 'user'],
            ['name' => 'design_team'],
            ['name' => 'basic'],
        ];

        foreach ($roles as $role) {
            \App\Models\Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}
