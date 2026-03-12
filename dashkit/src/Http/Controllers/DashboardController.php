<?php

namespace Dashkit\Http\Controllers;

use Dashkit\Services\WidgetRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly WidgetRegistry $widgets)
    {
    }

    public function index(): View
    {
        return view('dashkit::dashboard', [
            'widgets' => $this->widgets->resolveAll(),
            'title' => config('dashkit.name', 'Dashkit'),
        ]);
    }

    public function page(string $page): View
    {
        $configuredNamespace = (string) config('dashkit.generated_pages_namespace', 'dashkit.pages');
        $view = $configuredNamespace . '.' . $page;

        if (! view()->exists($view)) {
            $view = 'dashkit::pages.' . $page;
        }

        abort_unless(view()->exists($view), 404);

        return view($view, [
            'widgets' => $this->widgets->resolveAll(),
            'title' => ucfirst(str_replace(['-', '_'], ' ', $page)),
            'slug' => $page,
        ]);
    }

    public function search(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));
        $results = [];

        if ($query !== '') {
            $needle = Str::lower($query);

            foreach ((array) config('dashkit.sidebar', []) as $item) {
                $title = (string) ($item['title'] ?? '');
                $routeName = (string) ($item['route'] ?? '');
                $params = (array) ($item['params'] ?? []);

                if ($title === '' || $routeName === '' || ! Route::has($routeName)) {
                    continue;
                }

                if (! Str::contains(Str::lower($title), $needle) && ! Str::contains(Str::lower($routeName), $needle)) {
                    continue;
                }

                try {
                    $href = route($routeName, $params);
                } catch (\Throwable) {
                    $href = '#';
                }

                $results[] = [
                    'title' => $title,
                    'route' => $routeName,
                    'href' => $href,
                    'kind' => 'Navigation',
                ];
            }

            $pages = [
                ['title' => 'Profile', 'route' => 'dashkit.page.profile'],
                ['title' => 'Settings', 'route' => 'dashkit.settings'],
            ];

            foreach ($pages as $page) {
                $title = $page['title'];
                $routeName = $page['route'];

                if (! Route::has($routeName)) {
                    continue;
                }

                if (! Str::contains(Str::lower($title), $needle) && ! Str::contains(Str::lower($routeName), $needle)) {
                    continue;
                }

                $results[] = [
                    'title' => $title,
                    'route' => $routeName,
                    'href' => route($routeName),
                    'kind' => 'Core Page',
                ];
            }
        }

        return view('dashkit::pages.search', [
            'title' => 'Search',
            'query' => $query,
            'results' => collect($results)->unique('href')->values()->all(),
        ]);
    }
}
