<?php

namespace Dashkit\Commands;

use Dashkit\Models\DashkitAuditLog;
use Dashkit\Support\ArtifactManifest;
use Dashkit\Support\CompatibilityGuard;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class DashkitMakeModuleCommand extends Command
{
    protected $signature = 'dashkit:make-module {name : Module name} {title? : Optional title} {--force : Overwrite files if they exist}';

    protected $description = 'Generate a global Dashkit module (model, controller, migration, view, route).';

    private ArtifactManifest $manifest;

    public function handle(Filesystem $files): int
    {
        if (! CompatibilityGuard::ensure($this)) {
            return self::FAILURE;
        }

        $this->manifest = new ArtifactManifest($files);

        $name = (string) $this->argument('name');
        $classBase = Str::studly($name);
        $slug = Str::slug($name);
        $force = (bool) $this->option('force');

        if ($classBase === '' || $slug === '') {
            $this->components->error('Please provide a valid module name.');
            return self::FAILURE;
        }

        $titleArg = $this->argument('title');
        $title = is_string($titleArg) && trim($titleArg) !== ''
            ? trim($titleArg)
            : Str::title(str_replace('-', ' ', $slug));

        $modelPath = app_path("Models/{$classBase}.php");
        $controllerPath = app_path("Http/Controllers/Dashkit/{$classBase}Controller.php");
        $viewPath = resource_path("views/dashkit/modules/{$slug}/index.blade.php");

        $table = Str::snake(Str::pluralStudly($classBase));
        $migrationPath = $this->resolveMigrationPath($files, $table);

        $this->writeFile($files, $modelPath, $this->modelTemplate($classBase), $force, 'Model');
        $this->writeFile($files, $controllerPath, $this->controllerTemplate($classBase, $slug), $force, 'Controller');
        $this->writeFile($files, $viewPath, $this->viewTemplate($title, $slug), $force, 'View');
        $this->writeFile($files, $migrationPath, $this->migrationTemplate($table), $force, 'Migration');

        $routeName = $this->appendModuleRoute($files, $slug, $classBase);
        $this->appendSidebarItem($files, $title, $routeName);

        $this->components->info('Dashkit module scaffolded successfully.');
        $this->line('Module URL: /' . trim((string) config('dashkit.route_prefix', 'dashboard'), '/') . '/' . $slug);
        $this->line('Route name: ' . $routeName);

        DashkitAuditLog::record(
            request(),
            'generator.module.created',
            'dashkit_module',
            $slug,
            [
                'slug' => $slug,
                'title' => $title,
                'model_path' => str_replace('\\', '/', $modelPath),
                'controller_path' => str_replace('\\', '/', $controllerPath),
                'view_path' => str_replace('\\', '/', $viewPath),
                'migration_path' => str_replace('\\', '/', $migrationPath),
                'route_name' => $routeName,
                'force' => $force,
            ]
        );

        return self::SUCCESS;
    }

    private function writeFile(Filesystem $files, string $path, string $content, bool $force, string $label): void
    {
        $files->ensureDirectoryExists(dirname($path));

        if ($files->exists($path) && ! $force) {
            $this->components->warn("{$label} exists, skipped: {$path}");
            return;
        }

        if ($files->exists($path) && $force) {
            $this->components->warn("{$label} overwritten: {$path}");
        }

        $files->put($path, $content);
        $this->manifest->addFile($path);
        $this->manifest->recordHash($path);
        $this->components->info("{$label} created: {$path}");
    }

    private function resolveMigrationPath(Filesystem $files, string $table): string
    {
        $base = date('Y_m_d_His') . "_create_{$table}_table.php";
        $path = database_path('migrations/' . $base);

        if (! $files->exists($path)) {
            return $path;
        }

        $suffix = 1;
        do {
            $alt = date('Y_m_d_His') . "_{$suffix}_create_{$table}_table.php";
            $path = database_path('migrations/' . $alt);
            $suffix++;
        } while ($files->exists($path));

        return $path;
    }

    private function appendModuleRoute(Filesystem $files, string $slug, string $classBase): string
    {
        $routeName = "dashkit.module.{$slug}";
        $routesFile = base_path('routes/web.php');

        if (! $files->exists($routesFile)) {
            $this->components->warn('routes/web.php not found. Skipping route append.');
            return $routeName;
        }

        $content = $files->get($routesFile);
        if (str_contains($content, "->name('{$routeName}')")) {
            $this->components->warn("Route already exists in routes/web.php: {$routeName}");
            return $routeName;
        }

        $prefix = trim((string) config('dashkit.route_prefix', 'dashboard'), '/');

        $snippet = PHP_EOL
            . "// Dashkit generated module route: {$slug}" . PHP_EOL
            . "\\Illuminate\\Support\\Facades\\Route::get('/{$prefix}/{$slug}', [\\App\\Http\\Controllers\\Dashkit\\{$classBase}Controller::class, 'index'])" . PHP_EOL
            . "    ->middleware(array_merge((array) config('dashkit.route_middleware', ['web']), (bool) config('dashkit.auth.enabled', true) ? ['auth:' . (string) config('dashkit.auth.guard', 'web')] : []))" . PHP_EOL
            . "    ->name('{$routeName}');" . PHP_EOL;

        $files->append($routesFile, $snippet);
        $this->manifest->addRoute($routeName, md5($snippet));
        $this->components->info("Route added to routes/web.php: {$routeName}");

        return $routeName;
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

    private function modelTemplate(string $classBase): string
    {
        return "<?php\n\nnamespace App\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Model;\n\nclass {$classBase} extends Model\n{\n    protected \$fillable = ['name'];\n}\n";
    }

    private function controllerTemplate(string $classBase, string $slug): string
    {
        return "<?php\n\nnamespace App\\Http\\Controllers\\Dashkit;\n\nuse App\\Http\\Controllers\\Controller;\n\nclass {$classBase}Controller extends Controller\n{\n    public function index()\n    {\n        return view('dashkit.modules.{$slug}.index');\n    }\n}\n";
    }

    private function viewTemplate(string $title, string $slug): string
    {
        return "<x-dashkit-layout title=\"{$title}\">\n"
            . "    <x-dashkit::ui.card title=\"{$title}\" description=\"Generated module page for {$slug}.\">\n"
            . "        <x-dashkit::ui.alert tone=\"info\" message=\"Reuse Dashkit UI components for actions, forms, and tables.\" />\n"
            . "\n"
            . "        <div class=\"mt-4 flex gap-2\">\n"
            . "            <x-dashkit::ui.button>New {$title}</x-dashkit::ui.button>\n"
            . "            <x-dashkit::ui.button variant=\"secondary\">Export</x-dashkit::ui.button>\n"
            . "        </div>\n"
            . "\n"
            . "        <div class=\"mt-4\">\n"
            . "            <x-dashkit::ui.table :headers=\"['Name', 'Created At']\">\n"
            . "                <tr>\n"
            . "                    <td class=\"px-4 py-3 text-slate-700\">Sample Row</td>\n"
            . "                    <td class=\"px-4 py-3 text-slate-500\">{{ now()->toDateTimeString() }}</td>\n"
            . "                </tr>\n"
            . "            </x-dashkit::ui.table>\n"
            . "        </div>\n"
            . "    </x-dashkit::ui.card>\n"
            . "</x-dashkit-layout>\n";
    }

    private function migrationTemplate(string $table): string
    {
        return "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n        Schema::create('{$table}', function (Blueprint \$table): void {\n            \$table->id();\n            \$table->string('name');\n            \$table->timestamps();\n        });\n    }\n\n    public function down(): void\n    {\n        Schema::dropIfExists('{$table}');\n    }\n};\n";
    }
}
