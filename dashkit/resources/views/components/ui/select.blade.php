@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'options' => [],
    'value' => null,
    'placeholder' => null,
])

@php
    $resolvedId = $id ?: $name;
@endphp

<div {{ $attributes->except(['class'])->merge(['class' => 'space-y-2']) }}>
    @if ($label)
        <label for="{{ $resolvedId }}" class="block text-sm font-medium text-slate-700">{{ $label }}</label>
    @endif

    <select
        id="{{ $resolvedId }}"
        @if ($name) name="{{ $name }}" @endif
        {{ $attributes->only(['required'])->merge(['class' => 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-cyan-500 focus:outline-none']) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</div>
