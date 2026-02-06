<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdminRole = \App\Models\Role::where('name', 'super_admin')->first();

        // AGGRESSIVELY CLEAN UP: Delete ALL existing super admin users except superadmin123@gmail.com
        \App\Models\User::where('email', '!=', 'superadmin123@gmail.com')
            ->whereHas('role', function($query) {
                $query->where('name', 'super_admin');
            })
            ->delete();

        // Delete the old superadmin@gmail.com specifically
        \App\Models\User::where('email', 'superadmin@gmail.com')->delete();

        // Check if superadmin123@gmail.com already exists
        $existingSuperAdmin = \App\Models\User::where('email', 'superadmin123@gmail.com')->first();
        if ($existingSuperAdmin) {
            // Ensure they have the correct role and status
            $existingSuperAdmin->update([
                'role_id' => $superAdminRole->id,
                'status' => 'active'
            ]);
            echo "Super admin superadmin123@gmail.com updated.\n";
            return;
        }

        // Create default company for Super Admin
        $company = \App\Models\Company::firstOrCreate([
            'email' => 'admin@system.com'
        ], [
            'name' => 'System Administration',
            'phone' => '+1234567890',
            'address' => 'System Administration Office',
            'status' => 'active',
            'settings' => json_encode([
                'max_users' => 1,
                'features' => ['system_administration']
            ])
        ]);

        \App\Models\User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin123@gmail.com',
            'password' => bcrypt('superadmin123'),
            'email_verified_at' => now(),
            'role_id' => $superAdminRole->id,
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        echo "Super admin superadmin123@gmail.com created.\n";
    }
}
