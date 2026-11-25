<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
