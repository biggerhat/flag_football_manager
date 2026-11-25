<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plays', function (Blueprint $table) {
            $table->index(['team_id', 'playbook_id']);
            $table->index(['team_id', 'updated_at']);
            $table->index('parent_id');
        });

        Schema::table('playbooks', fn (Blueprint $table) => $table->index('team_id'));
        Schema::table('drills', fn (Blueprint $table) => $table->index(['team_id', 'category']));
        Schema::table('formations', fn (Blueprint $table) => $table->index('team_id'));
        Schema::table('game_plans', fn (Blueprint $table) => $table->index(['team_id', 'date']));
        Schema::table('practices', fn (Blueprint $table) => $table->index(['team_id', 'date']));

        Schema::table('practice_segments', function (Blueprint $table) {
            $table->index('practice_id');
            $table->index('drill_id');
            $table->index('play_id');
        });

        Schema::table('game_plan_play', fn (Blueprint $table) => $table->index('play_id'));
    }

    public function down(): void
    {
        Schema::table('plays', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'playbook_id']);
            $table->dropIndex(['team_id', 'updated_at']);
            $table->dropIndex(['parent_id']);
        });

        Schema::table('playbooks', fn (Blueprint $table) => $table->dropIndex(['team_id']));
        Schema::table('drills', fn (Blueprint $table) => $table->dropIndex(['team_id', 'category']));
        Schema::table('formations', fn (Blueprint $table) => $table->dropIndex(['team_id']));
        Schema::table('game_plans', fn (Blueprint $table) => $table->dropIndex(['team_id', 'date']));
        Schema::table('practices', fn (Blueprint $table) => $table->dropIndex(['team_id', 'date']));

        Schema::table('practice_segments', function (Blueprint $table) {
            $table->dropIndex(['practice_id']);
            $table->dropIndex(['drill_id']);
            $table->dropIndex(['play_id']);
        });

        Schema::table('game_plan_play', fn (Blueprint $table) => $table->dropIndex(['play_id']));
    }
};
