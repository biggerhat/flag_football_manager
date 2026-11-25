<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            // Invitations created together (one person, several teams) share a batch and are accepted together.
            $table->string('batch', 40)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->dropColumn('batch');
        });
    }
};
