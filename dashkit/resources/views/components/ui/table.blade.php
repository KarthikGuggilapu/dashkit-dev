@props([
    'headers' => [],
])

<div {{ $attributes->merge(['class' => 'overflow-x-auto rounded-xl border border-slate-200']) }}>
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        @if (! empty($headers))
            <thead class="bg-slate-50">
                <tr>
                    @foreach ($headers as $header)
                        <th class="px-4 py-3 text-left font-semibold text-slate-700">{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif

        <tbody class="divide-y divide-slate-200 bg-white">
            {{ $slot }}
        </tbody>
    </table>
</div>
