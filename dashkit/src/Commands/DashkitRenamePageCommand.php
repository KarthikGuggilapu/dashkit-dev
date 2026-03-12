<?php

namespace Dashkit\Commands;

use Dashkit\Models\DashkitAuditLog;
use Dashkit\Support\ArtifactManifest;
use Dashkit\Support\CompatibilityGuard;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class DashkitRenamePageCommand extends Command
{
    protected $signature = 'dashkit:rename-page {from : Existing page slug/name} {to : New page slug/name} {title? : Optional new sidebar title} {--force : Overwrite destination file if it exists}';

    protected $description = 'Rename a Dashkit-generated dashboard page and keep route/sidebar tracking in sync.';

    public function handle(Filesystem $files): int
    {
        if (! CompatibilityGuard::ensure($this)) {
            return self::FAILURE;
        }

        $manifest = new ArtifactManifest($files);
        $fromSlug = Str::slug((string) $this->argument('from'));
        $toSlug = Str::slug((string) $this->argument('to'));
        $force = (bool) $this->option('force');

        if ($fromSlug === '' || $toSlug === '') {
            $this->components->error('Both source and destination page names must be valid slugs.');

            return self::FAILURE;
        }

        if ($fromSlug === $toSlug) {
            $this->components->warn('Source and destination are the same. Nothing to rename.');

            return self::SUCCESS;
        }

        $pagesPath = $this->resolveAppPagesPath();
        $fromPath = $pagesPath.DIRECTORY_SEPARATOR.$fromSlug.'.blade.php';
        $toPath = $pagesPath.DIRECTORY_SEPARATOR.$toSlug.'.blade.php';

        if (! $files->exists($fromPath)) {
            $this->components->error('Source page file not found: '.$fromPath);

            return self::FAILURE;
        }

        if ($files->exists($toPath) && ! $force) {
            $this->components->error('Destination page already exists. Use --force to overwrite.');

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($toPath));

        if ($files->exists($toPath) && $force) {
            $files->delete($toPath);
        }

        $files->move($fromPath, $toPath);

        $fromRoute = 'dashkit.page.'.$fromSlug;
        $toRoute = 'dashkit.page.'.$toSlug;

        $this->updateRoutesFile($files, $fromSlug, $toSlug, $fromRoute, $toRoute);
        $this->updateSidebar($files, $fromRoute, $toRoute, $this->argument('title'));

        $manifest->renameFile($fromPath, $toPath);
        $manifest->recordHash($toPath);
        $manifest->renameRoute($fromRoute, $toRoute);

        DashkitAuditLog::record(
            request(),
            'generator.page.renamed',
            'dashkit_page',
            $toSlug,
            [
                'from_slug' => $fromSlug,
                'to_slug' => $toSlug,
                'from_path' => str_replace('\\', '/', $fromPath),
                'to_path' => str_replace('\\', '/', $toPath),
            ]
        );

        $this->components->info("Renamed page [{$fromSlug}] to [{$toSlug}].");

        return self::SUCCESS;
    }

    private function resolveAppPagesPath(): string
    {
        $configured = (string) config('dashkit.generated_pages_path', resource_path('views/dashkit/pages'));

        if (str_contains(str_replace('\\', '/', $configured), '/views/vendor/dashkit/pages')) {
            return resource_path('views/dashkit/pages');
        }

        return $configured;
    }

    private function updateRoutesFile(Filesystem $files, string $fromSlug, string $toSlug, string $fromRoute, string $toRoute): void
    {
        $routesFile = base_path('routes/web.php');

        if (! $files->exists($routesFile)) {
            return;
        }

        $content = (string) $files->get($routesFile);
        $updated = str_replace(
            [
                "->name('{$fromRoute}')",
                "->defaults('page', '{$fromSlug}')",
                "/{$fromSlug}'",
                "/{$fromSlug}\"",
            ],
            [
                "->name('{$toRoute}')",
                "->defaults('page', '{$toSlug}')",
                "/{$toSlug}'",
                "/{$toSlug}\"",
            ],
            $content
        );

        if ($updated !== $content) {
            $files->put($routesFile, $updated);
        }
    }

    private function updateSidebar(Filesystem $files, string $fromRoute, string $toRoute, mixed $title): void
    {
        $configFile = config_path('dashkit.php');

        if (! $files->exists($configFile)) {
            return;
        }

        $content = (string) $files->get($configFile);
        $updated = str_replace("'route' => '{$fromRoute}'", "'route' => '{$toRoute}'", $content);

        if (is_string($title) && trim($title) !== '') {
            $safeTitle = str_replace("'", "\\'", trim($title));
            $pattern = "/\['title'\s*=>\s*'[^']*',\s*'route'\s*=>\s*'".preg_quote($toRoute, '/')."'\]/";
            $updated = (string) preg_replace($pattern, "['title' => '{$safeTitle}', 'route' => '{$toRoute}']", $updated);
        }

        if ($updated !== $content) {
            $files->put($configFile, $updated);
            $this->callSilent('config:clear');
        }
    }
}
