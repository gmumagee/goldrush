<?php

namespace App\Console\Commands;

use App\Services\AuditLogArchiveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ArchiveAuditLog extends Command
{
    protected $signature = 'audit-log:archive {--days=}';

    protected $description = 'Archive audit log rows older than the retention window to CSV, then prune them.';

    public function __construct(protected AuditLogArchiveService $archiveService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $daysOption = $this->option('days');
        $days = $daysOption !== null && $daysOption !== ''
            ? (int) $daysOption
            : 30;

        try {
            $summary = $this->archiveService->archiveOlderThan($days);
        } catch (Throwable $exception) {
            $this->error('Audit log archive failed: '.$exception->getMessage());

            Log::error('audit-log:archive command failed.', [
                'days' => $days,
                'error' => $exception->getMessage(),
            ]);

            return self::FAILURE;
        }

        if ($summary['archived'] === 0) {
            $message = sprintf(
                'No audit log rows older than %s were found.',
                $summary['cutoff']->toDateTimeString()
            );

            $this->info($message);
            Log::info('Audit log archive no-op.', [
                'days' => $days,
                'cutoff' => $summary['cutoff']->toDateTimeString(),
            ]);

            return self::SUCCESS;
        }

        $message = sprintf(
            'Archived %d audit log row(s) to %s and deleted %d row(s).',
            $summary['archived'],
            $summary['file_path'],
            $summary['deleted']
        );

        $this->info($message);
        Log::info('Audit log archive completed.', [
            'days' => $days,
            'cutoff' => $summary['cutoff']->toDateTimeString(),
            'archived' => $summary['archived'],
            'deleted' => $summary['deleted'],
            'file_path' => $summary['file_path'],
        ]);

        return self::SUCCESS;
    }
}
