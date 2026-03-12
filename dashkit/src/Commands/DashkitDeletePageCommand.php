<?php

namespace Dashkit\Commands;

use Dashkit\Models\DashkitAuditLog;
use Dashkit\Support\ArtifactManifest;
use Dashkit\Support\CompatibilityGuard;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class DashkitDeletePageCommand extends Command
{
    protected $signature = 'dashkit:delete-page {name : Page slug/name} {--force : Delete without confirmation}';

    protected $description = 'Delete a Dashkit-generated dashboard page and clean route/sidebar tracking.';

    public function handle(Filesystem $files): int
    {
        if (! CompatibilityGuard::ensure($this)) {
            return self::FAILURE;
        }

        $manifest = new ArtifactManifest($files);
        $slug = Str::slug((string) $this->argument('name'));
        $force = (bool) $this->option('force');

        if ($slug === '') {
            $this->components->error('Please provide a valid page slug/name.');

            return self::FAILURE;
        }

        $path = $this->resolveAppPagesPath().DIRECTORY_SEPARATOR.$slug.'.blade.php';

        if (! $files->exists($path)) {
            $this->components->warn('Page file not found: '.$path);
        }

        if (! $force && ! $this->confirm("Delete Dashkit page [{$slug}] and remove its route/sidebar entry?", false)) {
            $this->components->info('Delete cancelled.');

            return self::SUCCESS;
        }

        if ($files->exists($path)) {
            $files->delete($path);
            $manifest->removeFile($path);
        }

        $routeName = 'dashkit.page.'.$slug;
        $this->removeRouteByName($files, $routeName);
        $this->removeSidebarByRoute($files, $routeName);
        $manifest->removeRoute($routeName);

        DashkitAuditLog::record(
            request(),
            'generator.page.deleted',
            'dashkit_page',
            $slug,
            [
                'slug' => $slug,
                'path' => str_replace('\\', '/', $path),
                'forced' => $force,
            ]
        );

        $this->components->info("Deleted page [{$slug}] and cleaned related tracking.");

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

    private function removeRouteByName(Filesystem $files, string $routeName): void
    {
        $routesFile = base_path('routes/web.php');

        if (! $files->exists($routesFile)) {
            return;
        }

        $content = (string) $files->get($routesFile);
        $pattern = '/\n?(?:\/\/ Dashkit generated .*\R|\/\/ Dashkit default .*\R)?\\\\Illuminate\\\\Support\\\\Facades\\\\Route::get\([\s\S]*?->name\(\''.preg_quote($routeName, '/').'\'\);\R?/m';
        $updated = (string) preg_replace($pattern, PHP_EOL, $content);

        if ($updated !== $content) {
            $files->put($routesFile, trim($updated).PHP_EOL);
        }
    }

    private function removeSidebarByRoute(Filesystem $files, string $routeName): void
    {
        $configFile = config_path('dashkit.php');

        if (! $files->exists($configFile)) {
            return;
        }

        $content = (string) $files->get($configFile);
        $pattern = '/\R?\s*\[\s*\'title\'\s*=>\s*\'[^\']*\',\s*\'route\'\s*=>\s*\''.preg_quote($routeName, '/').'\'\s*\],?/m';
        $updated = (string) preg_replace($pattern, PHP_EOL, $content);

        if ($updated !== $content) {
            $files->put($configFile, $updated);
            $this->callSilent('config:clear');
        }
    }
}
