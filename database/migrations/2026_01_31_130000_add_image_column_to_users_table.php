<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'image')) {
                $table->string('image')->nullable()->after('avatar');
            }
        });

        // Backfill image column from avatar for existing users
        try {
            DB::statement('UPDATE users SET image = avatar WHERE avatar IS NOT NULL');
        } catch (\Exception $e) {
            // If DB is not available at migration time, log and continue; user can run backfill command later.
            \Log::warning('AddImageColumn migration: failed to backfill image from avatar', ['exception' => $e->getMessage()]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'image')) {
                $table->dropColumn('image');
            }
        });
    }
};
