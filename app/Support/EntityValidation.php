<?php

namespace App\Support;

use App\Models\DataDictionary;
use App\Models\Machine;
use App\Models\Product;
use Illuminate\Validation\Rule;

class EntityValidation
{
    public static function productRules(int $accountId, ?Product $product = null): array
    {
        return [
            'vendor_id' => [
                'nullable',
                'integer',
                Rule::exists('tbl_vendors', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'category' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('tbl_products', 'sku')
                    ->where(fn ($query) => $query->where('account_id', $accountId))
                    ->ignore($product?->id),
            ],
            'product_name' => ['required', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:100'],
            'package_type' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:100'],
        ];
    }

    public static function machineRules(
        int $accountId,
        ?Machine $machine = null,
        array $installedOnRules = ['nullable', 'regex:/^\d{2}-\d{2}-\d{4}$/']
    ): array {
        return [
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('tbl_locations', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'type' => ['required', 'string', 'max:100'],
            'serial_number' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('tbl_machines', 'serial_number')
                    ->where(fn ($query) => $query->where('account_id', $accountId))
                    ->ignore($machine?->id),
            ],
            'model' => ['nullable', 'string', 'max:255'],
            'status' => [
                'required',
                'string',
                'max:50',
                self::activeDictionaryValueRule(DataDictionary::GROUP_MACHINE_STATUS, $accountId),
            ],
            'installed_on' => $installedOnRules,
        ];
    }

    public static function locationRules(int $accountId): array
    {
        return [
            'route_id' => [
                'nullable',
                'integer',
                Rule::exists('tbl_routes', 'id')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'location_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:20'],
        ];
    }

    public static function contactRules(string $notesField = 'notes'): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'organization' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile_phone' => ['nullable', 'string', 'max:50'],
            $notesField => ['nullable', 'string'],
        ];
    }

    public static function contactRelationshipRules(int $accountId, string $notesField = 'relationship_notes'): array
    {
        return [
            'contact_role' => ['nullable', 'string', self::activeDictionaryValueRule(DataDictionary::GROUP_LOCATION_CONTACT_ROLE, $accountId)],
            'is_primary' => ['nullable', 'boolean'],
            $notesField => ['nullable', 'string'],
        ];
    }

    public static function ensureContactHasIdentity(array $data, string $errorKey = 'first_name'): void
    {
        if (
            trim((string) ($data['first_name'] ?? '')) === ''
            && trim((string) ($data['last_name'] ?? '')) === ''
            && trim((string) ($data['organization'] ?? '')) === ''
            && trim((string) ($data['email'] ?? '')) === ''
            && trim((string) ($data['phone'] ?? '')) === ''
            && trim((string) ($data['mobile_phone'] ?? '')) === ''
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $errorKey => 'Enter at least a name, organization, email, or phone number.',
            ]);
        }
    }

    public static function normalizeSku(?string $sku): ?string
    {
        $normalized = trim((string) $sku);

        return $normalized !== '' ? $normalized : null;
    }

    protected static function activeDictionaryValueRule(string $group, ?int $accountId = null): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('tbl_data_dictionary', 'value')->where(function ($query) use ($group, $accountId) {
            $query->where('name', $group)
                ->where('is_active', true)
                ->where(function ($dictionaryQuery) use ($accountId) {
                    $dictionaryQuery->whereNull('account_id');

                    if ($accountId !== null) {
                        $dictionaryQuery->orWhere('account_id', $accountId);
                    }
                });
        });
    }
}
