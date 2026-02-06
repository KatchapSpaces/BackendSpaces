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
        Schema::create('attribute_held_by_responses', function (Blueprint $table) {
            $table->id();

            // Link to Held By
            $table->unsignedBigInteger('held_by_id');

            // Response text
            $table->text('response_text');

            // Optional attachment
            $table->string('attachment')->nullable();

            // User who responded
            $table->unsignedBigInteger('responded_by');

            $table->timestamps();

            // Foreign keys
            $table->foreign('held_by_id')->references('id')->on('attribute_held_by')->cascadeOnDelete();
            $table->foreign('responded_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attribute_held_by_responses');
    }
};
