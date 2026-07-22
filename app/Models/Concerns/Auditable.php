<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

trait Auditable
{
    protected const AUDIT_IGNORED_ATTRIBUTES = [
        'created_at',
        'updated_at',
    ];

    protected const AUDIT_MAX_STRING_LENGTH = 500;

    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            if ($model->shouldAuditEvent(AuditLog::EVENT_CREATED)) {
                $model->writeAuditEntry(AuditLog::EVENT_CREATED, $model->auditCreatedChanges());
            }
        });

        static::updated(function (Model $model): void {
            if (! $model->shouldAuditEvent(AuditLog::EVENT_UPDATED)) {
                return;
            }

            $changes = $model->auditUpdatedChanges();

            if ($changes === []) {
                return;
            }

            $model->writeAuditEntry(AuditLog::EVENT_UPDATED, $changes);
        });

        static::deleted(function (Model $model): void {
            if ($model->shouldAuditEvent(AuditLog::EVENT_DELETED)) {
                $model->writeAuditEntry(AuditLog::EVENT_DELETED, $model->auditDeletedChanges());
            }
        });
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable', 'auditable_type', 'auditable_id');
    }

    protected function shouldAuditEvent(string $event): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function auditCreatedChanges(): array
    {
        return $this->auditSnapshot();
    }

    /**
     * @return array<string, mixed>
     */
    protected function auditDeletedChanges(): array
    {
        return $this->auditSnapshot();
    }

    /**
     * @return array<string, array{old:mixed,new:mixed}>
     */
    protected function auditUpdatedChanges(): array
    {
        if (! $this->wasChanged()) {
            return [];
        }

        $changes = [];

        foreach (array_keys($this->getChanges()) as $field) {
            if ($this->shouldIgnoreAuditedAttribute($field)) {
                continue;
            }

            $oldValue = $this->normalizeAuditValue($this->getOriginal($field));
            $newValue = $this->normalizeAuditValue($this->getAttribute($field));

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

    protected function writeAuditEntry(string $event, array $changes): void
    {
        AuditLog::query()->create([
            'account_id' => $this->getAttribute('account_id'),
            'user_id' => Auth::id(),
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'event' => $event,
            'changes' => $changes === [] ? null : $changes,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function auditSnapshot(): array
    {
        $snapshot = [];

        foreach ($this->attributesToArray() as $field => $value) {
            if ($this->shouldIgnoreAuditedAttribute($field)) {
                continue;
            }

            $snapshot[$field] = $this->normalizeAuditValue($value);
        }

        return $snapshot;
    }

    protected function shouldIgnoreAuditedAttribute(string $field): bool
    {
        return in_array($field, $this->auditableExcludedAttributes(), true);
    }

    /**
     * @return list<string>
     */
    protected function auditableExcludedAttributes(): array
    {
        return self::AUDIT_IGNORED_ATTRIBUTES;
    }

    protected function normalizeAuditValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value)) {
            return Str::limit($value, self::AUDIT_MAX_STRING_LENGTH);
        }

        if (is_bool($value) || $value === null || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_array($value)) {
            return Arr::map($value, fn (mixed $item) => $this->normalizeAuditValue($item));
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return Str::limit((string) $value, self::AUDIT_MAX_STRING_LENGTH);
        }

        return $value;
    }
}
