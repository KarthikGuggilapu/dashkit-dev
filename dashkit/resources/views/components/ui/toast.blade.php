@props([
    'message' => null,
    'tone' => 'info',
    'timeout' => 4200,
])

@php
    $tones = [
        'info' => 'border-cyan-200 bg-cyan-50 text-cyan-800',
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        'error' => 'border-rose-200 bg-rose-50 text-rose-800',
    ];
    $toneClass = $tones[$tone] ?? $tones['info'];
    $toastId = 'dk-toast-'.substr(md5((string) $message.microtime()), 0, 10);
@endphp

@if ((string) $message !== '')
    <style>
        #{{ $toastId }} { opacity: 0; transform: translateY(-8px); transition: opacity .2s ease, transform .2s ease; }
        #{{ $toastId }}.dk-toast-show { opacity: 1; transform: translateY(0); }
    </style>

    <div id="{{ $toastId }}" class="fixed right-5 top-5 z-[9999] max-w-sm rounded-lg border px-4 py-3 text-sm shadow-lg {{ $toneClass }}" role="status" aria-live="polite">
        {{ $message }}
    </div>

    <script>
        (function () {
            var toast = document.getElementById('{{ $toastId }}');
            if (!toast) {
                return;
            }

            requestAnimationFrame(function () {
                toast.classList.add('dk-toast-show');
            });

            setTimeout(function () {
                toast.classList.remove('dk-toast-show');
            }, {{ (int) $timeout }});

            setTimeout(function () {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, {{ (int) $timeout + 250 }});
        })();
    </script>
@endif
