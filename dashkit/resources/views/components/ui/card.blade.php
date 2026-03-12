@props([
    'title' => null,
    'description' => null,
])

<article {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-white p-6 shadow-sm']) }}>
    @if ($title)
        <h3 class="text-lg font-bold text-slate-900">{{ $title }}</h3>
    @endif

    @if ($description)
        <p class="mt-1 text-sm text-slate-600">{{ $description }}</p>
    @endif

    <div class="{{ $title || $description ? 'mt-4' : '' }}">
        {{ $slot }}
    </div>
</article>
