<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_assignee', function (Blueprint $table) {
            $table->id();
            $table->string('space_id');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('attribute_id');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('created_by');

            $table->timestamps();

            // Foreign Keys
            $table->foreign('space_id')
                  ->references('number')->on('spaces')
                  ->cascadeOnDelete();

            $table->foreign('service_id')
                  ->references('id')->on('services')
                  ->cascadeOnDelete();

            $table->foreign('attribute_id')
                  ->references('id')->on('attributes')
                  ->cascadeOnDelete();

            $table->foreign('assigned_to')
                  ->references('id')->on('users')
                  ->nullOnDelete();

            $table->foreign('created_by')
                  ->references('id')->on('users')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_assignee');
    }
};
