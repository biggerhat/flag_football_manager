<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drills', fn (Blueprint $table) => $table->json('diagram')->nullable());
    }

    public function down(): void
    {
        Schema::table('drills', fn (Blueprint $table) => $table->dropColumn('diagram'));
    }
};
