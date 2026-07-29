<?php

namespace App\Services;

use App\Models\AuditLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class AuditLogArchiveService
{
    /**
     * @return array{archived:int, deleted:int, file_path:?string, cutoff:CarbonInterface}
     */
    public function archiveOlderThan(int $days = 30): array
    {
        if ($days < 1) {
            throw new RuntimeException('Archive days must be a positive integer.');
        }

        $cutoff = now()->subDays($days);
        $query = AuditLog::query()
            ->where('created_at', '<', $cutoff)
            ->orderBy('id');

        $expectedCount = (clone $query)->count();

        if ($expectedCount === 0) {
            return [
                'archived' => 0,
                'deleted' => 0,
                'file_path' => null,
                'cutoff' => $cutoff,
            ];
        }

        $diskName = (string) config('filesystems.audit_log_archive_disk', 'private');
        $directory = trim((string) config('filesystems.audit_log_archive_directory', 'audit-archives'), '/');
        $disk = Storage::disk($diskName);

        $timestamp = now()->format('Y-m-d-His');
        $baseName = 'audit-log-'.$timestamp;
        $finalRelativePath = $directory.'/'.$baseName.'.csv';
        $temporaryCsvRelativePath = $directory.'/.'.$baseName.'.tmp.csv';
        $temporaryIdsRelativePath = $directory.'/.'.$baseName.'.ids';
        $finalizedArchive = false;

        try {
            $disk->makeDirectory($directory);

            $csvHandle = fopen($disk->path($temporaryCsvRelativePath), 'wb');
            $idsHandle = fopen($disk->path($temporaryIdsRelativePath), 'wb');

            if (! is_resource($csvHandle) || ! is_resource($idsHandle)) {
                throw new RuntimeException('Unable to create audit log archive files.');
            }

            try {
                $this->writeCsvHeader($csvHandle);

                $archivedCount = 0;

                foreach ((clone $query)->lazyById(500, 'id') as $entry) {
                    if (fputcsv($csvHandle, $this->csvRowFor($entry)) === false) {
                        throw new RuntimeException('Unable to write an audit log archive row.');
                    }

                    if (fwrite($idsHandle, (string) $entry->id.PHP_EOL) === false) {
                        throw new RuntimeException('Unable to record archived audit log ids.');
                    }

                    $archivedCount++;
                }
            } finally {
                fclose($csvHandle);
                fclose($idsHandle);
            }

            $this->verifyArchiveWrite($diskName, $temporaryCsvRelativePath, $archivedCount, $expectedCount);

            if (! $disk->move($temporaryCsvRelativePath, $finalRelativePath)) {
                throw new RuntimeException('Unable to finalize the audit log archive file.');
            }

            $finalizedArchive = true;

            $deletedCount = $this->deleteArchivedRows($disk->path($temporaryIdsRelativePath), $cutoff, $expectedCount);

            $disk->delete($temporaryIdsRelativePath);

            return [
                'archived' => $archivedCount,
                'deleted' => $deletedCount,
                'file_path' => $finalRelativePath,
                'cutoff' => $cutoff,
            ];
        } catch (Throwable $exception) {
            Log::error('Audit log archive failed.', [
                'days' => $days,
                'cutoff' => $cutoff->toDateTimeString(),
                'file_path' => $finalizedArchive ? $finalRelativePath : null,
                'error' => $exception->getMessage(),
            ]);

            $disk->delete($temporaryIdsRelativePath);

            if (! $finalizedArchive) {
                $disk->delete($temporaryCsvRelativePath);
            }

            throw $exception;
        }
    }

    /**
     * @param resource $handle
     */
    protected function writeCsvHeader($handle): void
    {
        if (fputcsv($handle, [
            'id',
            'account_id',
            'user_id',
            'auditable_type',
            'auditable_id',
            'event',
            'batch_id',
            'changes',
            'created_at',
        ]) === false) {
            throw new RuntimeException('Unable to write the audit log archive header row.');
        }
    }

    /**
     * @return list<string>
     */
    protected function csvRowFor(AuditLog $entry): array
    {
        $changes = $entry->getAttribute('changes');

        return [
            (string) $entry->id,
            $entry->account_id !== null ? (string) $entry->account_id : '',
            $entry->user_id !== null ? (string) $entry->user_id : '',
            (string) $entry->auditable_type,
            (string) $entry->auditable_id,
            (string) $entry->event,
            $entry->batch_id !== null ? (string) $entry->batch_id : '',
            is_array($changes)
                ? (json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '')
                : ((string) $changes),
            $entry->created_at?->format('Y-m-d H:i:s') ?? '',
        ];
    }

    protected function verifyArchiveWrite(string $diskName, string $relativePath, int $archivedCount, int $expectedCount): void
    {
        $disk = Storage::disk($diskName);

        if (! $disk->exists($relativePath)) {
            throw new RuntimeException('The audit log archive file was not created.');
        }

        if ($disk->size($relativePath) < 1) {
            throw new RuntimeException('The audit log archive file is empty.');
        }

        if ($archivedCount !== $expectedCount) {
            throw new RuntimeException(sprintf(
                'Archived row count mismatch. Expected %d row(s), wrote %d row(s).',
                $expectedCount,
                $archivedCount
            ));
        }
    }

    protected function deleteArchivedRows(string $idsPath, CarbonInterface $cutoff, int $expectedCount): int
    {
        return DB::transaction(function () use ($idsPath, $cutoff, $expectedCount) {
            $handle = fopen($idsPath, 'rb');

            if (! is_resource($handle)) {
                throw new RuntimeException('Unable to read archived audit log ids for deletion.');
            }

            $deletedCount = 0;
            $idBuffer = [];

            try {
                while (($line = fgets($handle)) !== false) {
                    $id = (int) trim($line);

                    if ($id < 1) {
                        continue;
                    }

                    $idBuffer[] = $id;

                    if (count($idBuffer) >= 500) {
                        $deletedCount += $this->deleteAuditLogChunk($idBuffer, $cutoff);
                        $idBuffer = [];
                    }
                }

                if ($idBuffer !== []) {
                    $deletedCount += $this->deleteAuditLogChunk($idBuffer, $cutoff);
                }
            } finally {
                fclose($handle);
            }

            if ($deletedCount !== $expectedCount) {
                throw new RuntimeException(sprintf(
                    'Deleted row count mismatch. Expected %d row(s), deleted %d row(s).',
                    $expectedCount,
                    $deletedCount
                ));
            }

            return $deletedCount;
        });
    }

    /**
     * @param list<int> $ids
     */
    protected function deleteAuditLogChunk(array $ids, CarbonInterface $cutoff): int
    {
        return AuditLog::query()
            ->whereIn('id', $ids)
            ->where('created_at', '<', $cutoff)
            ->delete();
    }
}
