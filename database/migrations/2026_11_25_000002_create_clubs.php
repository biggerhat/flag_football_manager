<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('organization_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16); // owner | admin
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
            $table->unique('user_id'); // one club per person, for now
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('team_user', function (Blueprint $table) {
            // Membership granted automatically because the person administers the team's club.
            $table->boolean('via_club')->default(false);
        });

        Schema::table('team_invitations', function (Blueprint $table) {
            // Set when accepting also makes the person an administrator of this club.
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });
        Schema::table('team_user', function (Blueprint $table) {
            $table->dropColumn('via_club');
        });
        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('organizations');
    }
};
