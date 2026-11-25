<?php

use App\Models\User;
use App\Support\DefaultFormations;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->json('diagram');
            $table->timestamps();
        });

        // Existing users get the built-in formations as editable rows.
        User::query()->each(function (User $user) {
            foreach (DefaultFormations::all() as $formation) {
                $user->formations()->create($formation);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formations');
    }
};
