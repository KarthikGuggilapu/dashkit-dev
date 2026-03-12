<?php

namespace Dashkit\Http\Controllers;

use Dashkit\Models\DashkitAuditLog;
use Dashkit\Models\DashkitSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class DashkitSettingsController extends Controller
{
    public function show(): View
    {
        return view('dashkit::pages.settings', [
            'title' => 'Settings',
            'appSettings' => [
                'app_name' => DashkitSetting::get('app_name', (string) config('app.name', 'Dashkit')),
                'app_timezone' => DashkitSetting::get('app_timezone', (string) config('app.timezone', 'UTC')),
                'app_locale' => DashkitSetting::get('app_locale', (string) config('app.locale', 'en')),
            ],
            'mailSettings' => [
                'mailer' => DashkitSetting::get('mail_mailer', (string) config('mail.default', 'smtp')),
                'host' => DashkitSetting::get('mail_host', (string) config('mail.mailers.smtp.host', '127.0.0.1')),
                'port' => DashkitSetting::get('mail_port', (string) config('mail.mailers.smtp.port', '2525')),
                'username' => DashkitSetting::get('mail_username', (string) config('mail.mailers.smtp.username', '')),
                'encryption' => DashkitSetting::get('mail_encryption', (string) config('mail.mailers.smtp.encryption', 'tls')),
                'from_address' => DashkitSetting::get('mail_from_address', (string) config('mail.from.address', 'hello@example.com')),
                'from_name' => DashkitSetting::get('mail_from_name', (string) config('mail.from.name', config('app.name', 'Dashkit'))),
            ],
            'topbarSettings' => [
                'show_search' => $this->toBool(DashkitSetting::get('topbar_show_search', config('dashkit.topbar.show_search', true) ? '1' : '0')),
                'user_menu' => $this->toBool(DashkitSetting::get('topbar_user_menu', config('dashkit.topbar.user_menu', true) ? '1' : '0')),
            ],
            'sidebarItems' => $this->resolvedSidebarItems(),
        ]);
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:120'],
            'app_timezone' => ['required', 'string', 'max:120'],
            'app_locale' => ['required', 'string', 'max:12'],
        ]);

        DashkitSetting::put('app_name', (string) $validated['app_name'], 'app', 'string');
        DashkitSetting::put('app_timezone', (string) $validated['app_timezone'], 'app', 'string');
        DashkitSetting::put('app_locale', (string) $validated['app_locale'], 'app', 'string');

        DashkitAuditLog::record($request, 'settings.general.updated', 'dashkit_settings', 'general', [
            'keys' => ['app_name', 'app_timezone', 'app_locale'],
        ]);

        return redirect()->route('dashkit.settings')->with('status', 'settings-general-updated');
    }

    public function updateMail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mail_mailer' => ['required', 'string', 'max:64'],
            'mail_host' => ['required', 'string', 'max:255'],
            'mail_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'string', 'max:32'],
            'mail_from_address' => ['required', 'email', 'max:255'],
            'mail_from_name' => ['required', 'string', 'max:255'],
        ]);

        DashkitSetting::put('mail_mailer', (string) $validated['mail_mailer'], 'mail', 'string');
        DashkitSetting::put('mail_host', (string) $validated['mail_host'], 'mail', 'string');
        DashkitSetting::put('mail_port', (string) $validated['mail_port'], 'mail', 'int');
        DashkitSetting::put('mail_username', (string) ($validated['mail_username'] ?? ''), 'mail', 'string');
        DashkitSetting::put('mail_encryption', (string) ($validated['mail_encryption'] ?? ''), 'mail', 'string');
        DashkitSetting::put('mail_from_address', (string) $validated['mail_from_address'], 'mail', 'string');
        DashkitSetting::put('mail_from_name', (string) $validated['mail_from_name'], 'mail', 'string');

        if (array_key_exists('mail_password', $validated) && (string) $validated['mail_password'] !== '') {
            DashkitSetting::putSecret('mail_password', (string) $validated['mail_password'], 'mail');
        }

        DashkitAuditLog::record($request, 'settings.mail.updated', 'dashkit_settings', 'mail', [
            'mail_password_updated' => array_key_exists('mail_password', $validated) && (string) $validated['mail_password'] !== '',
        ]);

        return redirect()->route('dashkit.settings')->with('status', 'settings-mail-updated');
    }

    public function updateTopbar(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'show_search' => ['nullable', 'string'],
            'user_menu' => ['nullable', 'string'],
        ]);

        $showSearch = array_key_exists('show_search', $validated);
        $userMenu = array_key_exists('user_menu', $validated);

        DashkitSetting::put('topbar_show_search', $showSearch ? '1' : '0', 'ui', 'bool');
        DashkitSetting::put('topbar_user_menu', $userMenu ? '1' : '0', 'ui', 'bool');

        DashkitAuditLog::record($request, 'settings.topbar.updated', 'dashkit_settings', 'topbar', [
            'show_search' => $showSearch,
            'user_menu' => $userMenu,
        ]);

        return redirect()->route('dashkit.settings')->with('status', 'settings-topbar-updated');
    }

    public function addSidebarItem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:80'],
            'route' => ['required', 'string', 'max:120'],
        ]);

        $items = $this->resolvedSidebarItems();
        $route = (string) $validated['route'];

        foreach ($items as $item) {
            if ((string) ($item['route'] ?? '') === $route) {
                return redirect()->route('dashkit.settings')->with('status', 'settings-sidebar-exists');
            }
        }

        $items[] = [
            'title' => (string) $validated['title'],
            'route' => $route,
        ];

        DashkitSetting::put('sidebar_items_json', json_encode(array_values($items), JSON_UNESCAPED_SLASHES) ?: '[]', 'ui', 'json');

        DashkitAuditLog::record($request, 'settings.sidebar.item_added', 'dashkit_settings', 'sidebar', [
            'title' => (string) $validated['title'],
            'route' => $route,
        ]);

        return redirect()->route('dashkit.settings')->with('status', 'settings-sidebar-updated');
    }

    public function removeSidebarItem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'route' => ['required', 'string', 'max:120'],
        ]);

        $targetRoute = (string) $validated['route'];
        $items = array_values(array_filter($this->resolvedSidebarItems(), static function (array $item) use ($targetRoute): bool {
            return (string) ($item['route'] ?? '') !== $targetRoute;
        }));

        DashkitSetting::put('sidebar_items_json', json_encode($items, JSON_UNESCAPED_SLASHES) ?: '[]', 'ui', 'json');

        DashkitAuditLog::record($request, 'settings.sidebar.item_removed', 'dashkit_settings', 'sidebar', [
            'route' => $targetRoute,
        ]);

        return redirect()->route('dashkit.settings')->with('status', 'settings-sidebar-updated');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolvedSidebarItems(): array
    {
        $stored = DashkitSetting::get('sidebar_items_json', '');

        if ($stored !== '') {
            $decoded = json_decode($stored, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, static function (mixed $item): bool {
                    if (! is_array($item)) {
                        return false;
                    }

                    $title = (string) ($item['title'] ?? '');
                    $route = (string) ($item['route'] ?? '');

                    return $title !== '' && $route !== '';
                }));
            }
        }

        return array_values(array_filter((array) config('dashkit.sidebar', []), static function (mixed $item): bool {
            return is_array($item) && (string) ($item['title'] ?? '') !== '' && (string) ($item['route'] ?? '') !== '';
        }));
    }

    private function toBool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}
