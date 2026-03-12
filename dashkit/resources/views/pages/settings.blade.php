<x-dashkit-layout title="Settings">
    @php
        $appSettings = $appSettings ?? [];
        $mailSettings = $mailSettings ?? [];
        $topbarSettings = $topbarSettings ?? [];
        $sidebarItems = $sidebarItems ?? [];
    @endphp

    @if (session('status') === 'settings-general-updated')
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">General settings saved.</div>
    @endif

    @if (session('status') === 'settings-topbar-updated')
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">Topbar settings saved.</div>
    @endif

    @if (session('status') === 'settings-mail-updated')
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">Mail settings saved.</div>
    @endif

    @if (session('status') === 'settings-sidebar-updated')
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">Sidebar updated.</div>
    @endif

    @if (session('status') === 'settings-sidebar-exists')
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Sidebar item route already exists.</div>
    @endif

    <section class="grid gap-6 lg:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-lg font-bold text-slate-900">General Settings</h3>
            <form method="POST" action="{{ route('dashkit.settings.general.update') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="app_name">App Name</label>
                    <input id="app_name" type="text" name="app_name" value="{{ old('app_name', (string) ($appSettings['app_name'] ?? config('app.name', 'Dashkit'))) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="app_timezone">Timezone</label>
                    <input id="app_timezone" type="text" name="app_timezone" value="{{ old('app_timezone', (string) ($appSettings['app_timezone'] ?? config('app.timezone', 'UTC'))) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="app_locale">Locale</label>
                    <input id="app_locale" type="text" name="app_locale" value="{{ old('app_locale', (string) ($appSettings['app_locale'] ?? config('app.locale', 'en'))) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                </div>

                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Save General</button>
            </form>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-lg font-bold text-slate-900">Topbar Controls</h3>
            <form method="POST" action="{{ route('dashkit.settings.topbar.update') }}" class="space-y-4">
                @csrf

                <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2">
                    <input type="checkbox" name="show_search" value="1" {{ ! empty($topbarSettings['show_search']) ? 'checked' : '' }}>
                    <span class="text-sm text-slate-700">Enable navbar search</span>
                </label>

                <label class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2">
                    <input type="checkbox" name="user_menu" value="1" {{ ! empty($topbarSettings['user_menu']) ? 'checked' : '' }}>
                    <span class="text-sm text-slate-700">Enable user menu</span>
                </label>

                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Save Topbar</button>
            </form>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
            <h3 class="mb-4 text-lg font-bold text-slate-900">Mail Settings</h3>
            <form method="POST" action="{{ route('dashkit.settings.mail.save') }}" class="grid gap-4 md:grid-cols-2">
                @csrf

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_mailer">Mailer</label>
                    <input id="mail_mailer" type="text" name="mail_mailer" value="{{ old('mail_mailer', (string) ($mailSettings['mailer'] ?? 'smtp')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_host">Host</label>
                    <input id="mail_host" type="text" name="mail_host" value="{{ old('mail_host', (string) ($mailSettings['host'] ?? '127.0.0.1')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_port">Port</label>
                    <input id="mail_port" type="number" name="mail_port" value="{{ old('mail_port', (string) ($mailSettings['port'] ?? '2525')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_username">Username</label>
                    <input id="mail_username" type="text" name="mail_username" value="{{ old('mail_username', (string) ($mailSettings['username'] ?? '')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_password">Password (leave blank to keep current)</label>
                    <input id="mail_password" type="password" name="mail_password" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_encryption">Encryption</label>
                    <input id="mail_encryption" type="text" name="mail_encryption" value="{{ old('mail_encryption', (string) ($mailSettings['encryption'] ?? 'tls')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_from_address">From Address</label>
                    <input id="mail_from_address" type="email" name="mail_from_address" value="{{ old('mail_from_address', (string) ($mailSettings['from_address'] ?? 'hello@example.com')) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="mail_from_name">From Name</label>
                    <input id="mail_from_name" type="text" name="mail_from_name" value="{{ old('mail_from_name', (string) ($mailSettings['from_name'] ?? config('app.name', 'Dashkit'))) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                </div>

                <div class="md:col-span-2">
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700">Save Mail</button>
                </div>
            </form>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
            <h3 class="mb-4 text-lg font-bold text-slate-900">Dynamic Sidebar</h3>

            <form method="POST" action="{{ route('dashkit.settings.sidebar.add') }}" class="mb-5 grid gap-4 md:grid-cols-2">
                @csrf
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="sidebar_title">Title</label>
                    <input id="sidebar_title" type="text" name="title" class="w-full rounded-lg border border-slate-300 px-3 py-2" required>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="sidebar_route">Route Name</label>
                    <input id="sidebar_route" type="text" name="route" class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="dashkit.page.reports" required>
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Add Sidebar Item</button>
                </div>
            </form>

            <div class="space-y-2">
                @forelse ($sidebarItems as $item)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">{{ (string) ($item['title'] ?? 'Item') }}</p>
                            <p class="text-xs text-slate-500">{{ (string) ($item['route'] ?? '-') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if (\Illuminate\Support\Facades\Route::has((string) ($item['route'] ?? '')))
                                @php
                                    $openRoute = (string) ($item['route'] ?? '');
                                    $openHref = route($openRoute);
                                @endphp
                                <a href="{{ $openHref }}" class="rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Open</a>
                            @endif

                            <form method="POST" action="{{ route('dashkit.settings.sidebar.remove') }}">
                                @csrf
                                <input type="hidden" name="route" value="{{ (string) ($item['route'] ?? '') }}">
                                <button type="submit" class="rounded-md border border-rose-200 px-3 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50">Remove</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No sidebar items configured.</p>
                @endforelse
            </div>
        </article>
    </section>
</x-dashkit-layout>
