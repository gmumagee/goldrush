<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountBackup;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class AccountBackupArchiveService
{
    public function __construct(protected AccountExportService $accountExportService)
    {
    }

    /**
     * @return array{file_disk:string, file_path:string, file_name:string, file_size_bytes:int, row_counts:array<string, int>}
     */
    public function generate(AccountBackup $backup, Account $account): array
    {
        $diskName = (string) config('backups.disk', 'private');
        $directory = trim((string) config('backups.directory', 'account-backups'), '/');
        $disk = Storage::disk($diskName);
        $timestamp = CarbonImmutable::now((string) config('app.timezone', 'UTC'));
        $slug = Str::slug($account->slug ?: $account->account_name) ?: 'account';
        $uniqueStamp = $timestamp->format('Y-m-d-His').'-'.$backup->id;
        $tempDirectory = $directory.'/tmp/backup-'.$backup->id.'-'.Str::uuid();
        $tempZipRelativePath = $tempDirectory.'/bundle.zip';
        $finalRelativePath = sprintf('%s/%s-%s.zip', $directory, $slug, $uniqueStamp);

        $disk->makeDirectory($tempDirectory);

        $rowCounts = [];

        try {
            foreach ($this->accountExportService->backupEntityKeys() as $entity) {
                $entryFilename = $this->accountExportService->backupEntryFilename($entity);
                $absolutePath = $disk->path($tempDirectory.'/'.$entryFilename);
                $rowCounts[$entryFilename] = $this->accountExportService->writeEntityCsvToPath($account, $entity, $absolutePath);
            }

            $manifestContents = $this->manifestContents($account, $timestamp, $rowCounts);
            file_put_contents($disk->path($tempDirectory.'/manifest.txt'), $manifestContents);

            $zip = new ZipArchive();
            $zipOpened = $zip->open($disk->path($tempZipRelativePath), ZipArchive::CREATE | ZipArchive::OVERWRITE);

            if ($zipOpened !== true) {
                throw new \RuntimeException('Unable to create backup zip archive.');
            }

            foreach (array_keys($rowCounts) as $entryFilename) {
                $zip->addFile($disk->path($tempDirectory.'/'.$entryFilename), $entryFilename);
            }

            $zip->addFile($disk->path($tempDirectory.'/manifest.txt'), 'manifest.txt');
            $zip->close();

            $disk->move($tempZipRelativePath, $finalRelativePath);
            $disk->deleteDirectory($tempDirectory);

            return [
                'file_disk' => $diskName,
                'file_path' => $finalRelativePath,
                'file_name' => basename($finalRelativePath),
                'file_size_bytes' => (int) $disk->size($finalRelativePath),
                'row_counts' => $rowCounts,
            ];
        } catch (\Throwable $exception) {
            $disk->deleteDirectory($tempDirectory);

            throw $exception;
        }
    }

    /**
     * @param array<string, int> $rowCounts
     */
    protected function manifestContents(Account $account, CarbonImmutable $timestamp, array $rowCounts): string
    {
        $lines = [
            'Account Backup Manifest',
            '=======================',
            'Account: '.$account->account_name,
            'Account ID: '.$account->id,
            'Generated At: '.$timestamp->format('Y-m-d H:i:s'),
            '',
            'Files:',
        ];

        foreach ($rowCounts as $filename => $rowCount) {
            $lines[] = sprintf('- %s (%d rows)', $filename, $rowCount);
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }
}
