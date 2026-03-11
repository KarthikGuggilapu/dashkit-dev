<?php

namespace Dashkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class DashkitUpgradeCommand extends Command
{
    protected $signature = 'dashkit:upgrade {--force : Overwrite published files during upgrade} {--dry-run : Preview upgrade actions without applying} {--type= : Dashboard preset [default|ecommerce|crm]}';

    protected $description = 'Upgrade an existing Dashkit installation to the latest package version.';

    public function handle(Filesystem $files): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $preset = $this->resolveUpgradePreset();

        $state = $this->readState($files);
        $installedVersion = (string) ($state['installed_version'] ?? '0.0.0');
        $currentVersion = $this->currentPackageVersion($files);

        $this->components->info('Dashkit upgrade check');
        $this->line('Installed version: '.$installedVersion);
        $this->line('Current package version: '.$currentVersion);
        $this->line('Preset: '.$preset);

        if (version_compare($currentVersion, $installedVersion, '<=') && ! $force) {
            $this->components->info('Dashkit is already up to date.');

            return self::SUCCESS;
        }

        if (version_compare($currentVersion, $installedVersion, '<=') && $force) {
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

        $this->call('vendor:publish', ['--tag' => 'dashkit-config', '--force' => $force]);
        $this->call('vendor:publish', ['--tag' => 'dashkit-views', '--force' => $force]);
        $this->call('vendor:publish', ['--tag' => 'dashkit-assets', '--force' => $force]);

        $this->ensureRouteInclude($files);
        $this->applyPresetSidebarConfig($files, $preset);
        $this->ensureDefaultPages($files, $preset);
        $this->upgradeLegacyProfilePage($files);
        $this->ensureDefaultPageRoutes($files, array_keys($this->presetPages($preset)));

        $this->call('migrate', ['--force' => true]);
        $this->callSilent('config:clear');
        $this->callSilent('view:clear');

        $state['installed_version'] = $currentVersion;
        $state['install_preset'] = $preset;
        $state['upgraded_at'] = now()->toDateTimeString();
        $this->writeState($files, $state);

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
        $user = auth()->user();
        $name = (string) ($user?->name ?? 'Dashkit User');
        $email = (string) ($user?->email ?? 'not-available@example.com');
        $initials = collect(explode(' ', trim($name)))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
        $initials = $initials !== '' ? $initials : 'DU';
    @endphp

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
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
            if ($slug === 'profile') {
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
            'settings' => 'Settings',
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
                'settings' => 'Settings',
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
                'settings' => 'Settings',
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
                "        ['title' => 'Settings', 'route' => 'dashkit.page.settings'],",
            ],
            'crm' => [
                "        ['title' => 'Overview', 'route' => 'dashkit.home'],",
                "        ['title' => 'Leads', 'route' => 'dashkit.page.leads'],",
                "        ['title' => 'Contacts', 'route' => 'dashkit.page.contacts'],",
                "        ['title' => 'Deals', 'route' => 'dashkit.page.deals'],",
                "        ['title' => 'Activities', 'route' => 'dashkit.page.activities'],",
                "        ['title' => 'Reports', 'route' => 'dashkit.page.reports'],",
                "        ['title' => 'Settings', 'route' => 'dashkit.page.settings'],",
            ],
            default => [
                "        ['title' => 'Overview', 'route' => 'dashkit.home'],",
                "        ['title' => 'Reports', 'route' => 'dashkit.page.reports'],",
                "        ['title' => 'Settings', 'route' => 'dashkit.page.settings'],",
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
