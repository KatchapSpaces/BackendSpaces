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
       Schema::create('service_attribute_copies', function (Blueprint $table) {
    $table->id();

    $table->unsignedBigInteger('source_service_id');
    $table->unsignedBigInteger('target_service_id');

    $table->unsignedBigInteger('attribute_id');

    $table->unsignedBigInteger('copied_by')->nullable();

    $table->timestamps();

    $table->foreign('source_service_id')
        ->references('id')->on('services')
        ->cascadeOnDelete();

    $table->foreign('target_service_id')
        ->references('id')->on('services')
        ->cascadeOnDelete();

    $table->foreign('attribute_id')
        ->references('id')->on('attributes')
        ->cascadeOnDelete();

    $table->foreign('copied_by')
        ->references('id')->on('users')
        ->nullOnDelete();

   $table->unique(
    ['source_service_id', 'target_service_id', 'attribute_id'],
    'svc_attr_copy_unique'
);


});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_attribute_copies');
    }
};
