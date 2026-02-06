<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('spaces', function (Blueprint $table) {
            $table->id();

            $table->foreignId('floorplan_id')
                ->constrained('floor_plans')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('number')->unique();

            // NEW FIELDS
            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('site_teams')
                ->nullOnDelete();

            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->float('x'); // 0–1 relative to PDF
            $table->float('y'); // 0–1 relative to PDF

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void {
        Schema::dropIfExists('spaces');
    }
};
