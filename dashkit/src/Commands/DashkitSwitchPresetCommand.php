<?php

namespace Dashkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class DashkitSwitchPresetCommand extends Command
{
    protected $signature = 'dashkit:switch-preset {type? : Preset [default|ecommerce|crm]}';

    protected $description = 'Switch Dashkit preset without running a full upgrade.';

    public function handle(Filesystem $files): int
    {
        $preset = $this->resolvePreset((string) $this->argument('type'));

        $this->components->info('Switching Dashkit preset to: '.$preset);

        $this->ensureRouteInclude($files);
        $this->applyPresetSidebarConfig($files, $preset);

        $pages = $this->presetPages($preset);
        $this->ensureDefaultPages($files, $pages);
        $this->ensureDefaultPageRoutes($files, array_keys($pages));

        $state = $this->readState($files);
        $state['install_preset'] = $preset;
        $state['preset_switched_at'] = now()->toDateTimeString();
        $this->writeState($files, $state);

        $this->callSilent('config:clear');
        $this->callSilent('view:clear');

        $this->components->info('Dashkit preset switched successfully.');

        return self::SUCCESS;
    }

    private function resolvePreset(string $provided): string
    {
        if (trim($provided) !== '') {
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

        $content = (string) $files->get($routesFile);

        if (str_contains($content, $marker)) {
            return;
        }

        $snippet = PHP_EOL."if (file_exists(base_path('vendor/dashkit/dashkit/routes/web.php'))) {".PHP_EOL
            ."    {$marker}".PHP_EOL
            .'}'.PHP_EOL;

        $files->append($routesFile, $snippet);
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

    /**
     * @param  array<string, string>  $pages
     */
    private function ensureDefaultPages(Filesystem $files, array $pages): void
    {
        $pagesPath = $this->resolveAppPagesPath();
        $files->ensureDirectoryExists($pagesPath);

        foreach ($pages as $slug => $title) {
            $path = $pagesPath.DIRECTORY_SEPARATOR.$slug.'.blade.php';

            if ($files->exists($path)) {
                continue;
            }

            if ($slug === 'profile') {
                $files->put($path, $this->profilePageTemplate());

                continue;
            }

            $files->put($path, $this->presetPageTemplate($title, $slug));
        }
    }

    /**
     * @param  array<int, string>  $slugs
     */
    private function ensureDefaultPageRoutes(Filesystem $files, array $slugs): void
    {
        $routesFile = base_path('routes/web.php');

        if (! $files->exists($routesFile)) {
            return;
        }

        $content = (string) $files->get($routesFile);
        $prefix = trim((string) config('dashkit.route_prefix', 'dashboard'), '/');

        foreach ($slugs as $slug) {
            if ($slug === 'profile') {
                continue;
            }

            $routeName = "dashkit.page.{$slug}";

            if (str_contains($content, "->name('{$routeName}')")) {
                continue;
            }

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

        $content = (string) $files->get($configFile);
        $replacement = "'sidebar' => [".PHP_EOL.implode(PHP_EOL, $sidebarItems).PHP_EOL.'    ],'.PHP_EOL.PHP_EOL."    'topbar' => [";
        $updated = (string) preg_replace("/'sidebar'\\s*=>\\s*\\[[\\s\\S]*?\\],\\r?\\n\\r?\\n\\s*'topbar'\\s*=>\\s*\\[/", $replacement, $content, 1);

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

    private function defaultPageTemplate(string $title): string
    {
        return "<x-dashkit-layout title=\"{$title}\">\n"
            ."    <section class=\"dk-panel\">\n"
            ."        <h2>{$title}</h2>\n"
            ."        <p>Preset page generated by dashkit:switch-preset.</p>\n"
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
    </section>
</x-dashkit-layout>
BLADE;
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
}
