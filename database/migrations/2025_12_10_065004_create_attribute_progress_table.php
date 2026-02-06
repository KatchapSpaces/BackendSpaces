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
        Schema::create('attribute_progress', function (Blueprint $table) {
            $table->id();

            // References
            $table->string('space_id');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('attribute_id');

            // Progress-specific fields
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedTinyInteger('progress')->default(0); // 0-100 %
            $table->unsignedBigInteger('assigned_to')->nullable();

            // Creator
            $table->unsignedBigInteger('created_by');

            $table->timestamps();

            // Foreign keys
            $table->foreign('space_id')->references('number')->on('spaces')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
            $table->foreign('attribute_id')->references('id')->on('attributes')->cascadeOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attribute_progress', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropForeign(['created_by']);
        });

        Schema::dropIfExists('attribute_progress');
    }
};
