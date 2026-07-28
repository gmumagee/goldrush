<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Location;
use App\Models\LocationContact;
use App\Models\Machine;
use App\Models\Product;
use App\Models\Vendor;
use App\Support\EntityValidation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CsvImportService
{
    public const TEMP_DISK = 'private';
    public const TEMP_DIRECTORY = 'import-tmp';
    public const TOKEN_TTL_SECONDS = 7200;

    public function __construct(protected ImportAuditLogger $auditLogger)
    {
    }

    public function analyzeUpload(string $entity, UploadedFile $file, Account $account, int $userId): array
    {
        $this->cleanupExpiredImports();

        $token = (string) Str::uuid();
        $relativePath = self::TEMP_DIRECTORY.'/'.$token.'.csv';
        Storage::disk(self::TEMP_DISK)->putFileAs(self::TEMP_DIRECTORY, $file, $token.'.csv');

        try {
            $preview = $this->buildPreview($entity, Storage::disk(self::TEMP_DISK)->path($relativePath), $account->id);
        } catch (\Throwable $exception) {
            Storage::disk(self::TEMP_DISK)->delete($relativePath);

            throw $exception;
        }

        Cache::put($this->cacheKey($token), [
            'entity' => $entity,
            'account_id' => $account->id,
            'user_id' => $userId,
            'disk' => self::TEMP_DISK,
            'path' => $relativePath,
            'hash' => hash_file('sha256', Storage::disk(self::TEMP_DISK)->path($relativePath)),
        ], now()->addSeconds(self::TOKEN_TTL_SECONDS));

        $preview['token'] = $token;

        return $preview;
    }

    public function commit(string $entity, string $token, Account $account, int $userId): array
    {
        $this->cleanupExpiredImports();

        $metadata = Cache::get($this->cacheKey($token));

        if (
            ! is_array($metadata)
            || ($metadata['account_id'] ?? null) !== $account->id
            || ($metadata['user_id'] ?? null) !== $userId
            || ($metadata['entity'] ?? null) !== $entity
        ) {
            throw ValidationException::withMessages([
                'import_file' => 'This import preview is no longer available. Upload the CSV again and re-run Analyze.',
            ]);
        }

        $disk = (string) ($metadata['disk'] ?? self::TEMP_DISK);
        $path = (string) ($metadata['path'] ?? '');

        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            Cache::forget($this->cacheKey($token));

            throw ValidationException::withMessages([
                'import_file' => 'The staged import file is no longer available. Upload the CSV again and re-run Analyze.',
            ]);
        }

        $absolutePath = Storage::disk($disk)->path($path);

        if (($metadata['hash'] ?? null) !== hash_file('sha256', $absolutePath)) {
            Storage::disk($disk)->delete($path);
            Cache::forget($this->cacheKey($token));

            throw ValidationException::withMessages([
                'import_file' => 'The staged import file changed unexpectedly. Upload the CSV again and re-run Analyze.',
            ]);
        }

        $preview = $this->buildPreview((string) $metadata['entity'], $absolutePath, $account->id);
        $batchId = (string) Str::uuid();
        $summary = [
            'created' => 0,
            'updated' => 0,
            'batch_id' => $batchId,
        ];

        DB::transaction(function () use (&$summary, $preview, $account, $userId, $batchId): void {
            foreach ($preview['rows'] as $row) {
                if (! ($row['can_commit'] ?? false)) {
                    continue;
                }

                match ($preview['entity']) {
                    'products' => $this->commitProductRow($row, $account->id, $userId, $batchId, $summary),
                    'machines' => $this->commitMachineRow($row, $account->id, $userId, $batchId, $summary),
                    'locations' => $this->commitLocationRow($row, $account->id, $userId, $batchId, $summary),
                    'contacts' => $this->commitContactRow($row, $account->id, $userId, $batchId, $summary),
                    default => throw ValidationException::withMessages([
                        'entity' => 'Unsupported import entity.',
                    ]),
                };
            }
        });

        Storage::disk($disk)->delete($path);
        Cache::forget($this->cacheKey($token));

        return $summary;
    }

    public function buildPreview(string $entity, string $absolutePath, int $accountId): array
    {
        $expectedHeaders = $this->expectedHeaders($entity);

        if ($absolutePath === '' || ! is_readable($absolutePath)) {
            throw ValidationException::withMessages([
                'import_file' => 'Unable to read the uploaded CSV file.',
            ]);
        }

        $handle = fopen($absolutePath, 'r');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'import_file' => 'Unable to open the uploaded CSV file.',
            ]);
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false) {
                throw ValidationException::withMessages([
                    'import_file' => 'The CSV file is empty.',
                ]);
            }

            $headers = array_map(
                static fn ($column) => trim((string) $column, " \xEF\xBB\xBF"),
                $header
            );

            $headersWithoutAccountId = array_values(array_filter($headers, fn (string $column) => $column !== 'account_id'));

            if ($headersWithoutAccountId !== $expectedHeaders) {
                throw ValidationException::withMessages([
                    'import_file' => sprintf(
                        'The CSV headers do not match the expected %s import template.',
                        Str::headline(Str::singular($entity))
                    ),
                ]);
            }

            $context = $this->contextForEntity($entity, $accountId);
            $rowNumber = 1;
            $rows = [];
            $sawDataRow = false;

            while (($data = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($this->rowIsBlank($data)) {
                    continue;
                }

                $sawDataRow = true;

                if (count($data) !== count($headers)) {
                    $rows[] = $this->errorRow($rowNumber, 'error', 'Column count mismatch for this row.');

                    continue;
                }

                $row = array_combine($headers, array_map(static fn ($value) => trim((string) $value), $data));
                unset($row['account_id']);

                $rows[] = match ($entity) {
                    'products' => $this->previewProductRow($rowNumber, $row, $accountId, $context),
                    'machines' => $this->previewMachineRow($rowNumber, $row, $accountId, $context),
                    'locations' => $this->previewLocationRow($rowNumber, $row, $accountId, $context),
                    'contacts' => $this->previewContactRow($rowNumber, $row, $accountId, $context),
                    default => $this->errorRow($rowNumber, 'error', 'Unsupported import entity.'),
                };
            }

            if (! $sawDataRow) {
                throw ValidationException::withMessages([
                    'import_file' => 'The CSV file contains headers but no data rows.',
                ]);
            }
        } finally {
            fclose($handle);
        }

        return [
            'entity' => $entity,
            'rows' => $rows,
            'counts' => [
                'create' => collect($rows)->where('action', 'create')->count(),
                'update' => collect($rows)->where('action', 'update')->count(),
                'error' => collect($rows)->where('action', 'error')->count(),
                'duplicate_warning' => collect($rows)->filter(fn (array $row) => ! empty($row['warning']))->count(),
            ],
        ];
    }

    public function expectedHeaders(string $entity): array
    {
        return match ($entity) {
            'products' => ['sku', 'category', 'brand', 'product_name', 'size', 'package_type', 'barcode', 'vendor_name'],
            'machines' => ['serial_number', 'type', 'model', 'status', 'installed_on', 'location_name'],
            'locations' => ['location_name', 'address', 'city', 'state', 'zip_code', 'primary_route_name', 'primary_contact_name', 'primary_contact_email', 'primary_contact_phone'],
            'contacts' => ['first_name', 'last_name', 'organization', 'title', 'email', 'phone', 'mobile_phone', 'location_name', 'contact_role', 'is_primary'],
            default => [],
        };
    }

    protected function contextForEntity(string $entity, int $accountId): array
    {
        return match ($entity) {
            'products' => [
                'products_by_sku' => Product::query()
                    ->where('account_id', $accountId)
                    ->get()
                    ->keyBy(fn (Product $product) => $this->normalizeKey($product->sku)),
                'vendors_by_name' => Vendor::query()
                    ->where('account_id', $accountId)
                    ->orderBy('id')
                    ->get()
                    ->groupBy(fn (Vendor $vendor) => $this->normalizeKey($vendor->vendor_name)),
            ],
            'machines' => [
                'machines_by_serial' => Machine::query()
                    ->where('account_id', $accountId)
                    ->get()
                    ->keyBy(fn (Machine $machine) => $this->normalizeKey($machine->serial_number)),
                'locations_by_name' => Location::query()
                    ->where('account_id', $accountId)
                    ->orderBy('id')
                    ->get()
                    ->groupBy(fn (Location $location) => $this->normalizeKey($location->location_name)),
            ],
            'locations' => [
                'locations_by_name' => Location::query()
                    ->where('account_id', $accountId)
                    ->orderBy('id')
                    ->get()
                    ->groupBy(fn (Location $location) => $this->normalizeKey($location->location_name)),
            ],
            'contacts' => [
                'locations_by_name' => Location::query()
                    ->where('account_id', $accountId)
                    ->orderBy('id')
                    ->get()
                    ->groupBy(fn (Location $location) => $this->normalizeKey($location->location_name)),
                'contacts' => Contact::query()
                    ->where('account_id', $accountId)
                    ->get(),
            ],
            default => [],
        };
    }

    protected function previewProductRow(int $rowNumber, array $row, int $accountId, array $context): array
    {
        $sku = EntityValidation::normalizeSku($row['sku'] ?? null);

        if ($sku === null) {
            return $this->errorRow($rowNumber, 'error', 'SKU is required for product imports.');
        }

        $vendorResolution = $this->resolveByName($context['vendors_by_name'], $row['vendor_name'] ?? '', 'Vendor', true);

        if ($vendorResolution['error'] !== null) {
            return $this->errorRow($rowNumber, 'error', $vendorResolution['error'], $sku);
        }

        /** @var Product|null $existingProduct */
        $existingProduct = $context['products_by_sku']->get($this->normalizeKey($sku));
        $attributes = [
            'vendor_id' => $vendorResolution['record']?->id,
            'category' => $this->nullable($row['category'] ?? null),
            'brand' => $this->nullable($row['brand'] ?? null),
            'sku' => $sku,
            'product_name' => $this->nullable($row['product_name'] ?? null),
            'size' => $this->nullable($row['size'] ?? null),
            'package_type' => $this->nullable($row['package_type'] ?? null),
            'barcode' => $this->nullable($row['barcode'] ?? null),
        ];

        $errors = $this->validationErrors(EntityValidation::productRules($accountId, $existingProduct), $attributes);

        if ($errors !== []) {
            return $this->errorRow($rowNumber, 'error', implode(' ', $errors), $sku);
        }

        return $this->successRow($rowNumber, $existingProduct ? 'update' : 'create', $sku, $attributes);
    }

    protected function previewMachineRow(int $rowNumber, array $row, int $accountId, array $context): array
    {
        $serialNumber = $this->nullable($row['serial_number'] ?? null);

        if ($serialNumber === null) {
            return $this->errorRow($rowNumber, 'error', 'Serial number is required for machine imports.');
        }

        $locationResolution = $this->resolveByName($context['locations_by_name'], $row['location_name'] ?? '', 'Location');

        if ($locationResolution['error'] !== null) {
            return $this->errorRow($rowNumber, 'error', $locationResolution['error'], $serialNumber);
        }

        /** @var Machine|null $existingMachine */
        $existingMachine = $context['machines_by_serial']->get($this->normalizeKey($serialNumber));
        $attributes = [
            'location_id' => $locationResolution['record']?->id,
            'type' => $this->nullable($row['type'] ?? null),
            'serial_number' => $serialNumber,
            'model' => $this->nullable($row['model'] ?? null),
            'status' => $this->nullable($row['status'] ?? null),
            'installed_on' => $this->nullable($row['installed_on'] ?? null),
        ];

        if ($attributes['location_id'] === null) {
            return $this->errorRow($rowNumber, 'error', 'Location name is required for machine imports.', $serialNumber);
        }

        $errors = $this->validationErrors(
            EntityValidation::machineRules($accountId, $existingMachine, ['nullable', 'date_format:Y-m-d']),
            $attributes
        );

        if ($errors !== []) {
            return $this->errorRow($rowNumber, 'error', implode(' ', $errors), $serialNumber);
        }

        return $this->successRow($rowNumber, $existingMachine ? 'update' : 'create', $serialNumber, $attributes);
    }

    protected function previewLocationRow(int $rowNumber, array $row, int $accountId, array $context): array
    {
        $attributes = [
            'route_id' => null,
            'location_name' => $this->nullable($row['location_name'] ?? null),
            'address' => $this->nullable($row['address'] ?? null),
            'city' => $this->nullable($row['city'] ?? null),
            'state' => $this->nullable($row['state'] ?? null),
            'zip_code' => $this->nullable($row['zip_code'] ?? null),
        ];

        $errors = $this->validationErrors(EntityValidation::locationRules($accountId), $attributes);

        if ($errors !== []) {
            return $this->errorRow($rowNumber, 'error', implode(' ', $errors), $attributes['location_name']);
        }

        $warning = $this->locationDuplicateWarning($attributes['location_name'], $context['locations_by_name']);

        return $this->successRow($rowNumber, 'create', $attributes['location_name'], $attributes, $warning);
    }

    protected function previewContactRow(int $rowNumber, array $row, int $accountId, array $context): array
    {
        $contactAttributes = [
            'first_name' => $this->nullable($row['first_name'] ?? null),
            'last_name' => $this->nullable($row['last_name'] ?? null),
            'organization' => $this->nullable($row['organization'] ?? null),
            'title' => $this->nullable($row['title'] ?? null),
            'email' => $this->nullable($row['email'] ?? null),
            'phone' => $this->nullable($row['phone'] ?? null),
            'mobile_phone' => $this->nullable($row['mobile_phone'] ?? null),
            'notes' => null,
        ];

        $errors = $this->validationErrors(EntityValidation::contactRules(), $contactAttributes);

        if ($errors !== []) {
            return $this->errorRow($rowNumber, 'error', implode(' ', $errors), $contactAttributes['email'] ?: $contactAttributes['organization']);
        }

        try {
            EntityValidation::ensureContactHasIdentity($contactAttributes);
        } catch (ValidationException $exception) {
            return $this->errorRow($rowNumber, 'error', collect($exception->errors())->flatten()->implode(' '), $contactAttributes['email'] ?: $contactAttributes['organization']);
        }

        $relationshipAttributes = [
            'contact_role' => $this->nullable($row['contact_role'] ?? null),
            'is_primary' => $this->nullable($row['is_primary'] ?? null),
            'relationship_notes' => null,
        ];

        $relationshipErrors = $this->validationErrors(
            EntityValidation::contactRelationshipRules($accountId),
            $relationshipAttributes
        );

        if ($relationshipErrors !== []) {
            return $this->errorRow($rowNumber, 'error', implode(' ', $relationshipErrors), $contactAttributes['email'] ?: $contactAttributes['organization']);
        }

        $locationResolution = $this->resolveByName($context['locations_by_name'], $row['location_name'] ?? '', 'Location', true);

        if ($locationResolution['error'] !== null) {
            return $this->errorRow($rowNumber, 'error', $locationResolution['error'], $contactAttributes['email'] ?: $contactAttributes['organization']);
        }

        $attributes = [
            ...$contactAttributes,
            'location_id' => $locationResolution['record']?->id,
            'contact_role' => $relationshipAttributes['contact_role'],
            'is_primary' => filter_var($relationshipAttributes['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];

        $warning = $this->contactDuplicateWarning($contactAttributes, $context['contacts']);

        return $this->successRow(
            $rowNumber,
            'create',
            $contactAttributes['email'] ?: trim(($contactAttributes['first_name'] ?? '').' '.($contactAttributes['last_name'] ?? '')),
            $attributes,
            $warning
        );
    }

    protected function commitProductRow(array $row, int $accountId, int $userId, string $batchId, array &$summary): void
    {
        $attributes = $row['attributes'];
        $product = Product::query()
            ->where('account_id', $accountId)
            ->where('sku', $attributes['sku'])
            ->first();

        if ($product) {
            $before = $product->attributesToArray();
            $product->update($attributes);
            $this->auditLogger->logUpdated($product->fresh(), $before, $userId, $batchId);
            $summary['updated']++;

            return;
        }

        $product = Product::create([
            ...$attributes,
            'account_id' => $accountId,
        ]);
        $this->auditLogger->logCreated($product, $userId, $batchId);
        $summary['created']++;
    }

    protected function commitMachineRow(array $row, int $accountId, int $userId, string $batchId, array &$summary): void
    {
        $attributes = $row['attributes'];
        $machine = Machine::query()
            ->where('account_id', $accountId)
            ->where('serial_number', $attributes['serial_number'])
            ->first();

        if ($machine) {
            $before = $machine->attributesToArray();
            $machine->update($attributes);
            $this->auditLogger->logUpdated($machine->fresh(), $before, $userId, $batchId);
            $summary['updated']++;

            return;
        }

        $machine = Machine::create([
            ...$attributes,
            'account_id' => $accountId,
        ]);
        $this->auditLogger->logCreated($machine, $userId, $batchId);
        $summary['created']++;
    }

    protected function commitLocationRow(array $row, int $accountId, int $userId, string $batchId, array &$summary): void
    {
        $location = Location::create([
            ...$row['attributes'],
            'account_id' => $accountId,
            'is_inventory' => null,
        ]);
        $this->auditLogger->logCreated($location, $userId, $batchId);
        $summary['created']++;
    }

    protected function commitContactRow(array $row, int $accountId, int $userId, string $batchId, array &$summary): void
    {
        $contact = Contact::create([
            'account_id' => $accountId,
            'first_name' => $row['attributes']['first_name'],
            'last_name' => $row['attributes']['last_name'],
            'organization' => $row['attributes']['organization'],
            'title' => $row['attributes']['title'],
            'email' => $row['attributes']['email'],
            'phone' => $row['attributes']['phone'],
            'mobile_phone' => $row['attributes']['mobile_phone'],
            'notes' => $row['attributes']['notes'],
        ]);
        $this->auditLogger->logCreated($contact, $userId, $batchId);

        if ($row['attributes']['location_id'] !== null) {
            if ($row['attributes']['is_primary']) {
                LocationContact::query()
                    ->where('account_id', $accountId)
                    ->where('location_id', $row['attributes']['location_id'])
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            LocationContact::create([
                'account_id' => $accountId,
                'location_id' => $row['attributes']['location_id'],
                'contact_id' => $contact->id,
                'contact_role' => $row['attributes']['contact_role'],
                'is_primary' => $row['attributes']['is_primary'],
                'notes' => null,
            ]);
        }

        $summary['created']++;
    }

    protected function validationErrors(array $rules, array $attributes): array
    {
        $validator = Validator::make($attributes, $rules);

        return $validator->fails()
            ? collect($validator->errors()->all())->values()->all()
            : [];
    }

    protected function resolveByName(Collection $groupedRecords, ?string $value, string $label, bool $allowBlank = false): array
    {
        $normalized = $this->normalizeKey($value);

        if ($normalized === '') {
            return $allowBlank
                ? ['record' => null, 'error' => null]
                : ['record' => null, 'error' => $label.' name is required.'];
        }

        /** @var Collection $matches */
        $matches = $groupedRecords->get($normalized, collect());

        if ($matches->count() === 0) {
            return ['record' => null, 'error' => sprintf("%s '%s' not found.", $label, trim((string) $value))];
        }

        if ($matches->count() > 1) {
            return ['record' => null, 'error' => sprintf("%s '%s' matches multiple records.", $label, trim((string) $value))];
        }

        return ['record' => $matches->first(), 'error' => null];
    }

    protected function locationDuplicateWarning(?string $locationName, Collection $locationsByName): ?string
    {
        if ($locationName === null) {
            return null;
        }

        $matches = $locationsByName->get($this->normalizeKey($locationName), collect());

        if ($matches->isEmpty()) {
            return null;
        }

        return sprintf("Possible duplicate: location name '%s' already exists in this account.", $locationName);
    }

    protected function contactDuplicateWarning(array $attributes, Collection $contacts): ?string
    {
        $email = $this->normalizeKey($attributes['email'] ?? null);
        $phone = $this->normalizeKey($attributes['phone'] ?? null);
        $mobilePhone = $this->normalizeKey($attributes['mobile_phone'] ?? null);
        $nameTuple = implode('|', [
            $this->normalizeKey($attributes['first_name'] ?? null),
            $this->normalizeKey($attributes['last_name'] ?? null),
            $this->normalizeKey($attributes['organization'] ?? null),
        ]);

        $duplicate = $contacts->first(function (Contact $contact) use ($email, $phone, $mobilePhone, $nameTuple): bool {
            return ($email !== '' && $this->normalizeKey($contact->email) === $email)
                || ($phone !== '' && $this->normalizeKey($contact->phone) === $phone)
                || ($mobilePhone !== '' && $this->normalizeKey($contact->mobile_phone) === $mobilePhone)
                || ($nameTuple !== '||' && implode('|', [
                    $this->normalizeKey($contact->first_name),
                    $this->normalizeKey($contact->last_name),
                    $this->normalizeKey($contact->organization),
                ]) === $nameTuple);
        });

        if (! $duplicate) {
            return null;
        }

        return sprintf("Possible duplicate: this contact looks similar to existing contact '%s'.", $duplicate->display_name);
    }

    protected function successRow(int $rowNumber, string $action, ?string $key, array $attributes, ?string $warning = null): array
    {
        return [
            'row_number' => $rowNumber,
            'key' => $key,
            'action' => $action,
            'message' => $warning,
            'warning' => $warning,
            'can_commit' => true,
            'attributes' => $attributes,
        ];
    }

    protected function errorRow(int $rowNumber, string $action, string $message, ?string $key = null): array
    {
        return [
            'row_number' => $rowNumber,
            'key' => $key,
            'action' => $action,
            'message' => $message,
            'warning' => null,
            'can_commit' => false,
            'attributes' => [],
        ];
    }

    protected function rowIsBlank(array $data): bool
    {
        foreach ($data as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function nullable(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    protected function normalizeKey(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    protected function cacheKey(string $token): string
    {
        return 'import-preview:'.$token;
    }

    protected function cleanupExpiredImports(): void
    {
        $disk = Storage::disk(self::TEMP_DISK);

        if (! $disk->exists(self::TEMP_DIRECTORY)) {
            return;
        }

        foreach ($disk->files(self::TEMP_DIRECTORY) as $path) {
            if (($disk->lastModified($path) + self::TOKEN_TTL_SECONDS) >= time()) {
                continue;
            }

            $disk->delete($path);
        }
    }
}
