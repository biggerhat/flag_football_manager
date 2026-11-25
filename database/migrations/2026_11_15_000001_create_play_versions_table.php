<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('play_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('play_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->json('diagram');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['play_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('play_versions');
    }
};
