<?php

namespace Dashkit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DashkitUserPreference extends Model
{
    protected $table = 'dashkit_user_preferences';

    protected $fillable = [
        'user_id',
        'key',
        'value',
        'value_type',
    ];

    public static function getValue(int $userId, string $key, string $default = ''): string
    {
        try {
            if (! Schema::hasTable('dashkit_user_preferences')) {
                return $default;
            }

            $row = static::query()
                ->where('user_id', $userId)
                ->where('key', $key)
                ->first();

            if (! $row instanceof self) {
                return $default;
            }

            return (string) ($row->value ?? $default);
        } catch (Throwable) {
            return $default;
        }
    }

    /**
     * @param  array<string, string>  $pairs
     */
    public static function putMany(int $userId, array $pairs): void
    {
        try {
            if (! Schema::hasTable('dashkit_user_preferences')) {
                return;
            }

            foreach ($pairs as $key => $value) {
                static::query()->updateOrCreate(
                    ['user_id' => $userId, 'key' => $key],
                    [
                        'value' => $value,
                        'value_type' => 'string',
                    ]
                );
            }
        } catch (Throwable) {
            // Ignore preference persistence issues so core profile update still succeeds.
        }
    }

    public static function forgetKey(int $userId, string $key): void
    {
        try {
            if (! Schema::hasTable('dashkit_user_preferences')) {
                return;
            }

            static::query()->where('user_id', $userId)->where('key', $key)->delete();
        } catch (Throwable) {
            // Ignore preference delete failures.
        }
    }
}
