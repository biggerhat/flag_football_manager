<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('formation')->nullable();
            $table->string('category')->default('run');
            $table->string('difficulty')->default('easy');
            $table->boolean('beginner_friendly')->default(true);
            $table->text('notes')->nullable();
            $table->json('diagram');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plays');
    }
};
