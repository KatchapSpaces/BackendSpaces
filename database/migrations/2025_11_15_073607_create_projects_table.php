<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('site_number')->nullable();
            $table->string('timezone')->nullable();
            $table->text('address')->nullable();
            $table->string('location')->nullable();
            $table->string('measurement')->nullable();
            $table->string('image_path')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
     public function down(): void {
        Schema::dropIfExists('projects');
    }
};
