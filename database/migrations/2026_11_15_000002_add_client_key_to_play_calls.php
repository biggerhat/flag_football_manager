<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('play_calls', function (Blueprint $table) {
            // Chosen by the browser per tap, so a retried request cannot log the same call twice.
            $table->string('client_key', 64)->nullable();
            $table->unique(['game_plan_id', 'client_key']);
        });
    }

    public function down(): void
    {
        Schema::table('play_calls', function (Blueprint $table) {
            $table->dropUnique(['game_plan_id', 'client_key']);
            $table->dropColumn('client_key');
        });
    }
};
