<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
       Schema::create('site_teams', function (Blueprint $table) {
    $table->id();

    $table->foreignId('user_id') // Link to users table
        ->constrained()
        ->onDelete('cascade');

    $table->foreignId('project_id')
        ->constrained()
        ->onDelete('cascade');

    $table->enum('role', [
        'admin',
        'manager',
        'design_team',
        'basic',
        'granular',
        'subcontractor'
    ]);

    $table->foreignId('created_by')
        ->constrained('users')
        ->onDelete('cascade');

    $table->timestamps();
});

    }

    public function down(): void
    {
        Schema::dropIfExists('site_teams');
    }
};
