<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('dashkit.name', 'Dashkit') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWix+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkR4j8R2f0x1B3p6k9R/+qvOB0fOkHn84q0g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            font-family: Manrope, sans-serif;
        }

        :root {
            --dk-sidebar-width: 272px;
            --dk-sidebar-collapsed-width: 92px;
        }

        .dk-sidebar-link {
            position: relative;
        }

        .dk-sidebar-link:hover {
            transform: translateX(1px);
        }

        @media (min-width: 1024px) {
            #dk-main {
                margin-left: var(--dk-sidebar-width);
            }

            #dk-shell.dk-sidebar-collapsed #dk-main {
                margin-left: var(--dk-sidebar-collapsed-width);
            }

            #dk-shell.dk-sidebar-collapsed #dk-sidebar {
                width: var(--dk-sidebar-collapsed-width);
            }

            #dk-shell.dk-sidebar-collapsed [data-dk-sidebar-label],
            #dk-shell.dk-sidebar-collapsed [data-dk-sidebar-brand-text] {
                display: none;
            }

            #dk-shell.dk-sidebar-collapsed [data-dk-sidebar-brand] {
                justify-content: center;
            }

            #dk-shell.dk-sidebar-collapsed [data-dk-sidebar-link] {
                justify-content: center;
                padding-left: 0.6rem;
                padding-right: 0.6rem;
            }
        }
    </style>
</head>
<body class="h-screen overflow-hidden bg-slate-100 text-slate-900">
@php
    $user = auth()->user();
    $displayName = (string) ($user?->name ?? 'User');
    $initials = collect(explode(' ', trim($displayName)))
        ->filter()
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->take(2)
        ->implode('');
    $initials = $initials !== '' ? $initials : 'U';

    $profileHref = '#';
    if (\Illuminate\Support\Facades\Route::has('dashkit.page.profile')) {
        $profileHref = route('dashkit.page.profile');
    } elseif (\Illuminate\Support\Facades\Route::has('dashkit.page')) {
        $profileHref = route('dashkit.page', ['page' => 'profile']);
    }

    $currentRouteName = request()->route()?->getName();
@endphp
<div id="dk-shell" class="relative h-screen overflow-hidden">
    <div class="pointer-events-none absolute inset-0 z-30 bg-slate-900/40 opacity-0 transition lg:hidden" data-dk-sidebar-backdrop></div>

    <aside id="dk-sidebar" class="fixed inset-y-0 left-0 z-40 w-[272px] -translate-x-full overflow-y-auto border-r border-slate-800 bg-slate-950 px-4 py-5 text-slate-100 shadow-2xl transition duration-300 lg:translate-x-0">
        <div class="mb-6 flex items-center justify-between gap-3" data-dk-sidebar-brand>
            <div class="flex items-center gap-2" data-dk-sidebar-brand>
                <span class="grid h-9 w-9 place-content-center rounded-xl bg-gradient-to-br from-cyan-500 to-emerald-500 text-sm font-black text-white">DK</span>
                <span class="text-lg font-extrabold tracking-wide" data-dk-sidebar-brand-text>{{ config('dashkit.name', 'Dashkit') }}</span>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" class="hidden h-8 w-8 place-content-center rounded-lg border border-slate-700 bg-slate-900 text-slate-300 transition hover:border-cyan-500 hover:text-cyan-300 lg:grid" data-dk-sidebar-collapse title="Toggle sidebar width" aria-label="Toggle sidebar width">
                    <svg class="h-4 w-4 transition-transform duration-300" data-dk-sidebar-collapse-icon viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <path d="M12.5 4.5L7 10l5.5 5.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <button type="button" class="grid h-8 w-8 place-content-center rounded-lg border border-slate-700 bg-slate-900 text-slate-300 transition hover:border-cyan-500 hover:text-cyan-300 lg:hidden" data-dk-sidebar-close title="Close menu" aria-label="Close menu">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <path d="M5 5l10 10M15 5L5 15" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
        </div>

        <nav class="space-y-2.5">
            @foreach((array) config('dashkit.sidebar', []) as $item)
                @php
                    $routeName = $item['route'] ?? null;
                    $routeParams = (array) ($item['params'] ?? []);
                    $href = '#';

                    if ($routeName && \Illuminate\Support\Facades\Route::has($routeName)) {
                        try {
                            $href = route($routeName, $routeParams);
                        } catch (\Throwable) {
                            $href = '#';
                        }
                    }

                    $itemTitle = (string) ($item['title'] ?? 'Item');
                    $itemTitleLower = strtolower($itemTitle);
                    $isActive = $routeName && $currentRouteName ? request()->routeIs($routeName) : false;
                @endphp
                <a
                    class="dk-sidebar-link group flex items-center gap-3 rounded-xl border px-3 py-2.5 text-sm font-semibold transition {{ $isActive ? 'border-cyan-400/60 bg-gradient-to-r from-cyan-500/20 to-emerald-400/10 text-white shadow-md shadow-cyan-900/20' : 'border-transparent text-slate-300 hover:border-slate-700 hover:bg-slate-900 hover:text-white' }}"
                    href="{{ $href }}"
                    data-dk-sidebar-link
                    title="{{ $itemTitle }}"
                >
                    <span class="grid h-8 w-8 shrink-0 place-content-center rounded-lg border {{ $isActive ? 'border-cyan-400/50 bg-cyan-400/15 text-cyan-200' : 'border-slate-700 bg-slate-900 text-slate-400 group-hover:border-cyan-400/40 group-hover:text-cyan-300' }}">
                        @if(str_contains($itemTitleLower, 'report') || str_contains((string) $routeName, 'report'))
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path d="M4.5 15.5h11M6 12.5V8m4 4.5V5.5m4 7V9" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        @elseif(str_contains($itemTitleLower, 'setting') || str_contains((string) $routeName, 'setting'))
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path d="M10 12.75a2.75 2.75 0 100-5.5 2.75 2.75 0 000 5.5z"/>
                                <path d="M16 10a6.8 6.8 0 01-.05.83l1.6 1.25-1.5 2.58-1.93-.6a6.97 6.97 0 01-1.43.84l-.33 2.01h-3l-.33-2.01a6.97 6.97 0 01-1.43-.84l-1.93.6-1.5-2.58 1.6-1.25A6.8 6.8 0 014 10c0-.28.02-.56.05-.83L2.45 7.92l1.5-2.58 1.93.6c.44-.34.92-.62 1.43-.84l.33-2.01h3l.33 2.01c.51.22.99.5 1.43.84l1.93-.6 1.5 2.58-1.6 1.25c.03.27.05.55.05.83z" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        @elseif(str_contains($itemTitleLower, 'order') || str_contains($itemTitleLower, 'deal'))
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path d="M3.5 5.5h13l-1.5 7.5h-10z" stroke-linejoin="round"/>
                                <path d="M7.5 14.5a1.5 1.5 0 100 3 1.5 1.5 0 000-3zm5 0a1.5 1.5 0 100 3 1.5 1.5 0 000-3z"/>
                            </svg>
                        @elseif(str_contains($itemTitleLower, 'profile') || str_contains($itemTitleLower, 'customer') || str_contains($itemTitleLower, 'lead') || str_contains($itemTitleLower, 'contact'))
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path d="M10 10.25a3.25 3.25 0 100-6.5 3.25 3.25 0 000 6.5z"/>
                                <path d="M4 16.25c.9-2.02 2.9-3.25 6-3.25s5.1 1.23 6 3.25" stroke-linecap="round"/>
                            </svg>
                        @elseif(str_contains($itemTitleLower, 'product') || str_contains($itemTitleLower, 'inventory'))
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path d="M10 3l6 3.5v7L10 17l-6-3.5v-7L10 3z" stroke-linejoin="round"/>
                                <path d="M4 6.5l6 3.5 6-3.5" stroke-linejoin="round"/>
                            </svg>
                        @else
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path d="M4.5 4.5h11v11h-11z" stroke-linejoin="round"/>
                            </svg>
                        @endif
                    </span>
                    <span class="truncate" data-dk-sidebar-label>{{ $itemTitle }}</span>
                </a>
            @endforeach
        </nav>
    </aside>

    <main id="dk-main" class="flex h-screen min-h-0 flex-col overflow-hidden transition-all duration-300">
        <header class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-5 py-3 shadow-sm">
            <div class="flex items-center gap-3">
                <button type="button" class="grid h-9 w-9 place-content-center rounded-lg border border-slate-300 text-slate-600 transition hover:border-slate-400 hover:text-slate-900 lg:hidden" data-dk-sidebar-open title="Open menu" aria-label="Open menu">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <path d="M3.5 5.5h13M3.5 10h13M3.5 14.5h13" stroke-linecap="round"/>
                    </svg>
                </button>

                <div class="text-lg font-semibold">{{ $title ?? 'Dashboard' }}</div>
            </div>

            <div class="flex items-center gap-3">
                @if(config('dashkit.topbar.show_search'))
                    <form method="GET" action="{{ route('dashkit.search') }}">
                        <input class="w-52 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none" type="search" name="q" value="{{ (string) request('q', '') }}" placeholder="Search dashboard">
                    </form>
                @endif

                @if(config('dashkit.auth.enabled') && config('dashkit.topbar.user_menu'))
                    <div class="relative" data-dk-profile-menu>
                        <button type="button" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 shadow-sm transition hover:border-slate-300" data-dk-profile-toggle>
                            <span class="grid h-8 w-8 place-content-center rounded-full bg-gradient-to-br from-teal-500 to-cyan-500 text-xs font-bold text-white">{{ $initials }}</span>
                            <span class="hidden sm:block text-left">
                                <span class="block max-w-[140px] truncate text-sm font-semibold text-slate-800">{{ $displayName }}</span>
                                <span class="block text-xs text-slate-500">Account</span>
                            </span>
                            <svg class="h-4 w-4 text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.154l3.71-3.923a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                            </svg>
                        </button>

                        <div class="absolute right-0 z-30 mt-2 hidden w-52 rounded-xl border border-slate-200 bg-white p-2 shadow-lg" data-dk-profile-panel>
                            <a href="{{ $profileHref }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                                <span class="grid h-6 w-6 place-content-center rounded-md bg-teal-100 text-teal-700">P</span>
                                Profile
                            </a>

                            <form method="POST" action="{{ route('dashkit.logout') }}" class="mt-1">
                                @csrf
                                <button class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-rose-600 transition hover:bg-rose-50" type="submit">
                                    <span class="grid h-6 w-6 place-content-center rounded-md bg-rose-100 text-rose-600">L</span>
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </header>

        <section class="min-h-0 flex-1 overflow-y-auto p-5 lg:p-6">
            {{ $slot }}
        </section>
    </main>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var menu = document.querySelector('[data-dk-profile-menu]');
        if (menu) {
            var toggle = menu.querySelector('[data-dk-profile-toggle]');
            var panel = menu.querySelector('[data-dk-profile-panel]');

            if (toggle && panel) {
                toggle.addEventListener('click', function () {
                    panel.classList.toggle('hidden');
                });

                document.addEventListener('click', function (event) {
                    if (!menu.contains(event.target)) {
                        panel.classList.add('hidden');
                    }
                });
            }
        }

        var shell = document.getElementById('dk-shell');
        var sidebar = document.getElementById('dk-sidebar');
        var backdrop = document.querySelector('[data-dk-sidebar-backdrop]');
        var openSidebar = document.querySelector('[data-dk-sidebar-open]');
        var closeSidebar = document.querySelector('[data-dk-sidebar-close]');
        var collapseSidebar = document.querySelector('[data-dk-sidebar-collapse]');
        var collapseSidebarIcon = document.querySelector('[data-dk-sidebar-collapse-icon]');
        var collapsedStorageKey = 'dashkit.sidebar.collapsed';

        if (!shell || !sidebar || !backdrop) {
            return;
        }

        function openMobileSidebar() {
            sidebar.classList.remove('-translate-x-full');
            backdrop.classList.remove('pointer-events-none', 'opacity-0');
        }

        function closeMobileSidebar() {
            sidebar.classList.add('-translate-x-full');
            backdrop.classList.add('pointer-events-none', 'opacity-0');
        }

        if (openSidebar) {
            openSidebar.addEventListener('click', openMobileSidebar);
        }

        if (closeSidebar) {
            closeSidebar.addEventListener('click', closeMobileSidebar);
        }

        backdrop.addEventListener('click', closeMobileSidebar);

        if (collapseSidebar) {
            var isCollapsed = localStorage.getItem(collapsedStorageKey) === '1';
            if (isCollapsed) {
                shell.classList.add('dk-sidebar-collapsed');
            }

            if (collapseSidebarIcon) {
                collapseSidebarIcon.classList.toggle('rotate-180', shell.classList.contains('dk-sidebar-collapsed'));
            }

            collapseSidebar.addEventListener('click', function () {
                shell.classList.toggle('dk-sidebar-collapsed');
                localStorage.setItem(collapsedStorageKey, shell.classList.contains('dk-sidebar-collapsed') ? '1' : '0');

                if (collapseSidebarIcon) {
                    collapseSidebarIcon.classList.toggle('rotate-180', shell.classList.contains('dk-sidebar-collapsed'));
                }
            });
        }

        window.addEventListener('resize', function () {
            if (window.innerWidth >= 1024) {
                closeMobileSidebar();
            }
        });
    });
</script>
</body>
</html>