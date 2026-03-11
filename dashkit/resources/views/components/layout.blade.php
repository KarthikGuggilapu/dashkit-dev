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
    <style>
        body {
            font-family: Manrope, sans-serif;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
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
@endphp
<div class="min-h-screen lg:grid lg:grid-cols-[260px_1fr]">
    <aside class="border-r border-slate-200 bg-slate-900 px-4 py-6 text-slate-100">
        <div class="mb-6 px-2 text-xl font-bold tracking-wide">{{ config('dashkit.name', 'Dashkit') }}</div>
        <nav class="space-y-2">
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
                @endphp
                <a class="block rounded-md px-3 py-2 text-sm font-medium text-slate-200 transition hover:bg-slate-800 hover:text-white" href="{{ $href }}">
                    {{ $item['title'] ?? 'Item' }}
                </a>
            @endforeach
        </nav>
    </aside>

    <main class="flex min-h-screen flex-col">
        <header class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-5 py-3 shadow-sm">
            <div class="text-lg font-semibold">{{ $title ?? 'Dashboard' }}</div>

            <div class="flex items-center gap-3">
                @if(config('dashkit.topbar.show_search'))
                    <input class="w-52 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none" type="search" placeholder="Search dashboard">
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

        <section class="flex-1 p-5 lg:p-6">
            {{ $slot }}
        </section>
    </main>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var menu = document.querySelector('[data-dk-profile-menu]');
        if (!menu) {
            return;
        }

        var toggle = menu.querySelector('[data-dk-profile-toggle]');
        var panel = menu.querySelector('[data-dk-profile-panel]');
        if (!toggle || !panel) {
            return;
        }

        toggle.addEventListener('click', function () {
            panel.classList.toggle('hidden');
        });

        document.addEventListener('click', function (event) {
            if (!menu.contains(event.target)) {
                panel.classList.add('hidden');
            }
        });
    });
</script>
</body>
</html>