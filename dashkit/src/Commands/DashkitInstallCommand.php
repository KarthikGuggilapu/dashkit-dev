<?php

namespace Dashkit\Commands;

use Dashkit\Models\DashkitAuditLog;
use Dashkit\Models\DashkitSetting;
use Dashkit\Support\ArtifactManifest;
use Dashkit\Support\CompatibilityGuard;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PDO;
use Throwable;

class DashkitInstallCommand extends Command
{
    protected $signature = 'dashkit:install {--force : Overwrite published files} {--resume : Continue from last incomplete install step} {--type= : Dashboard preset [default|ecommerce|crm]}';

    protected $description = 'Install Dashkit by publishing config, views, assets and route registration.';

    /** @var array{files: array<string, array{existed: bool, content: string|null}>, created_at?: string} */
    private array $installState = ['files' => []];

    private Filesystem $files;

    private ArtifactManifest $manifest;

    /** @var array{completed: array<int, string>, setup?: array<string, string>} */
    private array $progress = ['completed' => []];

    public function handle(Filesystem $files): int
    {
        if (! CompatibilityGuard::ensure($this)) {
            return self::FAILURE;
        }

        $this->files = $files;
        $this->manifest = new ArtifactManifest($files);
        $this->installState = ['files' => [], 'created_at' => now()->toDateTimeString()];
        $force = (bool) $this->option('force');
        $resume = (bool) $this->option('resume');
        $this->progress = $this->loadInstallProgress();

        if (! $resume) {
            $this->progress = ['completed' => []];
        }

        $this->components->info('Installing Dashkit package...');
        $this->line('Step 1/7: Publishing configuration');
        if (! $this->stepCompleted('publish_config')) {
            $this->publishTag('dashkit-config', $force);
            $this->markStepCompleted('publish_config');
        }

        $this->line('Step 2/7: Publishing views');
        if (! $this->stepCompleted('publish_views')) {
            $this->publishTag('dashkit-views', $force);
            $this->markStepCompleted('publish_views');
        }

        $this->line('Step 3/7: Publishing assets');
        if (! $this->stepCompleted('publish_assets')) {
            $this->publishTag('dashkit-assets', $force);
            $this->markStepCompleted('publish_assets');
        }

        $this->line('Step 4/7: Route registration');
        if (! $this->stepCompleted('route_registration')) {
            if ((bool) config('dashkit.install.append_routes', true)) {
                $this->appendRouteInclude($files);
            } else {
                $this->components->warn('Skipped appending routes include (dashkit.install.append_routes=false).');
            }

            $this->markStepCompleted('route_registration');
        }

        $this->line('Step 5/7: Collecting setup inputs and updating .env');
        $setup = $this->progress['setup'] ?? [];
        if (! $this->stepCompleted('env_setup') || ! is_array($setup) || $setup === []) {
            $setup = $this->collectSetupInputs();
            $this->updateEnvironment($setup);
            $this->progress['setup'] = $setup;
            $this->markStepCompleted('env_setup');
        }

        $this->line('Step 6/7: Enforcing dashboard login redirect and preparing preset pages');
        if (! $this->stepCompleted('post_setup')) {
            $preset = $this->normalizePreset((string) ($setup['DASHKIT_INSTALL_PRESET'] ?? 'default'));
            $this->ensureGuestRedirectConfigured();
            $this->applyPresetSidebarConfig($preset);
            $this->ensureDefaultPages($preset);
            $this->markStepCompleted('post_setup');
        }

        $this->line('Step 7/7: Running migrations and seeding admin user');
        if (! $this->stepCompleted('migrate_seed')) {
            if (! $this->runMigrationAndSeed($setup)) {
                $this->components->warn('Install progress has been saved. Re-run with --resume after fixing DB issues.');

                return self::FAILURE;
            }

            $this->markStepCompleted('migrate_seed');
        }

        $this->persistInstallState();
        $this->persistPackageState();
        $this->clearInstallProgress();
        $this->injectComposerUpdateScript();

        $this->components->info('Dashkit installed successfully.');
        $this->line('Visit: /');
        $this->components->info('Use the seeded admin credentials to login. You can update them later in the profile page.');
        $this->components->info('To get future Dashkit updates, run: composer run dashkit-update');

        DashkitAuditLog::record(
            request(),
            'package.install.completed',
            'dashkit',
            'install',
            [
                'version' => (string) (($this->readPackageState()['installed_version'] ?? '0.0.0')),
                'db_connection' => (string) ($setup['DB_CONNECTION'] ?? ''),
                'install_preset' => (string) ($setup['DASHKIT_INSTALL_PRESET'] ?? 'default'),
                'mail_mailer' => (string) ($setup['MAIL_MAILER'] ?? ''),
            ]
        );

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function readPackageState(): array
    {
        $statePath = storage_path('app/dashkit/package-state.json');

        if (! $this->files->exists($statePath)) {
            return [];
        }

        $decoded = json_decode((string) $this->files->get($statePath), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, string>
     */
    private function collectSetupInputs(): array
    {
        $appName = (string) $this->ask('Application name', (string) env('APP_NAME', config('app.name', 'Laravel')));
        $dbConnection = (string) $this->choice('DB connection', ['sqlite', 'mysql', 'pgsql'], (string) env('DB_CONNECTION', 'mysql'));

        if ($dbConnection === 'sqlite') {
            $dbDatabase = $this->normalizeSqliteDatabasePath($this->defaultSqliteDatabaseInput());
            $dbHost = '';
            $dbPort = '';
            $dbUsername = '';
            $dbPassword = '';
            $this->components->info('SQLite selected. Database file will be used at: '.$dbDatabase);
        } else {
            $defaultPort = $dbConnection === 'pgsql' ? '5432' : '3306';
            $dbHost = (string) $this->ask('DB host', (string) env('DB_HOST', '127.0.0.1'));
            $dbPort = (string) $this->ask('DB port', (string) env('DB_PORT', $defaultPort));
            $dbDatabase = (string) $this->ask('DB database', (string) env('DB_DATABASE', 'laravel'));
            $dbUsername = (string) $this->ask('DB username', (string) env('DB_USERNAME', 'root'));
            $dbPassword = (string) ($this->secret('DB password (leave empty to keep current)') ?: (string) env('DB_PASSWORD', ''));
        }

        $adminName = (string) $this->ask('Default admin name', (string) env('DASHKIT_DEFAULT_ADMIN_NAME', 'Dashkit Admin'));
        $adminEmail = (string) $this->ask('Default admin email', (string) env('DASHKIT_DEFAULT_ADMIN_EMAIL', 'admin@example.com'));
        $adminPassword = (string) ($this->secret('Default admin password') ?: 'password');

        $mailMailer = (string) $this->ask('Mail driver (smtp/log/mailgun/etc)', (string) env('MAIL_MAILER', 'smtp'));
        $mailHost = (string) $this->ask('Mail host', (string) env('MAIL_HOST', '127.0.0.1'));
        $mailPort = (string) $this->ask('Mail port', (string) env('MAIL_PORT', '2525'));
        $mailUsername = (string) $this->ask('Mail username', (string) env('MAIL_USERNAME', ''));
        $mailPassword = (string) ($this->secret('Mail password (leave empty to keep current)') ?: (string) env('MAIL_PASSWORD', ''));
        $mailEncryption = (string) $this->ask('Mail encryption (tls/ssl/null)', (string) env('MAIL_ENCRYPTION', 'tls'));
        $mailFromAddress = (string) $this->ask('Mail from address', (string) env('MAIL_FROM_ADDRESS', 'hello@example.com'));
        $mailFromName = (string) $this->ask('Mail from name', (string) env('MAIL_FROM_NAME', $appName));
        $preset = $this->resolveInstallPreset();

        return [
            'APP_NAME' => $appName,
            'DB_CONNECTION' => $dbConnection,
            'DB_HOST' => $dbHost,
            'DB_PORT' => $dbPort,
            'DB_DATABASE' => $dbDatabase,
            'DB_USERNAME' => $dbUsername,
            'DB_PASSWORD' => $dbPassword,
            'DASHKIT_DEFAULT_ADMIN_NAME' => $adminName,
            'DASHKIT_DEFAULT_ADMIN_EMAIL' => $adminEmail,
            'DASHKIT_DEFAULT_ADMIN_PASSWORD' => $adminPassword,
            'MAIL_MAILER' => $mailMailer,
            'MAIL_HOST' => $mailHost,
            'MAIL_PORT' => $mailPort,
            'MAIL_USERNAME' => $mailUsername,
            'MAIL_PASSWORD' => $mailPassword,
            'MAIL_ENCRYPTION' => $mailEncryption,
            'MAIL_FROM_ADDRESS' => $mailFromAddress,
            'MAIL_FROM_NAME' => $mailFromName,
            'DASHKIT_MAIL_FROM_ADDRESS' => $mailFromAddress,
            'DASHKIT_MAIL_FROM_NAME' => $mailFromName,
            'DASHKIT_INSTALL_PRESET' => $preset,
        ];
    }

    private function defaultSqliteDatabaseInput(): string
    {
        $currentConnection = (string) env('DB_CONNECTION', '');
        $currentDatabase = (string) env('DB_DATABASE', '');

        if ($currentConnection === 'sqlite' && $currentDatabase !== '') {
            return $currentDatabase;
        }

        return 'database/database.sqlite';
    }

    private function normalizeSqliteDatabasePath(string $database): string
    {
        $database = trim($database);

        if ($database === '') {
            return database_path('database.sqlite');
        }

        if (! str_ends_with(strtolower($database), '.sqlite')) {
            $database .= '.sqlite';
        }

        if (str_contains($database, ':') || str_starts_with($database, '/') || str_starts_with($database, '\\')) {
            return $database;
        }

        if (str_starts_with(str_replace('\\', '/', $database), 'database/')) {
            return base_path($database);
        }

        return database_path($database);
    }

    private function resolveInstallPreset(): string
    {
        $provided = (string) $this->option('type');

        if ($provided !== '') {
            return $this->normalizePreset($provided);
        }

        $choice = (string) $this->choice(
            'Select dashboard preset',
            ['default', 'ecommerce', 'crm'],
            'default'
        );

        return $this->normalizePreset($choice);
    }

    private function normalizePreset(string $preset): string
    {
        $normalized = strtolower(trim($preset));

        return in_array($normalized, ['default', 'ecommerce', 'crm'], true)
            ? $normalized
            : 'default';
    }

    /**
     * @param  array<string, string>  $setup
     */
    private function updateEnvironment(array $setup): void
    {
        $envPath = base_path('.env');

        if (! $this->files->exists($envPath)) {
            $this->components->warn('.env file not found. Skipping environment update.');

            return;
        }

        $this->backupFileBeforeWrite($envPath);
        $content = $this->files->get($envPath);

        foreach ($setup as $key => $value) {
            $content = $this->upsertEnv($content, $key, $this->envValue($value));
        }

        $this->files->put($envPath, $content);
        $this->applyRuntimeDatabaseConfiguration($setup);

        $this->components->info('Updated .env with app, database, and default admin values.');
    }

    private function envValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/\s/', $value) === 1 || str_contains($value, '#')) {
            return '"'.str_replace('"', '\\"', $value).'"';
        }

        return $value;
    }

    private function upsertEnv(string $content, string $key, string $value): string
    {
        $line = $key.'='.$value;

        $content = (string) preg_replace('/^[\s#]*'.preg_quote($key, '/').'=.*$/m', '', $content);

        return rtrim($content).PHP_EOL.$line.PHP_EOL;
    }

    private function ensureGuestRedirectConfigured(): void
    {
        $appBootstrap = base_path('bootstrap/app.php');

        if (! $this->files->exists($appBootstrap)) {
            $this->components->warn('bootstrap/app.php not found. Skipping guest redirect setup.');

            return;
        }

        $content = $this->files->get($appBootstrap);
        $marker = "redirectGuestsTo(static fn () => route('dashkit.login'))";

        if (str_contains($content, $marker)) {
            $this->components->info('Guest redirect to dashkit.login is already configured.');

            return;
        }

        $search = '->withMiddleware(function (Middleware $middleware): void {';

        if (! str_contains($content, $search)) {
            $this->components->warn('Could not locate middleware configuration block in bootstrap/app.php.');

            return;
        }

        $replace = $search.PHP_EOL
            .'        // Redirect unauthenticated users to Dashkit login.'.PHP_EOL
            ."        \$middleware->redirectGuestsTo(static fn () => route('dashkit.login'));";

        $this->backupFileBeforeWrite($appBootstrap);
        $this->files->put($appBootstrap, str_replace($search, $replace, $content));

        $this->components->info('Configured guest redirect to dashkit.login in bootstrap/app.php.');
    }

    private function ensureDefaultPages(string $preset = 'default'): void
    {
        $pages = $this->presetPages($preset);

        $pagesPath = $this->resolveAppPagesPath();
        $this->files->ensureDirectoryExists($pagesPath);

        foreach ($pages as $slug => $title) {
            $pagePath = $pagesPath.DIRECTORY_SEPARATOR.$slug.'.blade.php';

            if ($this->files->exists($pagePath)) {
                $this->components->info("Page [{$slug}] already exists.");

                continue;
            }

            $template = match ($slug) {
                'profile' => $this->profilePageTemplate(),
                'overview' => $this->overviewPageTemplate(),
                'reports' => $this->reportsPageTemplate(),
                'settings' => $this->settingsPageTemplate(),
                default => $this->presetPageTemplate($title, $slug),
            };

            $this->backupFileBeforeWrite($pagePath);
            $this->files->put($pagePath, $template);
            $this->manifest->addFile($pagePath);
            $this->components->info("Created default page: {$slug}");
        }

        $this->ensureDefaultPageRoutes(array_keys($pages));
    }

    /**
     * @param  array<int, string>  $slugs
     */
    private function ensureDefaultPageRoutes(array $slugs): void
    {
        $routesFile = base_path('routes/web.php');

        if (! $this->files->exists($routesFile)) {
            return;
        }

        $content = $this->files->get($routesFile);

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

            $this->backupFileBeforeWrite($routesFile);
            $this->files->append($routesFile, $snippet);
            $this->manifest->addRoute($routeName, md5($snippet));
        }

        $this->components->info('Ensured default page routes exist in routes/web.php');
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

    private function applyPresetSidebarConfig(string $preset): void
    {
        $configFile = config_path('dashkit.php');

        if (! $this->files->exists($configFile)) {
            $this->components->warn('config/dashkit.php not found. Skipping preset sidebar update.');

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

        $content = $this->files->get($configFile);
        $replacement = "'sidebar' => [".PHP_EOL.implode(PHP_EOL, $sidebarItems).PHP_EOL.'    ],'.PHP_EOL.PHP_EOL."    'topbar' => [";
        $updated = (string) preg_replace("/'sidebar'\s*=>\s*\[[\s\S]*?\],\r?\n\r?\n\s*'topbar'\s*=>\s*\[/", $replacement, $content, 1);

        if ($updated !== $content) {
            $this->backupFileBeforeWrite($configFile);
            $this->files->put($configFile, $updated);
            $this->callSilent('config:clear');
            $this->components->info("Applied {$preset} sidebar preset.");
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

    private function ensureProfilePage(): void
    {
        $pagesPath = $this->resolveAppPagesPath();
        $profilePath = $pagesPath.DIRECTORY_SEPARATOR.'profile.blade.php';

        $this->files->ensureDirectoryExists($pagesPath);

        if ($this->files->exists($profilePath)) {
            $this->components->info('Profile page already exists.');

            return;
        }

        $template = <<<'BLADE'
<x-dashkit-layout title="Profile">
    <section class="dk-panel">
        <h2>Profile</h2>
        <p>You can update your name, email, and password here later.</p>
    </section>
</x-dashkit-layout>
BLADE;

        $this->backupFileBeforeWrite($profilePath);
        $this->files->put($profilePath, $template.PHP_EOL);
        $this->manifest->addFile($profilePath);
        $this->components->info('Created default profile page.');
    }

    private function resolveAppPagesPath(): string
    {
        $configured = (string) config('dashkit.generated_pages_path', resource_path('views/dashkit/pages'));

        // Keep generation in app-level resources (outside package/vendor) by default.
        if (str_contains(str_replace('\\', '/', $configured), '/views/vendor/dashkit/pages')) {
            return resource_path('views/dashkit/pages');
        }

        return $configured;
    }

    /**
     * @param  array<string, string>  $setup
     */
    private function runMigrationAndSeed(array $setup): bool
    {
        $seederPath = database_path('seeders/DashkitAdminSeeder.php');
        $seederContent = $this->buildAdminSeeder(
            $setup['DASHKIT_DEFAULT_ADMIN_NAME'],
            $setup['DASHKIT_DEFAULT_ADMIN_EMAIL'],
            $setup['DASHKIT_DEFAULT_ADMIN_PASSWORD']
        );

        $this->backupFileBeforeWrite($seederPath);
        $this->files->put($seederPath, $seederContent);
        $this->manifest->addFile($seederPath);
        $this->components->info('Generated DashkitAdminSeeder with provided admin details.');

        $databaseReady = $this->ensureDatabaseExists($setup);

        if (! $databaseReady || ! $this->canConnectToConfiguredDatabase($setup)) {
            $this->components->error('Database connection failed. Installation stopped before migrations.');
            $this->components->warn('Start your database service (e.g., MySQL/XAMPP), verify host/port/user/password, then run dashkit:install again.');

            return false;
        }

        if (! $this->runMigrationsAndSeedSafely($setup)) {
            return false;
        }

        $this->persistDashkitCoreSettings($setup);

        $this->line('Default admin email: '.$setup['DASHKIT_DEFAULT_ADMIN_EMAIL']);
        $this->line('Default admin password: '.$setup['DASHKIT_DEFAULT_ADMIN_PASSWORD']);

        return true;
    }

    private function ensureMigrationRepository(): bool
    {
        try {
            $code = $this->call('migrate:install');

            return $code === self::SUCCESS;
        } catch (Throwable $e) {
            $this->components->warn('migrate:install failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * @param  array<string, string>  $setup
     */
    private function recoverMigrationRepository(array $setup): bool
    {
        try {
            if (! $this->repairMigrationsTableWithPdo($setup)) {
                return false;
            }

            DB::purge($setup['DB_CONNECTION']);

            return $this->ensureMigrationRepository();
        } catch (Throwable $e) {
            $this->components->warn('Could not recover migrations table automatically: '.$e->getMessage());

            return false;
        }
    }

    /**
     * @param  array<string, string>  $setup
     */
    private function runMigrationsAndSeedSafely(array $setup): bool
    {
        $this->ensureMigrationRepository();

        try {
            $migrateCode = $this->call('migrate', ['--force' => true]);

            if ($migrateCode !== self::SUCCESS) {
                throw new \RuntimeException('Migration command returned non-zero exit code.');
            }
        } catch (Throwable $e) {
            $this->components->warn('Migration failed: '.$e->getMessage());
            $this->components->warn('Attempting migrations table recovery...');

            if (! $this->recoverMigrationRepository($setup)) {
                $this->components->error('Migration repository recovery failed.');

                return false;
            }

            try {
                $retryCode = $this->call('migrate', ['--force' => true]);
                if ($retryCode !== self::SUCCESS) {
                    $this->components->error('Migration still failed after recovery attempt.');

                    return false;
                }
            } catch (Throwable $retryException) {
                $this->components->error('Migration crashed again after recovery: '.$retryException->getMessage());

                return false;
            }
        }

        try {
            $seedCode = $this->call('db:seed', ['--class' => 'Database\\Seeders\\DashkitAdminSeeder', '--force' => true]);
            if ($seedCode !== self::SUCCESS) {
                $this->components->error('Seeding failed.');

                return false;
            }
        } catch (Throwable $e) {
            $this->components->error('Seeding crashed: '.$e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, string>  $setup
     */
    private function repairMigrationsTableWithPdo(array $setup): bool
    {
        $connection = $setup['DB_CONNECTION'];

        try {
            if ($connection === 'mysql') {
                $pdo = new PDO(
                    'mysql:host='.$setup['DB_HOST'].';port='.$setup['DB_PORT'].';dbname='.$setup['DB_DATABASE'].';charset=utf8mb4',
                    $setup['DB_USERNAME'],
                    $setup['DB_PASSWORD'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );

                $pdo->exec('DROP TABLE IF EXISTS `migrations`');
                $pdo->exec('CREATE TABLE `migrations` (`id` int unsigned NOT NULL AUTO_INCREMENT, `migration` varchar(255) NOT NULL, `batch` int NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

                return true;
            }

            if ($connection === 'pgsql') {
                $pdo = new PDO(
                    'pgsql:host='.$setup['DB_HOST'].';port='.$setup['DB_PORT'].';dbname='.$setup['DB_DATABASE'],
                    $setup['DB_USERNAME'],
                    $setup['DB_PASSWORD'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );

                $pdo->exec('DROP TABLE IF EXISTS migrations');
                $pdo->exec('CREATE TABLE migrations (id serial PRIMARY KEY, migration varchar(255) NOT NULL, batch integer NOT NULL)');

                return true;
            }
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $this->components->warn('Direct migrations table repair failed: '.$message);

            if (str_contains($message, '1813') || str_contains(strtolower($message), 'tablespace')) {
                $this->components->warn('MySQL reports an orphaned migrations tablespace.');
                $this->components->warn('Fix by removing the stale migrations tablespace file from the database data directory, then re-run: php artisan dashkit:install --resume');
            }

            return false;
        }

        return false;
    }

    private function buildAdminSeeder(string $name, string $email, string $password): string
    {
        $nameExport = var_export($name, true);
        $emailExport = var_export($email, true);
        $passwordHash = Hash::make($password);
        $passwordExport = var_export($passwordHash, true);

        return <<<PHP
<?php

namespace Database\\Seeders;

use App\\Models\\User;
use Illuminate\\Database\\Seeder;

class DashkitAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => {$emailExport}],
            [
                'name' => {$nameExport},
                'password' => {$passwordExport},
            ]
        );
    }
}
PHP;
    }

    private function publishTag(string $tag, bool $force): void
    {
        $this->call('vendor:publish', [
            '--tag' => $tag,
            '--force' => $force,
        ]);
    }

    private function appendRouteInclude(Filesystem $files): void
    {
        $routesFile = base_path('routes/web.php');
        $marker = "require base_path('vendor/dashkit/dashkit/routes/web.php');";

        if (! $files->exists($routesFile)) {
            $this->components->warn('routes/web.php not found, skipping route include append.');

            return;
        }

        $content = $files->get($routesFile);
        $content = $this->removeDefaultWelcomeRootRoute($content, $routesFile);

        if (str_contains($content, $marker)) {
            $this->components->warn('Route include already exists in routes/web.php');

            return;
        }

        $snippet = PHP_EOL."if (file_exists(base_path('vendor/dashkit/dashkit/routes/web.php'))) {".PHP_EOL
            ."    {$marker}".PHP_EOL
            .'}'.PHP_EOL;

        $this->backupFileBeforeWrite($routesFile);
        $files->append($routesFile, $snippet);

        $this->components->info('Added Dashkit route include to routes/web.php');
    }

    private function removeDefaultWelcomeRootRoute(string $content, string $routesFile): string
    {
        $updated = $content;

        $patterns = [
            '/\R?\s*Route::get\(\s*[\'\"]\/[\'\"]\s*,\s*function\s*\(\)\s*\{\s*return\s+view\(\s*[\'\"]welcome[\'\"]\s*\)\s*;\s*\}\s*\)\s*;\R?/m',
            '/\R?\s*Route::view\(\s*[\'\"]\/[\'\"]\s*,\s*[\'\"]welcome[\'\"]\s*\)\s*;\R?/m',
        ];

        foreach ($patterns as $pattern) {
            $updated = (string) preg_replace($pattern, PHP_EOL, $updated);
        }

        if ($updated !== $content) {
            $this->backupFileBeforeWrite($routesFile);
            $this->files->put($routesFile, $updated);
            $this->components->info('Removed default root welcome route from routes/web.php.');
        }

        return $updated;
    }

    /**
     * @param  array<string, string>  $setup
     */
    private function applyRuntimeDatabaseConfiguration(array $setup): void
    {
        $connection = $setup['DB_CONNECTION'];

        config(['app.name' => $setup['APP_NAME']]);
        config(['database.default' => $connection]);

        if ($connection === 'sqlite') {
            config([
                'database.connections.sqlite.database' => $this->normalizeSqliteDatabasePath($setup['DB_DATABASE']),
            ]);
            DB::purge('sqlite');

            return;
        }

        config([
            'database.connections.'.$connection.'.host' => $setup['DB_HOST'],
            'database.connections.'.$connection.'.port' => $setup['DB_PORT'],
            'database.connections.'.$connection.'.database' => $setup['DB_DATABASE'],
            'database.connections.'.$connection.'.username' => $setup['DB_USERNAME'],
            'database.connections.'.$connection.'.password' => $setup['DB_PASSWORD'],
        ]);

        DB::purge($connection);
    }

    /**
     * @param  array<string, string>  $setup
     */
    private function ensureDatabaseExists(array $setup): bool
    {
        $connection = $setup['DB_CONNECTION'];

        if ($connection === 'sqlite') {
            $databasePath = $this->normalizeSqliteDatabasePath($setup['DB_DATABASE']);

            if (! $this->files->exists($databasePath)) {
                $this->files->ensureDirectoryExists(dirname($databasePath));
                $this->files->put($databasePath, '');
                $this->components->info('Created SQLite database: '.$databasePath);
            }

            config(['database.connections.sqlite.database' => $databasePath]);
            DB::purge('sqlite');

            return true;
        }

        try {
            $host = $setup['DB_HOST'];
            $port = $setup['DB_PORT'];
            $dbName = $setup['DB_DATABASE'];
            $user = $setup['DB_USERNAME'];
            $pass = $setup['DB_PASSWORD'];

            if ($connection === 'mysql') {
                $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 5,
                ]);
                $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.str_replace('`', '``', $dbName).'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $this->components->info('Verified MySQL database exists: '.$dbName);
            }

            if ($connection === 'pgsql') {
                $pdo = new PDO("pgsql:host={$host};port={$port};dbname=postgres", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 5,
                ]);
                $safeDb = str_replace('"', '""', $dbName);
                $existsStmt = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = :name');
                $existsStmt->execute(['name' => $dbName]);

                if (! $existsStmt->fetchColumn()) {
                    $pdo->exec('CREATE DATABASE "'.$safeDb.'"');
                }

                $this->components->info('Verified PostgreSQL database exists: '.$dbName);
            }

            return true;
        } catch (Throwable $e) {
            $this->components->error('Could not create/verify database automatically: '.$e->getMessage());
            $this->components->warn('Please verify DB credentials and ensure the database service is running.');

            return false;
        }
    }

    /**
     * @param  array<string, string>  $setup
     */
    private function canConnectToConfiguredDatabase(array $setup): bool
    {
        try {
            DB::purge($setup['DB_CONNECTION']);
            $connection = DB::connection($setup['DB_CONNECTION']);
            $connection->getPdo();
            $connection->select('SELECT 1');

            DB::disconnect($setup['DB_CONNECTION']);

            return true;
        } catch (Throwable $e) {
            $this->components->error('Connection test failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * @param  array<string, string>  $setup
     */
    private function persistDashkitCoreSettings(array $setup): void
    {
        try {
            DashkitSetting::put('app_name', $setup['APP_NAME'], 'app', 'string');
            DashkitSetting::put('app_env', (string) env('APP_ENV', 'production'), 'app', 'string');
            DashkitSetting::put('app_url', (string) env('APP_URL', ''), 'app', 'string');
            DashkitSetting::put('app_timezone', (string) config('app.timezone', 'UTC'), 'app', 'string');
            DashkitSetting::put('app_locale', (string) config('app.locale', 'en'), 'app', 'string');

            DashkitSetting::put('db_connection', $setup['DB_CONNECTION'], 'database', 'string');
            DashkitSetting::put('db_host', (string) ($setup['DB_HOST'] ?? ''), 'database', 'string');
            DashkitSetting::put('db_port', (string) ($setup['DB_PORT'] ?? ''), 'database', 'string');
            DashkitSetting::put('db_database', $setup['DB_DATABASE'], 'database', 'string');
            DashkitSetting::put('db_username', (string) ($setup['DB_USERNAME'] ?? ''), 'database', 'string');
            // Intentionally do not store DB password in dashkit_settings.

            DashkitSetting::put('mail_mailer', $setup['MAIL_MAILER'], 'mail', 'string');
            DashkitSetting::put('mail_host', $setup['MAIL_HOST'], 'mail', 'string');
            DashkitSetting::put('mail_port', $setup['MAIL_PORT'], 'mail', 'int');
            DashkitSetting::put('mail_username', $setup['MAIL_USERNAME'], 'mail', 'string');
            DashkitSetting::put('mail_encryption', $setup['MAIL_ENCRYPTION'], 'mail', 'string');
            DashkitSetting::put('mail_from_address', $setup['MAIL_FROM_ADDRESS'], 'mail', 'string');
            DashkitSetting::put('mail_from_name', $setup['MAIL_FROM_NAME'], 'mail', 'string');

            if ((string) $setup['MAIL_PASSWORD'] !== '') {
                DashkitSetting::putSecret('mail_password', $setup['MAIL_PASSWORD'], 'mail');
            }

            DashkitSetting::put('auth_guard', (string) config('dashkit.auth.guard', 'web'), 'auth', 'string');
            DashkitSetting::put('auth_password_broker', (string) config('dashkit.auth.password_broker', 'users'), 'auth', 'string');
            DashkitSetting::put('route_prefix', (string) config('dashkit.route_prefix', 'dashboard'), 'routing', 'string');
            DashkitSetting::put('install_preset', (string) ($setup['DASHKIT_INSTALL_PRESET'] ?? 'default'), 'app', 'string');

            $this->components->info('Stored Dashkit core app/mail/database metadata in dashkit_settings.');
        } catch (Throwable $e) {
            $this->components->warn('Could not persist Dashkit core settings to DB: '.$e->getMessage());
        }
    }

    private function backupFileBeforeWrite(string $path): void
    {
        if (isset($this->installState['files'][$path])) {
            return;
        }

        $existed = $this->files->exists($path);
        $content = $existed ? base64_encode((string) $this->files->get($path)) : null;

        $this->installState['files'][$path] = [
            'existed' => $existed,
            'content' => $content,
        ];
    }

    private function persistInstallState(): void
    {
        $statePath = storage_path('app/dashkit/install-state.json');
        $this->files->ensureDirectoryExists(dirname($statePath));
        $this->files->put($statePath, json_encode($this->installState, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->components->info('Stored installer rollback state.');
    }

    private function injectComposerUpdateScript(): void
    {
        $composerJsonPath = base_path('composer.json');

        if (! $this->files->exists($composerJsonPath)) {
            return;
        }

        $decoded = json_decode((string) $this->files->get($composerJsonPath), true);

        if (! is_array($decoded)) {
            return;
        }

        if (isset($decoded['scripts']['dashkit-update'])) {
            return;
        }

        $decoded['scripts']['dashkit-update'] = [
            '@composer update dashkit/dashkit',
            '@php artisan dashkit:upgrade',
        ];

        $this->files->put(
            $composerJsonPath,
            json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );

        $this->components->info('Added "dashkit-update" script to composer.json.');
    }

    private function persistPackageState(): void
    {
        $composerPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'composer.json';
        $version = '0.0.0';

        if ($this->files->exists($composerPath)) {
            $decoded = json_decode((string) $this->files->get($composerPath), true);
            if (is_array($decoded) && isset($decoded['version']) && is_string($decoded['version']) && $decoded['version'] !== '') {
                $version = $decoded['version'];
            }
        }

        $statePath = storage_path('app/dashkit/package-state.json');
        $this->files->ensureDirectoryExists(dirname($statePath));
        $state = [
            'installed_version' => $version,
            'installed_at' => now()->toDateTimeString(),
            'package_fingerprint' => $this->currentPackageFingerprint(),
            'view_hashes' => $this->buildViewHashes(),
            'file_hashes' => $this->buildManagedFileHashes(),
        ];

        $this->files->put($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->components->info('Recorded Dashkit package version state.');
    }

    private function currentPackageFingerprint(): string
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
            if ($this->files->isFile($path)) {
                $relativePath = str_replace($packageRoot.DIRECTORY_SEPARATOR, '', $path);
                $entries[] = str_replace('\\', '/', $relativePath).':'.(md5_file($path) ?: '');
                continue;
            }

            if (! $this->files->isDirectory($path)) {
                continue;
            }

            /** @var \SplFileInfo[] $allFiles */
            $allFiles = $this->files->allFiles($path);

            foreach ($allFiles as $file) {
                $relativePath = str_replace($packageRoot.DIRECTORY_SEPARATOR, '', $file->getPathname());
                $entries[] = str_replace('\\', '/', $relativePath).':'.(md5_file($file->getPathname()) ?: '');
            }
        }

        sort($entries);

        return md5(implode('|', $entries));
    }

    /**
     * Build md5 hashes of every package source view file.
     * These represent the "original baseline" the developer received.
     *
     * @return array<string, string>
     */
    private function buildViewHashes(): array
    {
        $sourceDir = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views';
        $hashes = [];

        if (! $this->files->isDirectory($sourceDir)) {
            return $hashes;
        }

        /** @var \SplFileInfo[] $allFiles */
        $allFiles = $this->files->allFiles($sourceDir);

        foreach ($allFiles as $file) {
            $relativePath = str_replace($sourceDir.DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relativePath = str_replace('\\', '/', $relativePath);
            $hashes[$relativePath] = md5_file($file->getPathname()) ?: '';
        }

        return $hashes;
    }

    /**
     * Snapshot md5 hashes of all app-level files that Dashkit created or modified during install.
     * Stored under relative keys so any developer's machine maps correctly.
     *
     * Covered:
     *   config/dashkit.php           — published config (may be tweaked by developer)
     *   routes/web.php               — Dashkit appends a require include
     *   bootstrap/app.php            — Dashkit adds guest redirect middleware
     *   resources/views/dashkit/pages/**  — generated preset pages
     *
     * @return array<string, string>
     */
    private function buildManagedFileHashes(): array
    {
        $hashes = [];

        $singleFiles = [
            'config/dashkit.php'  => config_path('dashkit.php'),
            'routes/web.php'      => base_path('routes/web.php'),
            'bootstrap/app.php'   => base_path('bootstrap/app.php'),
        ];

        foreach ($singleFiles as $key => $absPath) {
            if ($this->files->exists($absPath)) {
                $hashes[$key] = md5_file($absPath) ?: '';
            }
        }

        $pagesDir = resource_path('views/dashkit/pages');
        if ($this->files->isDirectory($pagesDir)) {
            /** @var \SplFileInfo[] $pageFiles */
            $pageFiles = $this->files->allFiles($pagesDir);
            foreach ($pageFiles as $file) {
                $rel = 'resources/views/dashkit/pages/'.str_replace('\\', '/', $file->getRelativePathname());
                $hashes[$rel] = md5_file($file->getPathname()) ?: '';
            }
        }

        return $hashes;
    }

    /** @return array{completed: array<int, string>, setup?: array<string, string>} */
    private function loadInstallProgress(): array
    {
        $path = storage_path('app/dashkit/install-progress.json');

        if (! $this->files->exists($path)) {
            return ['completed' => []];
        }

        $decoded = json_decode((string) $this->files->get($path), true);

        if (! is_array($decoded)) {
            return ['completed' => []];
        }

        $completed = isset($decoded['completed']) && is_array($decoded['completed']) ? array_values(array_unique(array_map('strval', $decoded['completed']))) : [];
        $setup = isset($decoded['setup']) && is_array($decoded['setup']) ? $decoded['setup'] : null;

        $progress = ['completed' => $completed];
        if ($setup !== null) {
            $progress['setup'] = $setup;
        }

        return $progress;
    }

    private function markStepCompleted(string $step): void
    {
        $completed = $this->progress['completed'] ?? [];
        if (! in_array($step, $completed, true)) {
            $completed[] = $step;
        }

        $this->progress['completed'] = array_values(array_unique($completed));
        $this->persistInstallProgress();
    }

    private function stepCompleted(string $step): bool
    {
        return in_array($step, $this->progress['completed'] ?? [], true);
    }

    private function persistInstallProgress(): void
    {
        $path = storage_path('app/dashkit/install-progress.json');
        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, json_encode($this->progress, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function clearInstallProgress(): void
    {
        $path = storage_path('app/dashkit/install-progress.json');
        if ($this->files->exists($path)) {
            $this->files->delete($path);
        }
    }
}
