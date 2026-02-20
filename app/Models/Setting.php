<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    protected $table = 'LGL_SYS_CONFIG';

    protected $primaryKey = 'CONFIG_ID'; // Migration renamed 'id' to 'CONFIG_ID'

    const CREATED_AT = 'REF_CONFIG_CREATED_DT';

    const UPDATED_AT = 'REF_CONFIG_UPDATED_DT';

    protected $fillable = [
        'CONFIG_KEY',
        'CONFIG_VALUE',
        'type', // Not renamed in migration check
        'description', // Not renamed in migration check
    ];

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $setting = Cache::remember("setting.{$key}", 3600, function () use ($key) {
                return static::where('CONFIG_KEY', $key)->first();
            });
        } catch (\Exception $e) {
            return $default;
        }

        if (! $setting) {
            return $default;
        }

        return static::castValue($setting->CONFIG_VALUE, $setting->type);
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, mixed $value, ?string $type = null, ?string $description = null): self
    {
        $setting = static::updateOrCreate(
            ['CONFIG_KEY' => $key],
            [
                'CONFIG_VALUE' => is_array($value) || is_object($value) ? json_encode($value) : (string) $value,
                'type' => $type ?? static::guessType($value),
                'description' => $description,
            ]
        );

        Cache::forget("setting.{$key}");

        return $setting;
    }

    /**
     * Cast value based on type.
     */
    protected static function castValue(string $value, ?string $type): mixed
    {
        return match ($type) {
            'integer', 'int' => (int) $value,
            'float', 'double' => (float) $value,
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json', 'array' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Guess the type of value.
     */
    protected static function guessType(mixed $value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_int($value)) {
            return 'integer';
        }

        if (is_float($value)) {
            return 'float';
        }

        if (is_array($value) || is_object($value)) {
            return 'json';
        }

        return 'string';
    }

    /**
     * Get all settings as key-value pairs.
     *
     * @return array<string, mixed>
     */
    public static function allAsArray(): array
    {
        return static::all()->mapWithKeys(function ($setting) {
            return [$setting->CONFIG_KEY => static::castValue($setting->CONFIG_VALUE, $setting->type)];
        })->toArray();
    }
}
