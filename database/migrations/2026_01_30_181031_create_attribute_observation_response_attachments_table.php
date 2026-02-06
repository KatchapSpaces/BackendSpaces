<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
      Schema::create('attribute_observation_response_attachments', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('attribute_observation_response_id');
    $table->string('file_path');
    $table->enum('file_type', ['image','pdf','doc']);
    $table->timestamps();

    $table->foreign('attribute_observation_response_id', 'aor_attach_fk')
          ->references('id')
          ->on('attribute_observation_responses')
          ->cascadeOnDelete();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_observation_response_attachments');
    }
};
