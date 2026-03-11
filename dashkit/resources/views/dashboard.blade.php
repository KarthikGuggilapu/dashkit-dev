<x-dashkit-layout :title="$title ?? config('dashkit.name', 'Dashkit')">
	<section class="mb-5 rounded-xl border border-teal-200 bg-gradient-to-r from-teal-50 to-cyan-50 p-4">
		<div class="flex flex-wrap items-center gap-3">
			<span class="rounded-full bg-teal-600 px-2.5 py-1 text-xs font-semibold text-white">New in v1.2.0</span>
			<h2 class="text-base font-semibold text-slate-900">Navbar now includes profile avatar menu and cleaner account actions</h2>
		</div>
		<p class="mt-2 text-sm text-slate-700">
			Use <code>php artisan dashkit:upgrade --force</code> to apply this UI update to already published views.
		</p>
	</section>

	@if(config('dashkit.widgets.enabled'))
		<section class="dk-grid">
			@foreach($widgets ?? [] as $widget)
				<article class="dk-widget">
					<p class="dk-widget-label">{{ $widget['title'] }}</p>
					@if(!empty($widget['description']))
						<p class="dk-widget-label">{{ $widget['description'] }}</p>
					@endif
					<p class="dk-widget-value">{{ $widget['value'] }}</p>
				</article>
			@endforeach
		</section>
	@endif

	<section class="dk-panel">
		<h2>Welcome to {{ config('dashkit.name', 'Dashkit') }}</h2>
		<p>Your package-powered dashboard is active and ready.</p>
	</section>
</x-dashkit-layout>