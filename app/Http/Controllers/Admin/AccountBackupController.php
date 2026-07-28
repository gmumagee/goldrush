<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateAccountBackup;
use App\Models\Account;
use App\Models\AccountBackup;
use App\Models\AuditLog;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountBackupController extends Controller
{
    public function store(Request $request, Account $account): RedirectResponse
    {
        $this->ensureAvailable();

        if (AccountBackup::query()
            ->where('account_id', $account->id)
            ->where('status', AccountBackup::STATUS_PENDING)
            ->exists()) {
            return redirect()
                ->route('admin.accounts.index')
                ->withErrors(['backup' => 'A backup is already being generated for this account.']);
        }

        $backup = AccountBackup::query()->create([
            'account_id' => $account->id,
            'requested_by_user_id' => (int) $request->user()->id,
            'status' => AccountBackup::STATUS_PENDING,
        ]);

        GenerateAccountBackup::dispatch($backup->id)->afterCommit();

        AuditLog::query()->create([
            'account_id' => $account->id,
            'user_id' => $request->user()?->id,
            'auditable_type' => AccountBackup::class,
            'auditable_id' => $backup->id,
            'event' => AuditLog::EVENT_CREATED,
            'changes' => [
                'operator_action' => 'backup_requested',
                'account_name' => $account->account_name,
                'status' => AccountBackup::STATUS_PENDING,
            ],
            'created_at' => now(),
        ]);

        return redirect()
            ->route('admin.accounts.index')
            ->with('status', 'Account backup queued successfully.');
    }

    public function download(Request $request, AccountBackup $accountBackup): StreamedResponse
    {
        $this->ensureAvailable();

        abort_unless(
            $accountBackup->isReady()
            && $accountBackup->file_disk
            && $accountBackup->file_path
            && Storage::disk($accountBackup->file_disk)->exists($accountBackup->file_path),
            404
        );

        AuditLog::query()->create([
            'account_id' => $accountBackup->account_id,
            'user_id' => $request->user()?->id,
            'auditable_type' => AccountBackup::class,
            'auditable_id' => $accountBackup->id,
            'event' => AuditLog::EVENT_UPDATED,
            'changes' => [
                'operator_action' => 'backup_downloaded',
                'file_name' => $accountBackup->file_name,
            ],
            'created_at' => now(),
        ]);

        return Storage::disk($accountBackup->file_disk)->download(
            $accountBackup->file_path,
            $accountBackup->file_name,
            ['Content-Type' => 'application/zip']
        );
    }

    protected function ensureAvailable(): void
    {
        abort_if(Tenancy::isSingle(), 404);
    }
}
