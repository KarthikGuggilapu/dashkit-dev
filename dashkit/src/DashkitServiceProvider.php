<?php

namespace Dashkit;

use Dashkit\Commands\DashkitInstallCommand;
use Dashkit\Commands\DashkitMakeModuleCommand;
use Dashkit\Commands\DashkitMakePageCommand;
use Dashkit\Commands\DashkitSwitchPresetCommand;
use Dashkit\Commands\DashkitUninstallCommand;
use Dashkit\Commands\DashkitUpgradeCommand;
use Dashkit\Services\WidgetRegistry;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class DashkitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->packagePath('config/dashkit.php'), 'dashkit');

        $this->app->singleton(WidgetRegistry::class, static function (): WidgetRegistry {
            return new WidgetRegistry();
        });
    }

    public function boot(): void
    {
        $this->loadPackageResources();

        Blade::component('dashkit::components.layout', 'dashkit-layout');

        if ($this->app->runningInConsole()) {
            $this->registerCommands();
            $this->registerPublishes();
        }

        $this->registerConfigWidgets();
    }

    private function loadPackageResources(): void
    {
        $this->loadRoutesFrom($this->packagePath('routes/web.php'));
        $this->loadViewsFrom($this->packagePath('resources/views'), 'dashkit');
    }

    private function registerCommands(): void
    {
        $this->commands([
            DashkitInstallCommand::class,
            DashkitMakeModuleCommand::class,
            DashkitMakePageCommand::class,
            DashkitSwitchPresetCommand::class,
            DashkitUninstallCommand::class,
            DashkitUpgradeCommand::class,
        ]);
    }

    private function registerPublishes(): void
    {
        $this->publishes([
            $this->packagePath('config/dashkit.php') => config_path('dashkit.php'),
        ], 'dashkit-config');

        $this->publishes([
            $this->packagePath('resources/views') => resource_path('views/vendor/dashkit'),
        ], 'dashkit-views');

        $this->publishes([
            $this->packagePath('resources/assets') => public_path('vendor/dashkit'),
        ], 'dashkit-assets');
    }

    private function registerConfigWidgets(): void
    {
        /** @var WidgetRegistry $registry */
        $registry = $this->app->make(WidgetRegistry::class);

        foreach ((array) config('dashkit.widgets.defaults', []) as $widget) {
            $registry->register($widget);
        }
    }

    private function packagePath(string $path): string
    {
        return __DIR__ . '/../' . ltrim($path, '/');
    }
}
