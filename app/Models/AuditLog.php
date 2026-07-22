<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    public const EVENT_CREATED = 'created';
    public const EVENT_UPDATED = 'updated';
    public const EVENT_DELETED = 'deleted';

    protected $table = 'tbl_audit_log';

    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'user_id',
        'auditable_type',
        'auditable_id',
        'event',
        'changes',
        'created_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Keep UI filter labels friendly while storing fully polymorphic model classes.
     *
     * @return array<string, string>
     */
    public static function entityTypeOptions(): array
    {
        return [
            Service::class => 'Service',
            Transaction::class => 'Transaction',
            Purchase::class => 'Purchase',
            PurchaseItem::class => 'Purchase Item',
            InventoryLedger::class => 'Inventory Ledger',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function eventOptions(): array
    {
        return [
            self::EVENT_CREATED,
            self::EVENT_UPDATED,
            self::EVENT_DELETED,
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function entityLabel(): string
    {
        return self::entityTypeOptions()[$this->auditable_type] ?? Str::headline(class_basename($this->auditable_type));
    }

    public function eventBadgeClasses(): string
    {
        return match ($this->event) {
            self::EVENT_CREATED => 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300',
            self::EVENT_UPDATED => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-500/15 dark:text-yellow-300',
            self::EVENT_DELETED => 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-700/60 dark:text-gray-200',
        };
    }

    public function userDisplayName(): string
    {
        return $this->user?->name ?? 'System';
    }

    /**
     * @return list<string>
     */
    public function changeLines(): array
    {
        $changes = is_array($this->changes) ? $this->changes : [];
        $lines = [];

        foreach ($changes as $field => $value) {
            $label = Str::headline((string) $field);

            if ($this->event === self::EVENT_UPDATED && is_array($value) && array_key_exists('old', $value) && array_key_exists('new', $value)) {
                $lines[] = sprintf(
                    '%s: %s -> %s',
                    $label,
                    $this->displayValue($value['old']),
                    $this->displayValue($value['new'])
                );

                continue;
            }

            $lines[] = sprintf('%s: %s', $label, $this->displayValue($value));
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    public function previewChangeLines(int $limit = 3): array
    {
        return array_slice($this->changeLines(), 0, $limit);
    }

    public function hasHiddenChangeLines(int $limit = 3): bool
    {
        return count($this->changeLines()) > $limit;
    }

    public function changeSummaryLabel(): string
    {
        $fieldCount = count(is_array($this->changes) ? $this->changes : []);

        return match ($this->event) {
            self::EVENT_CREATED => 'Initial values captured'.($fieldCount > 0 ? " ({$fieldCount} fields)" : '.'),
            self::EVENT_DELETED => 'Final values captured'.($fieldCount > 0 ? " ({$fieldCount} fields)" : '.'),
            default => $fieldCount === 0
                ? 'No changed fields captured.'
                : Str::plural('field', $fieldCount).' updated',
        };
    }

    protected function displayValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—';
        }

        return (string) $value;
    }
}
