<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category');
            $table->unsignedSmallInteger('duration')->default(10);
            $table->unsignedSmallInteger('players')->nullable();
            $table->string('equipment')->nullable();
            $table->text('instructions')->nullable();
            $table->text('coaching_points')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drills');
    }
};
