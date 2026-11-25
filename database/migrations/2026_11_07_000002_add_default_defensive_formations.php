<?php

use App\Models\User;
use App\Support\DefaultFormations;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Existing coaches get the defensive alignments alongside their offensive formations.
        User::query()->each(function (User $user) {
            foreach (DefaultFormations::defense() as $formation) {
                if (! $user->formations()->where('name', $formation['name'])->exists()) {
                    $user->formations()->create($formation);
                }
            }
        });
    }

    public function down(): void
    {
        //
    }
};
