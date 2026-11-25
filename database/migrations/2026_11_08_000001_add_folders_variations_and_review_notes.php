<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plays', function (Blueprint $table) {
            $table->string('folder', 40)->nullable()->after('tags');
            $table->foreignId('parent_id')->nullable()->after('user_id')->constrained('plays')->nullOnDelete();
        });

        Schema::table('practices', function (Blueprint $table) {
            $table->text('review_notes')->nullable()->after('notes');
        });

        Schema::create('game_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('opponent')->nullable();
            $table->date('date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('game_plan_play', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('play_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_plan_play');
        Schema::dropIfExists('game_plans');

        Schema::table('practices', function (Blueprint $table) {
            $table->dropColumn('review_notes');
        });

        Schema::table('plays', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('folder');
        });
    }
};
