<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $showAllAccounts = $request->user()?->isSuperAdmin() ?? false;
        $filters = $request->validate([
            'event' => ['nullable', 'in:'.implode(',', AuditLog::eventOptions())],
            'entity_type' => ['nullable', 'in:'.implode(',', array_keys(AuditLog::entityTypeOptions()))],
        ]);

        $auditEntries = AuditLog::query()
            ->with(['user'])
            ->when($showAllAccounts, fn ($query) => $query->with('account'))
            ->when(! $showAllAccounts, fn ($query) => $query->where('account_id', $this->currentAccountId($request)))
            ->when(($filters['event'] ?? '') !== '', fn ($query) => $query->where('event', $filters['event']))
            ->when(($filters['entity_type'] ?? '') !== '', fn ($query) => $query->where('auditable_type', $filters['entity_type']))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('audit-log.index', [
            'auditEntries' => $auditEntries,
            'eventOptions' => AuditLog::eventOptions(),
            'entityTypeOptions' => AuditLog::entityTypeOptions(),
            'filters' => [
                'event' => $filters['event'] ?? '',
                'entity_type' => $filters['entity_type'] ?? '',
            ],
            'showAllAccounts' => $showAllAccounts,
        ]);
    }
}
