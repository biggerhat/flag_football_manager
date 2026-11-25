<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playbooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::table('plays', function (Blueprint $table) {
            $table->foreignId('playbook_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        // Everything coaches already built goes into one starting playbook.
        foreach (DB::table('plays')->distinct()->pluck('user_id') as $userId) {
            $playbookId = DB::table('playbooks')->insertGetId([
                'user_id' => $userId,
                'name' => 'My playbook',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('plays')->where('user_id', $userId)->update(['playbook_id' => $playbookId]);
        }
    }

    public function down(): void
    {
        Schema::table('plays', function (Blueprint $table) {
            $table->dropConstrainedForeignId('playbook_id');
        });

        Schema::dropIfExists('playbooks');
    }
};
