<?php

namespace App\Console\Commands;

use App\Models\AccountBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class PruneAccountBackups extends Command
{
    protected $signature = 'backups:prune-account-bundles {--days=}';

    protected $description = 'Delete stored account backup bundles older than the configured retention window.';

    public function handle(): int
    {
        $daysOption = $this->option('days');
        $retentionDays = $daysOption !== null && $daysOption !== ''
            ? (int) $daysOption
            : (int) config('backups.retention_days', 90);

        if ($retentionDays < 1) {
            $this->error('Retention days must be a positive integer.');

            return self::FAILURE;
        }

        $cutoff = Carbon::now()->subDays($retentionDays);
        $deleted = 0;

        AccountBackup::query()
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($backups) use (&$deleted) {
                foreach ($backups as $backup) {
                    if ($backup->file_disk && $backup->file_path) {
                        Storage::disk($backup->file_disk)->delete($backup->file_path);
                    }

                    $backup->delete();
                    $deleted++;
                }
            });

        $this->info(sprintf('Deleted %d expired account backup(s).', $deleted));

        return self::SUCCESS;
    }
}
