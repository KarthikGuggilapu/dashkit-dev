@props([
    'type' => 'button',
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'iconLib' => 'hero',
    'iconPosition' => 'left',
])

@php
    $base = 'inline-flex items-center justify-center rounded-lg font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-1';
    $variants = [
        'primary' => 'bg-slate-900 text-white hover:bg-slate-700 focus:ring-slate-400',
        'secondary' => 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 focus:ring-slate-300',
        'success' => 'bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-300',
        'warning' => 'bg-amber-500 text-white hover:bg-amber-600 focus:ring-amber-300',
        'danger' => 'bg-rose-600 text-white hover:bg-rose-700 focus:ring-rose-300',
        'ghost' => 'text-slate-700 hover:bg-slate-100 focus:ring-slate-300',
    ];
    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-5 py-2.5 text-sm',
    ];

    $variantClass = $variants[$variant] ?? $variants['primary'];
    $sizeClass = $sizes[$size] ?? $sizes['md'];
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => $base.' '.$variantClass.' '.$sizeClass]) }}>
    @if ($icon && $iconPosition !== 'right')
        <x-dashkit::ui.icon :name="$icon" :lib="$iconLib" size="sm" class="mr-2" />
    @endif

    {{ $slot }}

    @if ($icon && $iconPosition === 'right')
        <x-dashkit::ui.icon :name="$icon" :lib="$iconLib" size="sm" class="ml-2" />
    @endif
</button>
