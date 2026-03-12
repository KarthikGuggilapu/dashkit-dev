<?php

namespace Dashkit\Commands;

use Dashkit\Models\DashkitAuditLog;
use Dashkit\Models\DashkitSetting;
use Dashkit\Support\CompatibilityGuard;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Throwable;

class DashkitUpgradeCommand extends Command
{
    protected $signature = 'dashkit:upgrade {--force : Overwrite published files during upgrade} {--dry-run : Preview upgrade actions without applying} {--type= : Dashboard preset [default|ecommerce|crm]}';

    protected $description = 'Upgrade an existing Dashkit installation to the latest package version.';

    public function handle(Filesystem $files): int
    {
        if (! CompatibilityGuard::ensure($this)) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $preset = $this->resolveUpgradePreset();

        $state = $this->readState($files);
        $installedVersion = (string) ($state['installed_version'] ?? '0.0.0');
        $installedFingerprint = (string) ($state['package_fingerprint'] ?? '');
        $currentVersion = $this->currentPackageVersion($files);
        $currentFingerprint = $this->currentPackageFingerprint($files);
        $versionAdvanced = version_compare($currentVersion, $installedVersion, '>');
        $fingerprintChanged = $installedFingerprint !== '' && $currentFingerprint !== '' && ! hash_equals($installedFingerprint, $currentFingerprint);

        $this->components->info('Dashkit upgrade check');
        $this->line('Installed version: '.$installedVersion);
        $this->line('Current package version: '.$currentVersion);
        $this->line('Preset: '.$preset);

        if (! $versionAdvanced && ! $fingerprintChanged && ! $force) {
            $this->components->info('Dashkit is already up to date.');

            return self::SUCCESS;
        }

        if (! $versionAdvanced && $fingerprintChanged) {
            $this->components->warn('Dashkit package contents changed, but composer.json version was not bumped.');
            $this->components->warn('Upgrade will continue using the package fingerprint diff. Bump the package version before release.');
        }

        if (! $versionAdvanced && ! $fingerprintChanged && $force) {
            $this->components->warn('Dashkit version is current, but --force was provided, so update actions will be re-applied.');
        }

        $this->components->warn('Upgrade available. Planned actions:');
        $this->line('- Publish config, views, and assets');
        $this->line('- Ensure Dashkit route include and default pages/routes');
        $this->line('- Run migrations');
        $this->line('- Clear config/view cache');

        if ($dryRun) {
            $this->components->info('Dry run complete. No changes were applied.');

            return self::SUCCESS;
        }

        $this->components->info('Applying upgrade...');

        $this->publishConfigWithPermission($files, $state, $force);
        $state['view_hashes'] = $this->publishViewsWithPermission($files, $state, $force);
        $this->call('vendor:publish', ['--tag' => 'dashkit-assets', '--force' => $force]);

        $this->ensureRouteInclude($files);
        $this->applyPresetSidebarConfig($files, $preset);
        $this->ensureDefaultPages($files, $preset);
        $this->ensureFunctionalProfilePage($files, $state, $force);
        $this->upgradeLegacyProfilePage($files);
        $this->ensureDefaultPageRoutes($files, array_keys($this->presetPages($preset)));

        $this->call('migrate', ['--force' => true]);
        $this->persistRuntimeCoreSettings($preset);
        $this->callSilent('config:clear');
        $this->callSilent('view:clear');

        $state['installed_version'] = $currentVersion;
        $state['install_preset'] = $preset;
        $state['package_fingerprint'] = $currentFingerprint;
        $state['upgraded_at'] = now()->toDateTimeString();
        $state['file_hashes'] = $this->buildCurrentFileHashes($files, $state);
        $this->writeState($files, $state);

        DashkitAuditLog::record(
            request(),
            'package.upgrade.completed',
            'dashkit',
            'upgrade',
            [
                'from_version' => $installedVersion,
                'to_version' => $currentVersion,
                'preset' => $preset,
                'forced' => $force,
                'fingerprint_changed' => $fingerprintChanged,
            ]
        );

        $this->components->info('Dashkit upgraded successfully to version '.$currentVersion.'.');

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

    /**
     * @param  array<string, mixed>  $state
     */
    private function writeState(Filesystem $files, array $state): void
    {
        $path = storage_path('app/dashkit/package-state.json');
        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
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

    private function currentPackageFingerprint(Filesystem $files): string
    {
        $packageRoot = dirname(__DIR__, 2);
        $paths = [
            $packageRoot.DIRECTORY_SEPARATOR.'composer.json',
            $packageRoot.DIRECTORY_SEPARATOR.'config',
            $packageRoot.DIRECTORY_SEPARATOR.'resources',
            $packageRoot.DIRECTORY_SEPARATOR.'routes',
            $packageRoot.DIRECTORY_SEPARATOR.'src',
        ];

        $entries = [];

        foreach ($paths as $path) {
            if ($files->isFile($path)) {
                $relativePath = str_replace($packageRoot.DIRECTORY_SEPARATOR, '', $path);
                $entries[] = str_replace('\\', '/', $relativePath).':'.(md5_file($path) ?: '');
                continue;
            }

            if (! $files->isDirectory($path)) {
                continue;
            }

            /** @var \SplFileInfo[] $allFiles */
            $allFiles = $files->allFiles($path);

            foreach ($allFiles as $file) {
                $relativePath = str_replace($packageRoot.DIRECTORY_SEPARATOR, '', $file->getPathname());
                $entries[] = str_replace('\\', '/', $relativePath).':'.(md5_file($file->getPathname()) ?: '');
            }
        }

        sort($entries);

        return md5(implode('|', $entries));
    }

    private function persistRuntimeCoreSettings(string $preset): void
    {
        try {
            DashkitSetting::put('app_name', (string) config('app.name', 'Dashkit'), 'app', 'string');
            DashkitSetting::put('app_env', (string) config('app.env', env('APP_ENV', 'production')), 'app', 'string');
            DashkitSetting::put('app_url', (string) config('app.url', env('APP_URL', '')), 'app', 'string');
            DashkitSetting::put('app_timezone', (string) config('app.timezone', 'UTC'), 'app', 'string');
            DashkitSetting::put('app_locale', (string) config('app.locale', 'en'), 'app', 'string');

            DashkitSetting::put('db_connection', (string) config('database.default', 'mysql'), 'database', 'string');
            DashkitSetting::put('db_host', (string) config('database.connections.'.config('database.default').'.host', ''), 'database', 'string');
            DashkitSetting::put('db_port', (string) config('database.connections.'.config('database.default').'.port', ''), 'database', 'string');
            DashkitSetting::put('db_database', (string) config('database.connections.'.config('database.default').'.database', ''), 'database', 'string');
            DashkitSetting::put('db_username', (string) config('database.connections.'.config('database.default').'.username', ''), 'database', 'string');

            DashkitSetting::put('mail_mailer', (string) config('mail.default', 'smtp'), 'mail', 'string');
            DashkitSetting::put('mail_host', (string) config('mail.mailers.smtp.host', ''), 'mail', 'string');
            DashkitSetting::put('mail_port', (string) config('mail.mailers.smtp.port', ''), 'mail', 'int');
            DashkitSetting::put('mail_username', (string) config('mail.mailers.smtp.username', ''), 'mail', 'string');
            DashkitSetting::put('mail_encryption', (string) config('mail.mailers.smtp.encryption', ''), 'mail', 'string');
            DashkitSetting::put('mail_from_address', (string) config('mail.from.address', ''), 'mail', 'string');
            DashkitSetting::put('mail_from_name', (string) config('mail.from.name', ''), 'mail', 'string');

            $mailPassword = (string) config('mail.mailers.smtp.password', '');
            if ($mailPassword !== '') {
                DashkitSetting::putSecret('mail_password', $mailPassword, 'mail');
            }

            DashkitSetting::put('auth_guard', (string) config('dashkit.auth.guard', 'web'), 'auth', 'string');
            DashkitSetting::put('auth_password_broker', (string) config('dashkit.auth.password_broker', 'users'), 'auth', 'string');
            DashkitSetting::put('route_prefix', (string) config('dashkit.route_prefix', 'dashboard'), 'routing', 'string');
            DashkitSetting::put('install_preset', $preset, 'app', 'string');
        } catch (Throwable) {
            // Do not fail upgrades if settings persistence cannot be completed.
        }
    }

    /**
     * Smart config publisher:
     * - Auto-publishes when the developer hasn't touched config/dashkit.php.
     * - Asks confirmation when the developer modified it, so their changes aren't lost.
     * - With --force, always overwrites.
     *
     * @param  array<string, mixed>  $state
     */
    private function publishConfigWithPermission(Filesystem $files, array $state, bool $force): void
    {
        $configPath = config_path('dashkit.php');

        /** @var array<string, string> $fileHashes */
        $fileHashes = isset($state['file_hashes']) && is_array($state['file_hashes'])
            ? $state['file_hashes']
            : [];

        $storedHash = $fileHashes['config/dashkit.php'] ?? null;

        if (! $force && $storedHash !== null && $files->exists($configPath)) {
            $currentHash = md5_file($configPath) ?: '';

            if ($currentHash !== $storedHash) {
                $this->components->warn('config/dashkit.php has local modifications.');
                $choice = $this->choice(
                    '[config/dashkit.php] What do you want to do?',
                    [
                        'skip'      => 'Keep my version (skip this file)',
                        'overwrite' => 'Overwrite with package version (lose my changes)',
                    ],
                    'skip'
                );

                if ($choice === 'skip') {
                    $this->line('  <fg=blue>Kept your config/dashkit.php</>');

                    return;
                }
            }
        }

        $this->call('vendor:publish', ['--tag' => 'dashkit-config', '--force' => true]);
    }

    /**
     * Rebuild the file_hashes map after upgrade — snapshot current state of all Dashkit-managed files.
     * Merges with any existing hashes so make-page / make-module tracked files are preserved.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, string>
     */
    private function buildCurrentFileHashes(Filesystem $files, array $state): array
    {
        /** @var array<string, string> $hashes */
        $hashes = isset($state['file_hashes']) && is_array($state['file_hashes'])
            ? $state['file_hashes']
            : [];

        $singleFiles = [
            'config/dashkit.php' => config_path('dashkit.php'),
            'routes/web.php'     => base_path('routes/web.php'),
            'bootstrap/app.php'  => base_path('bootstrap/app.php'),
        ];

        foreach ($singleFiles as $key => $absPath) {
            if ($files->exists($absPath)) {
                $hashes[$key] = md5_file($absPath) ?: '';
            }
        }

        $pagesDir = resource_path('views/dashkit/pages');
        if ($files->isDirectory($pagesDir)) {
            /** @var \SplFileInfo[] $pageFiles */
            $pageFiles = $files->allFiles($pagesDir);
            foreach ($pageFiles as $file) {
                $rel = 'resources/views/dashkit/pages/'.str_replace('\\', '/', $file->getRelativePathname());
                $hashes[$rel] = md5_file($file->getPathname()) ?: '';
            }
        }

        return $hashes;
    }

    /**
     * Smart view publisher:
     * - Auto-updates files the developer has NOT customised.
     * - Asks confirmation only when BOTH the package AND the developer changed the same file.
     * - Skips files that only the developer changed (their customisation, leave it).
     * - Always copies new files that don't exist in the app yet.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, string> Updated view hashes to store in state
     */
    private function publishViewsWithPermission(Filesystem $files, array $state, bool $force): array
    {
        $sourceDir = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views';
        $destDir = resource_path('views/vendor/dashkit');

        if (! $files->isDirectory($sourceDir)) {
            return [];
        }

        /** @var array<string, string> $storedHashes */
        $storedHashes = isset($state['view_hashes']) && is_array($state['view_hashes'])
            ? $state['view_hashes']
            : [];

        $newHashes = [];

        /** @var \SplFileInfo[] $allFiles */
        $allFiles = $files->allFiles($sourceDir);

        foreach ($allFiles as $sourceFile) {
            $relativePath = str_replace($sourceDir.DIRECTORY_SEPARATOR, '', $sourceFile->getPathname());
            $relativePath = str_replace('\\', '/', $relativePath);

            $destPath = $destDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $sourceHash = md5_file($sourceFile->getPathname()) ?: '';
            $newHashes[$relativePath] = $sourceHash;
            $storedHash = $storedHashes[$relativePath] ?? null;

            // File doesn't exist in the app yet — always copy it (new file added to package)
            if (! $files->exists($destPath)) {
                $files->ensureDirectoryExists(dirname($destPath));
                $files->copy($sourceFile->getPathname(), $destPath);
                $this->line("  <fg=green>Published new view:</> $relativePath");
                continue;
            }

            $currentHash = md5_file($destPath) ?: '';

            // --force flag: overwrite everything without asking
            if ($force) {
                $files->copy($sourceFile->getPathname(), $destPath);
                $this->line("  <fg=yellow>Force updated view:</> $relativePath");
                continue;
            }

            // Package hasn't changed this file — leave whatever the developer has
            if ($storedHash !== null && $sourceHash === $storedHash) {
                continue;
            }

            // Package has a new version of this file
            // Check if the developer modified their copy
            if ($storedHash !== null && $currentHash === $storedHash) {
                // Developer's copy is still the original — safe to auto-update
                $files->copy($sourceFile->getPathname(), $destPath);
                $this->line("  <fg=green>Auto-updated view:</> $relativePath");
                continue;
            }

            // Both package and developer changed this file — ask permission
            $this->components->warn("View file has package updates AND local changes: $relativePath");
            $choice = $this->choice(
                "  [$relativePath] What do you want to do?",
                [
                    'skip'      => 'Keep my version (skip this file)',
                    'overwrite' => 'Overwrite with package version (lose my changes)',
                ],
                'skip'
            );

            if ($choice === 'overwrite') {
                $files->copy($sourceFile->getPathname(), $destPath);
                $this->line("  <fg=yellow>Overwritten:</> $relativePath");
            } else {
                $this->line("  <fg=blue>Kept your version:</> $relativePath");
            }
        }

        // Return merged hashes — handle() merges them into state before final writeState
        return array_merge($storedHashes, $newHashes);
    }

    private function ensureRouteInclude(Filesystem $files): void
    {
        if (! (bool) config('dashkit.install.append_routes', true)) {
            return;
        }

        $routesFile = base_path('routes/web.php');
        $marker = "require base_path('vendor/dashkit/dashkit/routes/web.php');";

        if (! $files->exists($routesFile)) {
            return;
        }

        $content = $files->get($routesFile);

        if (str_contains($content, $marker)) {
            return;
        }

        $snippet = PHP_EOL."if (file_exists(base_path('vendor/dashkit/dashkit/routes/web.php'))) {".PHP_EOL
            ."    {$marker}".PHP_EOL
            .'}'.PHP_EOL;

        $files->append($routesFile, $snippet);
    }

    private function ensureDefaultPages(Filesystem $files, string $preset = 'default'): void
    {
        $pagesPath = $this->resolveAppPagesPath();
        $files->ensureDirectoryExists($pagesPath);

        $pages = $this->presetPages($preset);

        foreach ($pages as $slug => $title) {
            $path = $pagesPath.DIRECTORY_SEPARATOR.$slug.'.blade.php';

            if ($files->exists($path)) {
                continue;
            }

            $template = match ($slug) {
                'profile' => $this->profilePageTemplate(),
                'overview' => $this->overviewPageTemplate(),
                'reports' => $this->reportsPageTemplate(),
                'settings' => $this->settingsPageTemplate(),
                default => $this->presetPageTemplate($title, $slug),
            };

            $files->put($path, $template);
        }
    }

    private function upgradeLegacyProfilePage(Filesystem $files): void
    {
        $profilePath = $this->resolveAppPagesPath().DIRECTORY_SEPARATOR.'profile.blade.php';

        if (! $files->exists($profilePath)) {
            return;
        }

        $content = (string) $files->get($profilePath);
        $legacyMarkers = [
            'Generated default page for Dashkit.',
            'You can update your name, email, and password here later.',
        ];

        foreach ($legacyMarkers as $marker) {
            if (str_contains($content, $marker)) {
                $files->put($profilePath, $this->profilePageTemplate());

                return;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function ensureFunctionalProfilePage(Filesystem $files, array $state, bool $force): void
    {
        $profilePath = $this->resolveAppPagesPath().DIRECTORY_SEPARATOR.'profile.blade.php';

        if (! $files->exists($profilePath)) {
            return;
        }

        $content = (string) $files->get($profilePath);

        if (str_contains($content, "route('dashkit.profile.update')")) {
            return;
        }

        /** @var array<string, string> $fileHashes */
        $fileHashes = isset($state['file_hashes']) && is_array($state['file_hashes'])
            ? $state['file_hashes']
            : [];

        $key = 'resources/views/dashkit/pages/profile.blade.php';
        $storedHash = $fileHashes[$key] ?? null;
        $currentHash = md5_file($profilePath) ?: '';

        if ($force || ($storedHash !== null && hash_equals($storedHash, $currentHash))) {
            $files->put($profilePath, $this->profilePageTemplate());
            $this->line('  <fg=green>Updated profile page template to functional auth/settings version.</>');

            return;
        }

        $this->components->warn('Profile page is outdated but has local changes: resources/views/dashkit/pages/profile.blade.php');
        $choice = $this->choice(
            '[profile.blade.php] Update to new functional template?',
            [
                'skip' => 'Keep my customized profile page',
                'overwrite' => 'Overwrite with new Dashkit functional template',
            ],
            'skip'
        );

        if ($choice === 'overwrite') {
            $files->put($profilePath, $this->profilePageTemplate());
            $this->line('  <fg=yellow>Profile page overwritten with functional template.</>');
        }
    }

    private function defaultPageTemplate(string $title): string
    {
        return "<x-dashkit-layout title=\"{$title}\">\n"
            ."    <section class=\"dk-panel\">\n"
            ."        <h2>{$title}</h2>\n"
            ."        <p>Generated default page for Dashkit.</p>\n"
            ."    </section>\n"
            ."</x-dashkit-layout>\n";
    }

    private function presetPageTemplate(string $title, string $slug): string
    {
        $meta = match ($slug) {
            'products' => ['Catalog Health', '5,483 SKUs', 'Active Listings', '4,921', 'Manage products and variants with stock visibility.'],
            'orders' => ['Orders Today', '286', 'Fulfillment SLA', '94%', 'Track order pipeline and dispatch performance.'],
            'customers' => ['Active Customers', '12,430', 'Repeat Rate', '38%', 'Understand customer behavior and retention trends.'],
            'inventory' => ['Stock Accuracy', '97.8%', 'Low Stock Items', '38', 'Monitor warehouse availability and replenishment.'],
            'leads' => ['New Leads', '142', 'Qualified Leads', '56', 'Prioritize top-potential prospects for follow-up.'],
            'contacts' => ['Total Contacts', '3,920', 'Engaged Contacts', '1,248', 'Organize communication history and touchpoints.'],
            'deals' => ['Open Pipeline', '$284K', 'Win Rate', '31%', 'Track deal stages and forecast monthly revenue.'],
            'activities' => ['Tasks Due Today', '19', 'Completed This Week', '143', 'Keep sales motions on schedule with clear visibility.'],
            default => null,
        };

        if (! is_array($meta)) {
            return $this->defaultPageTemplate($title);
        }

        return "<x-dashkit-layout title=\"{$title}\">\n"
            ."    <section class=\"space-y-6\">\n"
            ."        <header class=\"rounded-2xl border border-slate-200 bg-white p-6 shadow-sm\">\n"
            ."            <h2 class=\"text-2xl font-bold text-slate-900\">{$title}</h2>\n"
            ."            <p class=\"mt-1 text-sm text-slate-500\">{$meta[4]}</p>\n"
            ."        </header>\n"
            ."\n"
            ."        <div class=\"grid gap-4 md:grid-cols-2\">\n"
            ."            <article class=\"rounded-xl border border-slate-200 bg-white p-5 shadow-sm\">\n"
            ."                <p class=\"text-xs font-semibold uppercase tracking-wide text-slate-500\">{$meta[0]}</p>\n"
            ."                <p class=\"mt-2 text-3xl font-bold text-slate-900\">{$meta[1]}</p>\n"
            ."            </article>\n"
            ."            <article class=\"rounded-xl border border-slate-200 bg-white p-5 shadow-sm\">\n"
            ."                <p class=\"text-xs font-semibold uppercase tracking-wide text-slate-500\">{$meta[2]}</p>\n"
            ."                <p class=\"mt-2 text-3xl font-bold text-slate-900\">{$meta[3]}</p>\n"
            ."            </article>\n"
            ."        </div>\n"
            ."\n"
            ."        <article class=\"rounded-xl border border-slate-200 bg-white p-5 shadow-sm\">\n"
            ."            <h3 class=\"text-lg font-semibold text-slate-900\">Quick Actions</h3>\n"
            ."            <div class=\"mt-4 grid gap-3 sm:grid-cols-3\">\n"
            ."                <button class=\"rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50\">Create</button>\n"
            ."                <button class=\"rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50\">Import</button>\n"
            ."                <button class=\"rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50\">Export</button>\n"
            ."            </div>\n"
            ."        </article>\n"
            ."    </section>\n"
            ."</x-dashkit-layout>\n";
    }

    private function overviewPageTemplate(): string
    {
        return <<<'BLADE'
<x-dashkit-layout title="Overview">
    <!-- Stat Cards Section -->
    <section class="mb-8">
        <h2 class="mb-4 text-2xl font-bold text-slate-900">Overview</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Stat Card 1 -->
            <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Total Revenue</p>
                        <p class="mt-2 text-3xl font-bold text-slate-900">$12,450</p>
                    </div>
                    <div class="rounded-lg bg-blue-100 p-3">
                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <p class="mt-4 text-xs text-emerald-600 font-medium">↑ 12% from last month</p>
            </article>

            <!-- Stat Card 2 -->
            <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Total Orders</p>
                        <p class="mt-2 text-3xl font-bold text-slate-900">2,859</p>
                    </div>
                    <div class="rounded-lg bg-emerald-100 p-3">
                        <svg class="h-6 w-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                    </div>
                </div>
                <p class="mt-4 text-xs text-emerald-600 font-medium">↑ 8% from last month</p>
            </article>

            <!-- Stat Card 3 -->
            <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Total Users</p>
                        <p class="mt-2 text-3xl font-bold text-slate-900">5,483</p>
                    </div>
                    <div class="rounded-lg bg-purple-100 p-3">
                        <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.856-1.487M15 10a3 3 0 11-6 0 3 3 0 016 0zM6 20h12v-2a9 9 0 00-9-9 9 9 0 00-3 .134V16"></path>
                        </svg>
                    </div>
                </div>
                <p class="mt-4 text-xs text-emerald-600 font-medium">↑ 5% from last month</p>
            </article>

            <!-- Stat Card 4 -->
            <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Conversion Rate</p>
                        <p class="mt-2 text-3xl font-bold text-slate-900">3.24%</p>
                    </div>
                    <div class="rounded-lg bg-orange-100 p-3">
                        <svg class="h-6 w-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                </div>
                <p class="mt-4 text-xs text-red-600 font-medium">↓ 2% from last month</p>
            </article>
        </div>
    </section>

    <!-- Charts & Analytics Section -->
    <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Chart 1 -->
        <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-lg font-bold text-slate-900">Revenue Trend</h3>
            <div class="flex h-64 items-center justify-center bg-slate-50">
                <div class="text-center">
                    <p class="text-slate-500">📊 Chart visualization will appear here</p>
                    <p class="mt-2 text-xs text-slate-400">Install a charting library (Chart.js, ApexCharts, etc.) to display data</p>
                </div>
            </div>
        </article>

        <!-- Chart 2 -->
        <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-lg font-bold text-slate-900">Category Distribution</h3>
            <div class="flex h-64 items-center justify-center bg-slate-50">
                <div class="text-center">
                    <p class="text-slate-500">📈 Pie/Donut chart will appear here</p>
                    <p class="mt-2 text-xs text-slate-400">Connect real data from your database</p>
                </div>
            </div>
        </article>
    </section>

    <!-- Recent Activity Section -->
    <section class="mt-8">
        <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-lg font-bold text-slate-900">Recent Orders</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200">
                            <th class="py-3 px-4 text-left font-semibold text-slate-700">Order ID</th>
                            <th class="py-3 px-4 text-left font-semibold text-slate-700">Customer</th>
                            <th class="py-3 px-4 text-left font-semibold text-slate-700">Amount</th>
                            <th class="py-3 px-4 text-left font-semibold text-slate-700">Status</th>
                            <th class="py-3 px-4 text-left font-semibold text-slate-700">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b border-slate-200 hover:bg-slate-50">
                            <td class="py-3 px-4 font-medium text-slate-900">#ORD-001</td>
                            <td class="py-3 px-4 text-slate-600">John Doe</td>
                            <td class="py-3 px-4 font-semibold text-slate-900">$1,299</td>
                            <td class="py-3 px-4"><span class="inline-block rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Completed</span></td>
                            <td class="py-3 px-4 text-slate-500">Mar 10, 2026</td>
                        </tr>
                        <tr class="border-b border-slate-200 hover:bg-slate-50">
                            <td class="py-3 px-4 font-medium text-slate-900">#ORD-002</td>
                            <td class="py-3 px-4 text-slate-600">Jane Smith</td>
                            <td class="py-3 px-4 font-semibold text-slate-900">$899</td>
                            <td class="py-3 px-4"><span class="inline-block rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">Processing</span></td>
                            <td class="py-3 px-4 text-slate-500">Mar 09, 2026</td>
                        </tr>
                        <tr class="hover:bg-slate-50">
                            <td class="py-3 px-4 font-medium text-slate-900">#ORD-003</td>
                            <td class="py-3 px-4 text-slate-600">Mike Johnson</td>
                            <td class="py-3 px-4 font-semibold text-slate-900">$2,150</td>
                            <td class="py-3 px-4"><span class="inline-block rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">Pending</span></td>
                            <td class="py-3 px-4 text-slate-500">Mar 08, 2026</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 text-center">
                <a href="javascript:void(0)" class="text-sm font-semibold text-blue-600 hover:text-blue-700">View all orders →</a>
            </div>
        </article>
    </section>
</x-dashkit-layout>
BLADE;
    }

    private function reportsPageTemplate(): string
    {
        return <<<'BLADE'
<x-dashkit-layout title="Reports">
    <section class="mb-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Reports</h2>
                <p class="mt-1 text-sm text-slate-500">Analyze your business performance and metrics</p>
            </div>
            <button class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700 transition">
                Export Report
            </button>
        </div>

        <!-- Filter Section -->
        <div class="mb-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 font-semibold text-slate-900">Filters</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Date Range</label>
                    <select class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        <option>Last 7 days</option>
                        <option>Last 30 days</option>
                        <option>Last 90 days</option>
                        <option>This year</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Category</label>
                    <select class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        <option>All Categories</option>
                        <option>Electronics</option>
                        <option>Clothing</option>
                        <option>Home & Garden</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Status</label>
                    <select class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        <option>All Statuses</option>
                        <option>Completed</option>
                        <option>Processing</option>
                        <option>Pending</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Reports Grid -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <!-- Sales Report -->
            <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-lg font-bold text-slate-900">Sales Report</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <span class="text-sm font-medium text-slate-700">Total Sales</span>
                        <span class="text-xl font-bold text-slate-900">$45,230</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <span class="text-sm font-medium text-slate-700">Avg Order Value</span>
                        <span class="text-xl font-bold text-slate-900">$156.50</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <span class="text-sm font-medium text-slate-700">Total Orders</span>
                        <span class="text-xl font-bold text-slate-900">289</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-700">Conversion Rate</span>
                        <span class="text-xl font-bold text-emerald-600">3.24%</span>
                    </div>
                </div>
            </article>

            <!-- Customer Report -->
            <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-lg font-bold text-slate-900">Customer Report</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <span class="text-sm font-medium text-slate-700">Total Customers</span>
                        <span class="text-xl font-bold text-slate-900">5,483</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <span class="text-sm font-medium text-slate-700">New Customers</span>
                        <span class="text-xl font-bold text-slate-900">342</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <span class="text-sm font-medium text-slate-700">Repeat Customers</span>
                        <span class="text-xl font-bold text-slate-900">1,245</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-700">Customer Retention</span>
                        <span class="text-xl font-bold text-blue-600">87.3%</span>
                    </div>
                </div>
            </article>

            <!-- Traffic Report -->
            <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-lg font-bold text-slate-900">Traffic Report</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <span class="text-sm font-medium text-slate-700">Total Visitors</span>
                        <span class="text-xl font-bold text-slate-900">24,592</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <span class="text-sm font-medium text-slate-700">Unique Visitors</span>
                        <span class="text-xl font-bold text-slate-900">18,324</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <span class="text-sm font-medium text-slate-700">Bounce Rate</span>
                        <span class="text-xl font-bold text-red-600">32%</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-700">Avg Session Duration</span>
                        <span class="text-xl font-bold text-slate-900">4m 23s</span>
                    </div>
                </div>
            </article>

            <!-- Inventory Report -->
            <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-lg font-bold text-slate-900">Inventory Report</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <span class="text-sm font-medium text-slate-700">Total Stock</span>
                        <span class="text-xl font-bold text-slate-900">5,483</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <span class="text-sm font-medium text-slate-700">Low Stock Items</span>
                        <span class="text-xl font-bold text-orange-600">38</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <span class="text-sm font-medium text-slate-700">Out of Stock</span>
                        <span class="text-xl font-bold text-red-600">12</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-slate-700">Stock Turnover Rate</span>
                        <span class="text-xl font-bold text-emerald-600">4.2x</span>
                    </div>
                </div>
            </article>
        </div>
    </section>
</x-dashkit-layout>
BLADE;
    }

    private function settingsPageTemplate(): string
    {
        return <<<'BLADE'
<x-dashkit-layout title="Settings">
    <section class="mb-8">
        <h2 class="mb-6 text-2xl font-bold text-slate-900">Settings</h2>

        <!-- General Settings -->
        <article class="mb-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="mb-6 text-lg font-bold text-slate-900">General Settings</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Application Name</label>
                    <input type="text" value="Dashkit" class="w-full rounded-lg border border-slate-300 px-4 py-2 text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="Enter app name">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Application URL</label>
                    <input type="url" value="https://example.com" class="w-full rounded-lg border border-slate-300 px-4 py-2 text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="Enter app URL">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Support Email</label>
                    <input type="email" value="support@example.com" class="w-full rounded-lg border border-slate-300 px-4 py-2 text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="Enter support email">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Timezone</label>
                    <select class="w-full rounded-lg border border-slate-300 px-4 py-2 text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        <option>UTC</option>
                        <option>EST</option>
                        <option>CST</option>
                        <option>PST</option>
                    </select>
                </div>
            </div>
            <div class="mt-6 pt-6 border-t border-slate-200">
                <button class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700 transition">
                    Save Changes
                </button>
            </div>
        </article>

        <!-- Email Settings -->
        <article class="mb-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="mb-6 text-lg font-bold text-slate-900">Email Configuration</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Mail Driver</label>
                    <select class="w-full rounded-lg border border-slate-300 px-4 py-2 text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        <option>SMTP</option>
                        <option>Mailgun</option>
                        <option>Postmark</option>
                        <option>SES</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">SMTP Host</label>
                    <input type="text" value="smtp.mailtrap.io" class="w-full rounded-lg border border-slate-300 px-4 py-2 text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">SMTP Port</label>
                    <input type="number" value="465" class="w-full rounded-lg border border-slate-300 px-4 py-2 text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">From Address</label>
                    <input type="email" value="noreply@example.com" class="w-full rounded-lg border border-slate-300 px-4 py-2 text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
            </div>
            <div class="mt-6 pt-6 border-t border-slate-200">
                <button class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700 transition">
                    Save Email Settings
                </button>
            </div>
        </article>

        <!-- Security Settings -->
        <article class="mb-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="mb-6 text-lg font-bold text-slate-900">Security & Privacy</h3>
            <div class="space-y-4">
                <div>
                    <label class="flex items-center space-x-3">
                        <input type="checkbox" checked class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span class="font-medium text-slate-900">Enable Two-Factor Authentication</span>
                    </label>
                </div>
                <div>
                    <label class="flex items-center space-x-3">
                        <input type="checkbox" checked class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span class="font-medium text-slate-900">Require password change on first login</span>
                    </label>
                </div>
                <div>
                    <label class="flex items-center space-x-3">
                        <input type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span class="font-medium text-slate-900">Allow users to export their data</span>
                    </label>
                </div>
                <div>
                    <label class="flex items-center space-x-3">
                        <input type="checkbox" checked class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span class="font-medium text-slate-900">Log all user activities</span>
                    </label>
                </div>
            </div>
            <div class="mt-6 pt-6 border-t border-slate-200">
                <button class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700 transition">
                    Save Security Settings
                </button>
            </div>
        </article>

        <!-- Backup & Maintenance -->
        <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="mb-6 text-lg font-bold text-slate-900">Backup & Maintenance</h3>
            <div class="space-y-4">
                <div class="rounded-lg bg-slate-50 p-4">
                    <p class="font-medium text-slate-900">Database Backup</p>
                    <p class="mt-1 text-sm text-slate-500">Last backup: Mar 10, 2026 at 03:45 AM</p>
                    <button class="mt-3 rounded-lg bg-white px-4 py-2 font-medium text-slate-900 border border-slate-300 hover:bg-slate-50 transition">
                        Backup Now
                    </button>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <p class="font-medium text-slate-900">Clear Cache</p>
                    <p class="mt-1 text-sm text-slate-500">Remove all cached data to free up space</p>
                    <button class="mt-3 rounded-lg bg-white px-4 py-2 font-medium text-slate-900 border border-slate-300 hover:bg-slate-50 transition">
                        Clear Cache
                    </button>
                </div>
            </div>
        </article>
    </section>
</x-dashkit-layout>
BLADE;
    }

    private function profilePageTemplate(): string
    {
        return <<<'BLADE'
<x-dashkit-layout title="Profile">
    @php
        $user = $user ?? auth()->user();
        $mailSettings = $mailSettings ?? [
            'mailer' => env('MAIL_MAILER', 'smtp'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', '2525'),
            'username' => env('MAIL_USERNAME', ''),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'from_address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
            'from_name' => env('MAIL_FROM_NAME', config('app.name', 'Dashkit')),
        ];
        $name = (string) ($user?->name ?? 'Dashkit User');
        $email = (string) ($user?->email ?? 'not-available@example.com');
        $initials = collect(explode(' ', trim($name)))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
        $initials = $initials !== '' ? $initials : 'DU';
    @endphp

    @if (session('status') === 'profile-updated')
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">Profile updated successfully.</div>
    @endif

    @if (session('status') === 'password-updated')
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">Password updated successfully.</div>
    @endif

    @if (session('status') === 'mail-settings-updated')
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">Mail settings saved.</div>
    @endif

    <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center gap-4">
            <div class="grid h-16 w-16 place-content-center rounded-2xl bg-gradient-to-br from-teal-500 to-cyan-500 text-xl font-bold text-white">{{ $initials }}</div>
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ $name }}</h2>
                <p class="text-sm text-slate-500">{{ $email }}</p>
            </div>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-3">
            <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Role</p>
                <p class="mt-1 text-sm font-semibold text-slate-800">Administrator</p>
            </article>
            <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Access</p>
                <p class="mt-1 text-sm font-semibold text-slate-800">Full Dashboard</p>
            </article>
            <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p>
                <p class="mt-1 text-sm font-semibold text-emerald-700">Active</p>
            </article>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-lg font-bold text-slate-900">Edit Profile</h3>
            <form method="POST" action="{{ route('dashkit.profile.update') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="profile_name">Name</label>
                    <input id="profile_name" type="text" name="name" value="{{ old('name', $name) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                    @error('name')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="profile_email">Email</label>
                    <input id="profile_email" type="email" name="email" value="{{ old('email', $email) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                    @error('email')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Save Profile</button>
            </form>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-lg font-bold text-slate-900">Change Password</h3>
            <form method="POST" action="{{ route('dashkit.profile.password.update') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="current_password">Current Password</label>
                    <input id="current_password" type="password" name="current_password" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                    @error('current_password')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="new_password">New Password</label>
                    <input id="new_password" type="password" name="password" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                    @error('password')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="new_password_confirmation">Confirm Password</label>
                    <input id="new_password_confirmation" type="password" name="password_confirmation" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                </div>

                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Update Password</button>
            </form>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
            <h3 class="mb-4 text-lg font-bold text-slate-900">Mail Settings</h3>
            <form method="POST" action="{{ route('dashkit.settings.mail.update') }}" class="grid gap-4 md:grid-cols-2">
                @csrf

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_mailer">Mailer</label>
                    <input id="mail_mailer" type="text" name="mail_mailer" value="{{ old('mail_mailer', (string) ($mailSettings['mailer'] ?? 'smtp')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_host">Host</label>
                    <input id="mail_host" type="text" name="mail_host" value="{{ old('mail_host', (string) ($mailSettings['host'] ?? '127.0.0.1')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_port">Port</label>
                    <input id="mail_port" type="number" name="mail_port" value="{{ old('mail_port', (string) ($mailSettings['port'] ?? '2525')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_username">Username</label>
                    <input id="mail_username" type="text" name="mail_username" value="{{ old('mail_username', (string) ($mailSettings['username'] ?? '')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_password">Password (leave blank to keep current)</label>
                    <input id="mail_password" type="password" name="mail_password" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_encryption">Encryption</label>
                    <input id="mail_encryption" type="text" name="mail_encryption" value="{{ old('mail_encryption', (string) ($mailSettings['encryption'] ?? 'tls')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_from_address">From Address</label>
                    <input id="mail_from_address" type="email" name="mail_from_address" value="{{ old('mail_from_address', (string) ($mailSettings['from_address'] ?? 'hello@example.com')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_from_name">From Name</label>
                    <input id="mail_from_name" type="text" name="mail_from_name" value="{{ old('mail_from_name', (string) ($mailSettings['from_name'] ?? config('app.name', 'Dashkit'))) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                </div>

                <div class="md:col-span-2">
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700">Save Mail Settings</button>
                </div>
            </form>
        </article>
    </section>
</x-dashkit-layout>
BLADE;
    }

    /**
     * @param array<int, string> $slugs
     */
    private function ensureDefaultPageRoutes(Filesystem $files, array $slugs): void
    {
        $routesFile = base_path('routes/web.php');

        if (! $files->exists($routesFile)) {
            return;
        }

        $content = $files->get($routesFile);

        foreach ($slugs as $slug) {
            if ($slug === 'profile' || $slug === 'settings') {
                continue;
            }

            $routeName = "dashkit.page.{$slug}";

            if (str_contains($content, "->name('{$routeName}')")) {
                continue;
            }

            $prefix = trim((string) config('dashkit.route_prefix', 'dashboard'), '/');

            $snippet = PHP_EOL
                ."// Dashkit default page route: {$slug}".PHP_EOL
                ."\\Illuminate\\Support\\Facades\\Route::get('/{$prefix}/{$slug}', [\\Dashkit\\Http\\Controllers\\DashboardController::class, 'page'])".PHP_EOL
                ."    ->middleware(array_merge((array) config('dashkit.route_middleware', ['web']), (bool) config('dashkit.auth.enabled', true) ? ['auth:' . (string) config('dashkit.auth.guard', 'web')] : []))".PHP_EOL
                ."    ->defaults('page', '{$slug}')".PHP_EOL
                ."    ->name('{$routeName}');".PHP_EOL;

            $files->append($routesFile, $snippet);
            $content .= $snippet;
        }
    }

    private function resolveUpgradePreset(): string
    {
        $provided = (string) $this->option('type');

        if ($provided !== '') {
            return $this->normalizePreset($provided);
        }

        return $this->normalizePreset((string) env('DASHKIT_INSTALL_PRESET', 'default'));
    }

    private function normalizePreset(string $preset): string
    {
        $normalized = strtolower(trim($preset));

        return in_array($normalized, ['default', 'ecommerce', 'crm'], true)
            ? $normalized
            : 'default';
    }

    /**
     * @return array<string, string>
     */
    private function presetPages(string $preset): array
    {
        $base = [
            'overview' => 'Overview',
            'profile' => 'Profile',
            'reports' => 'Reports',
        ];

        if ($preset === 'ecommerce') {
            return [
                'overview' => 'Overview',
                'profile' => 'Profile',
                'products' => 'Products',
                'orders' => 'Orders',
                'customers' => 'Customers',
                'inventory' => 'Inventory',
                'reports' => 'Reports',
            ];
        }

        if ($preset === 'crm') {
            return [
                'overview' => 'Overview',
                'profile' => 'Profile',
                'leads' => 'Leads',
                'contacts' => 'Contacts',
                'deals' => 'Deals',
                'activities' => 'Activities',
                'reports' => 'Reports',
            ];
        }

        return $base;
    }

    private function applyPresetSidebarConfig(Filesystem $files, string $preset): void
    {
        $configFile = config_path('dashkit.php');

        if (! $files->exists($configFile)) {
            return;
        }

        $sidebarItems = match ($preset) {
            'ecommerce' => [
                "        ['title' => 'Overview', 'route' => 'dashkit.home'],",
                "        ['title' => 'Products', 'route' => 'dashkit.page.products'],",
                "        ['title' => 'Orders', 'route' => 'dashkit.page.orders'],",
                "        ['title' => 'Customers', 'route' => 'dashkit.page.customers'],",
                "        ['title' => 'Inventory', 'route' => 'dashkit.page.inventory'],",
                "        ['title' => 'Reports', 'route' => 'dashkit.page.reports'],",
                "        ['title' => 'Settings', 'route' => 'dashkit.settings'],",
            ],
            'crm' => [
                "        ['title' => 'Overview', 'route' => 'dashkit.home'],",
                "        ['title' => 'Leads', 'route' => 'dashkit.page.leads'],",
                "        ['title' => 'Contacts', 'route' => 'dashkit.page.contacts'],",
                "        ['title' => 'Deals', 'route' => 'dashkit.page.deals'],",
                "        ['title' => 'Activities', 'route' => 'dashkit.page.activities'],",
                "        ['title' => 'Reports', 'route' => 'dashkit.page.reports'],",
                "        ['title' => 'Settings', 'route' => 'dashkit.settings'],",
            ],
            default => [
                "        ['title' => 'Overview', 'route' => 'dashkit.home'],",
                "        ['title' => 'Reports', 'route' => 'dashkit.page.reports'],",
                "        ['title' => 'Settings', 'route' => 'dashkit.settings'],",
            ],
        };

        $content = $files->get($configFile);
        $replacement = "'sidebar' => [".PHP_EOL.implode(PHP_EOL, $sidebarItems).PHP_EOL."    ],".PHP_EOL.PHP_EOL."    'topbar' => [";
        $updated = (string) preg_replace("/'sidebar'\s*=>\s*\[[\s\S]*?\],\r?\n\r?\n\s*'topbar'\s*=>\s*\[/", $replacement, $content, 1);

        if ($updated !== $content) {
            $files->put($configFile, $updated);
        }
    }

    private function resolveAppPagesPath(): string
    {
        $configured = (string) config('dashkit.generated_pages_path', resource_path('views/dashkit/pages'));

        if (str_contains(str_replace('\\', '/', $configured), '/views/vendor/dashkit/pages')) {
            return resource_path('views/dashkit/pages');
        }

        return $configured;
    }
}
