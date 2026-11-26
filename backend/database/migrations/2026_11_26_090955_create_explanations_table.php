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
    Schema::create('explanations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('concept_id')->constrained()->cascadeOnDelete();
    $table->string('style');
    $table->text('explanation')->nullable();
    $table->text('example')->nullable();
    $table->string('status')->default('pending');
    $table->unsignedTinyInteger('attempts')->default(0);
    $table->timestamps();

    $table->unique(['concept_id', 'style']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('explanations');
    }
};
