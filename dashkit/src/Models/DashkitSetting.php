<?php

namespace Dashkit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class DashkitSetting extends Model
{
    protected $table = 'dashkit_settings';

    protected $fillable = [
        'key',
        'group',
        'value',
        'value_type',
        'is_secret',
        'autoload',
        'updated_by',
    ];

    protected $casts = [
        'is_secret' => 'bool',
        'autoload' => 'bool',
    ];

    public static function get(string $key, string $default = ''): string
    {
        $row = static::query()->where('key', $key)->first();

        if (! $row instanceof self) {
            return $default;
        }

        $value = (string) ($row->value ?? '');

        if (! $row->is_secret) {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return $default;
        }
    }

    public static function put(string $key, string $value, string $group = 'general', string $valueType = 'string', bool $autoload = true, ?int $updatedBy = null): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'value' => $value,
                'value_type' => $valueType,
                'is_secret' => false,
                'autoload' => $autoload,
                'updated_by' => $updatedBy,
            ]
        );
    }

    public static function putSecret(string $key, string $value, string $group = 'general', bool $autoload = true, ?int $updatedBy = null): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'value' => Crypt::encryptString($value),
                'value_type' => 'secret',
                'is_secret' => true,
                'autoload' => $autoload,
                'updated_by' => $updatedBy,
            ]
        );
    }
}
