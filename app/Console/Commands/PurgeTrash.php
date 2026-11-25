<?php

namespace App\Console\Commands;

use App\Models\Drill;
use App\Models\Formation;
use App\Models\GamePlan;
use App\Models\Play;
use App\Models\Practice;
use Illuminate\Console\Command;

class PurgeTrash extends Command
{
    protected $signature = 'trash:purge {--days=30 : Delete items trashed longer ago than this}';

    protected $description = 'Permanently delete soft-deleted items older than the Trash retention window';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));
        $total = 0;

        foreach ([Play::class, Drill::class, Formation::class, Practice::class, GamePlan::class] as $class) {
            $total += $class::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();
        }

        $this->info("Purged {$total} item(s).");

        return self::SUCCESS;
    }
}
