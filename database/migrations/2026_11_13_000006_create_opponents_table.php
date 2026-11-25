<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opponents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opponents');
    }
};
