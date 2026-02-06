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
    Schema::create('program_progress', function (Blueprint $table) {
        $table->id();

        $table->unsignedBigInteger('attribute_program_id');
        $table->unsignedTinyInteger('progress'); // 0–100
        $table->unsignedBigInteger('updated_by');

        $table->timestamps();

        $table->foreign('attribute_program_id')
              ->references('id')
              ->on('attribute_program')
              ->cascadeOnDelete();

        $table->foreign('updated_by')
              ->references('id')
              ->on('users')
              ->cascadeOnDelete();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_progress');
    }
};
