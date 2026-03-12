<?php

namespace Dashkit\Commands;

use Dashkit\Models\DashkitAuditLog;
use Dashkit\Support\ArtifactManifest;
use Dashkit\Support\CompatibilityGuard;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class DashkitRenameModuleCommand extends Command
{
    protected $signature = 'dashkit:rename-module {from : Existing module name/slug} {to : New module name/slug} {title? : Optional sidebar title} {--force : Overwrite destination files if they exist}';

    protected $description = 'Rename a Dashkit-generated module (model/controller/view/route/sidebar) and keep tracking in sync.';

    public function handle(Filesystem $files): int
    {
        if (! CompatibilityGuard::ensure($this)) {
            return self::FAILURE;
        }

        $manifest = new ArtifactManifest($files);

        $fromRaw = (string) $this->argument('from');
        $toRaw = (string) $this->argument('to');
        $fromSlug = Str::slug($fromRaw);
        $toSlug = Str::slug($toRaw);
        $fromClass = Str::studly($fromRaw);
        $toClass = Str::studly($toRaw);
        $force = (bool) $this->option('force');

        if ($fromSlug === '' || $toSlug === '' || $fromClass === '' || $toClass === '') {
            $this->components->error('Please provide valid source and destination module names.');

            return self::FAILURE;
        }

        if ($fromSlug === $toSlug) {
            $this->components->warn('Source and destination module names are the same.');

            return self::SUCCESS;
        }

        $oldModel = app_path("Models/{$fromClass}.php");
        $newModel = app_path("Models/{$toClass}.php");
        $oldController = app_path("Http/Controllers/Dashkit/{$fromClass}Controller.php");
        $newController = app_path("Http/Controllers/Dashkit/{$toClass}Controller.php");
        $oldViewDir = resource_path("views/dashkit/modules/{$fromSlug}");
        $newViewDir = resource_path("views/dashkit/modules/{$toSlug}");

        if (! $files->exists($oldModel) && ! $files->exists($oldController) && ! $files->isDirectory($oldViewDir)) {
            $this->components->error('No module artifacts found for source module.');

            return self::FAILURE;
        }

        if (! $force && ($files->exists($newModel) || $files->exists($newController) || $files->isDirectory($newViewDir))) {
            $this->components->error('Destination module artifacts already exist. Use --force to overwrite.');

            return self::FAILURE;
        }

        $this->renameFile($files, $oldModel, $newModel, $manifest, $force);
        $this->renameFile($files, $oldController, $newController, $manifest, $force);
        $this->renameDirectory($files, $oldViewDir, $newViewDir, $manifest, $force);

        $this->rewriteModelClass($files, $newModel, $toClass);
        $this->rewriteControllerClass($files, $newController, $fromClass, $toClass, $fromSlug, $toSlug);

        $fromRoute = 'dashkit.module.'.$fromSlug;
        $toRoute = 'dashkit.module.'.$toSlug;
        $this->updateRoutesFile($files, $fromSlug, $toSlug, $fromClass, $toClass, $fromRoute, $toRoute);
        $this->updateSidebar($files, $fromRoute, $toRoute, $this->argument('title'));

        $manifest->renameRoute($fromRoute, $toRoute);

        DashkitAuditLog::record(
            request(),
            'generator.module.renamed',
            'dashkit_module',
            $toSlug,
            [
                'from_slug' => $fromSlug,
                'to_slug' => $toSlug,
                'from_class' => $fromClass,
                'to_class' => $toClass,
                'from_route' => $fromRoute,
                'to_route' => $toRoute,
                'force' => $force,
            ]
        );

        $this->components->warn('Migration files are not renamed automatically to avoid schema/history conflicts.');
        $this->components->info("Renamed module [{$fromSlug}] to [{$toSlug}].");

        return self::SUCCESS;
    }

    private function renameFile(Filesystem $files, string $old, string $new, ArtifactManifest $manifest, bool $force): void
    {
        if (! $files->exists($old)) {
            return;
        }

        $files->ensureDirectoryExists(dirname($new));

        if ($files->exists($new) && $force) {
            $files->delete($new);
        }

        if (! $files->exists($new)) {
            $files->move($old, $new);
            $manifest->renameFile($old, $new);
            $manifest->recordHash($new);
        }
    }

    private function renameDirectory(Filesystem $files, string $old, string $new, ArtifactManifest $manifest, bool $force): void
    {
        if (! $files->isDirectory($old)) {
            return;
        }

        if ($files->isDirectory($new) && $force) {
            $files->deleteDirectory($new);
        }

        if ($files->isDirectory($new)) {
            return;
        }

        $files->ensureDirectoryExists(dirname($new));
        $files->moveDirectory($old, $new);

        /** @var \SplFileInfo[] $newFiles */
        $newFiles = $files->allFiles($new);
        foreach ($newFiles as $file) {
            $manifest->addFile($file->getPathname());
            $manifest->recordHash($file->getPathname());
        }
    }

    private function rewriteModelClass(Filesystem $files, string $path, string $newClass): void
    {
        if (! $files->exists($path)) {
            return;
        }

        $content = (string) $files->get($path);
        $updated = (string) preg_replace('/class\s+\w+\s+extends\s+Model/', 'class '.$newClass.' extends Model', $content, 1);

        if ($updated !== $content) {
            $files->put($path, $updated);
        }
    }

    private function rewriteControllerClass(Filesystem $files, string $path, string $fromClass, string $toClass, string $fromSlug, string $toSlug): void
    {
        if (! $files->exists($path)) {
            return;
        }

        $content = (string) $files->get($path);
        $updated = str_replace(
            [
                'class '.$fromClass.'Controller',
                "dashkit.modules.{$fromSlug}.index",
            ],
            [
                'class '.$toClass.'Controller',
                "dashkit.modules.{$toSlug}.index",
            ],
            $content
        );

        if ($updated !== $content) {
            $files->put($path, $updated);
        }
    }

    private function updateRoutesFile(Filesystem $files, string $fromSlug, string $toSlug, string $fromClass, string $toClass, string $fromRoute, string $toRoute): void
    {
        $routesFile = base_path('routes/web.php');

        if (! $files->exists($routesFile)) {
            return;
        }

        $content = (string) $files->get($routesFile);
        $updated = str_replace(
            [
                "->name('{$fromRoute}')",
                "{$fromClass}Controller::class",
                "/{$fromSlug}'",
                "/{$fromSlug}\"",
            ],
            [
                "->name('{$toRoute}')",
                "{$toClass}Controller::class",
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
