<?php

namespace Dashkit\Commands;

use Dashkit\Support\CompatibilityGuard;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class DashkitVersionCommand extends Command
{
    protected $signature = 'dashkit:version {--json : Output as JSON}';

    protected $description = 'Show installed and current Dashkit package versions.';

    public function handle(Filesystem $files): int
    {
        if (! CompatibilityGuard::ensure($this)) {
            return self::FAILURE;
        }

        $currentVersion = $this->currentPackageVersion($files);
        $state = $this->readState($files);
        $installedVersion = (string) ($state['installed_version'] ?? '0.0.0');
        $upToDate = version_compare($currentVersion, $installedVersion, '<=');

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'current_package_version' => $currentVersion,
                'installed_version' => $installedVersion,
                'up_to_date' => $upToDate,
            ], JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->components->info('Dashkit version information');
        $this->line('Current package version: '.$currentVersion);
        $this->line('Installed version: '.$installedVersion);

        if ($upToDate) {
            $this->components->info('Status: up to date');
        } else {
            $this->components->warn('Status: update available');
            $this->line('Run: composer run dashkit-update');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function readState(Filesystem $files): array
    {
        $path = storage_path('app/dashkit/package-state.json');

        if (! $files->exists($path)) {
            return [];
        }

        $decoded = json_decode((string) $files->get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function currentPackageVersion(Filesystem $files): string
    {
        $composerPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'composer.json';

        if (! $files->exists($composerPath)) {
            return '0.0.0';
        }

        $decoded = json_decode((string) $files->get($composerPath), true);

        if (! is_array($decoded)) {
            return '0.0.0';
        }

        $version = $decoded['version'] ?? null;

        return is_string($version) && $version !== '' ? $version : '0.0.0';
    }
}
