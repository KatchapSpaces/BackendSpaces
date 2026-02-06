<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('attribute_images', function (Blueprint $table) {
            $table->id();
             $table->string('space_id'); 
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('attribute_id');
            $table->string('image_path');  // uploaded file
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();   // user_id
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
            // Foreign keys
            $table->foreign('space_id')->references('number')->on('spaces')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
            $table->foreign('attribute_id')->references('id')->on('attributes')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attribute_images');
    }
};
