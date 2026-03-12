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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWix+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkR4j8R2f0x1B3p6k9R/+qvOB0fOkHn84q0g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body { font-family: 'Inter', sans-serif; }

        :root {
            --dk-sb-w: 256px;
            --dk-sb-cw: 68px;
        }

        #dk-sidebar { width: var(--dk-sb-w); }

        @media (min-width: 1024px) {
            #dk-main { margin-left: var(--dk-sb-w); }
        }

        #dk-shell.dk-collapsed #dk-sidebar { width: var(--dk-sb-cw); }
        #dk-shell.dk-collapsed #dk-main { margin-left: var(--dk-sb-cw); }

        #dk-shell.dk-collapsed .dk-sb-label,
        #dk-shell.dk-collapsed .dk-sb-brand-text,
        #dk-shell.dk-collapsed .dk-sb-user-info,
        #dk-shell.dk-collapsed .dk-sb-group-label {
            opacity: 0; width: 0; overflow: hidden; white-space: nowrap; pointer-events: none;
        }
        #dk-shell.dk-collapsed .dk-sb-brand { justify-content: center; }
        #dk-shell.dk-collapsed .dk-sb-link { justify-content: center; padding-left: 0; padding-right: 0; }
        #dk-shell.dk-collapsed .dk-sb-user-row { justify-content: center; padding-left: 0.5rem; padding-right: 0.5rem; }

        #dk-sidebar, #dk-main { transition: width 0.22s ease, margin-left 0.22s ease; }

        .dk-nav-tip {
            display: none; position: absolute; left: calc(100% + 12px);
            top: 50%; transform: translateY(-50%); background: #1e293b;
            color: #f8fafc; font-size: 11px; font-weight: 600;
            padding: 4px 10px; border-radius: 6px; white-space: nowrap;
            z-index: 100; box-shadow: 0 4px 12px rgba(0,0,0,.25); pointer-events: none;
        }
        #dk-shell.dk-collapsed .dk-sb-link:hover .dk-nav-tip { display: block; }

        #dk-sb-nav { scrollbar-width: thin; scrollbar-color: rgba(255,255,255,.08) transparent; }
        #dk-sb-nav::-webkit-scrollbar { width: 3px; }
        #dk-sb-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,.12); border-radius: 2px; }

        #dk-content { scrollbar-width: thin; scrollbar-color: #cbd5e1 #f1f5f9; }
        #dk-content::-webkit-scrollbar { width: 4px; }
        #dk-content::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 2px; }

        #dk-user-panel:not(.hidden) { animation: dk-dropdown 0.14s ease; }
        @keyframes dk-dropdown {
            from { opacity: 0; transform: translateY(-6px) scale(.97); }
            to   { opacity: 1; transform: translateY(0)  scale(1); }
        }
    </style>
</head>
<body class="h-screen overflow-hidden bg-slate-50 text-slate-900">

@php
    $user        = auth()->user();
    $displayName = (string) ($user?->name ?? 'User');
    $userEmail   = (string) ($user?->email ?? '');

    $initials = collect(explode(' ', trim($displayName)))
        ->filter()->map(fn ($p) => strtoupper(substr($p, 0, 1)))->take(2)->implode('');
    $initials = $initials !== '' ? $initials : 'U';

    $profileHref = '#';
    if (\Illuminate\Support\Facades\Route::has('dashkit.page.profile')) {
        $profileHref = route('dashkit.page.profile');
    } elseif (\Illuminate\Support\Facades\Route::has('dashkit.page')) {
        $profileHref = route('dashkit.page', ['page' => 'profile']);
    }

    $settingsHref = '#';
    if (\Illuminate\Support\Facades\Route::has('dashkit.settings')) {
        $settingsHref = route('dashkit.settings');
    }

    $homeHref = '#';
    if (\Illuminate\Support\Facades\Route::has('dashkit.home')) {
        try { $homeHref = route('dashkit.home'); } catch (\Throwable) {}
    }

    $currentRouteName = request()->route()?->getName();

    $avatarUrl = null;
    if ($user) {
        try {
            $avatarPath = \Dashkit\Models\DashkitUserPreference::getValue((int) $user->id, 'avatar', '');
            if ($avatarPath) {
                $avatarUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($avatarPath);
            }
        } catch (\Throwable) {}
    }
@endphp

<div id="dk-shell" class="relative h-screen overflow-hidden">

    {{-- Mobile backdrop --}}
    <div id="dk-backdrop" class="pointer-events-none fixed inset-0 z-30 bg-black/50 opacity-0 transition-opacity duration-200 lg:hidden"></div>

    {{-- ══════════════════════════════ SIDEBAR ══ --}}
    <aside id="dk-sidebar" class="fixed inset-y-0 left-0 z-40 flex flex-col overflow-hidden bg-slate-900 -translate-x-full lg:translate-x-0 border-r border-slate-800/60 shadow-xl shadow-black/20 transition-transform duration-200 lg:transition-none">

        {{-- Brand --}}
        <div class="dk-sb-brand flex shrink-0 items-center justify-between gap-2 border-b border-slate-800/60 px-4 py-4">
            <a href="{{ $homeHref }}" class="flex min-w-0 items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-500 to-indigo-600 shadow-lg shadow-cyan-900/40">
                    <span class="text-sm font-black tracking-tighter text-white">DK</span>
                </div>
                <span class="dk-sb-brand-text truncate text-[15px] font-bold text-white transition-all duration-200">
                    {{ config('dashkit.name', 'Dashkit') }}
                </span>
            </a>

            <button id="dk-collapse-btn" class="hidden h-7 w-7 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-800 hover:text-slate-200 lg:flex" title="Collapse sidebar" aria-label="Collapse sidebar">
                <svg id="dk-collapse-icon" class="h-4 w-4 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                </svg>
            </button>

            <button id="dk-close-btn" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-800 hover:text-slate-200 lg:hidden" aria-label="Close menu">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Navigation --}}
        <nav id="dk-sb-nav" class="flex-1 overflow-y-auto px-3 py-3">
            @php $prevGroup = ''; @endphp
            @foreach((array) config('dashkit.sidebar', []) as $item)
                @php
                    $routeName   = $item['route'] ?? null;
                    $routeParams = (array) ($item['params'] ?? []);
                    $href        = '#';

                    if ($routeName && \Illuminate\Support\Facades\Route::has($routeName)) {
                        try { $href = route($routeName, $routeParams); } catch (\Throwable) {}
                    }

                    $itemTitle = (string) ($item['title'] ?? 'Item');
                    $itemGroup = (string) ($item['group'] ?? '');

                    $itemIcon = $item['icon'] ?? null;
                    if (!$itemIcon) {
                        $lc = strtolower($itemTitle . ' ' . (string) $routeName);
                        $itemIcon = match(true) {
                            str_contains($lc, 'home') || str_contains($lc, 'overview') || $routeName === 'dashkit.home' => 'home',
                            str_contains($lc, 'report')    => 'reports',
                            str_contains($lc, 'analytic')  => 'analytics',
                            str_contains($lc, 'setting')   => 'cog',
                            str_contains($lc, 'profile')   => 'profile',
                            str_contains($lc, 'user')      => 'users',
                            str_contains($lc, 'order')     => 'orders',
                            str_contains($lc, 'deal')      => 'deals',
                            str_contains($lc, 'product') || str_contains($lc, 'inventory') => 'products',
                            str_contains($lc, 'customer')  => 'customers',
                            str_contains($lc, 'lead')      => 'leads',
                            str_contains($lc, 'contact')   => 'contacts',
                            str_contains($lc, 'mail') || str_contains($lc, 'email') => 'mail',
                            str_contains($lc, 'calendar')  => 'calendar',
                            str_contains($lc, 'task')      => 'tasks',
                            str_contains($lc, 'notif') || str_contains($lc, 'bell') => 'bell',
                            str_contains($lc, 'chart')     => 'reports',
                            default                        => 'layout',
                        };
                    }

                    $isActive = $routeName && $currentRouteName ? request()->routeIs($routeName) : false;
                @endphp

                @if($itemGroup !== '' && $itemGroup !== $prevGroup)
                    @if($prevGroup !== '')
                        <div class="my-2.5 border-t border-slate-800/60"></div>
                    @else
                        <div class="mb-1 mt-2"></div>
                    @endif
                    <p class="dk-sb-group-label mb-1 px-3 text-[10px] font-semibold uppercase tracking-widest text-slate-600 transition-all duration-200">{{ $itemGroup }}</p>
                    @php $prevGroup = $itemGroup; @endphp
                @elseif($itemGroup === '' && $prevGroup !== '')
                    <div class="my-2.5 border-t border-slate-800/60"></div>
                    @php $prevGroup = ''; @endphp
                @endif

                <a
                    href="{{ $href }}"
                    title="{{ $itemTitle }}"
                    aria-current="{{ $isActive ? 'page' : 'false' }}"
                    class="dk-sb-link group relative mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150
                        {{ $isActive
                            ? 'bg-slate-800 text-white shadow-sm ring-1 ring-slate-700/60'
                            : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-200' }}"
                >
                    @if($isActive)
                        <span class="absolute left-0 inset-y-2.5 w-[3px] rounded-r-full bg-cyan-400"></span>
                    @endif

                    <span class="shrink-0 transition-colors duration-150 {{ $isActive ? 'text-cyan-400' : 'text-slate-500 group-hover:text-slate-300' }}">
                        <x-dashkit::ui.icon :name="$itemIcon" size="sm" />
                    </span>

                    <span class="dk-sb-label flex-1 truncate transition-all duration-200">{{ $itemTitle }}</span>

                    <span class="dk-nav-tip">{{ $itemTitle }}</span>
                </a>
            @endforeach
        </nav>

        {{-- Bottom user card --}}
        @if(config('dashkit.auth.enabled') && $user)
            <div class="shrink-0 border-t border-slate-800/60 p-3">
                <a href="{{ $profileHref }}"
                   class="dk-sb-user-row group flex items-center gap-3 rounded-lg px-2 py-2.5 transition hover:bg-slate-800">
                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="{{ $displayName }}"
                             class="h-8 w-8 shrink-0 rounded-full object-cover ring-2 ring-slate-700">
                    @else
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-teal-500 to-cyan-600 text-xs font-bold text-white ring-2 ring-slate-700">
                            {{ $initials }}
                        </div>
                    @endif
                    <div class="dk-sb-user-info min-w-0 flex-1 transition-all duration-200">
                        <p class="truncate text-[13px] font-semibold text-slate-200">{{ $displayName }}</p>
                        <p class="truncate text-[11px] text-slate-500">{{ $userEmail }}</p>
                    </div>
                    <svg class="dk-sb-user-info h-3.5 w-3.5 shrink-0 text-slate-600 transition group-hover:text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        @endif
    </aside>

    {{-- ══════════════════════════════════ MAIN ══ --}}
    <main id="dk-main" class="flex h-screen flex-col overflow-hidden">

        {{-- TOPBAR --}}
        <header class="z-10 flex h-16 shrink-0 items-center justify-between border-b border-slate-200 bg-white px-4 lg:px-6 shadow-sm">

            <div class="flex items-center gap-3">
                <button id="dk-open-btn"
                        class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:border-slate-300 hover:text-slate-700 lg:hidden"
                        aria-label="Open menu">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                <div class="flex flex-col justify-center">
                    <h1 class="text-[15px] font-semibold leading-tight text-slate-800">{{ $title ?? 'Dashboard' }}</h1>
                    <p class="hidden text-[11px] text-slate-400 sm:block">{{ config('dashkit.name', 'Dashkit') }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2">

                @if(config('dashkit.topbar.show_search'))
                    <form method="GET" action="{{ route('dashkit.search') }}" class="hidden sm:block">
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </span>
                            <input
                                type="search" name="q" value="{{ (string) request('q', '') }}" placeholder="Search…"
                                class="h-9 w-44 rounded-lg border border-slate-200 bg-slate-50 pl-9 pr-3 text-sm text-slate-700 placeholder-slate-400 transition focus:border-cyan-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-100 lg:w-52"
                            >
                        </div>
                    </form>
                @endif

                <button type="button"
                        class="relative flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:border-slate-300 hover:text-slate-700"
                        title="Notifications" aria-label="Notifications">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                </button>

                @if(config('dashkit.auth.enabled') && config('dashkit.topbar.user_menu'))
                    <div class="relative" id="dk-user-menu">
                        <button type="button" id="dk-user-toggle"
                                class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white py-1.5 pl-1.5 pr-2.5 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none">
                            @if($avatarUrl)
                                <img src="{{ $avatarUrl }}" alt="{{ $displayName }}" class="h-7 w-7 rounded-full object-cover">
                            @else
                                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-teal-500 to-cyan-600 text-[11px] font-bold text-white">
                                    {{ $initials }}
                                </div>
                            @endif
                            <span class="hidden sm:flex sm:flex-col sm:text-left">
                                <span class="block max-w-[120px] truncate text-[13px] font-semibold leading-tight text-slate-800">{{ $displayName }}</span>
                                <span class="block text-[11px] leading-tight text-slate-400">{{ $userEmail ?: 'Account' }}</span>
                            </span>
                            <svg id="dk-user-chevron" class="h-3.5 w-3.5 text-slate-400 transition-transform duration-150" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div id="dk-user-panel" class="absolute right-0 z-50 mt-2 w-56 hidden">
                            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60">

                                <div class="flex items-center gap-3 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-4 py-3">
                                    @if($avatarUrl)
                                        <img src="{{ $avatarUrl }}" alt="{{ $displayName }}"
                                             class="h-9 w-9 rounded-full object-cover ring-2 ring-slate-200">
                                    @else
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-teal-500 to-cyan-600 text-xs font-bold text-white ring-2 ring-slate-100">
                                            {{ $initials }}
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-800">{{ $displayName }}</p>
                                        <p class="truncate text-xs text-slate-500">{{ $userEmail }}</p>
                                    </div>
                                </div>

                                <div class="p-1.5">
                                    <a href="{{ $profileHref }}"
                                       class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-blue-50 text-blue-600">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        </span>
                                        My Profile
                                    </a>
                                    <a href="{{ $settingsHref }}"
                                       class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-500">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        </span>
                                        Settings
                                    </a>
                                </div>

                                <div class="border-t border-slate-100 p-1.5">
                                    <form method="POST" action="{{ route('dashkit.logout') }}">
                                        @csrf
                                        <button type="submit"
                                                class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-rose-600 transition hover:bg-rose-50">
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-rose-50 text-rose-500">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                            </span>
                                            Sign out
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </header>

        <section id="dk-content" class="flex-1 overflow-y-auto bg-slate-50 p-5 lg:p-6">
            {{ $slot }}
        </section>
    </main>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var shell       = document.getElementById('dk-shell');
        var sidebar     = document.getElementById('dk-sidebar');
        var backdrop    = document.getElementById('dk-backdrop');
        var openBtn     = document.getElementById('dk-open-btn');
        var closeBtn    = document.getElementById('dk-close-btn');
        var collapseBtn  = document.getElementById('dk-collapse-btn');
        var collapseIcon = document.getElementById('dk-collapse-icon');
        var userMenu    = document.getElementById('dk-user-menu');
        var userToggle  = document.getElementById('dk-user-toggle');
        var userPanel   = document.getElementById('dk-user-panel');
        var userChevron = document.getElementById('dk-user-chevron');
        var COLLAPSE_KEY = 'dk.sb.collapsed';

        // Mobile sidebar
        function openMobile() {
            if (!sidebar || !backdrop) return;
            sidebar.classList.remove('-translate-x-full');
            backdrop.classList.remove('pointer-events-none', 'opacity-0');
            backdrop.classList.add('pointer-events-auto', 'opacity-100');
        }
        function closeMobile() {
            if (!sidebar || !backdrop) return;
            sidebar.classList.add('-translate-x-full');
            backdrop.classList.remove('pointer-events-auto', 'opacity-100');
            backdrop.classList.add('pointer-events-none', 'opacity-0');
        }

        if (openBtn)  openBtn.addEventListener('click', openMobile);
        if (closeBtn) closeBtn.addEventListener('click', closeMobile);
        if (backdrop) backdrop.addEventListener('click', closeMobile);
        window.addEventListener('resize', function () { if (window.innerWidth >= 1024) closeMobile(); });

        // Desktop collapse
        if (shell && collapseBtn) {
            if (localStorage.getItem(COLLAPSE_KEY) === '1') {
                shell.classList.add('dk-collapsed');
                if (collapseIcon) collapseIcon.style.transform = 'rotate(180deg)';
            }
            collapseBtn.addEventListener('click', function () {
                shell.classList.toggle('dk-collapsed');
                var c = shell.classList.contains('dk-collapsed');
                localStorage.setItem(COLLAPSE_KEY, c ? '1' : '0');
                if (collapseIcon) collapseIcon.style.transform = c ? 'rotate(180deg)' : '';
            });
        }

        // User dropdown
        if (userToggle && userPanel) {
            userToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                var isHidden = userPanel.classList.toggle('hidden');
                if (userChevron) userChevron.style.transform = isHidden ? '' : 'rotate(180deg)';
            });
            document.addEventListener('click', function (e) {
                if (userMenu && !userMenu.contains(e.target)) {
                    userPanel.classList.add('hidden');
                    if (userChevron) userChevron.style.transform = '';
                }
            });
        }
    });
</script>
</body>
</html>
