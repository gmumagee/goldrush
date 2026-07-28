<?php

namespace App\Jobs;

use App\Models\AccountBackup;
use App\Services\AccountBackupArchiveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateAccountBackup implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1200;

    public function __construct(public int $accountBackupId)
    {
    }

    public function handle(AccountBackupArchiveService $archiveService): void
    {
        $backup = AccountBackup::query()
            ->with('account')
            ->find($this->accountBackupId);

        if (! $backup) {
            Log::warning('Account backup job skipped because the backup record no longer exists.', [
                'account_backup_id' => $this->accountBackupId,
            ]);

            return;
        }

        if (! $backup->account) {
            $backup->forceFill([
                'status' => AccountBackup::STATUS_FAILED,
                'failure_message' => 'The target account no longer exists.',
                'failed_at' => now(),
            ])->save();

            return;
        }

        try {
            $result = $archiveService->generate($backup, $backup->account);

            $backup->forceFill([
                'status' => AccountBackup::STATUS_READY,
                'file_disk' => $result['file_disk'],
                'file_path' => $result['file_path'],
                'file_name' => $result['file_name'],
                'file_size_bytes' => $result['file_size_bytes'],
                'row_counts' => $result['row_counts'],
                'failure_message' => null,
                'ready_at' => now(),
                'failed_at' => null,
            ])->save();
        } catch (\Throwable $exception) {
            $backup->forceFill([
                'status' => AccountBackup::STATUS_FAILED,
                'failure_message' => $exception->getMessage(),
                'failed_at' => now(),
            ])->save();

            throw $exception;
        }
    }
}
