<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add new role values to the enum column. Using raw statement for compatibility.
        $sql = "ALTER TABLE `users` MODIFY `role` ENUM('admin','project_manager','site_team','subcontractor','basic','granular') NOT NULL DEFAULT 'site_team'";
        Schema::getConnection()->getPdo()->exec($sql);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        $sql = "ALTER TABLE `users` MODIFY `role` ENUM('admin','project_manager','site_team') NOT NULL DEFAULT 'site_team'";
        Schema::getConnection()->getPdo()->exec($sql);
    }
};
