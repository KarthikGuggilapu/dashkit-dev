<x-dashkit-layout title="Search">
    <section class="space-y-6">
        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-2xl font-bold text-slate-900">Dashboard Search</h2>
            <p class="mt-1 text-sm text-slate-500">Search pages and navigation items across your active preset.</p>

            <form class="mt-4" method="GET" action="{{ route('dashkit.search') }}">
                <input
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                    type="search"
                    name="q"
                    value="{{ (string) ($query ?? '') }}"
                    placeholder="Search by page title or route name"
                    required
                >
            </form>
        </article>

        @if ((string) ($query ?? '') === '')
            <article class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600 shadow-sm">
                Enter a query to see matching dashboard destinations.
            </article>
        @elseif (empty($results))
            <article class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800 shadow-sm">
                No matches found for "{{ $query }}".
            </article>
        @else
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($results as $result)
                    <a href="{{ $result['href'] }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-teal-300 hover:shadow-md">
                        <p class="text-xs font-semibold uppercase tracking-wide text-teal-700">{{ $result['kind'] }}</p>
                        <h3 class="mt-2 text-lg font-bold text-slate-900">{{ $result['title'] }}</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ $result['route'] }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</x-dashkit-layout>
