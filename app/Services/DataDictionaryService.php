<?php

namespace App\Services;

use App\Models\DataDictionary;
use Illuminate\Support\Collection;

class DataDictionaryService
{
    public function options(string $group, ?int $accountId = null): Collection
    {
        // Prefer account-specific overrides before global rows, then dedupe by
        // canonical value so a dropdown only renders each choice once.
        return DataDictionary::query()
            ->forGroup($group)
            ->active()
            ->forAccountScope($accountId)
            ->orderByRaw('CASE WHEN account_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->unique(fn (DataDictionary $entry) => $this->normalizeValue($entry->value))
            ->values();
    }

    public function values(string $group, ?int $accountId = null): array
    {
        // Controllers can validate against the canonical stored values.
        return $this->options($group, $accountId)->pluck('value')->all();
    }

    public function labels(string $group, ?int $accountId = null, bool $normalizedKeys = false): array
    {
        // Views use a simple value-to-label map instead of repeating lookup
        // queries or hard-coded status text.
        return $this->options($group, $accountId)
            ->mapWithKeys(function (DataDictionary $entry) use ($normalizedKeys) {
                $key = $normalizedKeys
                    ? $this->normalizeValue($entry->value)
                    : $entry->value;

                return [$key => $entry->displayLabel()];
            })
            ->all();
    }

    protected function normalizeValue(?string $value): string
    {
        // Normalized keys let legacy casing continue to resolve to the same
        // display label while the database is cleaned up over time.
        return mb_strtolower(trim((string) $value));
    }
}
