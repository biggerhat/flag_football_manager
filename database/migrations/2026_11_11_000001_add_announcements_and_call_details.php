<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('announcement', 500)->nullable();
        });

        Schema::table('play_calls', function (Blueprint $table) {
            $table->unsignedTinyInteger('down')->nullable();
            $table->string('note', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('play_calls', function (Blueprint $table) {
            $table->dropColumn(['down', 'note']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('announcement');
        });
    }
};
