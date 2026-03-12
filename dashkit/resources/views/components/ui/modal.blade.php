@props([
    'id',
    'title' => 'Dialog',
])

<div id="{{ $id }}" class="fixed inset-0 z-50 hidden" data-dk-modal="{{ $id }}" aria-hidden="true">
    <div class="absolute inset-0 bg-slate-900/50" data-dk-modal-close="{{ $id }}"></div>

    <div class="relative mx-auto mt-20 w-[92%] max-w-xl rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-slate-900">{{ $title }}</h3>
            <button type="button" data-dk-modal-close="{{ $id }}" class="rounded-md border border-slate-200 px-2 py-1 text-xs text-slate-600 hover:bg-slate-100">Close</button>
        </div>

        <div>
            {{ $slot }}
        </div>
    </div>
</div>

<script>
    (function () {
        if (window.__dashkitModalBound) {
            return;
        }

        window.__dashkitModalBound = true;

        document.addEventListener('click', function (event) {
            var openTarget = event.target.closest('[data-dk-modal-open]');
            if (openTarget) {
                var openId = openTarget.getAttribute('data-dk-modal-open');
                var openModal = document.querySelector('[data-dk-modal="' + openId + '"]');
                if (openModal) {
                    openModal.classList.remove('hidden');
                }
            }

            var closeTarget = event.target.closest('[data-dk-modal-close]');
            if (closeTarget) {
                var closeId = closeTarget.getAttribute('data-dk-modal-close');
                var closeModal = document.querySelector('[data-dk-modal="' + closeId + '"]');
                if (closeModal) {
                    closeModal.classList.add('hidden');
                }
            }
        });
    })();
</script>
