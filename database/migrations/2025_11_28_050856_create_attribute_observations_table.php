<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
{
    Schema::create('attribute_observations', function (Blueprint $table) {
        $table->id();
        $table->string('space_id'); 
        $table->unsignedBigInteger('service_id');
        $table->unsignedBigInteger('attribute_id');
        $table->text('description');
        $table->date('deadline')->nullable();
        $table->string('status')->default('open');     // open/closed
        $table->unsignedBigInteger('created_by')->nullable(); 
        $table->string('company_name')->nullable();   
        $table->unsignedBigInteger('assigned_to')->nullable();
        $table->foreign('assigned_to')->references('id')->on('site_teams')->nullOnDelete();
        $table->timestamps();
        $table->foreign('space_id')->references('number')->on('spaces')->cascadeOnDelete();
        $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
        $table->foreign('attribute_id')->references('id')->on('attributes')->cascadeOnDelete();
        $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
    });
}
   public function down(): void
{
    Schema::table('attribute_observations', function (Blueprint $table) {
        $table->dropForeign(['assigned_to']);
        $table->dropColumn('company_name'); 
        $table->dropColumn('assigned_to');   
    });

    Schema::dropIfExists('attribute_observations');
}
};
