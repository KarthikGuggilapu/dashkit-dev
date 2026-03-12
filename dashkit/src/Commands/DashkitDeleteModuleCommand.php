<?php

namespace Dashkit\Commands;

use Dashkit\Models\DashkitAuditLog;
use Dashkit\Support\ArtifactManifest;
use Dashkit\Support\CompatibilityGuard;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class DashkitDeleteModuleCommand extends Command
{
    protected $signature = 'dashkit:delete-module {module : Module name/slug} {--force : Delete without confirmation}';

    protected $description = 'Delete a Dashkit-generated module and clean up tracked artifacts.';

    public function handle(Filesystem $files): int
    {
        if (! CompatibilityGuard::ensure($this)) {
            return self::FAILURE;
        }

        $manifest = new ArtifactManifest($files);

        $raw = (string) $this->argument('module');
        $slug = Str::slug($raw);
        $class = Str::studly($raw);
        $force = (bool) $this->option('force');

        if ($slug === '' || $class === '') {
            $this->components->error('Please provide a valid module name.');

            return self::FAILURE;
        }

        $modelPath = app_path("Models/{$class}.php");
        $controllerPath = app_path("Http/Controllers/Dashkit/{$class}Controller.php");
        $viewDir = resource_path("views/dashkit/modules/{$slug}");

        $hasArtifacts = $files->exists($modelPath) || $files->exists($controllerPath) || $files->isDirectory($viewDir);

        if (! $hasArtifacts) {
            $this->components->warn('No module artifacts found. Nothing to delete.');

            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm("Delete module [{$slug}] and remove its generated files/routes/sidebar entry?", false)) {
            $this->components->info('Cancelled.');

            return self::SUCCESS;
        }

        $this->deleteFile($files, $modelPath, $manifest);
        $this->deleteFile($files, $controllerPath, $manifest);
        $this->deleteDirectory($files, $viewDir, $manifest);

        $routeName = 'dashkit.module.'.$slug;
        $this->removeRouteEntry($files, $routeName, $class, $slug);
        $this->removeSidebarEntry($files, $routeName);
        $manifest->removeRoute($routeName);

        DashkitAuditLog::record(
            request(),
            'generator.module.deleted',
            'dashkit_module',
            $slug,
            [
                'module_slug' => $slug,
                'module_class' => $class,
                'route' => $routeName,
                'force' => $force,
            ]
        );

        $this->components->warn('Migration files are not deleted automatically to protect database history.');
        $this->components->info("Deleted module [{$slug}] artifacts and cleaned references.");

        return self::SUCCESS;
    }

    private function deleteFile(Filesystem $files, string $path, ArtifactManifest $manifest): void
    {
        if (! $files->exists($path)) {
            return;
        }

        $files->delete($path);
        $manifest->removeFile($path);
    }

    private function deleteDirectory(Filesystem $files, string $dir, ArtifactManifest $manifest): void
    {
        if (! $files->isDirectory($dir)) {
            return;
        }

        /** @var \SplFileInfo[] $all */
        $all = $files->allFiles($dir);
        foreach ($all as $file) {
            $manifest->removeFile($file->getPathname());
        }

        $files->deleteDirectory($dir);
    }

    private function removeRouteEntry(Filesystem $files, string $routeName, string $class, string $slug): void
    {
        $routesFile = base_path('routes/web.php');

        if (! $files->exists($routesFile)) {
            return;
        }

        $content = (string) $files->get($routesFile);

        $patterns = [
            '/^.*'.preg_quote("->name('{$routeName}')", '/').'.*\R?/m',
            '/^.*'.preg_quote($class.'Controller::class', '/').'.*\R?/m',
            '/^.*'.preg_quote('/'.$slug, '/').'.*\R?/m',
        ];

        $updated = $content;
        foreach ($patterns as $pattern) {
            $updated = (string) preg_replace($pattern, '', $updated);
        }

        if ($updated !== $content) {
            $files->put($routesFile, $updated);
        }
    }

    private function removeSidebarEntry(Filesystem $files, string $routeName): void
    {
        $configFile = config_path('dashkit.php');

        if (! $files->exists($configFile)) {
            return;
        }

        $content = (string) $files->get($configFile);
        $pattern = '/\s*\[[^\]]*\'route\'\s*=>\s*\''.preg_quote($routeName, '/').'\'[^\]]*\],?\R?/m';
        $updated = (string) preg_replace($pattern, '', $content);

        if ($updated !== $content) {
            $files->put($configFile, $updated);
            $this->callSilent('config:clear');
        }
    }
}
