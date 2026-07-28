<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ImportAuditLogger
{
    protected const IGNORED_ATTRIBUTES = [
        'created_at',
        'updated_at',
    ];

    protected const MAX_STRING_LENGTH = 500;

    public function logCreated(Model $model, ?int $userId, string $batchId): void
    {
        $this->writeEntry($model, AuditLog::EVENT_CREATED, $this->snapshot($model->attributesToArray()), $userId, $batchId);
    }

    public function logUpdated(Model $model, array $beforeAttributes, ?int $userId, string $batchId): void
    {
        $changes = $this->diffSnapshots($beforeAttributes, $model->attributesToArray());

        if ($changes === []) {
            return;
        }

        $this->writeEntry($model, AuditLog::EVENT_UPDATED, $changes, $userId, $batchId);
    }

    protected function writeEntry(Model $model, string $event, array $changes, ?int $userId, string $batchId): void
    {
        AuditLog::query()->create([
            'account_id' => $model->getAttribute('account_id'),
            'user_id' => $userId,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'event' => $event,
            'changes' => $changes === [] ? null : $changes,
            'batch_id' => $batchId,
            'created_at' => now(),
        ]);
    }

    protected function diffSnapshots(array $beforeAttributes, array $afterAttributes): array
    {
        $before = $this->snapshot($beforeAttributes);
        $after = $this->snapshot($afterAttributes);
        $changes = [];

        foreach (array_keys(array_merge($before, $after)) as $field) {
            $oldValue = $before[$field] ?? null;
            $newValue = $after[$field] ?? null;

            if ($oldValue === $newValue) {
                continue;
            }

            $changes[$field] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        return $changes;
    }

    protected function snapshot(array $attributes): array
    {
        $snapshot = [];

        foreach ($attributes as $field => $value) {
            if (in_array($field, self::IGNORED_ATTRIBUTES, true)) {
                continue;
            }

            $snapshot[$field] = $this->normalizeValue($value);
        }

        return $snapshot;
    }

    protected function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value)) {
            return Str::limit($value, self::MAX_STRING_LENGTH);
        }

        if (is_bool($value) || $value === null || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_array($value)) {
            return Arr::map($value, fn (mixed $item) => $this->normalizeValue($item));
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return Str::limit((string) $value, self::MAX_STRING_LENGTH);
        }

        return $value;
    }
}
