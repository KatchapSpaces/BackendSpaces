<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('assigned_admin_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_manager_id')->nullable()->after('assigned_admin_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['assigned_admin_id']);
            $table->dropForeign(['assigned_manager_id']);
            $table->dropColumn(['assigned_admin_id', 'assigned_manager_id']);
        });
    }
};
