<?php

namespace Dashkit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DashkitAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'dashkit_audit_logs';

    protected $fillable = [
        'actor_id',
        'action',
        'target_type',
        'target_key',
        'meta',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function record(Request $request, string $action, ?string $targetType = null, ?string $targetKey = null, array $meta = []): void
    {
        try {
            if (! Schema::hasTable('dashkit_audit_logs')) {
                return;
            }

            $user = $request->user();

            static::query()->create([
                'actor_id' => is_object($user) && method_exists($user, 'getAuthIdentifier')
                    ? (int) $user->getAuthIdentifier()
                    : null,
                'action' => $action,
                'target_type' => $targetType,
                'target_key' => $targetKey,
                'meta' => $meta,
                'ip_address' => (string) ($request->ip() ?? ''),
                'user_agent' => (string) ($request->userAgent() ?? ''),
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Never break user actions because audit logging failed.
        }
    }
}
