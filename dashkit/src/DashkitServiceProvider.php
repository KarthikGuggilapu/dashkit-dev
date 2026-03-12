<?php

namespace Dashkit;

use Dashkit\Commands\DashkitAuditCommand;
use Dashkit\Commands\DashkitDeleteModuleCommand;
use Dashkit\Commands\DashkitDeletePageCommand;
use Dashkit\Commands\DashkitInstallCommand;
use Dashkit\Commands\DashkitMakeModuleCommand;
use Dashkit\Commands\DashkitMakePageCommand;
use Dashkit\Commands\DashkitReleaseCheckCommand;
use Dashkit\Commands\DashkitRenameModuleCommand;
use Dashkit\Commands\DashkitRenamePageCommand;
use Dashkit\Commands\DashkitSwitchPresetCommand;
use Dashkit\Commands\DashkitUninstallCommand;
use Dashkit\Commands\DashkitUpgradeCommand;
use Dashkit\Commands\DashkitVersionCommand;
use Dashkit\Models\DashkitSetting;
use Dashkit\Services\WidgetRegistry;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class DashkitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->packagePath('config/dashkit.php'), 'dashkit');

        $this->app->singleton(WidgetRegistry::class, static function (): WidgetRegistry {
            return new WidgetRegistry;
        });
    }

    public function boot(): void
    {
        $this->loadPackageResources();
        $this->configurePasswordResetUrl();

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
        $this->loadMigrationsFrom($this->packagePath('database/migrations'));
        $this->applyStoredRuntimeSettings();
    }

    private function registerCommands(): void
    {
        $this->commands([
            DashkitAuditCommand::class,
            DashkitDeleteModuleCommand::class,
            DashkitDeletePageCommand::class,
            DashkitInstallCommand::class,
            DashkitMakeModuleCommand::class,
            DashkitMakePageCommand::class,
            DashkitRenameModuleCommand::class,
            DashkitRenamePageCommand::class,
            DashkitReleaseCheckCommand::class,
            DashkitSwitchPresetCommand::class,
            DashkitUninstallCommand::class,
            DashkitUpgradeCommand::class,
            DashkitVersionCommand::class,
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

    private function configurePasswordResetUrl(): void
    {
        ResetPassword::createUrlUsing(static function (object $notifiable, string $token): string {
            $email = method_exists($notifiable, 'getEmailForPasswordReset')
                ? (string) $notifiable->getEmailForPasswordReset()
                : '';

            $parameters = [
                'token' => $token,
                'email' => $email,
            ];

            if (app('router')->has('dashkit.password.reset')) {
                return route('dashkit.password.reset', $parameters);
            }

            if (app('router')->has('password.reset')) {
                return route('password.reset', $parameters);
            }

            $fallbackUrl = rtrim((string) config('app.url', ''), '/').'/reset-password/'.$token;

            return $email === ''
                ? $fallbackUrl
                : $fallbackUrl.'?email='.urlencode($email);
        });
    }

    private function packagePath(string $path): string
    {
        return __DIR__.'/../'.ltrim($path, '/');
    }

    private function applyStoredRuntimeSettings(): void
    {
        try {
            if (! Schema::hasTable('dashkit_settings')) {
                return;
            }

            $mailer = DashkitSetting::get('mail_mailer', (string) config('mail.default', 'smtp'));
            $topbarShowSearch = $this->toBool(DashkitSetting::get('topbar_show_search', config('dashkit.topbar.show_search', true) ? '1' : '0'));
            $topbarUserMenu = $this->toBool(DashkitSetting::get('topbar_user_menu', config('dashkit.topbar.user_menu', true) ? '1' : '0'));
            $sidebarJson = DashkitSetting::get('sidebar_items_json', '');
            $sidebarItems = json_decode($sidebarJson, true);

            config([
                'app.name' => DashkitSetting::get('app_name', (string) config('app.name', 'Dashkit')),
                'app.timezone' => DashkitSetting::get('app_timezone', (string) config('app.timezone', 'UTC')),
                'app.locale' => DashkitSetting::get('app_locale', (string) config('app.locale', 'en')),
                'mail.default' => $mailer,
                'mail.mailers.smtp.host' => DashkitSetting::get('mail_host', (string) config('mail.mailers.smtp.host')),
                'mail.mailers.smtp.port' => (int) DashkitSetting::get('mail_port', (string) config('mail.mailers.smtp.port')),
                'mail.mailers.smtp.username' => DashkitSetting::get('mail_username', (string) config('mail.mailers.smtp.username')),
                'mail.mailers.smtp.password' => DashkitSetting::get('mail_password', (string) config('mail.mailers.smtp.password')),
                'mail.mailers.smtp.encryption' => DashkitSetting::get('mail_encryption', (string) config('mail.mailers.smtp.encryption')),
                'mail.from.address' => DashkitSetting::get('mail_from_address', (string) config('mail.from.address')),
                'mail.from.name' => DashkitSetting::get('mail_from_name', (string) config('mail.from.name')),
                'dashkit.topbar.show_search' => $topbarShowSearch,
                'dashkit.topbar.user_menu' => $topbarUserMenu,
            ]);

            if (is_array($sidebarItems) && $sidebarItems !== []) {
                config(['dashkit.sidebar' => $sidebarItems]);
            }
        } catch (Throwable) {
            // Silently skip runtime overrides when DB is unavailable during bootstrap.
        }
    }

    private function toBool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}
