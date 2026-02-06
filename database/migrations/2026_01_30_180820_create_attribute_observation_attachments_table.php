<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_observation_attachments', function (Blueprint $table) {
            $table->id();
            // Foreign key to attribute_observations
            $table->unsignedBigInteger('attribute_observation_id');
            // File information
            $table->string('file_path');
          $table->enum('file_type', ['image','pdf','doc']);
            $table->timestamps();
            // Foreign key constraint with short name (MySQL safe)
            $table->foreign(
                'attribute_observation_id',
                'aoa_attr_obs_fk' // short FK name
            )
            ->references('id')
            ->on('attribute_observations')
            ->onDelete('cascade');

            // Optional: index for faster queries
            $table->index('attribute_observation_id');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('attribute_observation_attachments');
    }
};