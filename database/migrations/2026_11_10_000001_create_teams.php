<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OWNED = ['playbooks', 'plays', 'drills', 'formations', 'practices', 'game_plans'];

    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->timestamps();
        });

        Schema::create('team_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 12)->default('coach');
            $table->timestamps();

            $table->unique(['team_id', 'user_id']);
        });

        Schema::create('team_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 12)->default('coach');
            $table->string('token', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['team_id', 'email']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_team_id')->nullable()->after('remember_token')->constrained('teams')->nullOnDelete();
        });

        foreach (self::OWNED as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('team_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
            });
        }

        // Every existing coach becomes the owner of a team holding all of their data.
        foreach (DB::table('users')->get(['id', 'name']) as $user) {
            $teamId = DB::table('teams')->insertGetId([
                'name' => "{$user->name}'s team",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('team_user')->insert([
                'team_id' => $teamId,
                'user_id' => $user->id,
                'role' => 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('users')->where('id', $user->id)->update(['current_team_id' => $teamId]);

            foreach (self::OWNED as $name) {
                DB::table($name)->where('user_id', $user->id)->update(['team_id' => $teamId]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::OWNED as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('team_id');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_team_id');
        });

        Schema::dropIfExists('team_invitations');
        Schema::dropIfExists('team_user');
        Schema::dropIfExists('teams');
    }
};
