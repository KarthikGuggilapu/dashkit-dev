<x-dashkit-layout title="Settings">
    @php
        $appSettings = $appSettings ?? [];
        $mailSettings = $mailSettings ?? [];
        $topbarSettings = $topbarSettings ?? [];
        $sidebarItems = $sidebarItems ?? [];

        $status = (string) session('status', '');
        $statusMessages = [
            'settings-general-updated' => ['tone' => 'success', 'text' => 'General settings saved.'],
            'settings-topbar-updated' => ['tone' => 'success', 'text' => 'Topbar settings saved.'],
            'settings-mail-updated' => ['tone' => 'success', 'text' => 'Mail settings saved.'],
            'settings-sidebar-updated' => ['tone' => 'success', 'text' => 'Sidebar updated.'],
            'settings-sidebar-exists' => ['tone' => 'warn', 'text' => 'Sidebar item route already exists.'],
        ];

        $flash = $statusMessages[$status] ?? null;

        $mailer = strtolower(trim((string) ($mailSettings['mailer'] ?? 'smtp')));
        $mailHost = trim((string) ($mailSettings['host'] ?? ''));
        $mailPort = (int) ($mailSettings['port'] ?? 0);
        $mailFromAddress = trim((string) ($mailSettings['from_address'] ?? ''));
        $mailLooksConfigured = $mailer !== ''
            && ! in_array($mailer, ['log', 'array'], true)
            && $mailHost !== ''
            && $mailPort > 0
            && filter_var($mailFromAddress, FILTER_VALIDATE_EMAIL);
    @endphp

    <style>
        .dk-settings-shell {
            display: grid;
            gap: 1.25rem;
        }

        .dk-settings-banner {
            position: relative;
            overflow: hidden;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            background: linear-gradient(135deg, #f8fafc 0%, #ecfeff 55%, #f0fdfa 100%);
            padding: 1.25rem;
        }

        .dk-settings-grid {
            display: grid;
            gap: .75rem;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            margin-top: 1rem;
        }

        .dk-kpi {
            border-radius: .85rem;
            border: 1px solid #dbeafe;
            background: #ffffff;
            padding: .75rem;
        }

        .dk-tabs-wrap {
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            padding: .75rem;
            display: flex;
            flex-wrap: wrap;
            gap: .55rem;
        }

        .dk-tab {
            border: 1px solid #e2e8f0;
            border-radius: .7rem;
            padding: .45rem .8rem;
            font-size: .85rem;
            color: #334155;
            background: #f8fafc;
            transition: all .2s ease;
        }

        .dk-tab:hover {
            border-color: #67e8f9;
            background: #ecfeff;
        }

        .dk-tab[data-active="1"] {
            color: #0f172a;
            border-color: #22d3ee;
            background: linear-gradient(135deg, #ccfbf1 0%, #cffafe 100%);
            box-shadow: 0 8px 20px rgba(8, 145, 178, .15);
        }

        .dk-tab-panel {
            display: none;
        }

        .dk-tab-panel[data-active="1"] {
            display: block;
        }
    </style>

    @if ($flash)
        <x-dashkit::ui.alert
            class="mb-4"
            :tone="$flash['tone'] === 'success' ? 'success' : 'warning'"
            :message="$flash['text']"
        />
    @endif

    @if ($errors->any())
        <x-dashkit::ui.alert class="mb-4" tone="error" :message="$errors->first()" />
    @endif

    @if ($flash)
        <x-dashkit::ui.toast
            :tone="$flash['tone'] === 'success' ? 'success' : 'warning'"
            :message="$flash['text']"
        />
    @endif

    <section class="dk-settings-shell">
        <article class="dk-settings-banner">
            <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-cyan-700">
                <x-dashkit::ui.icon name="sparkles" size="sm" class="text-cyan-600" />
                Settings Center
            </p>
            <h2 class="mt-1 flex items-center gap-2 text-2xl font-bold text-slate-900">
                <x-dashkit::ui.icon name="sliders" lib="fa" class="text-cyan-600" />
                Clean controls, faster updates
            </h2>
            <p class="mt-1 text-sm text-slate-600">Manage app identity, mail delivery, interface controls, and sidebar links from one place.</p>

            <div class="dk-settings-grid">
                <div class="dk-kpi">
                    <p class="flex items-center gap-1.5 text-xs uppercase tracking-wide text-slate-500">
                        <x-dashkit::ui.icon name="mail" size="sm" class="text-slate-400" />
                        Mail Status
                    </p>
                    <p class="mt-1 text-sm font-semibold {{ $mailLooksConfigured ? 'text-emerald-700' : 'text-rose-700' }}">{{ $mailLooksConfigured ? 'Configured' : 'Needs Setup' }}</p>
                </div>
                <div class="dk-kpi">
                    <p class="flex items-center gap-1.5 text-xs uppercase tracking-wide text-slate-500">
                        <x-dashkit::ui.icon name="sidebar" size="sm" class="text-slate-400" />
                        Sidebar Items
                    </p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ count($sidebarItems) }} active links</p>
                </div>
                <div class="dk-kpi">
                    <p class="flex items-center gap-1.5 text-xs uppercase tracking-wide text-slate-500">
                        <x-dashkit::ui.icon name="clock" lib="fa" class="text-slate-400" />
                        Locale / Timezone
                    </p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ (string) ($appSettings['app_locale'] ?? 'en') }} / {{ (string) ($appSettings['app_timezone'] ?? 'UTC') }}</p>
                </div>
                <div class="dk-kpi">
                    <p class="flex items-center gap-1.5 text-xs uppercase tracking-wide text-slate-500">
                        <x-dashkit::ui.icon name="rocket" lib="fa" class="text-slate-400" />
                        App Name
                    </p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ (string) ($appSettings['app_name'] ?? config('app.name', 'Dashkit')) }}</p>
                </div>
            </div>
        </article>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="dk-tabs-wrap" role="tablist" aria-label="Settings tabs">
                    <button type="button" class="dk-tab flex items-center gap-2" data-tab="general" data-active="1">
                        <x-dashkit::ui.icon name="cog" size="sm" />
                        General
                    </button>
                    <button type="button" class="dk-tab flex items-center gap-2" data-tab="mail">
                        <x-dashkit::ui.icon name="mail" size="sm" />
                        Mail
                    </button>
                    <button type="button" class="dk-tab flex items-center gap-2" data-tab="ui">
                        <x-dashkit::ui.icon name="wand-magic-sparkles" lib="fa" class="text-xs" />
                        UI
                    </button>
                    <button type="button" class="dk-tab flex items-center gap-2" data-tab="sidebar">
                        <x-dashkit::ui.icon name="sidebar" size="sm" />
                        Sidebar
                    </button>
                </div>

                <input id="dk-settings-search" type="text" placeholder="Search fields in current tab..." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm md:max-w-xs">
            </div>

            <div class="dk-tab-panel" data-panel="general" data-active="1">
                <article class="rounded-2xl border border-slate-200 bg-white p-6">
                    <h3 class="mb-4 text-lg font-bold text-slate-900">General Settings</h3>
                    <form method="POST" action="{{ route('dashkit.settings.general.update') }}" class="grid gap-4 md:grid-cols-2" data-settings-form>
                        @csrf

                        <div data-setting-item>
                            <label class="mb-2 block text-sm font-medium text-slate-700" for="app_name">App Name</label>
                            <input id="app_name" type="text" name="app_name" value="{{ old('app_name', (string) ($appSettings['app_name'] ?? config('app.name', 'Dashkit'))) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        </div>

                        <div data-setting-item>
                            <label class="mb-2 block text-sm font-medium text-slate-700" for="app_timezone">Timezone</label>
                            <input id="app_timezone" type="text" name="app_timezone" value="{{ old('app_timezone', (string) ($appSettings['app_timezone'] ?? config('app.timezone', 'UTC'))) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        </div>

                        <div data-setting-item>
                            <label class="mb-2 block text-sm font-medium text-slate-700" for="app_locale">Locale</label>
                            <input id="app_locale" type="text" name="app_locale" value="{{ old('app_locale', (string) ($appSettings['app_locale'] ?? config('app.locale', 'en'))) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        </div>

                        <div class="md:col-span-2">
                            <x-dashkit::ui.button type="submit" icon="floppy-disk" icon-lib="fa">Save General</x-dashkit::ui.button>
                        </div>
                    </form>
                </article>
            </div>

            <div class="dk-tab-panel" data-panel="mail">
                <article class="rounded-2xl border border-slate-200 bg-white p-6">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-lg font-bold text-slate-900">Mail Settings</h3>
                        <x-dashkit::ui.button type="button" id="dk-mail-defaults" variant="secondary" size="sm" icon="magic" icon-lib="fa">Use Recommended Defaults</x-dashkit::ui.button>
                    </div>

                    <form method="POST" action="{{ route('dashkit.settings.mail.save') }}" class="grid gap-4 md:grid-cols-2" data-settings-form>
                        @csrf

                        <div data-setting-item>
                            <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_mailer">Mailer</label>
                            <input id="mail_mailer" type="text" name="mail_mailer" value="{{ old('mail_mailer', (string) ($mailSettings['mailer'] ?? 'smtp')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        </div>

                        <div data-setting-item>
                            <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_host">Host</label>
                            <input id="mail_host" type="text" name="mail_host" value="{{ old('mail_host', (string) ($mailSettings['host'] ?? '127.0.0.1')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        </div>

                        <div data-setting-item>
                            <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_port">Port</label>
                            <input id="mail_port" type="number" name="mail_port" value="{{ old('mail_port', (string) ($mailSettings['port'] ?? '2525')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        </div>

                        <div data-setting-item>
                            <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_username">Username</label>
                            <input id="mail_username" type="text" name="mail_username" value="{{ old('mail_username', (string) ($mailSettings['username'] ?? '')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        </div>

                        <div data-setting-item>
                            <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_password">Password (leave blank to keep current)</label>
                            <input id="mail_password" type="password" name="mail_password" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        </div>

                        <div data-setting-item>
                            <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_encryption">Encryption</label>
                            <input id="mail_encryption" type="text" name="mail_encryption" value="{{ old('mail_encryption', (string) ($mailSettings['encryption'] ?? 'tls')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        </div>

                        <div data-setting-item>
                            <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_from_address">From Address</label>
                            <input id="mail_from_address" type="email" name="mail_from_address" value="{{ old('mail_from_address', (string) ($mailSettings['from_address'] ?? 'hello@example.com')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        </div>

                        <div data-setting-item>
                            <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_from_name">From Name</label>
                            <input id="mail_from_name" type="text" name="mail_from_name" value="{{ old('mail_from_name', (string) ($mailSettings['from_name'] ?? config('app.name', 'Dashkit'))) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        </div>

                        <div class="md:col-span-2">
                            <x-dashkit::ui.button type="submit" variant="success" icon="paper-plane" icon-lib="fa">Save Mail</x-dashkit::ui.button>
                        </div>
                    </form>
                </article>
            </div>

            <div class="dk-tab-panel" data-panel="ui">
                <article class="rounded-2xl border border-slate-200 bg-white p-6">
                    <h3 class="mb-4 text-lg font-bold text-slate-900">Topbar Controls</h3>
                    <form method="POST" action="{{ route('dashkit.settings.topbar.update') }}" class="space-y-4" data-settings-form>
                        @csrf

                        <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2" data-setting-item>
                            <input type="checkbox" name="show_search" value="1" {{ ! empty($topbarSettings['show_search']) ? 'checked' : '' }}>
                            <span class="text-sm text-slate-700">Enable navbar search</span>
                        </label>

                        <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2" data-setting-item>
                            <input type="checkbox" name="user_menu" value="1" {{ ! empty($topbarSettings['user_menu']) ? 'checked' : '' }}>
                            <span class="text-sm text-slate-700">Enable user menu</span>
                        </label>

                        <x-dashkit::ui.button type="submit" icon="wand-magic-sparkles" icon-lib="fa">Save Topbar</x-dashkit::ui.button>
                    </form>
                </article>
            </div>

            <div class="dk-tab-panel" data-panel="sidebar">
                <article class="rounded-2xl border border-slate-200 bg-white p-6">
                    <h3 class="mb-4 text-lg font-bold text-slate-900">Dynamic Sidebar</h3>

                    <form method="POST" action="{{ route('dashkit.settings.sidebar.add') }}" class="mb-5 grid gap-4 md:grid-cols-2" data-settings-form>
                        @csrf
                        <div data-setting-item>
                            <label class="mb-2 block text-sm font-medium text-slate-700" for="sidebar_title">Title</label>
                            <input id="sidebar_title" type="text" name="title" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                        </div>
                        <div data-setting-item>
                            <label class="mb-2 block text-sm font-medium text-slate-700" for="sidebar_route">Route Name</label>
                            <input id="sidebar_route" type="text" name="route" class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="dashkit.page.reports" required>
                        </div>
                        <div class="md:col-span-2">
                            <x-dashkit::ui.button type="submit" icon="plus" icon-lib="fa">Add Sidebar Item</x-dashkit::ui.button>
                        </div>
                    </form>

                    <div class="space-y-2">
                        @forelse ($sidebarItems as $item)
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2" data-setting-item>
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">{{ (string) ($item['title'] ?? 'Item') }}</p>
                                    <p class="text-xs text-slate-500">{{ (string) ($item['route'] ?? '-') }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-dashkit::ui.button type="button" data-copy-route="{{ (string) ($item['route'] ?? '') }}" variant="secondary" size="sm" icon="copy" icon-lib="fa">Copy Route</x-dashkit::ui.button>

                                    @if (\Illuminate\Support\Facades\Route::has((string) ($item['route'] ?? '')))
                                        @php
                                            $openRoute = (string) ($item['route'] ?? '');
                                            $openHref = route($openRoute);
                                        @endphp
                                        <a href="{{ $openHref }}" class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"><x-dashkit::ui.icon name="arrow-up-right-from-square" lib="fa" class="text-[10px]" />Open</a>
                                    @endif

                                    <form method="POST" action="{{ route('dashkit.settings.sidebar.remove') }}">
                                        @csrf
                                        <input type="hidden" name="route" value="{{ (string) ($item['route'] ?? '') }}">
                                        <x-dashkit::ui.button type="submit" variant="danger" size="sm" icon="trash" icon-lib="fa">Remove</x-dashkit::ui.button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No sidebar items configured.</p>
                        @endforelse
                    </div>
                </article>
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-tab]'));
            var panels = Array.prototype.slice.call(document.querySelectorAll('[data-panel]'));
            var searchInput = document.getElementById('dk-settings-search');
            var defaultTab = localStorage.getItem('dashkit.settings.activeTab') || 'general';

            function activateTab(name) {
                tabs.forEach(function (tab) {
                    var isActive = tab.getAttribute('data-tab') === name;
                    tab.setAttribute('data-active', isActive ? '1' : '0');
                });

                panels.forEach(function (panel) {
                    var isActive = panel.getAttribute('data-panel') === name;
                    panel.setAttribute('data-active', isActive ? '1' : '0');
                });

                localStorage.setItem('dashkit.settings.activeTab', name);
                filterSettings();
            }

            function filterSettings() {
                if (!searchInput) {
                    return;
                }

                var keyword = searchInput.value.trim().toLowerCase();
                var activePanel = document.querySelector('[data-panel][data-active="1"]');

                if (!activePanel) {
                    return;
                }

                Array.prototype.slice.call(activePanel.querySelectorAll('[data-setting-item]')).forEach(function (item) {
                    var text = (item.textContent || '').toLowerCase();
                    item.style.display = keyword === '' || text.indexOf(keyword) !== -1 ? '' : 'none';
                });
            }

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    activateTab(tab.getAttribute('data-tab') || 'general');
                });
            });

            if (searchInput) {
                searchInput.addEventListener('input', filterSettings);
            }

            var mailDefaultsButton = document.getElementById('dk-mail-defaults');
            if (mailDefaultsButton) {
                mailDefaultsButton.addEventListener('click', function () {
                    var defaults = {
                        mail_mailer: 'smtp',
                        mail_host: '127.0.0.1',
                        mail_port: '2525',
                        mail_encryption: 'tls'
                    };

                    Object.keys(defaults).forEach(function (id) {
                        var el = document.getElementById(id);
                        if (el) {
                            el.value = defaults[id];
                        }
                    });
                });
            }

            Array.prototype.slice.call(document.querySelectorAll('[data-copy-route]')).forEach(function (button) {
                button.addEventListener('click', function () {
                    var route = button.getAttribute('data-copy-route') || '';
                    if (route === '') {
                        return;
                    }

                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(route);
                    }

                    button.textContent = 'Copied';
                    setTimeout(function () {
                        button.textContent = 'Copy Route';
                    }, 1200);
                });
            });

            activateTab(defaultTab);
        });
    </script>
</x-dashkit-layout>
