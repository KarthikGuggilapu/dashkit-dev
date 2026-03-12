<?php

namespace Dashkit\Commands;

use Dashkit\Models\DashkitAuditLog;
use Dashkit\Support\ArtifactManifest;
use Dashkit\Support\CompatibilityGuard;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class DashkitUninstallCommand extends Command
{
    protected $signature = 'dashkit:uninstall {--yes : Remove all immediately without interactive prompt} {--backup : Create backup before uninstall}';

    protected $description = 'Uninstall Dashkit by removing all installer and generator artifacts.';

    public function handle(Filesystem $files): int
    {
        if (! CompatibilityGuard::ensure($this)) {
            return self::FAILURE;
        }

        $state = $this->loadInstallState($files);
        $packageState = $this->loadPackageState($files);
        $manifest = new ArtifactManifest($files);
        $artifacts = $manifest->all();
        $targets = $this->buildTargets($state, $artifacts);

        $this->components->warn('Dashkit uninstall preview (what will be reset/removed):');
        foreach ($targets as $item) {
            $this->line('- '.$item);
        }

        if ((bool) $this->option('yes')) {
            if ((bool) $this->option('backup')) {
                $backupPath = $this->backupBeforeReset($files, $targets);
                $this->components->info('Backup created at: '.$backupPath);
                $this->components->warn('Mode selected: --yes --backup (backup + remove all at once).');
            } else {
                $this->components->warn('Mode selected: --yes (remove all at once, no backup).');
            }

            $this->performReset($files, $packageState, $artifacts, $manifest, (bool) $this->option('backup'), true);

            DashkitAuditLog::record(
                request(),
                'package.uninstall.completed',
                'dashkit',
                'uninstall',
                [
                    'mode' => 'yes',
                    'backup' => (bool) $this->option('backup'),
                ]
            );

            return self::SUCCESS;
        }

        $mode = $this->choice(
            'Choose uninstall action',
            [
                'yes - keep backup in storage/app/dashkit/uninstall-backups and reset project',
                'no - cancel (undo)',
            ],
            1
        );

        if (str_starts_with($mode, 'no')) {
            $this->components->info('Uninstall cancelled.');

            return self::SUCCESS;
        }

        $backupPath = $this->backupBeforeReset($files, $targets);
        $this->components->info('Backup created at: '.$backupPath);

        $this->performReset($files, $packageState, $artifacts, $manifest, true, false);

        DashkitAuditLog::record(
            request(),
            'package.uninstall.completed',
            'dashkit',
            'uninstall',
            [
                'mode' => 'interactive',
                'backup' => true,
            ]
        );

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $packageState
     * @param  array{files?: array<int, string>, routes?: array<int, string>, hashes?: array<string, string>}  $artifacts
     */
    private function performReset(Filesystem $files, array $packageState, array $artifacts, ArtifactManifest $manifest, bool $keepBackups, bool $assumeYes): void
    {
        $this->components->info('Uninstalling Dashkit package...');

        $this->line('Step 1/11: Restoring installer-tracked file changes');
        $this->restoreInstallState($files);

        $this->line('Step 2/11: Removing config/dashkit.php');
        $this->deleteFile($files, config_path('dashkit.php'));

        $this->line('Step 3/11: Removing resources/views/vendor/dashkit');
        $this->deleteDirectory($files, resource_path('views/vendor/dashkit'));

        $this->line('Step 4/11: Removing public/vendor/dashkit assets');
        $this->deleteDirectory($files, public_path('vendor/dashkit'));

        $this->line('Step 5/11: Removing generated dashboard pages');
        $this->removeTrackedPackagePages($files, $packageState, $assumeYes);

        $this->line('Step 6/11: Removing Dashkit route include');
        $this->removeRouteInclude($files);

        $this->line('Step 7/11: Removing tracked Dashkit generated files/routes');
        $preservedRoutes = $this->removeTrackedArtifacts($files, $artifacts, $assumeYes);
        $this->removeDirectoryIfEmpty($files, resource_path('views/dashkit/pages'));
        $this->removeDirectoryIfEmpty($files, resource_path('views/dashkit/modules'));
        $this->removeDirectoryIfEmpty($files, resource_path('views/dashkit'));

        $this->line('Step 8/11: Removing Dashkit default routes from routes/web.php');
        $this->removeDashkitDefaultRoutes($files, $preservedRoutes);

        $this->line('Step 9/11: Removing Dashkit bootstrap/provider modifications');
        $this->removeBootstrapChanges($files);

        $this->line('Step 10/11: Removing DASHKIT_* environment variables');
        $this->removeDashkitEnvEntries($files);

        $this->line('Step 11/11: Removing Dashkit state/cache files');
        $this->deleteFile($files, storage_path('app/dashkit/package-state.json'));
        $this->deleteFile($files, storage_path('app/dashkit/install-state.json'));

        $manifest->clear();

        if ($keepBackups) {
            $this->deleteDirectory($files, storage_path('app/dashkit'));
            $files->ensureDirectoryExists(storage_path('app/dashkit/uninstall-backups'));
        } else {
            $this->deleteDirectory($files, storage_path('app/dashkit'));
        }

        $this->clearDirectoryContents($files, base_path('bootstrap/cache'));
        $this->callSilent('optimize:clear');

        $this->components->info('Dashkit uninstall completed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function loadInstallState(Filesystem $files): array
    {
        $statePath = storage_path('app/dashkit/install-state.json');

        if (! $files->exists($statePath)) {
            return [];
        }

        $decoded = json_decode((string) $files->get($statePath), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadPackageState(Filesystem $files): array
    {
        $statePath = storage_path('app/dashkit/package-state.json');

        if (! $files->exists($statePath)) {
            return [];
        }

        $decoded = json_decode((string) $files->get($statePath), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array{files?: array<int, string>, routes?: array<int, string>}  $artifacts
     * @return array<int, string>
     */
    private function buildTargets(array $state, array $artifacts): array
    {
        $targets = [
            config_path('dashkit.php'),
            resource_path('views/vendor/dashkit'),
            public_path('vendor/dashkit'),
            resource_path('views/dashkit/pages'),
            resource_path('views/dashkit/modules'),
            base_path('routes/web.php').' (Dashkit route include + generated routes)',
            base_path('bootstrap/app.php').' (guest redirect changes)',
            base_path('.env').' (Dashkit installer values)',
            database_path('seeders/DashkitAdminSeeder.php'),
        ];

        if (isset($state['files']) && is_array($state['files']) && $state['files'] !== []) {
            $targets[] = 'Installer tracked files:';
            foreach (array_keys($state['files']) as $trackedPath) {
                if (is_string($trackedPath)) {
                    $targets[] = '  '.$trackedPath;
                }
            }
        }

        if (isset($artifacts['files']) && is_array($artifacts['files']) && $artifacts['files'] !== []) {
            $targets[] = 'Dashkit generated files:';
            foreach ($artifacts['files'] as $filePath) {
                if (is_string($filePath)) {
                    $targets[] = '  '.$filePath;
                }
            }
        }

        if (isset($artifacts['routes']) && is_array($artifacts['routes']) && $artifacts['routes'] !== []) {
            $targets[] = 'Dashkit generated routes (routes/web.php):';
            foreach ($artifacts['routes'] as $routeName) {
                if (is_string($routeName)) {
                    $targets[] = '  '.$routeName;
                }
            }
        }

        return array_values(array_unique($targets));
    }

    /**
     * @param  array<int, string>  $targets
     */
    private function backupBeforeReset(Filesystem $files, array $targets): string
    {
        $backupRoot = storage_path('app/dashkit/uninstall-backups/'.date('Ymd_His'));
        $files->ensureDirectoryExists($backupRoot);

        foreach ($targets as $target) {
            if (str_contains($target, ' (')) {
                $target = substr($target, 0, (int) strpos($target, ' ('));
            }

            if (str_starts_with($target, '  ')) {
                $target = trim($target);
            }

            if ($target === 'Installer tracked files:' || $target === 'Dashkit generated files:' || $target === 'Dashkit generated routes (routes/web.php):') {
                continue;
            }

            if ($files->isFile($target)) {
                $destination = $backupRoot.DIRECTORY_SEPARATOR.$this->safeRelativePath($target);
                $files->ensureDirectoryExists(dirname($destination));
                $files->copy($target, $destination);
            }

            if ($files->isDirectory($target)) {
                $destination = $backupRoot.DIRECTORY_SEPARATOR.$this->safeRelativePath($target);
                $files->copyDirectory($target, $destination);
            }
        }

        return $backupRoot;
    }

    private function safeRelativePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $normalized = str_replace(':', '', $normalized);

        return ltrim($normalized, '/');
    }

    private function deleteFile(Filesystem $files, string $path): void
    {
        if (! $files->exists($path)) {
            $this->components->warn("Not found: {$path}");

            return;
        }

        $files->delete($path);
        $this->components->info("Deleted: {$path}");
    }

    private function deleteDirectory(Filesystem $files, string $path): void
    {
        if (! $files->isDirectory($path)) {
            $this->components->warn("Not found: {$path}");

            return;
        }

        $files->deleteDirectory($path);
        $this->components->info("Deleted: {$path}");
    }

    private function removeDirectoryIfEmpty(Filesystem $files, string $path): void
    {
        if (! $files->isDirectory($path)) {
            return;
        }

        if ($files->allFiles($path) !== []) {
            $this->components->warn("Kept non-empty directory: {$path}");

            return;
        }

        $files->deleteDirectory($path);
        $this->components->info("Deleted empty directory: {$path}");
    }

    private function clearDirectoryContents(Filesystem $files, string $path): void
    {
        if (! $files->isDirectory($path)) {
            $files->ensureDirectoryExists($path);
            $this->components->info("Created: {$path}");

            return;
        }

        $files->cleanDirectory($path);
        $this->components->info("Cleared: {$path}");
    }

    private function removeRouteInclude(Filesystem $files): void
    {
        $routesFile = base_path('routes/web.php');

        if (! $files->exists($routesFile)) {
            $this->components->warn('routes/web.php not found, skipping route include removal.');

            return;
        }

        $content = $files->get($routesFile);

        $pattern = '/\n?if \(file_exists\(base_path\(\'vendor\/dashkit\/dashkit\/routes\/web\.php\'\)\)\) \{\R\s*require base_path\(\'vendor\/dashkit\/dashkit\/routes\/web\.php\'\);\R\}\R?/m';
        $updated = preg_replace($pattern, PHP_EOL, $content) ?? $content;

        $marker = "require base_path('vendor/dashkit/dashkit/routes/web.php');";
        $updated = str_replace($marker.PHP_EOL, '', $updated);
        $updated = str_replace(PHP_EOL.$marker, '', $updated);
        $updated = str_replace($marker, '', $updated);

        if ($updated === $content) {
            $this->components->warn('No Dashkit route include found in routes/web.php');

            return;
        }

        $files->put($routesFile, $updated);
        $this->components->info('Removed Dashkit route include from routes/web.php');
    }

    private function removeDashkitDefaultRoutes(Filesystem $files, array $preservedRoutes = []): void
    {
        $routesFile = base_path('routes/web.php');

        if (! $files->exists($routesFile)) {
            return;
        }

        $content = $files->get($routesFile);
        $updated = $content;

        foreach (['overview', 'reports', 'settings'] as $slug) {
            $routeName = 'dashkit.page.'.$slug;

            if (in_array($routeName, $preservedRoutes, true)) {
                continue;
            }

            $updated = $this->removeRouteByName($updated, $routeName);
        }

        $updated = (string) preg_replace('/^\s*$/m', '', $updated);
        $updated = trim($updated).PHP_EOL;

        if ($updated !== $content) {
            $files->put($routesFile, $updated);
            $this->components->info('Removed Dashkit default route blocks from routes/web.php');
        }
    }

    private function removeBootstrapChanges(Filesystem $files): void
    {
        $this->removeDashkitProviderRegistration($files);
        $this->removeDashkitGuestRedirect($files);
    }

    private function removeDashkitProviderRegistration(Filesystem $files): void
    {
        if ($this->projectRequiresDashkitPackage($files)) {
            $this->components->info('Dashkit remains a project dependency; keeping bootstrap/providers.php registration.');

            return;
        }

        $providersFile = base_path('bootstrap/providers.php');

        if (! $files->exists($providersFile)) {
            return;
        }

        $content = $files->get($providersFile);
        $updated = str_replace(
            [
                '    Dashkit\\DashkitServiceProvider::class,'.PHP_EOL,
                'Dashkit\\DashkitServiceProvider::class,'.PHP_EOL,
            ],
            '',
            $content
        );

        if ($updated === $content) {
            $updated = (string) preg_replace('/^\s*Dashkit\\\\DashkitServiceProvider::class,\R?/m', '', $content);
        }

        if ($updated !== $content) {
            $files->put($providersFile, $updated);
            $this->components->info('Removed Dashkit service provider from bootstrap/providers.php');
        }
    }

    private function projectRequiresDashkitPackage(Filesystem $files): bool
    {
        $composerFile = base_path('composer.json');

        if (! $files->exists($composerFile)) {
            return false;
        }

        $decoded = json_decode((string) $files->get($composerFile), true);

        if (! is_array($decoded)) {
            return false;
        }

        $require = $decoded['require'] ?? [];

        return is_array($require) && array_key_exists('dashkit/dashkit', $require);
    }

    private function removeDashkitGuestRedirect(Filesystem $files): void
    {
        $appBootstrap = base_path('bootstrap/app.php');

        if (! $files->exists($appBootstrap)) {
            return;
        }

        $content = $files->get($appBootstrap);
        $updated = $content;

        $updated = (string) preg_replace('/\s*\/\/ Redirect unauthenticated users to Dashkit login\.\R\s*\$middleware->redirectGuestsTo\(static fn \(\) => route\(\'dashkit\.login\'\)\);\R?/m', '', $updated);
        $updated = (string) preg_replace('/\s*\$middleware->redirectGuestsTo\(static fn \(\) => route\(\'dashkit\.login\'\)\);\R?/m', '', $updated);

        if ($updated !== $content) {
            $files->put($appBootstrap, $updated);
            $this->components->info('Removed Dashkit guest redirect from bootstrap/app.php');
        }
    }

    private function removeDashkitEnvEntries(Filesystem $files): void
    {
        $envPath = base_path('.env');

        if (! $files->exists($envPath)) {
            return;
        }

        $content = $files->get($envPath);
        $updated = (string) preg_replace('/^DASHKIT_[A-Z0-9_]+=.*\R?/m', '', $content);

        if ($updated !== $content) {
            $files->put($envPath, $updated);
            $this->components->info('Removed DASHKIT_* keys from .env');
        }
    }

    /**
     * @param  array<string, mixed>  $packageState
     */
    private function removeTrackedPackagePages(Filesystem $files, array $packageState, bool $assumeYes): void
    {
        /** @var array<string, string> $fileHashes */
        $fileHashes = isset($packageState['file_hashes']) && is_array($packageState['file_hashes'])
            ? $packageState['file_hashes']
            : [];

        foreach ($fileHashes as $relativePath => $storedHash) {
            if (! str_starts_with($relativePath, 'resources/views/dashkit/pages/')) {
                continue;
            }

            $absolutePath = base_path(str_replace('/', DIRECTORY_SEPARATOR, $relativePath));

            if (! $files->isFile($absolutePath)) {
                continue;
            }

            if (! $this->shouldDeleteTrackedFile($files, $absolutePath, $storedHash, $assumeYes, 'Package-generated page')) {
                continue;
            }

            $files->delete($absolutePath);
            $this->components->info('Removed package-generated page: '.$absolutePath);
        }
    }

    /**
     * @param  array{files?: array<int, string>, routes?: array<int, string>, hashes?: array<string, string>}  $artifacts
     * @return array<int, string>
     */
    private function removeTrackedArtifacts(Filesystem $files, array $artifacts, bool $assumeYes): array
    {
        $preservedRoutes = [];
        /** @var array<string, string> $hashes */
        $hashes = isset($artifacts['hashes']) && is_array($artifacts['hashes'])
            ? $artifacts['hashes']
            : [];

        if (isset($artifacts['files']) && is_array($artifacts['files'])) {
            foreach ($artifacts['files'] as $path) {
                if (! is_string($path)) {
                    continue;
                }

                if ($files->isFile($path)) {
                    $storedHash = $hashes[$path] ?? null;

                    if (! $this->shouldDeleteTrackedFile($files, $path, is_string($storedHash) ? $storedHash : null, $assumeYes, 'Generated file')) {
                        continue;
                    }

                    $files->delete($path);
                    $this->components->info('Removed tracked file: '.$path);
                }
            }
        }

        if (! isset($artifacts['routes']) || ! is_array($artifacts['routes']) || $artifacts['routes'] === []) {
            return $preservedRoutes;
        }

        $routesFile = base_path('routes/web.php');
        if (! $files->exists($routesFile)) {
            return $preservedRoutes;
        }

        $content = $files->get($routesFile);
        $updated = $content;

        foreach ($artifacts['routes'] as $routeName) {
            if (! is_string($routeName)) {
                continue;
            }

            if (! $this->shouldRemoveTrackedRoute($routeName, $updated, $hashes['route:'.$routeName] ?? null, $assumeYes)) {
                $preservedRoutes[] = $routeName;

                continue;
            }

            $updated = $this->removeRouteByName($updated, $routeName);
        }

        if ($updated !== $content) {
            $files->put($routesFile, $updated);
            $this->components->info('Removed tracked Dashkit routes from routes/web.php');
        }

        return $preservedRoutes;
    }

    private function removeRouteByName(string $content, string $routeName): string
    {
        $pattern = '/\n?(?:\/\/ Dashkit generated .*\R)?\\\\Illuminate\\\\Support\\\\Facades\\\\Route::get\([\s\S]*?->name\(\''.preg_quote($routeName, '/').'\'\);\R?/m';

        return (string) preg_replace($pattern, PHP_EOL, $content);
    }

    private function shouldDeleteTrackedFile(Filesystem $files, string $path, ?string $storedHash, bool $assumeYes, string $label): bool
    {
        if ($assumeYes) {
            return true;
        }

        if ($storedHash === null) {
            $this->components->warn("{$label} has no tracked baseline: {$path}");

            return $this->confirm("Delete {$label}?", false);
        }

        $currentHash = md5_file($path) ?: '';

        if (hash_equals($storedHash, $currentHash)) {
            return true;
        }

        $this->components->warn("{$label} has local changes: {$path}");

        return $this->confirm("Delete {$label} and lose those changes?", false);
    }

    private function shouldRemoveTrackedRoute(string $routeName, string $content, mixed $storedSignature, bool $assumeYes): bool
    {
        $routeBlock = $this->extractRouteByName($content, $routeName);

        if ($routeBlock === null) {
            return false;
        }

        if ($assumeYes) {
            return true;
        }

        if (! is_string($storedSignature) || $storedSignature === '') {
            $this->components->warn("Generated route has no tracked baseline: {$routeName}");

            return $this->confirm("Remove route {$routeName}?", false);
        }

        if (hash_equals($storedSignature, md5($routeBlock))) {
            return true;
        }

        $this->components->warn("Generated route has local changes: {$routeName}");

        return $this->confirm("Remove route {$routeName} and lose those changes?", false);
    }

    private function extractRouteByName(string $content, string $routeName): ?string
    {
        $pattern = '/(?:\/\/ Dashkit (?:generated|default) .*\R)?\\\\Illuminate\\\\Support\\\\Facades\\\\Route::get\([\s\S]*?->name\(\''.preg_quote($routeName, '/',).'\'\);\R?/m';

        if (preg_match($pattern, $content, $matches) !== 1) {
            return null;
        }

        return $matches[0] ?? null;
    }

    private function restoreInstallState(Filesystem $files): void
    {
        $statePath = storage_path('app/dashkit/install-state.json');

        if (! $files->exists($statePath)) {
            $this->components->warn('No installer rollback state found. Skipping tracked restore.');

            return;
        }

        $state = json_decode((string) $files->get($statePath), true);

        if (! is_array($state) || ! isset($state['files']) || ! is_array($state['files'])) {
            $this->components->warn('Installer rollback state is invalid. Skipping tracked restore.');

            return;
        }

        foreach ($state['files'] as $path => $meta) {
            $existed = (bool) ($meta['existed'] ?? false);
            $encoded = $meta['content'] ?? null;

            if ($existed) {
                $content = is_string($encoded) ? base64_decode($encoded, true) : false;

                if ($content === false) {
                    $this->components->warn('Skipped restore (invalid backup): '.$path);

                    continue;
                }

                $files->ensureDirectoryExists(dirname($path));
                $files->put($path, $content);
                $this->components->info('Restored: '.$path);

                continue;
            }

            if ($files->exists($path)) {
                $files->delete($path);
                $this->components->info('Removed created file: '.$path);
            }
        }

        $files->delete($statePath);
        $this->components->info('Rollback state cleared.');
    }
}
