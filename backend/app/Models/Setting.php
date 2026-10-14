<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'is_public',
        'description',
        'constraints',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Cast the stored string value to the declared setting type.
     */
    public function typedValue(): string|int|bool|null
    {
        return $this->typedValueOf($this->value);
    }

    /**
     * Cast an arbitrary storage-representation value to this setting's type.
     */
    public function typedValueOf(?string $storageValue): string|int|bool|null
    {
        if ($storageValue === null) {
            return null;
        }

        return match ($this->type) {
            'boolean' => in_array(strtolower($storageValue), ['1', 'true', 'yes', 'on'], true),
            'integer' => (int) $storageValue,
            default => $storageValue,
        };
    }

    /**
     * Convert a validated update value into its storage representation.
     */
    public function toStorageValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($this->type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) (int) $value,
            default => (string) $value,
        };
    }
}
