@props([
    'tone' => 'info',
    'message' => null,
])

@php
    $tones = [
        'info' => 'border-cyan-200 bg-cyan-50 text-cyan-800',
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        'error' => 'border-rose-200 bg-rose-50 text-rose-800',
    ];
    $toneClass = $tones[$tone] ?? $tones['info'];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border px-4 py-3 text-sm '.$toneClass]) }} role="alert">
    {{ $message ?? $slot }}
</div>
