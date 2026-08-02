<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\AccountExportService;
use App\Services\CsvImportService;
use App\Support\Demo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportExportController extends Controller
{
    public function __construct(
        protected CsvImportService $csvImportService,
        protected AccountExportService $accountExportService,
    ) {}

    /**
     * CSV headers intentionally match the planned import template:
     * products upsert by sku; machines by serial_number plus in-account location_name
     * and optional key_number / telemetry_id metadata;
     * contacts export one row per contact-location pair; locations need a unique-key
     * decision before import is implemented.
     */
    public function index(Request $request): View
    {
        abort_if(Demo::isEnabled(), 404);

        return $this->renderIndex($request);
    }

    public function export(Request $request, string $entity): StreamedResponse
    {
        abort_if(Demo::isEnabled(), 404);

        $definition = $this->entityDefinitions()[$entity] ?? null;

        if (! $definition) {
            abort(404);
        }

        $this->authorize('viewAny', $definition['model']);

        return $this->accountExportService->streamEntity(
            $this->currentAccount($request),
            $entity,
            [
                'search' => trim((string) $request->string('search')),
                'location_scope' => trim((string) $request->string('location_scope')),
            ],
        );
    }

    public function analyzeImport(Request $request): View
    {
        abort_if(Demo::isEnabled(), 404);

        $entity = (string) $request->input('entity');

        $this->authorizeImportEntity($request, $entity);

        $request->validate([
            'entity' => ['required', 'string'],
            'import_file' => [
                'required',
                'file',
                'max:5120',
                'extensions:csv,txt',
                'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel',
            ],
        ]);

        $preview = $this->csvImportService->analyzeUpload(
            $entity,
            $request->file('import_file'),
            $this->currentAccount($request),
            (int) $request->user()->id,
        );

        return $this->renderIndex($request, [
            'activeTab' => 'import',
            'selectedImportEntity' => $entity,
            'importPreview' => $preview,
        ]);
    }

    public function confirmImport(Request $request): RedirectResponse
    {
        abort_if(Demo::isEnabled(), 404);

        $data = $request->validate([
            'entity' => ['required', 'string'],
            'token' => ['required', 'string'],
        ]);

        $this->authorizeImportEntity($request, $data['entity']);

        $summary = $this->csvImportService->commit(
            $data['entity'],
            $data['token'],
            $this->currentAccount($request),
            (int) $request->user()->id,
        );

        return redirect()
            ->route('import-export.index')
            ->with('status', sprintf('Imported: %d created, %d updated.', $summary['created'], $summary['updated']));
    }

    public function availableExportEntities(Request $request): array
    {
        return $this->availableEntitiesForAbility($request, 'viewAny');
    }

    public function availableImportEntities(Request $request): array
    {
        return $this->availableEntitiesForAbility($request, 'create');
    }

    protected function entityDefinitions(): array
    {
        return $this->accountExportService->importExportEntityDefinitions();
    }

    protected function availableEntitiesForAbility(Request $request, string $ability): array
    {
        $gate = Gate::forUser($request->user());

        return collect($this->entityDefinitions())
            ->filter(function (array $definition) use ($ability, $gate): bool {
                if ($ability === 'create' && array_key_exists('supports_import', $definition) && $definition['supports_import'] === false) {
                    return false;
                }

                return $gate->allows($ability, $definition['model']);
            })
            ->map(fn (array $definition, string $key) => [
                'key' => $key,
                'label' => $definition['label'],
            ])
            ->all();
    }

    protected function renderIndex(Request $request, array $overrides = []): View
    {
        $availableExportEntities = $this->availableExportEntities($request);
        $availableImportEntities = $this->availableImportEntities($request);

        abort_if($availableExportEntities === [] && $availableImportEntities === [], 403);

        return view('import-export.index', [
            'availableExportEntities' => $availableExportEntities,
            'availableImportEntities' => $availableImportEntities,
            'defaultExportEntity' => array_key_first($availableExportEntities) ?: array_key_first($availableImportEntities),
            'defaultImportEntity' => array_key_first($availableImportEntities),
            'activeTab' => 'export',
            'selectedImportEntity' => array_key_first($availableImportEntities),
            'importPreview' => null,
            ...$overrides,
        ]);
    }

    protected function authorizeImportEntity(Request $request, string $entity): void
    {
        $definition = $this->entityDefinitions()[$entity] ?? null;

        if (! $definition) {
            abort(404);
        }

        $this->authorize('create', $definition['model']);
    }

    protected function currentAccount(Request $request): Account
    {
        return Account::query()->findOrFail($this->currentAccountId($request));
    }
}
