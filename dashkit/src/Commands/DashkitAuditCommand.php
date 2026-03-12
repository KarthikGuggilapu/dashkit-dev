<?php

namespace Dashkit\Commands;

use Dashkit\Models\DashkitAuditLog;
use Dashkit\Support\CompatibilityGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DashkitAuditCommand extends Command
{
    protected $signature = 'dashkit:audit
        {--action= : Filter by action, e.g. profile.updated}
        {--actor= : Filter by actor user id}
        {--target= : Filter by target key}
        {--from= : Start date/time (Y-m-d or Y-m-d H:i:s)}
        {--to= : End date/time (Y-m-d or Y-m-d H:i:s)}
        {--limit=50 : Max rows to show (1-500)}';

    protected $description = 'Show Dashkit audit log events with optional filters.';

    public function handle(): int
    {
        if (! CompatibilityGuard::ensure($this)) {
            return self::FAILURE;
        }

        if (! Schema::hasTable('dashkit_audit_logs')) {
            $this->components->warn('dashkit_audit_logs table was not found. Run migrations first.');

            return self::SUCCESS;
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $query = DashkitAuditLog::query()->orderByDesc('created_at');

        $action = trim((string) $this->option('action'));
        if ($action !== '') {
            $query->where('action', $action);
        }

        $actor = trim((string) $this->option('actor'));
        if ($actor !== '' && ctype_digit($actor)) {
            $query->where('actor_id', (int) $actor);
        }

        $target = trim((string) $this->option('target'));
        if ($target !== '') {
            $query->where('target_key', $target);
        }

        $from = trim((string) $this->option('from'));
        if ($from !== '') {
            $query->where('created_at', '>=', $from);
        }

        $to = trim((string) $this->option('to'));
        if ($to !== '') {
            $query->where('created_at', '<=', $to);
        }

        $rows = $query->limit($limit)->get();

        if ($rows->isEmpty()) {
            $this->components->info('No audit events matched the current filters.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'When', 'Actor', 'Action', 'Target', 'IP'],
            $rows->map(static function (DashkitAuditLog $row): array {
                return [
                    (string) $row->getAttribute('id'),
                    (string) $row->getAttribute('created_at'),
                    (string) ($row->getAttribute('actor_id') ?? '-'),
                    (string) $row->getAttribute('action'),
                    (string) ($row->getAttribute('target_key') ?? '-'),
                    (string) ($row->getAttribute('ip_address') ?? '-'),
                ];
            })->all()
        );

        $this->line('Total shown: '.$rows->count());

        return self::SUCCESS;
    }
}
