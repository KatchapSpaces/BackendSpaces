<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
      Schema::create('attribute_observation_responses', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('attribute_observation_id');
    $table->unsignedBigInteger('site_team_id'); // <-- define this
    $table->index('site_team_id');
    $table->text('description')->nullable();
    $table->enum('status', ['submitted', 'approved', 'rejected'])->default('submitted');
    $table->unsignedBigInteger('reviewed_by')->nullable();
    $table->text('review_comment')->nullable();
    $table->timestamps();

    $table->foreign('attribute_observation_id')
          ->references('id')
          ->on('attribute_observations')
          ->cascadeOnDelete();

    $table->foreign('site_team_id')
          ->references('id')
          ->on('site_teams')
          ->cascadeOnDelete();

    $table->foreign('reviewed_by')
          ->references('id')
          ->on('users')
          ->nullOnDelete();
});

    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_observation_responses');
    }
};
