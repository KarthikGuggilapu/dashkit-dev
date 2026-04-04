<?php

namespace Dashkit\Support;

use Illuminate\Filesystem\Filesystem;

class ProjectTraceInspector
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * @return array{
      *   lifecycle_status: 'fresh'|'resume-available'|'ready-to-upgrade'|'leftovers-detected',
     *   status: 'clean'|'partial'|'installed',
     *   installed_version: string|null,
      *   recommended_action: string,
      *   recommended_command: string,
     *   present_count: int,
     *   traces: array<int, array{key: string, label: string, present: bool, detail?: string, path?: string}>
     * }
     */
    public function inspect(): array
    {
        $packageStatePath = storage_path('app/dashkit/package-state.json');
        $packageState = $this->readJson($packageStatePath);
        $installedVersion = is_array($packageState)
            ? $this->normalizeInstalledVersion($packageState['installed_version'] ?? null)
            : null;

        $traces = [
            $this->trace('config', 'Published config', $this->files->exists(config_path('dashkit.php')), config_path('dashkit.php')),
            $this->trace('published_views', 'Published vendor views', $this->files->isDirectory(resource_path('views/vendor/dashkit')), resource_path('views/vendor/dashkit')),
            $this->trace('published_assets', 'Published public assets', $this->files->isDirectory(public_path('vendor/dashkit')), public_path('vendor/dashkit')),
            $this->trace('generated_views', 'Generated app views', $this->files->isDirectory(resource_path('views/dashkit')), resource_path('views/dashkit')),
            $this->trace('artifact_manifest', 'Artifact manifest', $this->files->exists(storage_path('app/dashkit/artifacts.json')), storage_path('app/dashkit/artifacts.json')),
            $this->trace('install_state', 'Installer rollback state', $this->files->exists(storage_path('app/dashkit/install-state.json')), storage_path('app/dashkit/install-state.json')),
            $this->trace('install_progress', 'Installer progress state', $this->files->exists(storage_path('app/dashkit/install-progress.json')), storage_path('app/dashkit/install-progress.json')),
            $this->trace('setup_token', 'GUI setup token', $this->files->exists(storage_path('app/dashkit/setup-token.json')), storage_path('app/dashkit/setup-token.json')),
            $this->trace('setup_database', 'GUI setup SQLite', $this->files->exists(storage_path('app/dashkit/setup.sqlite')), storage_path('app/dashkit/setup.sqlite')),
            $this->trace('package_state', 'Package state', $installedVersion !== null || $this->files->exists($packageStatePath), $packageStatePath, $installedVersion !== null ? 'installed_version='.$installedVersion : null),
            $this->trace('route_include', 'Dashkit route include', $this->routesContainDashkitInclude(), base_path('routes/web.php')),
            $this->trace('bootstrap_redirect', 'Dashkit guest redirect', $this->bootstrapContainsDashkitRedirect(), base_path('bootstrap/app.php')),
            $this->trace('env_enabled', 'DASHKIT_ENABLED flag', $this->envContains('DASHKIT_ENABLED=true'), base_path('.env')),
            $this->trace('env_keys', 'DASHKIT_* environment keys', $this->envHasDashkitKeys(), base_path('.env')),
        ];

        $presentCount = count(array_filter($traces, static fn (array $trace): bool => $trace['present']));
        $status = $this->resolveStatus($traces, $installedVersion);

        return [
            'lifecycle_status' => $this->resolveLifecycleStatus($status, $traces),
            'status' => $status,
            'installed_version' => $installedVersion,
            'recommended_action' => $this->recommendedAction($status, $traces),
            'recommended_command' => $this->recommendedCommand($status, $traces),
            'present_count' => $presentCount,
            'traces' => $traces,
        ];
    }

    /**
     * @param  array{lifecycle_status: string, status: string, installed_version: string|null, recommended_action: string, recommended_command: string, present_count: int, traces: array<int, array{key: string, label: string, present: bool, detail?: string, path?: string}>}  $report
     * @return array<int, string>
     */
    public function summaryLines(array $report): array
    {
        $lines = [
            'Dashkit lifecycle: '.strtoupper($report['lifecycle_status']),
            'Dashkit trace status: '.strtoupper($report['status']),
            'Detected traces: '.$report['present_count'],
            'Recommended action: '.$report['recommended_action'],
            'Recommended command: '.$report['recommended_command'],
        ];

        if ($report['installed_version'] !== null) {
            $lines[] = 'Installed version trace: '.$report['installed_version'];
        }

        foreach ($report['traces'] as $trace) {
            if (! $trace['present']) {
                continue;
            }

            $line = '- '.$trace['label'];

            if (isset($trace['detail']) && $trace['detail'] !== '') {
                $line .= ' ('.$trace['detail'].')';
            }

            if (isset($trace['path']) && $trace['path'] !== '') {
                $line .= ': '.$trace['path'];
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
        * @param  array{lifecycle_status: string, status: string, installed_version: string|null, recommended_action: string, recommended_command: string, present_count: int, traces: array<int, array{key: string, label: string, present: bool, detail?: string, path?: string}>}  $report
     * @return array<int, array<int, string>>
     */
    public function tableRows(array $report): array
    {
        $rows = [];

        foreach ($report['traces'] as $trace) {
            $rows[] = [
                $trace['label'],
                $trace['present'] ? 'present' : 'missing',
                (string) ($trace['detail'] ?? ''),
                (string) ($trace['path'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @return array{key: string, label: string, present: bool, detail?: string, path?: string}
     */
    private function trace(string $key, string $label, bool $present, ?string $path = null, ?string $detail = null): array
    {
        $trace = [
            'key' => $key,
            'label' => $label,
            'present' => $present,
        ];

        if ($path !== null) {
            $trace['path'] = $path;
        }

        if ($detail !== null && $detail !== '') {
            $trace['detail'] = $detail;
        }

        return $trace;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $path): ?array
    {
        if (! $this->files->exists($path)) {
            return null;
        }

        $decoded = json_decode((string) $this->files->get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function normalizeInstalledVersion(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function routesContainDashkitInclude(): bool
    {
        $path = base_path('routes/web.php');

        if (! $this->files->exists($path)) {
            return false;
        }

        $content = (string) $this->files->get($path);

        return str_contains($content, "vendor/dashkit/dashkit/routes/web.php");
    }

    private function bootstrapContainsDashkitRedirect(): bool
    {
        $path = base_path('bootstrap/app.php');

        if (! $this->files->exists($path)) {
            return false;
        }

        $content = (string) $this->files->get($path);

        return str_contains($content, "route('dashkit.login')");
    }

    private function envContains(string $needle): bool
    {
        $path = base_path('.env');

        if (! $this->files->exists($path)) {
            return false;
        }

        return str_contains((string) $this->files->get($path), $needle);
    }

    private function envHasDashkitKeys(): bool
    {
        $path = base_path('.env');

        if (! $this->files->exists($path)) {
            return false;
        }

        return preg_match('/^DASHKIT_[A-Z0-9_]+=.*$/m', (string) $this->files->get($path)) === 1;
    }

    /**
     * @param  array<int, array{key: string, label: string, present: bool, detail?: string, path?: string}>  $traces
     */
    private function resolveStatus(array $traces, ?string $installedVersion): string
    {
        $present = array_column(array_filter($traces, static fn (array $trace): bool => $trace['present']), 'key');

        if ($present === []) {
            return 'clean';
        }

        if ($installedVersion !== null) {
            return 'installed';
        }

        $persistentSignals = ['config', 'published_views', 'published_assets', 'route_include', 'env_enabled'];
        $persistentMatches = array_intersect($persistentSignals, $present);

        return count($persistentMatches) >= 3 ? 'installed' : 'partial';
    }

    /**
     * @param  array<int, array{key: string, label: string, present: bool, detail?: string, path?: string}>  $traces
     */
    private function resolveLifecycleStatus(string $status, array $traces): string
    {
        if ($status === 'clean') {
            return 'fresh';
        }

        if ($status === 'installed') {
            return 'ready-to-upgrade';
        }

        return $this->hasTrace($traces, 'install_progress')
            ? 'resume-available'
            : 'leftovers-detected';
    }

    /**
     * @param  array<int, array{key: string, label: string, present: bool, detail?: string, path?: string}>  $traces
     */
    private function recommendedAction(string $status, array $traces): string
    {
        return match ($status) {
            'clean' => 'Run a fresh Dashkit install',
            'installed' => 'Use upgrade or uninstall before reinstalling',
            default => $this->hasTrace($traces, 'install_progress')
                ? 'Resume the interrupted install or reinstall intentionally'
                : 'Inspect leftovers and then continue or reinstall intentionally',
        };
    }

    /**
     * @param  array<int, array{key: string, label: string, present: bool, detail?: string, path?: string}>  $traces
     */
    private function recommendedCommand(string $status, array $traces): string
    {
        return match ($status) {
            'clean' => 'php artisan dashkit:install',
            'installed' => 'php artisan dashkit:upgrade  or  php artisan dashkit:uninstall --backup',
            default => $this->hasTrace($traces, 'install_progress')
                ? 'php artisan dashkit:install --resume  or  php artisan dashkit:install --force'
                : 'php artisan dashkit:inspect  then  php artisan dashkit:install --force',
        };
    }

    /**
     * @param  array<int, array{key: string, label: string, present: bool, detail?: string, path?: string}>  $traces
     */
    private function hasTrace(array $traces, string $key): bool
    {
        foreach ($traces as $trace) {
            if ($trace['key'] === $key && $trace['present']) {
                return true;
            }
        }

        return false;
    }
}