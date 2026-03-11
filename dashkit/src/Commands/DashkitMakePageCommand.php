<?php

namespace Dashkit\Commands;

use Dashkit\Support\ArtifactManifest;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class DashkitMakePageCommand extends Command
{
    protected $signature = 'dashkit:make-page {name : Page name or slug} {title? : Optional page title} {--force : Overwrite existing page file}';

    protected $description = 'Generate a dashboard page for Dashkit.';

    private ArtifactManifest $manifest;

    public function handle(Filesystem $files): int
    {
        $this->manifest = new ArtifactManifest($files);

        $rawName = (string) $this->argument('name');
        $slug = Str::slug($rawName);
        $force = (bool) $this->option('force');

        if ($slug === '') {
            $this->components->error('Please provide a valid page name.');
            return self::FAILURE;
        }

        $targetDirectory = $this->resolveAppPagesPath();
        $targetPath = $targetDirectory . DIRECTORY_SEPARATOR . $slug . '.blade.php';
        $titleArgument = $this->argument('title');
        $title = is_string($titleArgument) && trim($titleArgument) !== ''
            ? trim($titleArgument)
            : Str::title(str_replace('-', ' ', $slug));

        if ($files->exists($targetPath) && ! $force) {
            $this->components->error("Page [{$slug}] already exists.");
            $this->line('Use --force to overwrite the existing file.');
            return self::FAILURE;
        }

        if ($files->exists($targetPath) && $force) {
            $this->components->warn("Overwriting existing page [{$slug}]...");
        }

        $files->ensureDirectoryExists($targetDirectory);
        $files->put($targetPath, $this->pageTemplate($title, $slug));
        $this->manifest->addFile($targetPath);
        $this->appendAppRoute($files, $slug);
        $this->appendSidebarItem($files, $title, 'dashkit.page.'.$slug);

        $this->components->info("Created page: {$targetPath}");
        $this->line('Route name: dashkit.page.' . $slug);

        return self::SUCCESS;
    }

    private function pageTemplate(string $title, string $slug): string
    {
        return "<x-dashkit-layout title=\"{$title}\">\n"
            . "    <section class=\"dk-panel\">\n"
            . "        <h2>{$title}</h2>\n"
            . "        <p>Generated with <code>php artisan dashkit:make-page {$slug}</code>.</p>\n"
            . "    </section>\n"
            . "</x-dashkit-layout>\n";
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

    private function appendAppRoute(Filesystem $files, string $slug): void
    {
        $routesFile = base_path('routes/web.php');

        if (! $files->exists($routesFile)) {
            $this->components->warn('routes/web.php not found. Skipping route append.');
            return;
        }

        $routeName = "dashkit.page.{$slug}";
        $content = $files->get($routesFile);

        if (str_contains($content, "->name('{$routeName}')")) {
            $this->components->warn("Route already exists in routes/web.php: {$routeName}");
            return;
        }

        $prefix = trim((string) config('dashkit.route_prefix', 'dashboard'), '/');

        $snippet = PHP_EOL
            . "// Dashkit generated page route: {$slug}" . PHP_EOL
            . "\\Illuminate\\Support\\Facades\\Route::get('/{$prefix}/{$slug}', [\\Dashkit\\Http\\Controllers\\DashboardController::class, 'page'])" . PHP_EOL
            . "    ->middleware(array_merge((array) config('dashkit.route_middleware', ['web']), (bool) config('dashkit.auth.enabled', true) ? ['auth:' . (string) config('dashkit.auth.guard', 'web')] : []))" . PHP_EOL
            . "    ->defaults('page', '{$slug}')" . PHP_EOL
            . "    ->name('{$routeName}');" . PHP_EOL;

        $files->append($routesFile, $snippet);
        $this->manifest->addRoute($routeName);
        $this->components->info("Added route to routes/web.php: {$routeName}");
    }

    private function appendSidebarItem(Filesystem $files, string $title, string $routeName): void
    {
        $configFile = config_path('dashkit.php');

        if (! $files->exists($configFile)) {
            $this->components->warn('config/dashkit.php not found. Skipping sidebar update.');
            return;
        }

        $content = $files->get($configFile);

        if (str_contains($content, "'route' => '{$routeName}'")) {
            $this->components->warn("Sidebar item already exists: {$routeName}");
            return;
        }

        $safeTitle = str_replace("'", "\\'", $title);
        $item = "        ['title' => '{$safeTitle}', 'route' => '{$routeName}'],";

        $pattern = "/('sidebar'\\s*=>\\s*\\[[\\s\\S]*?)(\\r?\\n\\s*\\],\\r?\\n\\r?\\n\\s*'topbar'\\s*=>)/";
        $updated = preg_replace($pattern, '$1'.PHP_EOL.$item.'$2', $content, 1, $count);

        if (! is_string($updated) || $count < 1) {
            $this->components->warn('Could not update sidebar config automatically.');
            return;
        }

        $files->put($configFile, $updated);
        $this->callSilent('config:clear');
        $this->components->info("Added sidebar item: {$safeTitle} ({$routeName})");
    }
}
