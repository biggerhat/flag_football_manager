<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_plans', function (Blueprint $table) {
            $table->string('location', 120)->nullable();
            $table->string('kickoff', 5)->nullable();
            $table->unsignedSmallInteger('score_for')->nullable();
            $table->unsignedSmallInteger('score_against')->nullable();
        });

        Schema::create('play_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_plan_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('play_id')->constrained()->cascadeOnDelete();
            $table->string('result', 12);
            $table->smallInteger('yards')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'play_id']);
            $table->index('game_plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('play_calls');

        Schema::table('game_plans', function (Blueprint $table) {
            $table->dropColumn(['location', 'kickoff', 'score_for', 'score_against']);
        });
    }
};
