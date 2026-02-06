<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Set company_id to null for any users who are super_admins
        DB::table('users')
            ->whereIn('role_id', function ($query) {
                $query->select('id')->from('roles')->where('name', 'super_admin');
            })
            ->update(['company_id' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: we cannot reliably restore previous company associations
    }
};
