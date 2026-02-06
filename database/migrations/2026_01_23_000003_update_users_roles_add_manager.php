<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Expand the enum to include manager, subcontractor, basic, granular
        DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('admin','project_manager','site_team','manager','subcontractor','basic','granular') NOT NULL DEFAULT 'site_team'");
    }

    public function down()
    {
        // Revert back to original three roles
        DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('admin','project_manager','site_team') NOT NULL DEFAULT 'site_team'");
    }
};
