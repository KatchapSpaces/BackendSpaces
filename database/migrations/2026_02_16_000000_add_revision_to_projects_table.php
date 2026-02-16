<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'revision')) {
                // Some DB drivers (SQLite) don't support the ->after() modifier
                // so only use ->after() on MySQL to avoid migration failures.
                $driver = Schema::getConnection()->getDriverName();
                if ($driver === 'mysql') {
                    $table->string('revision')->nullable()->after('site_number');
                } else {
                    $table->string('revision')->nullable();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'revision')) {
                $table->dropColumn('revision');
            }
        });
    }
};
