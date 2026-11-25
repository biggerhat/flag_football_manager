<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('play_calls', function (Blueprint $table) {
            $table->foreignId('player_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('play_calls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('player_id');
        });
    }
};
