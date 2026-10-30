<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocietySetting extends Model
{
    public const INCLUDE_FBR_TAX = 'include_fbr_tax';

    public const ENABLE_LATE_FEE_RULE = 'enable_late_fee_rule';

    /**
     * Every setting an owner can toggle, keyed by its storage key.
     *
     * The `default` value decides how the setting behaves when no row exists,
     * so adding a setting never changes the behaviour of existing societies.
     */
    public const DEFINITIONS = [
        self::INCLUDE_FBR_TAX => [
            'label' => 'Include FBR Tax',
            'description' => 'Charge FBR tax on the payment plans committed in this society.',
            'default' => true,
        ],
        self::ENABLE_LATE_FEE_RULE => [
            'label' => 'Enable Late Fee Rule',
            'description' => 'Apply late fees to overdue payments in this society.',
            'default' => false,
        ],
    ];

    protected $guarded = [];

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class, 'society_id');
    }

    /**
     * The raw stored value for a setting, or the given default when unset.
     */
    public static function get(int $societyId, string $key, mixed $default = null): mixed
    {
        $value = static::query()
            ->where('society_id', $societyId)
            ->where('key', $key)
            ->value('value');

        return $value ?? $default;
    }

    /**
     * Persist a single setting, storing booleans as "1"/"0".
     */
    public static function set(int $societyId, string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['society_id' => $societyId, 'key' => $key],
            ['value' => static::toString($value)]
        );
    }

    /**
     * Resolved (cast) values for every known setting of a society.
     *
     * @return array<string, mixed>
     */
    public static function settingsFor(int $societyId): array
    {
        $stored = static::query()->where('society_id', $societyId)->pluck('value', 'key');

        $settings = [];
        foreach (self::DEFINITIONS as $key => $definition) {
            $default = $definition['default'];
            $settings[$key] = $stored->has($key)
                ? static::cast($stored->get($key), $default)
                : $default;
        }

        return $settings;
    }

    /**
     * Whether the society charges FBR tax. Unconfigured societies include it,
     * matching the behaviour before this setting existed.
     */
    public static function includeFbrTax(?int $societyId): bool
    {
        if (! $societyId) {
            return true;
        }

        return (bool) static::get($societyId, self::INCLUDE_FBR_TAX, true);
    }

    private static function toString(mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return $value === null ? null : (string) $value;
    }

    private static function cast(?string $value, mixed $default): mixed
    {
        return match (true) {
            is_bool($default) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            is_int($default) => (int) $value,
            is_float($default) => (float) $value,
            default => $value,
        };
    }
}
