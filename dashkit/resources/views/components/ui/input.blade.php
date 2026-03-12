@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'type' => 'text',
    'value' => null,
    'help' => null,
])

@php
    $resolvedId = $id ?: $name;
@endphp

<div {{ $attributes->except(['class'])->merge(['class' => 'space-y-2']) }}>
    @if ($label)
        <label for="{{ $resolvedId }}" class="block text-sm font-medium text-slate-700">{{ $label }}</label>
    @endif

    <input
        id="{{ $resolvedId }}"
        type="{{ $type }}"
        @if ($name) name="{{ $name }}" @endif
        value="{{ $value }}"
        {{ $attributes->only(['required', 'placeholder', 'autocomplete', 'min', 'max', 'step'])->merge(['class' => 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-cyan-500 focus:outline-none']) }}
    >

    @if ($help)
        <p class="text-xs text-slate-500">{{ $help }}</p>
    @endif
</div>
