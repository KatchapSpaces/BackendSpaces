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
        Schema::create('attribute_held_by', function (Blueprint $table) {
            $table->id();

            // Space, Service, Attribute
            $table->string('space_id'); // link to spaces.number
            $table->unsignedBigInteger('service_id'); // link to services.id
            $table->unsignedBigInteger('attribute_id'); // link to attributes.id

            // Creator of Held By
            $table->unsignedBigInteger('created_by');

            // Assigned person
            $table->unsignedBigInteger('assigned_to')->nullable();

            // Reason / motive
            $table->text('motive');

            // Status: pending, responded, accepted, rejected, closed
            $table->enum('status', ['pending', 'responded', 'accepted', 'rejected', 'closed'])->default('pending');

            $table->timestamps();

            // Foreign keys
            $table->foreign('space_id')->references('number')->on('spaces')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
            $table->foreign('attribute_id')->references('id')->on('attributes')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('assigned_to')->references('id')->on('site_teams')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attribute_held_by');
    }
};
