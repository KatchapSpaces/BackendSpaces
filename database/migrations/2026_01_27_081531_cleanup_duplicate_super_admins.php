<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get super admin role
        $superAdminRole = Role::where('name', 'super_admin')->first();

        if ($superAdminRole) {
            // Delete ALL users with super_admin role except superadmin123@gmail.com
            User::where('role_id', $superAdminRole->id)
                ->where('email', '!=', 'superadmin123@gmail.com')
                ->delete();

            // Also delete any users with superadmin@gmail.com email regardless of role
            User::where('email', 'superadmin@gmail.com')->delete();

            // Ensure superadmin123@gmail.com exists and has correct role
            $superAdmin = User::where('email', 'superadmin123@gmail.com')->first();
            if ($superAdmin) {
                $superAdmin->update([
                    'role_id' => $superAdminRole->id,
                    'status' => 'active'
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Nothing to reverse - this is a cleanup migration
    }
};
