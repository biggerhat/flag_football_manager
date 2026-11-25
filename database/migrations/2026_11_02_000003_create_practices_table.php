<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('date')->nullable();
            $table->time('start_time')->nullable();
            $table->unsignedSmallInteger('duration')->default(60);
            $table->string('location')->nullable();
            $table->text('objectives')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_template')->default(false);
            $table->timestamps();
        });

        Schema::create('practice_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drill_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('play_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->unsignedSmallInteger('duration')->default(10);
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_segments');
        Schema::dropIfExists('practices');
    }
};
