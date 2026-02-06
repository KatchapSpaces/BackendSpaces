<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Add 'admin' and 'subcontractor' roles to the site_teams role enum
        DB::statement("ALTER TABLE `site_teams` MODIFY `role` ENUM('admin', 'manager', 'design_team', 'basic', 'granular', 'subcontractor') NOT NULL");
    }

    public function down(): void
    {
        // Remove 'admin' and 'subcontractor' roles from the site_teams role enum
        DB::statement("ALTER TABLE `site_teams` MODIFY `role` ENUM('manager', 'design_team', 'basic', 'granular') NOT NULL");
    }
};
